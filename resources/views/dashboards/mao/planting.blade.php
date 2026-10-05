{{-- Planting guide: the Heat Map's planting advice for each barangay, counted into three plain answers. --}}
@php
    $p = $mao['planting'] ?? null;
    $rows = [
        'good' => ['label' => 'Good time to plant', 'hint' => 'Rain is right for planting and farm risk is low.'],
        'care' => ['label' => 'Can plant, check the field first', 'hint' => 'Planting is possible, but conditions need watching.'],
        'wait' => ['label' => 'Better to wait', 'hint' => 'Too much or too little rain, or high farm risk.'],
    ];
@endphp
@if ($p)
    <section class="mtd-card" aria-labelledby="mtdPlantingTitle" data-mao-planting>
        <div class="mtd-card-head"><div>
                <h2 id="mtdPlantingTitle">Planting Guide</h2>
                <p class="mtd-sub">Can farmers plant rice now? Based on this month's expected rain and each barangay's farm risk.</p>
            </div></div>
        <ul class="mtd-guide">
            @foreach ($rows as $key => $row)
                @php $names = $p['groups'][$key]; @endphp
                <li class="mtd-guide-row is-{{ $key }}">
                    <span class="mtd-guide-count">{{ count($names) }}</span>
                    <div class="mtd-guide-body">
                        <strong>{{ $row['label'] }}</strong>
                        <span>@if (count($names) === 0)No barangay right now.@elseif (count($names) <= 4){{ implode(', ', $names) }}@else{{ count($names) }} of {{ $p['rated'] }} rated barangays. {{ $row['hint'] }}@endif</span>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($p['schedules'])
            <dl class="mtd-rows">
                <div><dt>Farmer planting schedules on record</dt><dd>{{ number_format($p['schedules']['total']) }}</dd></div>
                <div><dt>Planting in the next 30 days</dt><dd>{{ number_format($p['schedules']['planting_soon']) }}</dd></div>
                <div><dt>Harvest expected in the next 30 days</dt><dd>{{ number_format($p['schedules']['harvest_soon']) }}</dd></div>
            </dl>
        @endif
        <p class="mtd-foot">A planning guide for each barangay, the same advice shown on the Heat Map. <a class="mtd-link" href="{{ route('calendar.index') }}">Open Farm Calendar</a></p>
    </section>
@endif
