<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('users.profile', ['user' => auth()->user(), 'keys' => auth()->user()->apiKeys()->latest()->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(10)],
        ]);
        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('status', 'Profile saved.');
    }

    public function createKey(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        return back()->with('status', 'API key created. Copy it now: it will not be shown again.')->with('new_key', ApiKey::issue($request->user(), $data['name']));
    }

    public function deleteKey(Request $request, ApiKey $key): RedirectResponse
    {
        abort_unless($key->user_id === $request->user()->id, 404);
        $key->delete();

        return back()->with('status', 'API key revoked.');
    }
}
