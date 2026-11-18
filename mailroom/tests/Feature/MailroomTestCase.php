<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MailroomTestCase extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create();
    }

    protected function audience(array $attributes = []): Audience
    {
        return Audience::query()->create($attributes + ['name' => 'News', 'from_name' => 'Acme', 'from_email' => 'news@acme.test', 'double_opt_in' => true]);
    }

    protected function contact(Audience $audience, string $email, ContactStatus $status = ContactStatus::Subscribed, array $extra = []): Contact
    {
        return $audience->contacts()->create($extra + ['email' => $email, 'status' => $status, 'subscribed_at' => now()]);
    }

    protected function campaign(Audience $audience, array $attributes = []): Campaign
    {
        return Campaign::query()->create($attributes + [
            'audience_id' => $audience->id, 'name' => 'Launch', 'subject' => 'Hi {{ first_name | friend }}',
            'from_name' => 'Acme', 'from_email' => 'news@acme.test',
            'html' => '<html><body><p>Hello {{ first_name }}</p><a href="https://acme.test/offer">Offer</a></body></html>',
        ]);
    }
}
