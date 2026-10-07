{{-- A staff record in the shared information pop-up (resources/js/info-modal.js, ?modal=1): the same fields as
     crud.show, in the frame of the Farmer Advisories pop-up (.fad-m-* in farmer-advisories.css). The full page
     (with the farm boundary map for farmer profiles) stays one click away. --}}
@php
    $tz = \App\Support\BusinessClock::timezone();
    $value = function ($field) use ($record) {
        $v = data_get($record, $field);
        if ($field === 'is_read') return $v ? 'Read' : 'Unread';
        if ($v instanceof \DateTimeInterface) return \Illuminate\Support\Carbon::instance($v)->format('M j, Y');
        if (is_bool($v)) return $v ? 'Yes' : 'No';
        return $v === null || $v === '' ? '—' : $v;
    };
@endphp
<header class="fad-m-head ic-m-head">
    <span class="ic-m-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'report'])</span>
    <div class="fad-m-head-text">
        <p class="fad-label">{{ $title }}</p>
        <h2 id="icModalTitle" tabindex="-1">{{ $title }} Details</h2>
        <p class="ic-m-desc">Record no. {{ $record->id }}</p>
    </div>
    <button type="button" class="fad-m-x" data-ic-close aria-label="Close {{ strtolower($title) }} details"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
</header>
<div class="fad-m-scroll">
    <section class="fad-m-section" aria-labelledby="icRecordFields">
        <h3 id="icRecordFields">Details</h3>
        <dl class="fad-m-details">
            @foreach ($columns as $field => $label)
                <div><dt>{{ $label }}</dt><dd>{{ $routeName === 'climate-records' && $field === 'source' ? \App\Services\Climate\ClimateRecordHistory::displaySource($record->source) : $value($field) }}</dd></div>
            @endforeach
            @if (isset($record->message) && ! array_key_exists('message', $columns))
                <div><dt>Message</dt><dd>{{ $record->message }}</dd></div>
            @endif
        </dl>
    </section>
    @if ($routeName === 'farmer-profiles')
        <p class="fad-m-note">{{ $record->farmBoundary ? 'This farmer has saved a farm boundary. Open the full page to see it on the map.' : 'This farmer has not saved a farm boundary.' }}</p>
    @endif
</div>
<footer class="fad-m-foot ic-m-foot">
    <p class="ic-m-note">Created {{ $record->created_at?->copy()->timezone($tz)->format('M j, Y g:i A') ?? '—' }} · Updated {{ $record->updated_at?->copy()->timezone($tz)->format('M j, Y g:i A') ?? '—' }}</p>
    <div class="ic-m-actions">
        <a class="fad-btn is-quiet" href="{{ route($routeName.'.show', $record) }}">Open full page</a>
        @if ($canManage)
            <a class="fad-btn" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
        @endif
    </div>
    <button type="button" class="fad-btn is-quiet" data-ic-close>Close</button>
</footer>
