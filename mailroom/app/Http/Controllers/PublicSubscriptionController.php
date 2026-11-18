<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Mail\ConfirmSubscriptionMail;
use App\Models\Audience;
use App\Models\CampaignSend;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PublicSubscriptionController extends Controller
{
    public function form(string $publicId): View
    {
        return view('public.form', ['audience' => $this->audience($publicId)]);
    }

    public function subscribe(Request $request, string $publicId): View|RedirectResponse
    {
        $audience = $this->audience($publicId);

        // Honeypot: bots fill every field; pretend success so they learn nothing.
        if ($request->filled('website')) {
            return view('public.message', ['audience' => $audience, 'title' => 'Almost done', 'message' => 'Check your inbox to confirm.']);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ]);
        $email = strtolower(trim($data['email']));

        /** @var Contact|null $contact */
        $contact = $audience->contacts()->where('email', $email)->first();
        $alreadySubscribed = $contact?->status === ContactStatus::Subscribed;

        if (! $alreadySubscribed) {
            $status = $audience->double_opt_in ? ContactStatus::Pending : ContactStatus::Subscribed;
            $contact ??= $audience->contacts()->make(['email' => $email, 'source' => 'form']);
            $contact->fill(array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]) + [
                'status' => $status,
                'subscribed_at' => $status === ContactStatus::Subscribed ? now() : null,
                'unsubscribed_at' => null,
            ])->save();

            if ($audience->double_opt_in) {
                Mail::to($contact->email)->send(new ConfirmSubscriptionMail($contact));
            }
        }

        // Same response for new and existing addresses, so the form can't be used to probe the list.
        return view('public.message', [
            'audience' => $audience,
            'title' => $audience->double_opt_in ? 'Almost done' : 'You\'re subscribed',
            'message' => $audience->double_opt_in
                ? 'We sent you an email. Click the link inside to confirm your subscription.'
                : 'Thanks for subscribing to '.$audience->name.'.',
        ]);
    }

    public function confirm(string $token): View
    {
        $contact = Contact::query()->where('token', $token)->firstOrFail();
        if ($contact->status === ContactStatus::Pending) {
            $contact->confirm();
        }

        return view('public.message', [
            'audience' => $contact->audience,
            'title' => 'Subscription confirmed',
            'message' => 'You\'re on the list for '.$contact->audience->name.'.',
        ]);
    }

    public function unsubscribePage(string $token): View
    {
        $contact = Contact::query()->where('token', $token)->firstOrFail();

        return view('public.unsubscribe', ['contact' => $contact, 'audience' => $contact->audience]);
    }

    public function unsubscribe(Request $request, string $token): View|Response
    {
        $contact = Contact::query()->where('token', $token)->firstOrFail();
        if ($contact->status !== ContactStatus::Unsubscribed) {
            $contact->unsubscribe();
        }

        $sendToken = (string) $request->query('send', $request->input('send', ''));
        if ($sendToken !== '') {
            CampaignSend::query()->where('token', $sendToken)->where('contact_id', $contact->id)
                ->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now()]);
        }

        // Mailbox providers send RFC 8058 one-click POSTs without a browser: no page needed.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return view('public.message', [
            'audience' => $contact->audience,
            'title' => 'You\'ve been unsubscribed',
            'message' => 'You will no longer receive emails from '.$contact->audience->name.'.',
        ]);
    }

    private function audience(string $publicId): Audience
    {
        return Audience::query()->where('public_id', $publicId)->firstOrFail();
    }
}
