{{-- One climate record as shown in the Climate Records "View" pop-up (CrudController@show with ?modal=1).
     Same frame and parts as the Advisories pop-up (advisories/_modal.blade.php, .fad-m-* in farmer-advisories.css),
     opened by farmer-advisories.js. Plain labels only: every value is the stored record, never a raw field name.
     The full page (farmer-records/show.blade.php) stays for direct links and for browsers without JavaScript. --}}
@php
    $tz = \App\Support\BusinessClock::timezone();
    // The stored number without padding zeros (31.00 reads as 31, 12.50 as 12.5); the value itself is never changed.
    $num = fn ($value) => $value === null || $value === '' ? null : rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $date = $record->record_date ? \Illuminate\Support\Carbon::parse($record->record_date) : null;
    $recorded = $record->created_at?->copy()->timezone($tz);
    $updated = $record->updated_at?->copy()->timezone($tz);
    $readings = [
        ['key' => 'temperature', 'label' => 'Temperature', 'value' => $num($record->temperature), 'unit' => '°C', 'note' => null],
        ['key' => 'rainfall', 'label' => 'Rainfall', 'value' => $num($record->rainfall), 'unit' => ' mm', 'note' => null],
        ['key' => 'humidity', 'label' => 'Humidity', 'value' => $num($record->humidity), 'unit' => '%', 'note' => null],
        ['key' => 'wind', 'label' => 'Wind Speed', 'value' => $num($record->wind_speed), 'unit' => '', 'note' => 'Shown as recorded; the record does not state its unit.'],
    ];
@endphp

<header class="fad-m-head">
    <div class="fad-m-head-text">
        <p class="fad-m-eyebrow">Climate Record</p>
        <h2 id="fadModalTitle" tabindex="-1">Climate Record Details</h2>
        <p class="fad-m-meta">
            @if ($date)<span>Date: {{ $date->format('F j, Y') }}</span>@endif
            <span>For: Lian, Batangas</span>
        </p>
    </div>
    <button type="button" class="fad-m-x" data-fad-close aria-label="Close climate record">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</header>

<div class="fad-m-scroll">
    <section class="fad-m-section" aria-labelledby="frecReadingsTitle">
        <h3 id="frecReadingsTitle">Recorded Conditions</h3>
        <dl class="fad-m-weather">
            @foreach ($readings as $reading)
                <div data-reading="{{ $reading['key'] }}">
                    <dt>{{ $reading['label'] }}</dt>
                    @if ($reading['value'] !== null)
                        <dd><strong>{{ $reading['value'] }}{{ $reading['unit'] }}</strong>@if ($reading['note'])<span>{{ $reading['note'] }}</span>@endif</dd>
                    @else
                        <dd><span class="is-missing">Not available</span></dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </section>

    <section class="fad-m-section" aria-labelledby="frecAboutTitle">
        <h3 id="frecAboutTitle">About This Record</h3>
        <dl class="fad-m-details">
            <div><dt>Date</dt><dd>{{ $date?->format('F j, Y') ?? 'Not available' }}</dd></div>
            <div><dt>Location</dt><dd>Lian, Batangas</dd></div>
            @if ($record->season)<div><dt>Season</dt><dd>{{ $record->season }} season</dd></div>@endif
            <div><dt>Source</dt><dd>{{ \App\Services\Climate\ClimateRecordHistory::displaySource($record->source) ?: 'Not recorded' }}</dd></div>
            @if ($recorded)<div><dt>Recorded On</dt><dd>{{ $recorded->format('F j, Y · g:i A') }}</dd></div>@endif
            @if ($updated && (! $recorded || $updated->gt($recorded)))<div><dt>Updated</dt><dd>{{ $updated->format('F j, Y · g:i A') }}</dd></div>@endif
        </dl>
        <p class="fad-m-small">This record is for Lian as a whole. It is not tied to one farm.</p>
    </section>
</div>

<footer class="fad-m-foot">
    <button type="button" class="fad-btn is-primary" data-fad-close>Close</button>
</footer>
