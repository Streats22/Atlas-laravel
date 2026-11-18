<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\Setting;
use App\Services\MailConfig;
use App\Services\Settings;

class SettingsTest extends MailroomTestCase
{
    private function payload(array $override = []): array
    {
        return $override + [
            'mail_provider' => 'postmark', 'mail_host' => 'smtp.postmarkapp.com', 'mail_port' => 587, 'mail_encryption' => 'tls',
            'mail_username' => 'token', 'mail_password' => 's3cret', 'mail_from_address' => 'hi@acme.test', 'mail_from_name' => 'Acme',
            'send_rate_per_minute' => 120, 'company_name' => 'Acme BV', 'company_address' => 'Main St 1, Utrecht',
        ];
    }

    public function test_settings_are_saved_with_encrypted_password_and_applied(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->put('/settings', $this->payload())->assertRedirect('/settings');

        $this->assertNotSame('s3cret', Setting::query()->find('mail_password')->value);
        $this->assertSame('s3cret', app(Settings::class)->get('mail_password'));

        app(MailConfig::class)->apply();
        $this->assertSame('mailroom', config('mail.default'));
        $this->assertSame('smtp.postmarkapp.com', config('mail.mailers.mailroom.host'));
        $this->assertSame('s3cret', config('mail.mailers.mailroom.password'));
        $this->assertSame('hi@acme.test', config('mail.from.address'));
        $this->assertSame(120, app(MailConfig::class)->perMinute());

        // Blank password keeps the stored one.
        $this->actingAs($user)->put('/settings', $this->payload(['mail_password' => '']))->assertRedirect();
        $this->assertSame('s3cret', app(Settings::class)->get('mail_password'));
    }

    public function test_host_header_injection_is_rejected(): void
    {
        $this->actingAs($this->admin())->put('/settings', $this->payload(['mail_host' => "evil.test\r\nX: y"]))->assertSessionHasErrors('mail_host');
    }

    public function test_company_address_is_added_to_campaign_footer(): void
    {
        app(Settings::class)->put(['company_name' => 'Acme BV', 'company_address' => 'Main St 1, Utrecht']);
        $audience = $this->audience();
        $mail = new CampaignMail($this->campaign($audience), $this->contact($audience, 'a@x.test'));
        $mail->assertSeeInHtml('Main St 1, Utrecht');
    }

    public function test_test_email_via_log_provider(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->put('/settings', $this->payload(['mail_provider' => 'log', 'mail_host' => null, 'mail_port' => null]))->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/settings/test', ['to' => 'me@acme.test'])->assertSessionHas('status');
    }

    public function test_settings_page_and_unconfigured_warning(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/')->assertSee('connect a sending provider');
        $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Authenticate your domain');
    }
}
