@php
    $stamp = fn ($t, $dateOnly = false) => $t ? $t->format($dateOnly ? 'M j, Y' : 'M j, Y g:i A') : null;
@endphp
@if (count($it['data']) > 0)
    <section class="itd-card" aria-labelledby="itdDataTitle" data-it-data>
        <div class="itd-card-head"><h2 id="itdDataTitle">Data Status</h2></div>
        <div class="itd-data">
            @foreach ($it['data'] as $item)
                <article class="itd-data-item" data-data-section="{{ $item['key'] }}">
                    <h3>{{ $item['title'] }}</h3>
                    @if ($item['state'] === 'error')
                        <p class="itd-error" role="alert">{{ $item['message'] }}</p>
                    @else
                        @if (! empty($item['value']))<strong class="itd-data-value">{{ $item['value'] }}</strong>@endif
                        @if ($stamp($item['at'] ?? null, $item['date_only'] ?? false))
                            <small class="itd-muted d-block">{{ $item['label'] }}: {{ $stamp($item['at'], $item['date_only'] ?? false) }}</small>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
