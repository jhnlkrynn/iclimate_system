<x-app-layout>
    @include('advisories._analytics-status')
    <style>
        /* Typhoon safety check: an actual alert, so it keeps its dark, high-contrast panel. */
        .advisory-safety-card {
            position: relative;
            overflow: hidden;
            height: 100%;
            border: 1px solid rgba(255,209,102,.48);
            border-radius: 6px;
            background: #0d1f18;
            color: #fff;
            padding: 20px 24px;
        }
        .advisory-safety-eyebrow {
            color: #ffd166;
            font-family: Inter, Arial, sans-serif;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .advisory-safety-card h2 {
            color: #fff;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.375rem;
            font-weight: 700;
            margin: 10px 0 6px;
        }
        .advisory-safety-card p { color: rgba(255,255,255,.85); line-height: 1.5; margin: 0; }
        .advisory-safety-status {
            display: inline-flex;
            align-items: center;
            border-radius: 4px;
            padding: 4px 10px;
            background: rgba(82,183,136,.18);
            color: #74c69d;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .advisory-safety-status.needs-help { background: rgba(255,209,102,.16); color: #ffe8a3; }
        .advisory-safety-note {
            min-height: 74px;
            border: 1px solid rgba(255,255,255,.24);
            border-radius: 4px;
            background: rgba(255,255,255,.06);
            color: #fff;
            padding: 10px 12px;
            font-size: 15px;
        }
        .advisory-safety-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .advisory-safety-actions .btn { min-height: 44px; border-radius: 4px; font-weight: 600; flex: 1 1 auto; }
    </style>

    @php
        $isFarmerView = auth()->user()->role === \App\Models\User::ROLE_FARMER;
        // Farmers choose a barangay with the picker above the cards, so it is not counted as a filter for them.
        $activeFilterCount = collect(['search', 'advisory_type', 'status', 'severity', ...($isFarmerView ? [] : ['target_barangay'])])
            ->filter(fn ($field) => request()->filled($field))
            ->count();
        $isPagasa = fn ($advisory) => $advisory->isOfficialPagasa();
        // PAGASA advisories are official; everything iClimate makes from forecast data is labelled as guidance, never as PAGASA.
        $sourceText = fn ($advisory) => $isPagasa($advisory)
            ? 'PAGASA (official advisory)'
            : ($advisory->generated_automatically ? 'iClimate guidance from forecast data' : $advisory->plainSourceLabel());
        $severityTone = ['information' => 'info', 'low' => 'good', 'moderate' => 'watch', 'high' => 'danger', 'critical' => 'danger'];
        $severityIcon = ['info' => 'info', 'good' => 'check', 'watch' => 'alert', 'danger' => 'alert'];
        $statusTone = ['published' => 'good', 'pending_review' => 'watch', 'expired' => 'muted', 'rejected' => 'danger', 'archived' => 'muted'];
        $statusIcon = ['good' => 'check', 'watch' => 'clock', 'danger' => 'danger', 'muted' => 'info'];
        $typeKey = fn ($advisory) => strtolower((string) ($advisory->advisory_type ?: $advisory->type));
        $iCount = max(0, $activeCount - $pagasaAdvisoryCount);
        // Times are stored in UTC; farmers read them in Lian time.
        $local = fn ($time) => $time?->copy()->timezone(\App\Support\BusinessClock::timezone());
    @endphp

    @php
        $pagasaState = $advisoryCheck['unavailable'] ? 'Temporarily unavailable' : ($advisoryCheck['pagasa_connected'] ? ($advisoryCheck['pagasa_complete'] ? 'Connected' : 'Some sources unavailable') : 'Not checked yet');
        $pagasaTone = $advisoryCheck['pagasa_connected'] && ! $advisoryCheck['unavailable'] ? 'good' : 'watch';
    @endphp
    {{-- No automatic refresh on open: the scheduler fetches PAGASA and the barangay weather; the Refresh button is the only manual fetch. --}}
    {{-- .fad-advisories scopes this page's layout (farmer-advisories.css); other pages using .fad-* are unchanged. --}}
    <div class="fad fad-advisories">
        {{-- 1. Header --}}
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Forecast-Based Guidance</span>
                <h1>Farming Advisories</h1>
                <p>View PAGASA online advisories for Lian, Batangas plus local farm guidance based on current forecast conditions.</p>
                @if(auth()->user()->role === \App\Models\User::ROLE_FARMER)
                    <p class="fad-hero-note fad-hero-status"><span class="fad-dot is-{{ $pagasaTone }}" aria-hidden="true"></span>@include('dashboards.mao.icon', ['name' => 'clock'])<span>Latest advisory check: {{ $advisoryCheck['checked_at']?->copy()->timezone(\App\Support\BusinessClock::timezone())->format('g:i A') ?? 'Not checked yet' }} · PAGASA {{ $pagasaState }}</span></p>
                @endif
            </div>
            <div class="fad-hero-actions">
                @if($canManage)
                    <a class="fad-btn is-on-dark" href="{{ route('management.advisories.index') }}">Review Advisories</a>
                    <a class="fad-btn is-on-dark" href="{{ route('planting-advisories.create') }}">Create Advisory</a>
                @endif
                <form method="POST" action="{{ route('planting-advisories.refresh-pagasa') }}" data-loading="true" @if(auth()->user()->role === \App\Models\User::ROLE_FARMER) data-fad-refresh data-no-loader @endif>
                    @csrf
                    @if($selectedBarangay)<input type="hidden" name="target_barangay" value="{{ $selectedBarangay }}">@endif
                    <button class="fad-btn is-light" type="submit" data-loading-text="Refreshing...">@include('dashboards.mao.icon', ['name' => 'refresh'])<span>Refresh Advisories</span></button>
                </form>
            </div>
        </header>
        <p class="fad-support" role="status" aria-live="polite" data-fad-refresh-status hidden></p>

        @if($isFarmerView)
            {{-- Which barangay's advice to show: My Barangay (default), all of Lian's barangays, or one barangay. --}}
            <form class="fad-barangay-picker" method="GET" action="{{ route('planting-advisories.index') }}" data-fad-barangay-picker>
                <label class="fad-label" for="fadBarangayPicker"><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'map'])</span>Barangay</label>
                <select class="fad-input" id="fadBarangayPicker" name="target_barangay">
                    <option value="" @selected(! $viewAllBarangays && $selectedBarangay === $farmerBarangay)>My Barangay{{ $farmerBarangay ? ' ('.$farmerBarangay.')' : '' }}</option>
                    <option value="all" @selected($viewAllBarangays)>All Lian Barangays</option>
                    @foreach($barangays as $barangay)
                        <option value="{{ $barangay }}" @selected(! $viewAllBarangays && $selectedBarangay === $barangay && $selectedBarangay !== $farmerBarangay)>{{ $barangay }}</option>
                    @endforeach
                </select>
                @foreach(['search', 'advisory_type', 'status', 'severity'] as $keep)
                    @if(is_string(request($keep)) && request($keep) !== '')<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
                @endforeach
                <button class="fad-btn is-quiet" type="submit">Show</button>
            </form>
        @endif

        {{-- 2. Summary cards: the same figures, each with an icon and one consistent shape. --}}
        <section class="fad-summary" aria-label="Advisory overview">
            <article class="fad-card fad-stat">
                <span class="fad-stat-icon is-info" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'advisory'])</span>
                <div class="fad-stat-body">
                    <h2 class="fad-label">Official Advisories</h2>
                    <p class="fad-value">{{ $local($latestPagasaAdvisory?->published_at)?->format('M j, Y · g:i A') ?? 'No published PAGASA update for Lian yet' }}</p>
                    <p class="fad-support">
                        Source: PAGASA
                        @if($latestPagasaAdvisory)
                            · Scope: {{ data_get($latestPagasaAdvisory->metadata, 'scope_name', $latestPagasaAdvisory->targetLabel()) }}
                        @endif
                    </p>
                    @isset($pagasaSummary)
                        <details class="fad-details">
                            <summary>View Details</summary>
                            <p class="fad-support">
                                PAGASA pages checked: {{ number_format($pagasaSummary['sources_checked'] ?? 0) }};
                                new updates: {{ number_format($pagasaSummary['advisories_created'] ?? 0) }};
                                none for Lian: {{ number_format($pagasaSummary['sources_without_lian_batangas_match'] ?? 0) }}
                            </p>
                        </details>
                    @endisset
                    <div class="fad-card-foot">
                        <span class="fad-badge is-{{ $pagasaTone }}"><span>PAGASA {{ $pagasaState }}</span></span>
                        <p class="fad-support">Last checked: {{ $local($advisoryCheck['checked_at'])?->format('M j, Y · g:i A') ?? 'Not checked yet' }}</p>
                        @if($lastWeather)
                            {{-- fetched_at is stored in UTC and shown in Lian time. --}}
                            <p class="fad-support">{{ $lastWeather->source }} weather last updated: {{ $local($lastWeather->fetched_at)?->format('M j, Y · g:i A') }}</p>
                        @endif
                    </div>
                </div>
            </article>

            <article class="fad-card fad-stat">
                <span class="fad-stat-icon is-good" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'weather'])</span>
                <div class="fad-stat-body">
                    <h2 class="fad-label">Weather Data</h2>
                    <p class="fad-value">Open-Meteo</p>
                    @if($selectedBarangay)
                        <p class="fad-support">Location: {{ $selectedBarangay }}, Lian, Batangas</p>
                    @else
                        <p class="fad-support">Add a barangay for location-specific conditions.</p>
                    @endif
                    <div class="fad-card-foot">
                        @if (! empty($barangayWeather['fetched_at']))
                            <p class="fad-support"><x-updated-at :at="$barangayWeather['fetched_at']" /></p>
                        @else
                            <p class="fad-support">Walang datos pa</p>
                        @endif
                    </div>
                </div>
            </article>

            <article class="fad-card fad-stat">
                <span class="fad-stat-icon is-brand" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'bell'])</span>
                <div class="fad-stat-body">
                    <h2 class="fad-label">Active Advisories</h2>
                    <p class="fad-kpi">{{ number_format($activeCount) }}</p>
                    <div class="fad-stat-split">
                        <p class="fad-support"><strong>{{ number_format($pagasaAdvisoryCount) }}</strong> Official PAGASA</p>
                        @if($iCount > 0)
                            <p class="fad-support"><strong>{{ number_format($iCount) }}</strong> iClimate Guidance</p>
                        @endif
                    </div>
                </div>
            </article>
        </section>

        @if($activeTyphoonSafetyEvent)
            <div class="advisory-safety-card" aria-labelledby="typhoonSafetyTitle">
                <div class="advisory-safety-eyebrow">Typhoon safety check</div>
                <h2 id="typhoonSafetyTitle">Are you safe right now?</h2>
                <p>{{ $activeTyphoonSafetyEvent['title'] }} | {{ $activeTyphoonSafetyEvent['severity'] }}</p>
                @if($typhoonSafetyResponse)
                    <div class="mt-3">
                        <span class="advisory-safety-status {{ $typhoonSafetyResponse->status === 'needs_help' ? 'needs-help' : '' }}">
                            Current response: {{ $typhoonSafetyResponse->statusLabel() }}
                        </span>
                    </div>
                @endif
                <form class="d-grid gap-2 mt-3" method="POST" action="{{ route('typhoon-safety.store') }}" data-loading="true">
                    @csrf
                    <input type="hidden" name="event_key" value="{{ $activeTyphoonSafetyEvent['key'] }}">
                    <label class="visually-hidden" for="typhoonSafetyNote">Optional note for MAO</label>
                    <textarea id="typhoonSafetyNote" class="advisory-safety-note" name="note" rows="2" placeholder="Optional note for MAO.">{{ old('note', $typhoonSafetyResponse?->note) }}</textarea>
                    <div class="advisory-safety-actions">
                        <button class="btn btn-primary" type="submit" name="status" value="safe">I am safe</button>
                        <button class="btn btn-danger" type="submit" name="status" value="needs_help">I need help</button>
                    </div>
                </form>
            </div>
        @else
            {{-- Safety reminder: one short information line instead of a large card. --}}
            <aside class="fad-banner" role="note" aria-label="Safety Reminder">
                <span class="fad-banner-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'info'])</span>
                <p><strong>Safety Reminder:</strong> Generated advisories support planning. Follow official PAGASA online advisories and LGU warnings during severe weather.</p>
            </aside>
        @endif

        {{-- 3. Filters (open on desktop, collapsed on phones; see bindResponsiveCollapse in app.js) --}}
        <details class="ic-collapse fad-filters" @if($activeFilterCount) data-force-open="true" @endif>
            <summary>
                <span class="fad-label">Filters</span>
                @if($activeFilterCount)
                    <span class="fad-count" aria-label="{{ $activeFilterCount }} active {{ $activeFilterCount === 1 ? 'filter' : 'filters' }}">{{ $activeFilterCount }}</span>
                @endif
            </summary>
            <form class="fad-filter-form" method="GET" action="{{ route('planting-advisories.index') }}">
                <div class="fad-field is-search">
                    <label class="fad-label" for="fadSearch">Search</label>
                    <input class="fad-input" id="fadSearch" type="search" name="search" value="{{ is_string(request('search')) ? request('search') : '' }}" placeholder="Search advisories">
                </div>
                <div class="fad-field">
                    <label class="fad-label" for="fadType">Type</label>
                    <select class="fad-input" id="fadType" name="advisory_type">
                        <option value="">All</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected(is_string(request('advisory_type')) && request('advisory_type') === $type)>{{ str($type)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fad-field">
                    <label class="fad-label" for="fadStatus">Status</label>
                    <select class="fad-input" id="fadStatus" name="status">
                        <option value="">All</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(is_string(request('status')) && request('status') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fad-field">
                    <label class="fad-label" for="fadSeverity">Severity</label>
                    <select class="fad-input" id="fadSeverity" name="severity">
                        <option value="">All</option>
                        @foreach($severities as $severity)
                            <option value="{{ $severity }}" @selected(is_string(request('severity')) && request('severity') === $severity)>{{ str($severity)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                @unless($isFarmerView)
                <div class="fad-field">
                    <label class="fad-label" for="fadBarangay">Target Barangay</label>
                    <select class="fad-input" id="fadBarangay" name="target_barangay">
                        <option value="">All Barangays</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay }}" @selected(is_string(request('target_barangay')) && request('target_barangay') === $barangay)>{{ $barangay }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                    {{-- Keep the farmer's barangay choice when the other filters are applied. --}}
                    @if(is_string(request('target_barangay')) && request('target_barangay') !== '')<input type="hidden" name="target_barangay" value="{{ request('target_barangay') }}">@endif
                @endunless
                <div class="fad-filter-actions">
                    <button class="fad-btn is-primary" type="submit">Apply Filters</button>
                    <a class="fad-btn is-quiet" href="{{ route('planting-advisories.index') }}">Reset</a>
                </div>
            </form>
        </details>

        {{-- 4. Advisories: compact cards. Farmers see their barangay first, then Lian-wide advice. --}}
        @if(auth()->user()->role === \App\Models\User::ROLE_FARMER)
            {{-- .fad-farmer-lists: the compact farmer card layout (farmer-advisories.css); staff keep the larger cards. --}}
            <div class="fad-farmer-lists">
            @include('advisories._farmer-lists', [
                'myBarangayAdvisories' => $myBarangayAdvisories,
                'otherBarangayAdvisories' => $otherBarangayAdvisories,
                'viewAllBarangays' => $viewAllBarangays,
                'lianWideAdvisories' => $lianWideAdvisories,
                'farmerBarangay' => $farmerBarangay,
                'selectedBarangay' => $selectedBarangay,
                'barangayAdvisory' => $barangayAdvisory,
                'barangayWeather' => $barangayWeather,
                'officialPagasaAdvisories' => $officialPagasaAdvisories,
                'additionalPagasaInformation' => $additionalPagasaInformation,
                'advisoryCheck' => $advisoryCheck,
            ])
            </div>
            @include('advisories._announcements', ['announcements' => $announcements ?? collect()])
        @else
        {{-- 4. Advisories: compact cards. "View" opens the full advisory in a popup (farmer-advisories.js);
             without JavaScript, or with Ctrl/Cmd-click, it is a normal link to the advisory page. --}}
        <section class="fad-list" aria-labelledby="fadListTitle">
            @if($selectedBarangay)
                @include('advisories._barangay-weather-card', [
                    'selectedBarangay' => $selectedBarangay,
                    'barangayAdvisory' => $advisories->getCollection()->first(fn ($advisory) => $advisory->target_barangay === $selectedBarangay && data_get($advisory->metadata, 'source_type') === 'iclimate'),
                    'barangayWeather' => $barangayWeather,
                ])
            @endif
            <div class="fad-list-head">
                <h2 id="fadListTitle">Available Advisories</h2>
                @if($advisories->total() > 0)
                    <p class="fad-support">Showing {{ $advisories->firstItem() }}–{{ $advisories->lastItem() }} of {{ $advisories->total() }}</p>
                @endif
            </div>

            @if($advisories->count())
                <ul class="fad-cards">
                    @foreach($advisories as $advisory)
                        @php
                            $sev = $severityTone[$advisory->severity] ?? 'info';
                            $st = $statusTone[$advisory->status] ?? 'muted';
                            $issued = $local($advisory->created_at);
                        @endphp
                        <li class="fad-acard">
                            <div class="fad-acard-tags">
                                <span class="fad-badge is-type" data-type="{{ $typeKey($advisory) }}">{{ $advisory->typeLabel() }}</span>
                                <span class="fad-badge is-{{ $sev }}">@include('dashboards.mao.icon', ['name' => $severityIcon[$sev]])<span>{{ $advisory->severityLabel() }}</span></span>
                                <span class="fad-badge is-{{ $st }} is-status">@include('dashboards.mao.icon', ['name' => $statusIcon[$st]])<span>{{ $advisory->statusLabel() }}</span></span>
                            </div>
                            <h3 class="fad-title">{{ $advisory->title }}</h3>
                            <p class="fad-summary-text">{{ $advisory->displaySummary() }}</p>
                            <dl class="fad-acard-facts">
                                <div><dt>Applies to</dt><dd>{{ $advisory->targetLabel() }}</dd></div>
                                <div><dt>Valid until</dt><dd>{{ $advisory->valid_until ? $local($advisory->valid_until)->format('M j, Y · g:i A') : 'Open' }}</dd></div>
                            </dl>
                            <div class="fad-acard-foot">
                                <p class="fad-meta"><span class="fad-source {{ $isPagasa($advisory) ? 'is-official' : '' }}">{{ $sourceText($advisory) }}</span>@if($issued)<span>Issued {{ $issued->format('M j, Y') }}</span>@endif</p>
                                {{-- data-fast-nav-bound: the site's link prefetch / page-fade (app.js) skips this link; the popup handles it. --}}
                                <a class="fad-btn is-primary fad-view" href="{{ route('planting-advisories.show', $advisory) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="View advisory: {{ $advisory->title }}">View</a>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if($advisories->hasPages())
                    <div class="fad-pages">{{ $advisories->links() }}</div>
                @endif
            @elseif($activeFilterCount)
                <div class="fad-empty">
                    <p class="fad-lead">No advisories match these filters.</p>
                    <p class="fad-body">Try a different type, severity or barangay, or clear the filters to see everything.</p>
                    <a class="fad-btn is-quiet" href="{{ route('planting-advisories.index') }}">Reset Filters</a>
                </div>
            @elseif(!empty($advisoryCheck['unavailable']))
                <div class="fad-empty">
                    <p class="fad-lead">Advisory Information Temporarily Unavailable</p>
                    <p class="fad-body">Online advisory source is temporarily unreachable. Stored guidance will update when connection is restored.</p>
                </div>
            @else
                <div class="fad-empty">
                    <p class="fad-lead">No active advisories are available for your location.</p>
                    <p class="fad-body">New advice will appear here when the weather calls for it.</p>
                </div>
            @endif
        </section>
        @endif

    </div>

    {{-- One pop-up for every advisory; its content comes from the advisory page (?modal=1). --}}
    @include('partials.fad-modal-shell')
</x-app-layout>
