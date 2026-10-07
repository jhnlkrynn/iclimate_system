<x-app-layout>
    {{-- One record, for farmers (CrudController@show). Notifications, climate records and rice production records. --}}
    @php
        $tz = \App\Support\BusinessClock::timezone();
        $num = fn ($value, int $decimals = 2, string $unit = '') => $value === null || $value === '' ? '—' : number_format((float) $value, $decimals).($unit ? ' '.$unit : '');
        [$kicker, $heading, $back] = match ($routeName) {
            'notifications' => ['Notification', $record->title, 'Back to Notifications'],
            'climate-records' => ['Climate Record', $record->record_date ? \Illuminate\Support\Carbon::parse($record->record_date)->format('F j, Y') : 'Climate Record', 'Back to Climate Records'],
            'rice-productions' => ['Rice Production Record', trim(($record->barangay ?: '').' · '.$record->year.' '.$record->season.' season', ' ·'), 'Back to Rice Production'],
            default => [$title, $title, 'Back'],
        };
        $facts = match ($routeName) {
            'notifications' => [
                'Type' => $record->type ?: '—',
                'Status' => $record->is_read ? 'Read' : 'New',
                'Received' => $record->created_at?->copy()->timezone($tz)->format('F j, Y · g:i A') ?? '—',
            ],
            'climate-records' => [
                'Rainfall' => $num($record->rainfall, 2, 'mm'),
                'Temperature' => $num($record->temperature, 2, '°C'),
                'Humidity' => $num($record->humidity, 2, '%'),
                'Wind speed (unit not recorded)' => $num($record->wind_speed),
                'Season' => $record->season ?: '—',
                'Source' => \App\Services\Climate\ClimateRecordHistory::displaySource($record->source) ?: '—',
            ],
            'rice-productions' => [
                'Barangay' => $record->barangay ?: '—',
                'Year' => $record->year ?: '—',
                'Season' => $record->season ?: '—',
                'Irrigation' => $record->irrigation_type ?: '—',
                'Harvest month' => $record->production_month ? \Illuminate\Support\Carbon::parse($record->production_month)->format('F Y') : '—',
                'Area' => $num($record->area_hectares, 2, 'ha'),
                'Yield' => $num($record->yield_per_hectare, 2, 't/ha'),
                'Total production' => $num($record->total_production, 2, 't'),
                'Remarks' => $record->remarks ?: '—',
            ],
            default => collect($columns)->mapWithKeys(fn ($label, $field) => [$label => data_get($record, $field) ?? '—'])->all(),
        };
    @endphp

    <div class="fad frec">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ $kicker }}</span>
                <h1>{{ $heading }}</h1>
            </div>
            <div class="fad-hero-actions">
                <a class="fad-btn is-light" href="{{ route($routeName.'.index') }}"><span aria-hidden="true">‹</span> {{ $back }}</a>
            </div>
        </header>

        <section class="frec-panel" aria-labelledby="frecDetailTitle">
            <div class="frec-panel-head"><h2 id="frecDetailTitle">Details</h2></div>
            @if($routeName === 'notifications' && $record->message)
                <p class="frec-message">{{ $record->message }}</p>
                @if($record->type === 'Advisory')
                    <p><a class="fad-btn is-primary" href="{{ route('planting-advisories.index') }}" data-notif-advisories>{{ __('farmer.advisories_page.open', [], \App\Support\FarmerLanguage::resolve(request())) }}</a></p>
                @endif
            @endif
            <dl class="frec-detail">
                @foreach($facts as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>
    </div>
</x-app-layout>
