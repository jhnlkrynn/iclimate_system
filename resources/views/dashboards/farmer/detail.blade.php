{{-- Farmer – Naicel (2026-11-22): one card's summary panel, kept in a <template> next to the card it belongs to so the
     timed refresh (farmer-dashboard.js) brings it up to date with the card. Opening it reads nothing from the server:
     farmer-dashboard.js copies this into the page's one drawer (farmer.blade.php, [data-fh-drawer]).
     $key: the card's key; $d: ['title', 'rows' => [label => value], 'badge'?, 'text'?, 'progress'?, 'updated_at'?, 'link'?]. --}}
<template data-fh-detail="{{ $key }}">
    <h2 class="fh-drawer-title" data-fh-drawer-title>{{ $d['title'] }}</h2>
    @if (! empty($d['badge']['label']))
        <p class="fh-risk-badge is-{{ $d['badge']['tone'] }}">{{ $d['badge']['label'] }}</p>
    @endif
    @if (! empty($d['rows']))
        <dl class="fh-drawer-facts">
            @foreach ($d['rows'] as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    @endif
    @if (($d['progress'] ?? null) !== null)
        <div class="fh-drawer-progress">
            <span class="fh-kpi-bar" role="img" aria-label="{{ __('farmer.kpis.crop.progress', ['percent' => $d['progress']]) }}"><span style="width: {{ $d['progress'] }}%"></span></span>
            <span class="fh-muted">{{ __('farmer.kpis.crop.progress', ['percent' => $d['progress']]) }}</span>
        </div>
    @endif
    @if (! empty($d['text']))
        <p class="fh-words">{{ $d['text'] }}</p>
    @endif
    @if (! empty($d['updated_at']))
        <x-updated-at :at="$d['updated_at']" :label="__('farmer.home.updated')" />
    @endif
    @if (! empty($d['link']))
        <a class="fh-link fh-drawer-more" href="{{ $d['link'] }}">{{ __('farmer.kpis.detail.view_full') }} <span aria-hidden="true">→</span></a>
    @endif
</template>
