@php
    $badgeClass = fn (string $status): string => match ($status) {
        \App\Services\SystemHealthService::HEALTHY => 'text-bg-success',
        \App\Services\SystemHealthService::NEEDS_ATTENTION => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
    $accentClass = fn (string $status): string => match ($status) {
        \App\Services\SystemHealthService::HEALTHY => 'sh-ok',
        \App\Services\SystemHealthService::NEEDS_ATTENTION => 'sh-warn',
        default => 'sh-off',
    };
@endphp

<x-app-layout>
    <style>
        /* Compact layout: same content, tighter spacing and type. */
        .sh-hero {
            border-radius: 8px;
            padding: .75rem 1rem;
            margin-bottom: .75rem;
            color: #1f2a24;
            background: linear-gradient(135deg, #f6f9f7 0%, #eef4f0 60%, #e7f0ea 100%);
            border: 1px solid #e3ece6;
            box-shadow: 0 .5rem 1.2rem rgba(31,42,36,.07);
        }
        .sh-hero h1 { font-size: 1.15rem; margin-bottom: .1rem !important; }
        .sh-hero p { color: #4a5c52; margin: 0; font-size: .85rem; }
        .sh-hero .btn-sm { padding: .2rem .6rem; font-size: .8rem; }
        .sh-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .5rem; }
        .sh-chip { border-radius: 999px; padding: .15rem .65rem; font-size: .78rem; font-weight: 600; background: #fff; border: 1px solid #d4e3d9; color: #1f2a24; }
        .sh-card { height: 100%; border: 1px solid #d4edda; border-left-width: 4px; border-radius: 8px; background: rgba(255,255,255,.97); box-shadow: 0 .3rem .8rem rgba(13,31,24,.06); }
        .sh-card .card-body { padding: .55rem .75rem; }
        .sh-card.sh-ok { border-left-color: #2f9e6e; }
        .sh-card.sh-warn { border-left-color: #d9a21b; }
        .sh-card.sh-off { border-left-color: #8a958f; }
        .sh-card-title { font-size: .92rem; font-weight: 700; margin: 0; color: #1f2a24; min-width: 0; overflow-wrap: anywhere; }
        .sh-card .badge { font-size: .7rem; padding: .3em .55em; flex: none; }
        .sh-summary { color: #33463c; margin: .25rem 0 0; font-size: .8rem; line-height: 1.3; overflow-wrap: anywhere; }
        .sh-details { margin: .45rem 0 0; padding: 0; list-style: none; font-size: .8rem; color: #4a5c52; }
        .sh-details li { display: flex; justify-content: space-between; gap: .6rem; padding: .1rem 0; border-top: 1px solid #eef3ef; font-size: .78rem; line-height: 1.3; }
        .sh-details span { min-width: 0; overflow-wrap: anywhere; }
        .sh-details span:last-child { text-align: right; color: #1f2a24; }
        @media (max-width: 575.98px) {
            .sh-hero { padding: .65rem .8rem; }
            .sh-details li { flex-wrap: wrap; }
        }
    </style>

    <div class="container-fluid py-2">
        <section class="sh-hero">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h1 class="h4 fw-bold mb-1">System Health</h1>
                    <p>{{ $summary['headline'] }} Checked {{ $checkedAt->format('M j, Y g:i A') }}.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-success btn-sm" href="{{ route('model-registry.index') }}">ML Model Registry</a>
                    <a class="btn btn-outline-success btn-sm" href="{{ route('system-health.index', ['refresh' => 1]) }}">Refresh</a>
                </div>
            </div>
            <div class="sh-chips" aria-label="Summary">
                <span class="sh-chip">{{ $summary['healthy'] }} Healthy</span>
                <span class="sh-chip">{{ $summary['needs_attention'] }} Needs Attention</span>
                <span class="sh-chip">{{ $summary['unavailable'] }} Unavailable</span>
            </div>
        </section>

        <div class="row g-2">
            @foreach ($checks as $check)
                <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                    <article class="card sh-card {{ $accentClass($check['status']) }}" data-health-check="{{ $check['key'] }}" data-health-status="{{ $check['status'] }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <h2 class="sh-card-title">{{ $check['title'] }}</h2>
                                <span class="badge {{ $badgeClass($check['status']) }}">{{ $check['status'] }}</span>
                            </div>
                            <p class="sh-summary">{{ $check['summary'] }}</p>
                            @if (! empty($check['details']))
                                <ul class="sh-details">
                                    @foreach ($check['details'] as $label => $value)
                                        <li><span>{{ $label }}</span><span>{{ $value }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
