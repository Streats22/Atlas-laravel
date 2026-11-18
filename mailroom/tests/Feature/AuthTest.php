<?php

declare(strict_types=1);

namespace Tests\Feature;

class AuthTest extends MailroomTestCase
{
    public function test_app_pages_require_login(): void
    {
        foreach (['/', '/campaigns', '/audiences'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_user_can_log_in_and_see_dashboard(): void
    {
        $user = $this->admin();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Subscribers');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->admin();
        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
