@php $setup = $f['setup']; @endphp
@if ($setup)
    <section class="mfd-card" aria-labelledby="mfdSetupTitle" data-mfd-setup>
        <div class="mfd-card-head">
            <h2 id="mfdSetupTitle">Finish Setting Up Your Farm</h2>
            <span class="mfd-muted">{{ $setup['done'] }} of {{ $setup['total'] }} done</span>
        </div>
        <div class="mfd-progress" role="progressbar" aria-label="Farm setup" aria-valuemin="0" aria-valuemax="{{ $setup['total'] }}" aria-valuenow="{{ $setup['done'] }}"><i @style(['width: ' . round($setup['done'] / $setup['total'] * 100) . '%'])></i></div>
        <ul class="mfd-setup">
            @foreach ($setup['items'] as $item)
                <li>
                    <div><strong>{{ $item['title'] }}</strong> <span class="mfd-muted">{{ $item['benefit'] }}</span></div>
                    <a class="mfd-btn is-quiet" href="{{ $item['href'] }}">{{ $item['cta'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
