<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Http\Requests\ContactRequest;
use App\Models\Audience;
use App\Models\Contact;
use App\Services\TagSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(private readonly TagSync $tags) {}

    public function create(Audience $audience): View
    {
        return view('contacts.edit', [
            'audience' => $audience,
            'contact' => new Contact(['status' => ContactStatus::Subscribed]),
        ]);
    }

    public function store(ContactRequest $request, Audience $audience): RedirectResponse
    {
        $data = $request->contactData();
        $contact = $audience->contacts()->create($data + [
            'subscribed_at' => $data['status'] === ContactStatus::Subscribed->value ? now() : null,
        ]);
        $this->tags->sync($contact, $request->tagNames());

        return redirect()->route('audiences.show', $audience)->with('status', 'Contact added.');
    }

    public function edit(Audience $audience, Contact $contact): View
    {
        $contact->load(['tags', 'sends.campaign']);

        return view('contacts.edit', ['audience' => $audience, 'contact' => $contact]);
    }

    public function update(ContactRequest $request, Audience $audience, Contact $contact): RedirectResponse
    {
        $data = $request->contactData();
        if ($data['status'] === ContactStatus::Unsubscribed->value && $contact->status !== ContactStatus::Unsubscribed) {
            $data['unsubscribed_at'] = now();
        }
        $contact->update($data);
        $this->tags->sync($contact, $request->tagNames());

        return redirect()->route('audiences.show', $audience)->with('status', 'Contact saved.');
    }

    public function destroy(Audience $audience, Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('audiences.show', $audience)->with('status', 'Contact deleted.');
    }

    /** GDPR right of access: everything stored about one contact, as JSON. */
    public function export(Audience $audience, Contact $contact): JsonResponse
    {
        $contact->load(['tags', 'sends.campaign']);

        return response()->json([
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'fields' => $contact->fields ?? (object) [],
            'status' => $contact->status->value,
            'source' => $contact->source,
            'tags' => $contact->tags->pluck('name'),
            'subscribed_at' => $contact->subscribed_at?->toIso8601String(),
            'confirmed_at' => $contact->confirmed_at?->toIso8601String(),
            'unsubscribed_at' => $contact->unsubscribed_at?->toIso8601String(),
            'consent' => ['given_at' => $contact->consent_at?->toIso8601String(), 'text' => $contact->consent_text, 'ip' => $contact->signup_ip],
            'notes' => $contact->notes,
            'emails' => $contact->sends->map(fn ($s) => [
                'campaign' => $s->campaign->name, 'subject' => $s->campaign->subject, 'sent_at' => $s->sent_at?->toIso8601String(),
                'opened_at' => $s->opened_at?->toIso8601String(), 'clicked_at' => $s->clicked_at?->toIso8601String(),
            ]),
        ], 200, ['Content-Disposition' => 'attachment; filename="contact-'.$contact->id.'.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function bulk(Request $request, Audience $audience): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['tag', 'untag', 'unsubscribe', 'delete'])],
            'tag' => ['required_if:action,tag,untag', 'nullable', 'string', 'max:60'],
        ]);
        $contacts = $audience->contacts()->whereKey($data['ids'])->get();

        foreach ($contacts as $contact) {
            match ($data['action']) {
                'tag' => $this->tags->attach($contact, $data['tag']),
                'untag' => $this->tags->detach($contact, $data['tag']),
                'unsubscribe' => $contact->unsubscribe(),
                'delete' => $contact->delete(),
            };
        }

        return back()->with('status', $contacts->count().' contacts updated.');
    }
}
