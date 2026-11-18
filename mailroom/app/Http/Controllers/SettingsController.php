<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MailConfig;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('settings.edit', [
            'settings' => $settings->all(),
            'providers' => MailConfig::PROVIDERS,
            'hasPassword' => $settings->get('mail_password') !== null,
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'mail_provider' => ['required', Rule::in(array_keys(MailConfig::PROVIDERS))],
            'mail_host' => ['nullable', 'required_unless:mail_provider,log', 'string', 'max:190', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'mail_port' => ['nullable', 'required_unless:mail_provider,log', 'integer', 'between:1,65535'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_from_address' => ['required', 'email', 'max:190'],
            'mail_from_name' => ['required', 'string', 'max:120'],
            'send_rate_per_minute' => ['nullable', 'integer', 'between:0,100000'],
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['required', 'string', 'max:300'],
        ]);

        // A blank password field means "keep the current one".
        if (($data['mail_password'] ?? '') === '') {
            unset($data['mail_password']);
        }
        $settings->put(array_map(fn ($v) => $v === null ? null : (string) $v, $data));

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }

    public function test(Request $request, MailConfig $mailConfig): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        $mailConfig->apply();

        try {
            Mail::raw('This is a test email from '.config('app.name').'. Your sending settings work.', function ($message) use ($data): void {
                $message->to($data['to'])->subject('Test email from '.config('app.name'));
            });
        } catch (Throwable $e) {
            return back()->withErrors(['to' => 'Sending failed: '.str($e->getMessage())->limit(300)]);
        }

        return back()->with('status', "Test email sent to {$data['to']} via ".config('mail.default').'.');
    }
}
