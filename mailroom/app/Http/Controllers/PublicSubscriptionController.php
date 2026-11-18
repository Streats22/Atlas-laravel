<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Mail\ConfirmSubscriptionMail;
use App\Models\Audience;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\LandingPage;
use App\Services\ContactFields;
use App\Services\TagSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PublicSubscriptionController extends Controller
{
    public function form(Request $request, string $publicId): View
    {
        return view('public.form', ['audience' => $this->audience($publicId), 'embed' => $request->boolean('embed')]);
    }

    /** Pop-up signup script: <script src=".../popup.js" data-delay="5" async></script> */
    public function popup(string $publicId): Response
    {
        $audience = $this->audience($publicId);

        return response(view('public.popup', ['url' => route('public.form', [$audience->public_id, 'embed' => 1]), 'key' => 'mr-popup-'.$audience->public_id])->render())
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function subscribe(Request $request, string $publicId, ContactFields $fields): View|RedirectResponse
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
            'fields' => ['nullable', 'array'],
            'consent' => [$audience->gdpr_enabled ? 'accepted' : 'nullable'],
        ] + $fields->rules($audience, 'form'));
        $email = strtolower(trim($data['email']));

        /** @var Contact|null $contact */
        $contact = $audience->contacts()->where('email', $email)->first();
        $alreadySubscribed = $contact?->status === ContactStatus::Subscribed;

        if ($contact === null) {
            $status = $audience->double_opt_in ? ContactStatus::Pending : ContactStatus::Subscribed;
            $contact = $audience->contacts()->make(['email' => $email, 'source' => 'form']);
            $contact->fill(array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]) + [
                'fields' => $fields->clean($audience, array_intersect_key((array) $request->input('fields', []), $audience->fields->where('show_on_form', true)->keyBy('key')->all())),
                'consent_at' => $audience->gdpr_enabled ? now() : null,
                'consent_text' => $audience->gdpr_enabled ? $audience->gdpr_text : null,
                'signup_ip' => $request->ip(),
                'status' => $status,
                'subscribed_at' => $status === ContactStatus::Subscribed ? now() : null,
            ])->save();
            if ($status === ContactStatus::Pending) {
                Mail::to($contact->email)->send(new ConfirmSubscriptionMail($contact));
            }
        } elseif (! $alreadySubscribed) {
            // Anyone can type any address into a form. Someone who left (or bounced) only comes back
            // by clicking the confirmation email, and the form never overwrites their stored data.
            $contact->update(['status' => ContactStatus::Pending]);
            Mail::to($contact->email)->send(new ConfirmSubscriptionMail($contact));
        }
        $pending = $audience->double_opt_in || $contact->status === ContactStatus::Pending;

        // Landing-page signups: count the conversion and apply the page's tag.
        $page = $request->integer('landing') ? LandingPage::query()->where('audience_id', $audience->id)->find($request->integer('landing')) : null;
        if ($page !== null && $contact !== null) {
            $page->increment('signups');
            if ($page->tag) {
                app(TagSync::class)->attach($contact, $page->tag);
            }
        }

        // Same response for new and existing addresses, so the form can't be used to probe the list.
        return view('public.message', [
            'audience' => $audience,
            'embed' => $request->boolean('embed'),
            'title' => $pending ? 'Almost done' : 'You\'re subscribed',
            'message' => $pending
                ? 'We sent you an email. Click the link inside to confirm your subscription.'
                : 'Thanks for subscribing to '.$audience->name.'.',
        ]);
    }

    /** GET only shows a button: mail scanners that follow links must not confirm on their own. */
    public function confirmPage(Request $request, Contact $contact): View
    {
        return view('public.confirm', ['contact' => $contact, 'audience' => $contact->audience, 'action' => URL::temporarySignedRoute('public.confirm.do', now()->addDays(7), ['contact' => $contact->id])]);
    }

    public function confirm(Contact $contact): View
    {
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
