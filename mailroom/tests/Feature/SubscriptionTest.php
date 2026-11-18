<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Mail\ConfirmSubscriptionMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SubscriptionTest extends MailroomTestCase
{
    public function test_double_opt_in_signup_is_pending_until_confirmed(): void
    {
        Mail::fake();
        $audience = $this->audience();

        $this->post("/f/{$audience->public_id}", ['email' => ' Jane@Example.com ', 'first_name' => 'Jane'])->assertOk()->assertSee('Almost done');

        $contact = $audience->contacts()->sole();
        $this->assertSame('jane@example.com', $contact->email);
        $this->assertSame(ContactStatus::Pending, $contact->status);
        Mail::assertSent(ConfirmSubscriptionMail::class);

        $url = null;
        Mail::assertSent(ConfirmSubscriptionMail::class, function (ConfirmSubscriptionMail $m) use (&$url) {
            $url = $m->content()->with['url'];

            return true;
        });
        $this->get("/confirm/{$contact->id}")->assertForbidden(); // unsigned
        $this->get($url)->assertOk()->assertSee('Yes, subscribe me');
        $this->assertSame(ContactStatus::Pending, $contact->fresh()->status); // GET alone never confirms
        $this->post(URL::temporarySignedRoute('public.confirm.do', now()->addDay(), ['contact' => $contact->id]))->assertOk();
        $this->assertSame(ContactStatus::Subscribed, $contact->fresh()->status);
    }

    public function test_single_opt_in_subscribes_immediately(): void
    {
        Mail::fake();
        $audience = $this->audience(['double_opt_in' => false]);
        $this->post("/f/{$audience->public_id}", ['email' => 'a@b.test'])->assertOk();
        $this->assertSame(ContactStatus::Subscribed, $audience->contacts()->sole()->status);
        Mail::assertNothingSent();
    }

    public function test_honeypot_silently_drops_bots(): void
    {
        $audience = $this->audience();
        $this->post("/f/{$audience->public_id}", ['email' => 'bot@spam.test', 'website' => 'http://spam'])->assertOk();
        $this->assertSame(0, $audience->contacts()->count());
    }

    public function test_unsubscribe_page_and_one_click_post(): void
    {
        $audience = $this->audience();
        $contact = $this->contact($audience, 'x@y.test');

        $this->get("/u/{$contact->token}")->assertOk()->assertSee('Unsubscribe');
        $this->post("/u/{$contact->token}", ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertSame(ContactStatus::Unsubscribed, $contact->fresh()->status);
    }

    public function test_form_never_resubscribes_or_overwrites_existing_contacts(): void
    {
        Mail::fake();
        $audience = $this->audience(['double_opt_in' => false]);
        $gone = $this->contact($audience, 'gone@x.test', ContactStatus::Unsubscribed, ['first_name' => 'Real']);

        $this->post("/f/{$audience->public_id}", ['email' => 'gone@x.test', 'first_name' => 'Hacked'])->assertOk()->assertSee('Almost done');
        $gone->refresh();
        $this->assertSame(ContactStatus::Pending, $gone->status);
        $this->assertSame('Real', $gone->first_name);
        Mail::assertSent(ConfirmSubscriptionMail::class, 1);
    }

    public function test_unknown_tokens_404(): void
    {
        $this->get('/u/nope')->assertNotFound();
        $this->get('/confirm/nope')->assertNotFound();
        $this->get('/f/nope')->assertNotFound();
    }
}
