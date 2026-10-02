@php
    $a = $it['attention'];
    $icons = ['ok' => '✓', 'warn' => '▲', 'crit' => '✕'];
    $title = ['ok' => 'All clear', 'warn' => 'Needs your attention', 'crit' => 'Needs your attention now'];
@endphp
<section class="itd-summary is-{{ $a['level'] }}" aria-labelledby="itdSummaryTitle" data-it-summary>
    <span class="itd-summary-icon" aria-hidden="true">{{ $icons[$a['level']] }}</span>
    <div class="itd-summary-body">
        <h2 id="itdSummaryTitle">{{ $title[$a['level']] }}</h2>
        <p>{{ $a['text'] }}</p>
        @if (count($a['chips']))
            <ul class="itd-chips" aria-label="Issues">
                @foreach ($a['chips'] as $chip)
                    <li><a class="itd-chip is-{{ $chip['severity'] === 'critical' ? 'crit' : 'warn' }}" href="{{ route('admin.dashboard', ['tab' => $chip['tab']]) }}#{{ $chip['anchor'] }}" data-tab-link="{{ $chip['tab'] }}" data-target="{{ $chip['anchor'] }}"><span aria-hidden="true">{{ $chip['severity'] === 'critical' ? '✕' : '▲' }}</span> {{ $chip['label'] }}</a></li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
