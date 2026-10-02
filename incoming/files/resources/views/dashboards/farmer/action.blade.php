@php
    $a = $f['action'];
    $w = $f['weather'];
    $iconFor = ['humidity' => 'humidity', 'rain' => 'rain', 'wind' => 'wind', 'heat' => 'heat', 'normal' => 'weather', 'advisory' => 'advisory'];
    $ask = 'It is currently '.($w['state'] === 'ok' ? strtolower($w['condition']).', '.number_format($w['temperature'], 1).'°C' : 'the usual weather')
        .' in '.($f['barangay'] ?: 'Lian, Batangas').'. What should I do for my farm today?';
@endphp
@if ($a)
    <section class="mfd-card mfd-action" aria-labelledby="mfdActionTitle" data-mfd-action>
        <div class="mfd-card-head"><h2 id="mfdActionTitle">What You Should Do Today</h2></div>
        <div class="mfd-action-body">
            <span class="mfd-action-icon">@include('dashboards.mao.icon', ['name' => $iconFor[$a['topic']] ?? 'weather'])</span>
            <div>
                <h3>{{ $a['title'] }}</h3>
                @foreach ($a['lines'] as $line)<p>{{ $line }}</p>@endforeach
                @if ($a['advisory'])
                    <p class="mfd-action-advisory"><strong>Advisory from the Municipal Agriculture Office:</strong> {{ $a['advisory']['title'] }}@if ($a['advisory']['summary']) — {{ $a['advisory']['summary'] }}@endif</p>
                @endif
            </div>
        </div>
        <div class="mfd-actions">
            @if ($a['advisory'])<a class="mfd-btn" href="{{ $a['advisory']['href'] }}">View Full Advisory</a>@endif
            <button type="button" class="mfd-btn is-quiet" data-ai-prefill="{{ $ask }}">Ask Climora AI</button>
        </div>
    </section>
@endif
