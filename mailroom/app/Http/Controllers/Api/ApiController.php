<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Mail\ConfirmSubscriptionMail;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\Contact;
use App\Services\ContactFields;
use App\Services\MailConfig;
use App\Services\TagSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/** Versioned JSON API (v1). Contacts are addressed by email, like most email-marketing APIs. */
class ApiController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'user' => $request->user()->email, 'role' => $request->user()->role->value]);
    }

    public function audiences(): JsonResponse
    {
        return response()->json(['data' => Audience::query()->withCount('contacts')->orderBy('name')->get()->map(fn (Audience $a) => [
            'id' => $a->id, 'name' => $a->name, 'contacts' => $a->contacts_count, 'double_opt_in' => $a->double_opt_in,
            'fields' => $a->fields->map(fn ($f) => ['key' => $f->key, 'label' => $f->label, 'type' => $f->type->value]),
        ])]);
    }

    public function contacts(Request $request, Audience $audience): JsonResponse
    {
        $status = ContactStatus::tryFrom((string) $request->query('status'));
        $page = $audience->contacts()->with('tags')->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('id')->paginate(min(1000, max(1, $request->integer('per_page', 100))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Contact $c) => $this->contact($c)),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function showContact(Audience $audience, string $email): JsonResponse
    {
        return response()->json(['data' => $this->contact($this->find($audience, $email))]);
    }

    /** Create or update by email. Never resubscribes someone who unsubscribed unless status is given explicitly. */
    public function upsertContact(Request $request, Audience $audience, ContactFields $fields, TagSync $tags): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'status_if_new' => ['nullable', Rule::enum(ContactStatus::class)],
            'fields' => ['nullable', 'array'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:60'],
        ] + $fields->rules($audience, enforceRequired: false));

        $email = strtolower(trim($data['email']));
        $contact = $audience->contacts()->where('email', $email)->first();
        $isNew = $contact === null;
        $contact ??= $audience->contacts()->make(['email' => $email, 'source' => 'api']);
        $status = $data['status'] ?? ($isNew ? ($data['status_if_new'] ?? 'subscribed') : $contact->status->value);

        $contact->fill(array_filter(['first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null], fn ($v) => $v !== null) + [
            'fields' => $fields->clean($audience, (array) ($data['fields'] ?? []), $contact->fields),
            'status' => $status,
            'subscribed_at' => $status === 'subscribed' ? ($contact->subscribed_at ?? now()) : $contact->subscribed_at,
            'unsubscribed_at' => $status === 'unsubscribed' ? ($contact->unsubscribed_at ?? now()) : null,
        ])->save();

        foreach ($data['tags'] ?? [] as $tag) {
            $tags->attach($contact, $tag);
        }
        if ($status === 'pending' && ($isNew || $contact->wasChanged('status'))) {
            Mail::to($contact->email)->send(new ConfirmSubscriptionMail($contact));
        }

        return response()->json(['data' => $this->contact($contact->fresh('tags'))], $isNew ? 201 : 200);
    }

    public function deleteContact(Audience $audience, string $email): JsonResponse
    {
        $this->find($audience, $email)->delete();

        return response()->json(null, 204);
    }

    public function tags(Request $request, Audience $audience, string $email, TagSync $tags): JsonResponse
    {
        $data = $request->validate([
            'add' => ['nullable', 'array', 'max:50'], 'add.*' => ['string', 'max:60'],
            'remove' => ['nullable', 'array', 'max:50'], 'remove.*' => ['string', 'max:60'],
        ]);
        $contact = $this->find($audience, $email);
        foreach ($data['add'] ?? [] as $tag) {
            $tags->attach($contact, $tag);
        }
        foreach ($data['remove'] ?? [] as $tag) {
            $tags->detach($contact, $tag);
        }

        return response()->json(['data' => $this->contact($contact->fresh('tags'))]);
    }

    public function campaigns(): JsonResponse
    {
        return response()->json(['data' => Campaign::query()->where('kind', 'regular')->latest()->limit(200)->get()->map(fn (Campaign $c) => [
            'id' => $c->id, 'name' => $c->name, 'subject' => $c->subject, 'status' => $c->status->value,
            'audience_id' => $c->audience_id, 'sent_at' => $c->sent_at?->toIso8601String(), 'web_url' => $c->webUrl(),
        ])]);
    }

    public function report(Campaign $campaign): JsonResponse
    {
        return response()->json(['data' => ['id' => $campaign->id, 'recipients' => $campaign->recipients_count] + $campaign->stats()]);
    }

    /** Transactional email through the configured provider (receipts, password resets of your own app...). */
    public function send(Request $request, MailConfig $mailConfig): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:190'],
            'html' => ['required_without:text', 'nullable', 'string', 'max:500000'],
            'text' => ['nullable', 'string', 'max:200000'],
            'from_email' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email'],
        ]);
        $mailConfig->apply();

        try {
            Mail::send([], [], function (Message $message) use ($data): void {
                $message->to($data['to'])->subject($data['subject']);
                if (! empty($data['from_email'])) {
                    $message->from($data['from_email'], $data['from_name'] ?? null);
                }
                if (! empty($data['reply_to'])) {
                    $message->replyTo($data['reply_to']);
                }
                if (! empty($data['html'])) {
                    $message->html($data['html']);
                }
                if (! empty($data['text'])) {
                    $message->text($data['text']);
                }
            });
        } catch (Throwable $e) {
            return response()->json(['message' => 'Sending failed: '.str($e->getMessage())->limit(200)], 502);
        }

        return response()->json(['ok' => true], 202);
    }

    private function find(Audience $audience, string $email): Contact
    {
        return $audience->contacts()->with('tags')->where('email', strtolower(urldecode($email)))->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function contact(Contact $c): array
    {
        return [
            'id' => $c->id, 'email' => $c->email, 'first_name' => $c->first_name, 'last_name' => $c->last_name,
            'status' => $c->status->value, 'fields' => $c->fields ?? (object) [], 'tags' => $c->tags->pluck('name'),
            'source' => $c->source, 'subscribed_at' => $c->subscribed_at?->toIso8601String(), 'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
