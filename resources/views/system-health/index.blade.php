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
        .sh-hero {
            border-radius: 8px;
            padding: 1.25rem 1.35rem;
            margin-bottom: 1rem;
            color: #1f2a24;
            background: linear-gradient(135deg, #f6f9f7 0%, #eef4f0 60%, #e7f0ea 100%);
            border: 1px solid #e3ece6;
            box-shadow: 0 1rem 2.2rem rgba(31,42,36,.08);
        }
        .sh-hero p { color: #4a5c52; margin: 0; }
        .sh-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .85rem; }
        .sh-chip { border-radius: 999px; padding: .3rem .8rem; font-size: .85rem; font-weight: 600; background: #fff; border: 1px solid #d4e3d9; color: #1f2a24; }
        .sh-card { height: 100%; border: 1px solid #d4edda; border-left-width: 5px; border-radius: 8px; background: rgba(255,255,255,.97); box-shadow: 0 .6rem 1.4rem rgba(13,31,24,.07); }
        .sh-card.sh-ok { border-left-color: #2f9e6e; }
        .sh-card.sh-warn { border-left-color: #d9a21b; }
        .sh-card.sh-off { border-left-color: #8a958f; }
        .sh-card-title { font-size: 1rem; font-weight: 700; margin: 0; color: #1f2a24; }
        .sh-summary { color: #33463c; margin: .5rem 0 0; }
        .sh-details { margin: .75rem 0 0; padding: 0; list-style: none; font-size: .88rem; color: #4a5c52; }
        .sh-details li { display: flex; justify-content: space-between; gap: 1rem; padding: .25rem 0; border-top: 1px solid #eef3ef; }
        .sh-details span:last-child { text-align: right; color: #1f2a24; }
    </style>

    <div class="container-fluid py-3">
        <section class="sh-hero">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h1 class="h4 fw-bold mb-1">System Health</h1>
                    <p>{{ $summary['headline'] }} Checked {{ $checkedAt->format('M j, Y g:i A') }}.</p>
                </div>
                <a class="btn btn-outline-success btn-sm" href="{{ route('system-health.index') }}">Refresh</a>
            </div>
            <div class="sh-chips" aria-label="Summary">
                <span class="sh-chip">{{ $summary['healthy'] }} Healthy</span>
                <span class="sh-chip">{{ $summary['needs_attention'] }} Needs Attention</span>
                <span class="sh-chip">{{ $summary['unavailable'] }} Unavailable</span>
            </div>
        </section>

        <div class="row g-3">
            @foreach ($checks as $check)
                <div class="col-12 col-md-6 col-xl-4">
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
