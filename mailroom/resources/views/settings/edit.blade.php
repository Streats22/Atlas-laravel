@extends('layouts.app')
@section('title', 'Settings')
@section('content')
    <header class="page-head"><h1>Settings</h1></header>
    <div class="grid-2">
        <form method="post" action="{{ route('settings.update') }}" class="card stack" id="mail-settings">
            @csrf @method('put')
            <h2>Sending provider</h2>
            <p class="muted small">Every provider below supports SMTP. Create SMTP credentials in your provider's dashboard and paste them here.</p>
            <label>Provider
                <select name="mail_provider" id="provider">
                    @foreach ($providers as $key => $p)
                        <option value="{{ $key }}" data-host="{{ $p['host'] }}" data-port="{{ $p['port'] }}" data-encryption="{{ $p['encryption'] }}" data-username="{{ $p['username'] }}" @selected(old('mail_provider', $settings['mail_provider'] ?? 'smtp') === $key)>{{ $p['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="smtp-fields stack">
                <div class="grid-2">
                    <label>SMTP host <input name="mail_host" id="mail_host" value="{{ old('mail_host', $settings['mail_host'] ?? '') }}"></label>
                    <label>Port <input type="number" name="mail_port" id="mail_port" value="{{ old('mail_port', $settings['mail_port'] ?? 587) }}"></label>
                </div>
                <label>Encryption
                    <select name="mail_encryption" id="mail_encryption">
                        @foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None (not recommended)'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('mail_encryption', $settings['mail_encryption'] ?? 'tls') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Username <input name="mail_username" id="mail_username" value="{{ old('mail_username', $settings['mail_username'] ?? '') }}" autocomplete="off"></label>
                <label>Password / API key <input type="password" name="mail_password" autocomplete="new-password" placeholder="{{ $hasPassword ? '•••••••• (leave blank to keep)' : '' }}"></label>
            </div>
            <div class="grid-2">
                <label>Default from email <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $settings['mail_from_address'] ?? '') }}" required></label>
                <label>Default from name <input name="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name'] ?? config('app.name')) }}" required></label>
            </div>
            <label>Max emails per minute <small class="muted">(0 = no limit; match your provider's plan)</small><input type="number" min="0" name="send_rate_per_minute" value="{{ old('send_rate_per_minute', $settings['send_rate_per_minute'] ?? 0) }}"></label>
            <h2>Company details</h2>
            <p class="muted small">Anti-spam laws (CAN-SPAM, GDPR) require your name and postal address in every campaign. They are added to the footer automatically.</p>
            <label>Company or sender name <input name="company_name" value="{{ old('company_name', $settings['company_name'] ?? '') }}" required></label>
            <label>Postal address <input name="company_address" value="{{ old('company_address', $settings['company_address'] ?? '') }}" required></label>
            <div class="row"><button class="btn btn-primary">Save settings</button></div>
        </form>

        <div class="stack">
            <form method="post" action="{{ route('settings.test') }}" class="card stack">
                @csrf
                <h2>Send a test email</h2>
                <p class="muted small">Save first, then check that the provider accepts mail.</p>
                <label>Send to <input type="email" name="to" value="{{ auth()->user()->email }}" required></label>
                <button class="btn">Send test email</button>
            </form>
            <section class="card">
                <h2>Authenticate your domain</h2>
                <p class="muted">Inbox providers reject or spam-folder bulk mail from unauthenticated domains. Add these DNS records for the domain of your from address (exact values are in your provider's dashboard):</p>
                <dl class="meta">
                    <dt>SPF</dt><dd>TXT record on your domain, e.g. <code>v=spf1 include:&lt;provider&gt; ~all</code></dd>
                    <dt>DKIM</dt><dd>CNAME or TXT records your provider generates, e.g. <code>s1._domainkey</code></dd>
                    <dt>DMARC</dt><dd>TXT on <code>_dmarc</code>: <code>v=DMARC1; p=none; rua=mailto:you@yourdomain</code></dd>
                </dl>
                <p class="muted small">Gmail and Yahoo require SPF, DKIM, DMARC and one-click unsubscribe for bulk senders. Mailroom adds the one-click unsubscribe header for you.</p>
            </section>
        </div>
    </div>
@endsection
@push('scripts')
<script>
(() => {
    const provider = document.getElementById('provider');
    const sync = (fill) => {
        const opt = provider.selectedOptions[0];
        document.querySelector('.smtp-fields').hidden = provider.value === 'log';
        if (!fill || provider.value === 'smtp' || provider.value === 'log') return;
        document.getElementById('mail_host').value = opt.dataset.host;
        document.getElementById('mail_port').value = opt.dataset.port;
        document.getElementById('mail_encryption').value = opt.dataset.encryption;
        document.getElementById('mail_username').placeholder = opt.dataset.username;
    };
    provider.addEventListener('change', () => sync(true));
    sync(false);
})();
</script>
@endpush
