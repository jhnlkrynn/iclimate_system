<x-app-layout>
    @php
        $fil = $locale === 'fil';
        $title = $fil ? 'Suriin ang ani ng mga bukid' : 'Explore farm results';
    @endphp
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="fad frec" data-explore-results>
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ $fil ? 'Mga Bukid sa Lian' : 'Lian Farm Results' }}</span>
                <h1>{{ $title }}</h1>
                <p>{{ $fil ? 'Tingnan ang pinagsama-samang ani ng mga aprubadong bukid sa Lian.' : 'See aggregated harvest results from approved farms in Lian.' }}</p>
            </div>
        </header>

        <section class="frec-panel mb-3" aria-label="{{ $fil ? 'Mga filter' : 'Filters' }}">
            <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="{{ $fil ? 'Uri ng bukid' : 'Farm type' }}">
                @foreach (['Irrigated', 'Rainfed'] as $typeOption)
                    <a href="{{ route('farmer.explore-results.index', array_filter(['farm_type' => $typeOption, 'barangay' => $result['barangay'], 'season' => $result['season'], 'year' => $result['year'], 'variety_id' => $result['variety_id']])) }}"
                        @class(['fad-btn', 'is-compact', 'is-primary' => $result['farm_type'] === $typeOption, 'is-quiet' => $result['farm_type'] !== $typeOption])
                        @if ($result['farm_type'] === $typeOption) aria-current="page" @endif>
                        {{ $typeOption }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('farmer.explore-results.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="farm_type" value="{{ $result['farm_type'] }}">
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expBarangay">{{ $fil ? 'Barangay' : 'Barangay' }}</label>
                    <select id="expBarangay" name="barangay" class="fad-input" data-autosubmit>
                        <option value="">{{ $fil ? 'Buong Lian' : 'All of Lian' }}</option>
                        @foreach ($options['barangays'] as $b)
                            <option value="{{ $b }}" @selected($result['barangay'] === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expSeason">{{ $fil ? 'Panahon' : 'Season' }}</label>
                    <select id="expSeason" name="season" class="fad-input" data-autosubmit>
                        <option value="">{{ $fil ? 'Lahat' : 'All seasons' }}</option>
                        <option value="Wet" @selected($result['season'] === 'Wet')>Wet</option>
                        <option value="Dry" @selected($result['season'] === 'Dry')>Dry</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expYear">{{ $fil ? 'Taon' : 'Year' }}</label>
                    <select id="expYear" name="year" class="fad-input" data-autosubmit>
                        <option value="">{{ $fil ? 'Lahat ng taon' : 'All years' }}</option>
                        @foreach ($options['years'] as $y)
                            <option value="{{ $y }}" @selected((int) $result['year'] === (int) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expVariety">{{ $fil ? 'Barayti' : 'Variety' }}</label>
                    <select id="expVariety" name="variety_id" class="fad-input" data-autosubmit>
                        <option value="">{{ $fil ? 'Lahat' : 'All varieties' }}</option>
                        @foreach ($options['varieties'] as $v)
                            <option value="{{ $v->id }}" @selected((int) $result['variety_id'] === (int) $v->id)>{{ $v->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </section>

        <section class="frec-panel mb-3" data-explore-insight>
            <p class="mb-0 fw-semibold" style="color: var(--sage-800, #1f3d28);">{{ $result['insight'] }}</p>
        </section>

        @if (! $result['sufficient'])
            <section class="frec-panel" data-explore-empty>
                <p class="mb-0 text-muted">{{ $fil ? 'Hindi pa sapat ang bilang ng mga bukid.' : 'Not enough farms yet.' }}</p>
            </section>
        @else
            <div class="row g-3 mb-3">
                <div class="col-4">
                    <div class="frec-panel h-100">
                        <div class="small text-muted">{{ $fil ? 'Mga bukid' : 'Farms' }}</div>
                        <div class="fs-4 fw-bold">{{ number_format($result['farm_count']) }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="frec-panel h-100">
                        <div class="small text-muted">{{ $fil ? 'Average na ani' : 'Average yield' }}</div>
                        <div class="fs-4 fw-bold">{{ number_format((float) $result['avg_yield'], 2) }} <small class="fs-6">t/ha</small></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="frec-panel h-100">
                        <div class="small text-muted">{{ $fil ? 'Karaniwang saklaw' : 'Typical range' }}</div>
                        <div class="fs-5 fw-bold">{{ number_format((float) $result['min_yield'], 2) }}–{{ number_format((float) $result['max_yield'], 2) }} <small class="fs-6">t/ha</small></div>
                    </div>
                </div>
            </div>

            @if (! empty($result['by_season']))
                <section class="frec-panel mb-3" aria-labelledby="expSeasonTitle">
                    <h2 id="expSeasonTitle" class="h5 mb-3">{{ $fil ? 'Ani bawat season' : 'Yield by season over time' }}</h2>
                    <div style="height: 220px;"><canvas id="exploreSeasonChart"></canvas></div>
                    <script type="application/json" id="exploreSeasonData">@json($result['by_season'])</script>
                </section>
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <section class="frec-panel h-100" aria-labelledby="expVarTitle">
                        <h2 id="expVarTitle" class="h5 mb-2">{{ $fil ? 'Nangungunang barayti' : 'Top varieties by average yield' }}</h2>
                        @if (empty($result['top_varieties']))
                            <p class="text-muted small mb-0">{{ $fil ? 'Hindi pa sapat ang bilang ng mga bukid.' : 'Not enough farms yet.' }}</p>
                        @else
                            <ul class="list-unstyled mb-0 d-grid gap-2">
                                @foreach ($result['top_varieties'] as $v)
                                    <li class="d-flex justify-content-between align-items-center border-bottom pb-1">
                                        <span class="fw-semibold">{{ $v['name'] }}</span>
                                        <span class="small">{{ number_format($v['avg'], 2) }} t/ha ({{ $v['farms'] }} {{ $fil ? 'bukid' : 'farms' }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
                <div class="col-md-6">
                    <section class="frec-panel h-100" aria-labelledby="expMonthTitle">
                        <h2 id="expMonthTitle" class="h5 mb-2">{{ $fil ? 'Karaniwang buwan ng pagtatanim' : 'Most common planting months' }}</h2>
                        @if (empty($result['common_months']))
                            <p class="text-muted small mb-0">{{ $fil ? 'Hindi pa sapat ang bilang ng mga bukid.' : 'Not enough farms yet.' }}</p>
                        @else
                            <ul class="list-unstyled mb-0 d-grid gap-2">
                                @foreach ($result['common_months'] as $m)
                                    <li class="d-flex justify-content-between align-items-center border-bottom pb-1">
                                        <span class="fw-semibold">{{ $m['month'] }}</span>
                                        <span class="small">{{ $m['farms'] }} {{ $fil ? 'bukid' : 'farms' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>
        @endif
    </div>

    <script @nonce>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('exploreSeasonData');
            const canvas = document.getElementById('exploreSeasonChart');
            if (!el || !canvas || !window.Chart) return;
            const rows = JSON.parse(el.textContent || '[]');
            new window.Chart(canvas, {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{
                        label: 't/ha',
                        data: rows.map(r => r.avg),
                        backgroundColor: '#2e6f40',
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        });
    </script>
</x-app-layout>
