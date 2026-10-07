<x-app-layout>
    <x-slot:title>Security</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 8: SystemSettingsController::security. --}}
    @php $s = $settings; $ok = fn (bool $v) => $v ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-warning">Check</span>'; @endphp
    <div class="mfd itd itp" data-security-settings>
        <x-it.page-header kicker="IT Personnel" title="Security" lede="Session timeout, password policy, failed-login lockout and HTTPS status." />
        @include('partials.manuscript-tabs', ['group' => 'settings', 'label' => 'Settings'])

        <form method="POST" action="{{ route('admin.settings.security.save') }}" data-loading="true">
            @csrf
            <section class="itp-section" aria-labelledby="secSession">
                <div class="itp-section-head"><h2 id="secSession">Session timeout</h2><p>Someone who is idle this long is signed out. Now: {{ $sessionMinutes }} minutes.</p></div>
                <div class="mfd-card"><label class="form-label fw-semibold" for="secTimeout">Minutes (5–1440)</label><input class="form-control" style="max-width: 200px" id="secTimeout" name="session_timeout_minutes" type="number" min="5" max="1440" required value="{{ old('session_timeout_minutes', $s['session_timeout_minutes'] ?? $sessionMinutes) }}"></div>
            </section>
            <section class="itp-section" aria-labelledby="secPassword">
                <div class="itp-section-head"><h2 id="secPassword">Password policy</h2><p>For new passwords: sign-up, IT-created accounts, password change and reset. At least 8 characters always.</p></div>
                <div class="mfd-card">
                    <label class="form-label fw-semibold" for="secMin">Minimum length</label>
                    <input class="form-control mb-2" style="max-width: 200px" id="secMin" name="password_min_length" type="number" min="8" max="64" required value="{{ old('password_min_length', $s['password_min_length']) }}">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="password_mixed_case" value="1" @checked($s['password_mixed_case'])> Upper- and lower-case letters</label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="password_numbers" value="1" @checked($s['password_numbers'])> At least one number</label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="password_symbols" value="1" @checked($s['password_symbols'])> At least one symbol</label>
                </div>
            </section>
            <section class="itp-section" aria-labelledby="secLockout">
                <div class="itp-section-head"><h2 id="secLockout">Failed-login lockout</h2><p>After this many wrong passwords for one email from one device, sign-in waits. Lockouts are in System Logs ("Login Lockout").</p></div>
                <div class="mfd-card row g-3 mx-0">
                    <div class="col-sm-4"><label class="form-label fw-semibold" for="secAttempts">Attempts (3–20)</label><input class="form-control" id="secAttempts" name="lockout_attempts" type="number" min="3" max="20" required value="{{ old('lockout_attempts', $s['lockout_attempts']) }}"></div>
                    <div class="col-sm-4"><label class="form-label fw-semibold" for="secMinutes">Wait (minutes)</label><input class="form-control" id="secMinutes" name="lockout_minutes" type="number" min="1" max="120" required value="{{ old('lockout_minutes', $s['lockout_minutes']) }}"></div>
                </div>
            </section>
            @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <button class="btn btn-primary" type="submit" data-loading-text="Saving...">Save security settings</button>
        </form>

        <section class="itp-section" aria-labelledby="secHttps">
            <div class="itp-section-head"><h2 id="secHttps">HTTPS and security status</h2><p>Set in the server's .env (see deploy/README.md); shown here so IT can check them.</p></div>
            <div class="mfd-card">
                <table class="table table-sm mb-0" data-https-status>
                    <tr><td>This page was opened over HTTPS</td><td>{!! $ok($https['request']) !!}</td></tr>
                    <tr><td>APP_URL starts with https://</td><td>{!! $ok($https['app_url']) !!}</td></tr>
                    <tr><td>Session cookie only over HTTPS (SESSION_SECURE_COOKIE)</td><td>{!! $ok($https['secure_cookie']) !!}</td></tr>
                    <tr><td>Debug mode off (APP_DEBUG=false)</td><td>{!! $ok($https['debug_off']) !!}</td></tr>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
