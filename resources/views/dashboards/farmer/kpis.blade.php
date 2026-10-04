@php $arrow = ['up' => 'arrow-up', 'down' => 'arrow-down', 'flat' => 'arrow-flat']; @endphp
<section aria-labelledby="mfdKpiTitle" data-mfd-kpis>
    <h2 class="mfd-section-title" id="mfdKpiTitle">{{ __('farmer.kpi.title') }}</h2>
    <div class="mfd-kpis">
        {{-- Today's heat is in Weather Now; alerts remain a KPI because they are a direct, actionable count. --}}
        @foreach (collect($f['kpis'])->reject(fn ($k) => $k['key'] === 'heat') as $k)
            <a class="mfd-kpi {{ $k['missing'] ? 'is-missing' : '' }}" href="{{ $k['href'] }}" data-kpi="{{ $k['key'] }}">
                <span class="mfd-kpi-label">{{ $k['label'] }}</span>
                <span class="mfd-kpi-value"><span>{{ $k['display'] }}</span>@if ($k['unit'] !== '' && ! $k['missing'])<small>{{ $k['unit'] }}</small>@endif</span>
                @if ($k['missing'])
                    <span class="mfd-kpi-meaning">{{ $k['reason'] }}</span>
                @else
                    @if (! empty($k['headline']))<span class="mfd-kpi-headline">{{ $k['headline'] }}</span>@endif
                    <span class="mfd-kpi-meaning">{{ $k['meaning'] }}</span>
                    @if ($k['trend'])
                        <span class="mfd-kpi-trend">@include('dashboards.mao.icon', ['name' => $arrow[$k['trend']['dir']]])<span>{{ $k['trend']['text'] }}</span></span>
                    @endif
                    @if (in_array($k['status'], ['good', 'watch', 'danger'], true))
                        @include('dashboards.farmer._status', ['level' => $k['status'], 'label' => __('farmer.status.'.$k['status'])])
                    @endif
                @endif
                <span class="mfd-kpi-source">{{ __('farmer.kpi.source', ['source' => $k['source'], 'time' => $k['updated'] ? __('farmer.kpi.updated', ['time' => $k['updated']]) : __('farmer.kpi.no_time')]) }}</span>
            </a>
        @endforeach
    </div>
</section>
