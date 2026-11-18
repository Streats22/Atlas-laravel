<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Mail\ConfirmSubscriptionMail;
use Illuminate\Support\Facades\Mail;

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

        $this->get("/confirm/{$contact->token}")->assertOk();
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

    public function test_unknown_tokens_404(): void
    {
        $this->get('/u/nope')->assertNotFound();
        $this->get('/confirm/nope')->assertNotFound();
        $this->get('/f/nope')->assertNotFound();
    }
}
