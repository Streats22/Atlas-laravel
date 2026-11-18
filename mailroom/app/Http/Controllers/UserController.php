<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\InviteUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::query()->orderBy('name')->get(), 'roles' => $this->assignable(auth()->user())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, $this->assignable($request->user())))],
        ]);
        $user = User::query()->create($data + ['password' => Str::random(40)]);
        $user->notify(new InviteUser(Password::broker()->createToken($user), $request->user()->name));

        return back()->with('status', "Invitation sent to {$user->email}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guard($request->user(), $user);
        $data = $request->validate(['role' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, $this->assignable($request->user())))]]);
        $user->update($data);

        return back()->with('status', "{$user->name} is now {$data['role']}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guard($request->user(), $user);
        $user->delete();

        return back()->with('status', "{$user->name} was removed.");
    }

    public function resendInvite(Request $request, User $user): RedirectResponse
    {
        $this->guard($request->user(), $user);
        $user->notify(new InviteUser(Password::broker()->createToken($user), $request->user()->name));

        return back()->with('status', "Invitation sent again to {$user->email}.");
    }

    /** Nobody edits themselves here, and only owners touch other owners. */
    private function guard(User $actor, User $target): void
    {
        abort_if($actor->is($target), 403, 'Change your own account under Profile.');
        abort_if($target->role === Role::Owner && $actor->role !== Role::Owner, 403);
    }

    /** @return list<Role> */
    private function assignable(User $actor): array
    {
        return array_values(array_filter(Role::cases(), fn (Role $r) => $r !== Role::Owner || $actor->role === Role::Owner));
    }
}
