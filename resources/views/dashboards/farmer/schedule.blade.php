{{-- "My Farm Schedule": the farmer's own planting schedule (the Calendar's records) as one short timeline: when it was
     planted, how far along it is, the activity due now or next, and the expected harvest. No planting date is ever made up. --}}
@php $s = $f['schedule']; @endphp
<section class="mfd-card mfd-schedule" aria-labelledby="mfdScheduleTitle" data-mfd-schedule>
    <div class="mfd-card-head">
        <h2 id="mfdScheduleTitle">{{ __('farmer.schedule.title') }}</h2>
        <a class="mfd-link" href="{{ route('calendar.index') }}">{{ __('farmer.schedule.view') }}</a>
    </div>

    @if ($s['state'] !== 'ok')
        {{-- No planting date yet: a planting guide instead of an empty box. The advice is the Heat Map's own rule for the
             barangay, the rain is the Predictions page's monthly estimate, and the steps are the usual rice calendar the
             Calendar follows once a date is saved (FarmerCropPlanService). Nothing here is presented as the farmer's own schedule. --}}
        @php
            $g = $f['planting_guide'];
            $statusIcon = ['good' => 'check', 'watch' => 'alert', 'info' => 'info'];
        @endphp
        <div class="mfd-guide" data-mfd-planting-guide>
            <h3 class="mfd-sub">{{ __('farmer.guide.heading') }}</h3>
            <p class="mfd-note is-{{ $g['tone'] === 'watch' ? 'watch' : ($g['tone'] === 'good' ? 'good' : 'info') }}">
                @include('dashboards.mao.icon', ['name' => $statusIcon[$g['tone']] ?? 'info'])
                <span><strong>{{ $g['title'] }}.</strong> {{ $g['note'] }}@if ($g['rain']) {{ $g['rain'] }}@endif</span>
            </p>
            @if ($g['key'])<p class="mfd-foot">{{ __('farmer.guide.source') }}</p>@endif

            <h3 class="mfd-sub">{{ __('farmer.guide.steps_title') }}</h3>
            <ol class="mfd-guide-steps">
                @foreach ($g['steps'] as $step)
                    <li data-step="{{ $step['key'] }}"><strong>{{ $step['label'] }}</strong><span class="mfd-muted">{{ $step['when'] }}</span></li>
                @endforeach
            </ol>
            <p class="mfd-foot">@include('dashboards.mao.icon', ['name' => 'calendar'])<span>{{ __('farmer.guide.harvest_if_today', ['date' => $g['harvest_if_today']->translatedFormat('F Y')]) }}</span></p>
            @if (($f['week']['state'] ?? null) === 'ok')
                <p class="mfd-foot">@include('dashboards.mao.icon', ['name' => 'check'])<span>{{ $g['good_days'] !== [] ? __('farmer.guide.good_days', ['days' => implode(', ', $g['good_days'])]) : __('farmer.guide.good_days_none') }}</span></p>
            @endif

            <div class="mfd-card-foot">
                <p class="mfd-muted">{{ __('farmer.guide.reminders') }}</p>
                <a class="mfd-btn" href="{{ route('farmer.planting-schedules.create') }}" data-mfd-popup="planting" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.today.add_planting') }}</a>
            </div>
        </div>
    @else
        <dl class="mfd-facts">
            <div><dt>{{ __('farmer.schedule.planting_date') }}</dt><dd>{{ $s['planting_date']->format('F j, Y') }}</dd></div>
            <div>
                <dt>{{ __('farmer.schedule.stage') }}</dt>
                <dd>
                    @if ($s['planted'])
                        {{ $s['stage'] }} <span class="mfd-muted">• {{ __('farmer.schedule.day_of', ['day' => $s['day'], 'total' => $s['duration']]) }}</span>
                    @else
                        {{ trans_choice('farmer.schedule.not_planted', $s['until_planting'], ['count' => $s['until_planting']]) }}
                    @endif
                </dd>
            </div>
        </dl>
        @if ($s['planted'])
            <div class="mfd-progress" role="progressbar" aria-label="{{ __('farmer.schedule.stage') }}" aria-valuemin="0" aria-valuemax="{{ $s['duration'] }}" aria-valuenow="{{ min($s['day'], $s['duration']) }}"><i @style(['width: '.$s['percent'].'%'])></i></div>
        @endif

        <div class="mfd-steps">
            @if ($s['current'])
                <div class="mfd-step is-now">
                    <span class="mfd-step-label">{{ __('farmer.schedule.now') }}</span>
                    <strong>{{ $s['current']['label'] }}</strong>
                    <span class="mfd-muted">{{ __('farmer.schedule.until', ['date' => $s['current']['end']->format('M j')]) }}</span>
                </div>
            @endif
            <div class="mfd-step">
                <span class="mfd-step-label">{{ __('farmer.schedule.next') }}</span>
                @if ($s['next'])
                    <strong>{{ $s['next']['label'] }}</strong>
                    <span class="mfd-muted">{{ $s['next']['start']->format('l, F j') }}</span>
                @else
                    <span class="mfd-muted">{{ __('farmer.schedule.none_next') }}</span>
                @endif
            </div>
            <div class="mfd-step">
                <span class="mfd-step-label">{{ __('farmer.schedule.harvest') }}</span>
                <strong>{{ $s['harvest_from']->format('M j') }} – {{ $s['harvest_to']->format('M j, Y') }}</strong>
            </div>
        </div>

        @foreach ($s['advice'] as $advice)
            <p class="mfd-note is-{{ $advice['severity'] === 'warning' ? 'watch' : 'info' }}">@include('dashboards.mao.icon', ['name' => $advice['severity'] === 'warning' ? 'alert' : 'info'])<span>{{ $advice['text'] }}</span></p>
        @endforeach

        @if ($s['plots'] > 1)
            <p class="mfd-foot">{{ trans_choice('farmer.schedule.more_plots', $s['plots'] - 1, ['count' => $s['plots'] - 1]) }}</p>
        @endif
        <p class="mfd-foot">{{ __('farmer.schedule.foot') }}</p>
        <a class="mfd-link" href="{{ route('farmer.planting-schedules.edit', $s['edit_id']) }}">{{ __('farmer.schedule.edit') }}</a>
    @endif
</section>
