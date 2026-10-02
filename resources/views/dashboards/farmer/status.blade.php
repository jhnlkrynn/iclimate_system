@php
    $schedule = $f['schedule'];
    $activity = $schedule['activity'] ?? null;
    $risk = $f['risk'];
    $farm = $f['farm'];
    $glyph = ['Low' => '✓', 'Moderate' => '⚠', 'High' => '!', 'Severe' => '‼'];
@endphp
<section aria-labelledby="mfdStatusTitle" data-mfd-status>
    <h2 class="mfd-section-title" id="mfdStatusTitle">My Farm Status</h2>
    <div class="mfd-status">
        <article class="mfd-card">
            <div class="mfd-card-title">Planting Schedule</div>
            @if ($activity)
                <div class="mfd-big">{{ $activity['kind'] }} {{ $activity['when'] }}</div>
                <p class="mfd-line"><span class="mfd-muted">Next activity:</span> {{ $activity['kind'] }}</p>
                <p class="mfd-line"><span class="mfd-muted">Date:</span> {{ $activity['date']->format('l, F j, Y') }}</p>
                <a class="mfd-btn is-quiet" href="{{ route('calendar.index') }}">Open Calendar</a>
            @elseif ($schedule)
                <div class="mfd-big">Schedule saved</div>
                <p class="mfd-line">Add a planting or harvest date so iClimate can remind you.</p>
                <a class="mfd-btn is-quiet" href="{{ route('calendar.index') }}">Open Calendar</a>
            @else
                <div class="mfd-big">No planting schedule yet.</div>
                <p class="mfd-line">Add your planting date to track your season.</p>
                <a class="mfd-btn" href="{{ route('farmer.planting-schedules.create') }}">Set Planting Schedule</a>
            @endif
        </article>

        @if (count($farm) > 0)
            <article class="mfd-card">
                <div class="mfd-card-title">Farm / Crop Information</div>
                <dl class="mfd-rows">
                    @foreach ($farm as $row)<div><dt>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd></div>@endforeach
                </dl>
            </article>
        @endif

        @if ($risk)
            <article class="mfd-card">
                <div class="mfd-card-title">Farm Risk in Your Barangay</div>
                @if ($f['barangay'])<div class="mfd-muted">Your Barangay: {{ $f['barangay'] }}</div>@endif
                <div class="mfd-risk is-{{ strtolower($risk['level']) }}"><span aria-hidden="true">{{ $glyph[$risk['level']] ?? '' }}</span> {{ $risk['level'] }} risk</div>
                <p class="mfd-line">{{ $risk['meaning'] }}</p>
                <a class="mfd-link" href="#farmRiskMap">View risk map</a>
                <p class="mfd-foot">A planning guide only, not an official hazard warning.@if ($risk['updated_at']) Updated {{ $risk['updated_at']->format('M j, g:i A') }}.@endif</p>
            </article>
        @endif
    </div>
</section>
