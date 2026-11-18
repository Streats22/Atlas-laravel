<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\InviteUser;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

class RolesTest extends MailroomTestCase
{
    private function as(Role $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_viewer_can_read_but_not_write(): void
    {
        $audience = $this->audience();
        $campaign = $this->campaign($audience);
        $viewer = $this->as(Role::Viewer);

        $this->actingAs($viewer)->get('/campaigns')->assertOk();
        $this->actingAs($viewer)->get("/audiences/{$audience->id}")->assertOk()->assertDontSee('Add contact')->assertDontSee('Export CSV');
        $this->actingAs($viewer)->put("/campaigns/{$campaign->id}", $campaign->toArray())->assertForbidden();
        $this->actingAs($viewer)->get("/audiences/{$audience->id}/export")->assertForbidden();
        $this->actingAs($viewer)->get('/settings')->assertForbidden();
    }

    public function test_author_edits_campaigns_but_cannot_send_or_touch_contacts(): void
    {
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');
        $campaign = $this->campaign($audience);
        $author = $this->as(Role::Author);

        $this->actingAs($author)->put("/campaigns/{$campaign->id}", ['name' => 'Edited'] + $campaign->only(['audience_id', 'subject', 'from_name', 'from_email', 'html']))->assertRedirect();
        $this->assertSame('Edited', $campaign->fresh()->name);
        $this->actingAs($author)->get("/campaigns/{$campaign->id}/edit")->assertDontSee('Send now');
        $this->actingAs($author)->post("/campaigns/{$campaign->id}/send")->assertForbidden();
        $this->actingAs($author)->post("/audiences/{$audience->id}/contacts", ['email' => 'b@x.test', 'status' => 'subscribed'])->assertForbidden();
    }

    public function test_manager_sends_but_cannot_manage_users_or_settings(): void
    {
        $manager = $this->as(Role::Manager);
        $this->actingAs($manager)->get('/users')->assertForbidden();
        $this->actingAs($manager)->put('/settings', [])->assertForbidden();
        $this->actingAs($manager)->post('/audiences', ['name' => 'A', 'from_name' => 'A', 'from_email' => 'a@b.test'])->assertRedirect();
    }

    public function test_admin_invites_user_who_sets_password(): void
    {
        Notification::fake();
        $admin = $this->as(Role::Admin);
        $this->actingAs($admin)->post('/users', ['name' => 'Ann', 'email' => 'ann@x.test', 'role' => 'owner'])->assertSessionHasErrors('role');
        $this->actingAs($admin)->post('/users', ['name' => 'Ann', 'email' => 'ann@x.test', 'role' => 'author'])->assertSessionHasNoErrors();

        $ann = User::query()->where('email', 'ann@x.test')->sole();
        $token = null;
        Notification::assertSentTo($ann, InviteUser::class, function (InviteUser $n) use ($ann, &$token) {
            $token = (fn () => $this->token)->call($n);

            return str_contains($n->toMail($ann)->actionUrl, '/reset-password/');
        });

        auth()->logout();
        $this->post('/reset-password', ['token' => $token, 'email' => 'ann@x.test', 'password' => 'a-strong-pass', 'password_confirmation' => 'a-strong-pass'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($ann);
    }

    public function test_owner_protection_and_self_edit(): void
    {
        $owner = $this->as(Role::Owner);
        $admin = $this->as(Role::Admin);
        $this->actingAs($admin)->put("/users/{$owner->id}", ['role' => 'viewer'])->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$owner->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();
        $this->actingAs($owner)->put("/users/{$admin->id}", ['role' => 'viewer'])->assertRedirect();
        $this->assertSame(Role::Viewer, $admin->fresh()->role);
    }

    public function test_forgot_password_does_not_reveal_accounts_and_profile_update(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@x.test'])->assertSessionHas('status');
        $user = $this->as(Role::Viewer);
        $this->actingAs($user)->put('/profile', ['name' => 'New', 'email' => $user->email, 'current_password' => 'wrong', 'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/profile', ['name' => 'New', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame('New', $user->fresh()->name);
        $this->assertSame(Password::RESET_LINK_SENT, Password::sendResetLink(['email' => $user->email]));
    }
}
