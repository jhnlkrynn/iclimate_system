@php
    $icon = fn (string $name): string => view('dashboards.mao.icon', ['name' => $name])->render();
    // Status word -> [card edge, shared status pill, icon]. Same pills as the IT dashboard.
    $tone = fn (string $status): array => match ($status) {
        \App\Services\SystemHealthService::HEALTHY => ['is-ok', 'is-good', $icon('check')],
        \App\Services\SystemHealthService::NEEDS_ATTENTION => ['is-warn', 'is-watch', $icon('alert')],
        default => ['is-off', 'is-unk', $icon('info')],
    };
    $needsAttention = (int) $summary['needs_attention'];
    $unavailable = (int) $summary['unavailable'];
    $overall = $needsAttention > 0
        ? $tone(\App\Services\SystemHealthService::NEEDS_ATTENTION) + [3 => \App\Services\SystemHealthService::NEEDS_ATTENTION]
        : $tone(\App\Services\SystemHealthService::HEALTHY) + [3 => \App\Services\SystemHealthService::HEALTHY];
@endphp

<x-app-layout>
    {{-- Same design system as the IT dashboard: .mfd / .itd parts, plus the IT page parts in it-pages.css. --}}
    <div class="mfd itd itp">
        <x-it.page-header kicker="iClimate System Administration" title="System Health" lede="Live checks on the database, backups, background jobs, weather data and prediction models.">
            <span class="mfd-pill {{ $overall[1] }}">{!! $overall[2] !!}<span>{{ $overall[3] }}</span></span>
            <div class="itp-header-meta" role="status">
                <span class="mfd-age">Checked {{ $checkedAt->format('M j, Y g:i A') }}</span>
            </div>
            <div class="itd-header-actions">
                <a class="mfd-btn is-ghost" href="{{ route('model-registry.index') }}">@include('dashboards.mao.icon', ['name' => 'sparkle'])ML Model Registry</a>
                <a class="mfd-btn is-quiet" href="{{ route('system-health.index', ['refresh' => 1]) }}">@include('dashboards.mao.icon', ['name' => 'refresh'])Refresh</a>
            </div>
        </x-it.page-header>

        <section class="itp-section" aria-labelledby="shOverallTitle">
            <div class="itp-section-head">
                <h2 id="shOverallTitle">Overall Status</h2>
                <p>{{ $summary['headline'] }}</p>
            </div>
            <div class="mfd-kpis is-status" aria-label="Summary">
                <div class="mfd-kpi is-ok">
                    <span class="mfd-kpi-label">Healthy</span>
                    <span class="mfd-kpi-value">{{ $summary['healthy'] }}<small class="itd-unit">{{ \Illuminate\Support\Str::plural('check', (int) $summary['healthy']) }}</small></span>
                    <span class="mfd-kpi-trend">Working as expected</span>
                </div>
                <div class="mfd-kpi {{ $needsAttention > 0 ? 'is-warn' : 'is-ok' }}">
                    <span class="mfd-kpi-label">Needs Attention</span>
                    <span class="mfd-kpi-value">{{ $needsAttention }}<small class="itd-unit">{{ \Illuminate\Support\Str::plural('check', $needsAttention) }}</small></span>
                    <span class="mfd-kpi-trend">{{ $needsAttention > 0 ? 'Review these first' : 'Nothing to review' }}</span>
                </div>
                <div class="mfd-kpi is-unk">
                    <span class="mfd-kpi-label">Unavailable</span>
                    <span class="mfd-kpi-value">{{ $unavailable }}<small class="itd-unit">{{ \Illuminate\Support\Str::plural('check', $unavailable) }}</small></span>
                    <span class="mfd-kpi-trend">No data recorded yet</span>
                </div>
                <div class="mfd-kpi">
                    <span class="mfd-kpi-label">Total Checks</span>
                    <span class="mfd-kpi-value">{{ count($checks) }}<small class="itd-unit">{{ \Illuminate\Support\Str::plural('check', count($checks)) }}</small></span>
                    <span class="mfd-kpi-trend">Run each time this page loads</span>
                </div>
            </div>
        </section>

        <section class="itp-section" aria-labelledby="shServicesTitle">
            <div class="itp-section-head">
                <h2 id="shServicesTitle">Service Status</h2>
                <p>Each check shows its current status and the details behind it.</p>
            </div>
            <div class="itp-grid">
                @foreach ($checks as $check)
                    @php $t = $tone($check['status']); @endphp
                    <article class="mfd-card itp-check {{ $t[0] }}" data-health-check="{{ $check['key'] }}" data-health-status="{{ $check['status'] }}">
                        <div class="itp-check-head">
                            <h3 class="itp-card-title">{{ $check['title'] }}</h3>
                            <span class="mfd-pill {{ $t[1] }}">{!! $t[2] !!}<span>{{ $check['status'] }}</span></span>
                        </div>
                        <p>{{ $check['summary'] }}</p>
                        @if (! empty($check['details']))
                            <dl class="mfd-facts itd-rows">
                                @foreach ($check['details'] as $label => $value)
                                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        {{-- Moved here from the IT dashboard (2026-10): Usage Patterns, Resources and the Data Pipeline. --}}
        @if ($it ?? null)
            <section class="itp-section" aria-labelledby="shUsageTitle" data-health-section="usage">
                <div class="itp-section-head">
                    <h2 id="shUsageTitle">Usage Patterns</h2>
                    <p>How people use the system, and how the chatbot is used.</p>
                </div>
                <div class="itd-row"><div class="itd-cell" id="itd-usage">@include('dashboards.it.usage')</div></div>
                <div class="itd-row"><div class="itd-cell" id="itd-ai">@include('dashboards.it.ai')</div></div>
            </section>

            <section class="itp-section" aria-labelledby="shResourcesTitle" data-health-section="resources">
                <div class="itp-section-head">
                    <h2 id="shResourcesTitle">Resources</h2>
                    <p>Storage, uploads and the counts of the main records.</p>
                </div>
                <div class="itd-row r-50-50">
                    <div class="itd-cell" id="itd-resources">@include('dashboards.it.resources')</div>
                    <div class="itd-cell" id="itd-integrity">@include('dashboards.it.integrity')</div>
                </div>
            </section>

            <section class="itp-section" aria-labelledby="shPipelineTitle" data-health-section="pipeline">
                <div class="itp-section-head">
                    <h2 id="shPipelineTitle">Data Pipeline</h2>
                    <p>When each data source was last updated.</p>
                </div>
                <div class="itd-row"><div class="itd-cell" id="itd-pipeline">@include('dashboards.it.pipeline')</div></div>
            </section>
        @else
            <section class="itp-section" aria-labelledby="shMoreTitle" data-health-section="not-prepared">
                <div class="itp-section-head">
                    <h2 id="shMoreTitle">Usage Patterns, Resources and Data Pipeline</h2>
                </div>
                <x-no-data note="These appear after the next scheduled update, or after the IT Dashboard is opened." />
            </section>
        @endif
    </div>
</x-app-layout>
