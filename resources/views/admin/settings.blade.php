<x-app-layout>
    <x-slot:title>System Settings</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 8: SystemSettingsController::general. Each field names where it is used. --}}
    @php $s = $settings; $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp
    <div class="mfd itd itp" data-system-settings>
        <x-it.page-header kicker="IT Personnel" title="System Settings" lede="Name, office, logo, risk thresholds, notifications and maintenance mode." />
        @include('partials.manuscript-tabs', ['group' => 'settings', 'label' => 'Settings'])
        @if ($s['maintenance_mode'])
            <div class="alert alert-warning" role="status" data-maintenance-on>Maintenance mode is ON: only IT can use iClimate right now.</div>
        @endif

        <form method="POST" action="{{ route('admin.settings.save') }}" enctype="multipart/form-data" data-loading="true">
            @csrf
            <section class="itp-section" aria-labelledby="stGeneral">
                <div class="itp-section-head"><h2 id="stGeneral">General</h2><p>Shown on page titles, printed reports and PDFs.</p></div>
                <div class="mfd-card row g-3 mx-0">
                    <div class="col-md-4"><label class="form-label fw-semibold" for="stName">System name</label><input class="form-control" id="stName" name="system_name" maxlength="60" required value="{{ old('system_name', $s['system_name']) }}"></div>
                    <div class="col-md-8"><label class="form-label fw-semibold" for="stOffice">Office / municipality (report header)</label><input class="form-control" id="stOffice" name="office_name" maxlength="150" required value="{{ old('office_name', $s['office_name']) }}"></div>
                    <div class="col-md-8"><label class="form-label fw-semibold" for="stWeather">Weather data sources (shown to IT and on reports)</label><input class="form-control" id="stWeather" name="weather_source_note" maxlength="300" value="{{ old('weather_source_note', $s['weather_source_note']) }}"></div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="stLogo">Logo (PNG/JPG, up to 1 MB)</label>
                        <input class="form-control" id="stLogo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                        @if ($logoUrl)
                            <div class="d-flex align-items-center gap-2 mt-2"><img src="{{ $logoUrl }}" alt="Current logo" style="height: 40px;"><label class="form-check small"><input class="form-check-input" type="checkbox" name="remove_logo" value="1"> Remove</label></div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="itp-section" aria-labelledby="stSeason">
                <div class="itp-section-head"><h2 id="stSeason">Season calendar</h2><p>Wet season: May–October; dry season: November–April. It is fixed to match how the rice-yield model was trained; it changes when the model is retrained (ML Models).</p></div>
            </section>

            <section class="itp-section" aria-labelledby="stRisk">
                <div class="itp-section-head"><h2 id="stRisk">Risk thresholds</h2><p>Weekly farm risk on the Heat Map and the farmer's risk badge. Leave a box empty to keep the system's value (shown). Changes apply at the next forecast refresh.</p></div>
                <div class="mfd-card row g-3 mx-0">
                    @foreach (['risk_heavy_rain_mm' => 'Heavy-rain day (mm)', 'risk_very_heavy_rain_mm' => 'Very heavy rain day = High (mm)', 'risk_strong_wind_kmh' => 'Strong wind (km/h)', 'risk_storm_wind_kmh' => 'Storm wind = High (km/h)', 'risk_hot_day_c' => 'Hot day (°C)'] as $key => $label)
                        <div class="col-sm-6 col-lg-4"><label class="form-label fw-semibold" for="{{ $key }}">{{ $label }}</label><input class="form-control" id="{{ $key }}" name="{{ $key }}" type="number" step="0.1" value="{{ old($key, $num($s[$key])) }}" placeholder="{{ $num($riskDefaults[$key]) }}"></div>
                    @endforeach
                </div>
                <div class="itp-section-head mt-3"><h2 class="h6">Monthly climate signals</h2><p>Weather Analysis and the Climate Trend report.</p></div>
                <div class="mfd-card row g-3 mx-0">
                    <div class="col-sm-4"><label class="form-label fw-semibold" for="stHeavy">Heavy-rain month (mm or more)</label><input class="form-control" id="stHeavy" name="heavy_rain_month_mm" type="number" step="1" required value="{{ old('heavy_rain_month_mm', $num($s['heavy_rain_month_mm'])) }}"></div>
                    <div class="col-sm-4"><label class="form-label fw-semibold" for="stDry">Dry month (below mm)</label><input class="form-control" id="stDry" name="dry_month_mm" type="number" step="1" required value="{{ old('dry_month_mm', $num($s['dry_month_mm'])) }}"></div>
                    <div class="col-sm-4"><label class="form-label fw-semibold" for="stHot">Hot month (°C average or more)</label><input class="form-control" id="stHot" name="hot_month_c" type="number" step="0.1" required value="{{ old('hot_month_c', $num($s['hot_month_c'])) }}"></div>
                </div>
            </section>

            <section class="itp-section" aria-labelledby="stNotify">
                <div class="itp-section-head"><h2 id="stNotify">Notifications and sign-up</h2></div>
                <div class="mfd-card">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="notify_farmers_on_publish" value="1" @checked($s['notify_farmers_on_publish'])> Alert farmers (bell) when the MAO publishes an advisory or sends an alert</label>
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="farmer_approval" value="1" @checked($farmerApproval)> New farmer sign-ups wait for MAO approval (Farmer Approvals)</label>
                </div>
            </section>

            <section class="itp-section" aria-labelledby="stMaint">
                <div class="itp-section-head"><h2 id="stMaint">Maintenance mode</h2><p>While on, everyone except IT sees the message below instead of iClimate. Sign-in stays open for IT.</p></div>
                <div class="mfd-card">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" @checked($s['maintenance_mode']) data-maintenance-toggle> Maintenance mode on</label>
                    <label class="form-label fw-semibold mt-2" for="stMsg">Message</label>
                    <input class="form-control" id="stMsg" name="maintenance_message" maxlength="300" required value="{{ old('maintenance_message', $s['maintenance_message']) }}">
                </div>
            </section>

            @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <button class="btn btn-primary" type="submit" data-loading-text="Saving...">Save settings</button>
        </form>
    </div>
</x-app-layout>
