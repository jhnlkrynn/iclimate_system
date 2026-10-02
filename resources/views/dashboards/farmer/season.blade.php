@php $season = $f['season']; @endphp
@if ($season['state'] === 'empty')
    {{-- The one and only prompt to add a planting date. --}}
    <section class="mfd-card" aria-labelledby="mfdSeasonTitle" data-mfd-season>
        <div class="mfd-card-head"><h2 id="mfdSeasonTitle">{{ __('farmer.season.title') }}</h2></div>
        <div class="mfd-empty">
            @include('dashboards.mao.icon', ['name' => 'calendar'])
            <div>
                <p><strong>{{ __('farmer.season.empty_title') }}</strong></p>
                <p class="mfd-muted">{{ __('farmer.season.empty_reason') }}</p>
            </div>
            <a class="mfd-btn" href="{{ route('farmer.planting-schedules.create') }}">@include('dashboards.mao.icon', ['name' => 'plus']){{ __('farmer.season.add') }}</a>
        </div>
    </section>
@else
    @php $multi = count($season['plots']) + $season['more'] > 1; @endphp
    <section class="mfd-card" aria-labelledby="mfdSeasonTitle" data-mfd-season>
        <div class="mfd-card-head">
            <h2 id="mfdSeasonTitle">{{ __('farmer.season.title') }}</h2>
            <a class="mfd-link" href="{{ route('calendar.index') }}">{{ __('farmer.season.open_calendar') }}</a>
        </div>
        <div class="mfd-plots">
            @foreach ($season['plots'] as $plot)
                @php $crop = $plot['crop']; $percent = $crop['value'] !== null ? min(100, max(0, round($crop['value'] / $crop['duration'] * 100))) : 0; @endphp
                <article class="mfd-plot" aria-label="{{ $plot['label'] }}">
                    @if ($multi)<h3>{{ $plot['label'] }}</h3>@endif
                    <p class="mfd-crop-age">
                        @if ($crop['value'] !== null)
                            <strong>{{ __('farmer.season.day_of', ['day' => $crop['display'], 'total' => $crop['duration']]) }}</strong>
                            <span>• {{ __('farmer.season.stage_line', ['stage' => $crop['stage_label']]) }}</span>
                        @else
                            <strong>{{ $crop['reason'] }}</strong>
                        @endif
                    </p>
                    @if ($crop['value'] !== null)
                        <div class="mfd-progress" role="progressbar" aria-label="{{ __('farmer.season.progress_label') }}" aria-valuemin="0" aria-valuemax="{{ $crop['duration'] }}" aria-valuenow="{{ min($crop['value'], $crop['duration']) }}"><i @style(['width: '.$percent.'%'])></i></div>
                    @endif

                    <ol class="mfd-stages" aria-label="{{ __('farmer.season.stages_label') }}">
                        @foreach ($crop['stages'] as $stage)
                            <li class="is-{{ $stage['state'] }}" @if ($stage['state'] === 'current') aria-current="step" @endif>
                                <span class="mfd-stage-mark">@include('dashboards.mao.icon', ['name' => $stage['state'] === 'done' ? 'check' : ($stage['state'] === 'current' ? 'sprout' : 'clock')])</span>
                                <span class="mfd-stage-body">
                                    <strong>{{ $stage['label'] }}</strong>
                                    <span class="mfd-muted">{{ $stage['start']->format('M j') }}@if ($stage['end']->ne($stage['start'])) – {{ $stage['end']->format('M j') }}@endif</span>
                                </span>
                                <span class="mfd-stage-state">{{ __('farmer.season.state_'.$stage['state']) }}</span>
                            </li>
                        @endforeach
                    </ol>

                    <dl class="mfd-facts">
                        <div><dt>{{ __('farmer.season.harvest_dates') }}</dt><dd>{{ $plot['harvest_from']->format('M j') }} – {{ $plot['harvest_to']->format('M j, Y') }}</dd></div>
                        @if ($plot['next'])
                            <div><dt>{{ __('farmer.season.next_task') }}</dt><dd>{{ $plot['next']['label'] }} • {{ $plot['next']['start']->format('M j') }}</dd></div>
                        @endif
                    </dl>

                    @foreach ($plot['reminders'] as $reminder)
                        <p class="mfd-note">@include('dashboards.mao.icon', ['name' => 'bell'])<span><strong>{{ $reminder['title'] }}</strong> <span class="mfd-muted">{{ $reminder['when'] }}</span></span></p>
                    @endforeach
                    @foreach ($plot['advice'] as $advice)
                        <p class="mfd-note is-{{ $advice['severity'] === 'warning' ? 'watch' : 'info' }}">@include('dashboards.mao.icon', ['name' => $advice['severity'] === 'warning' ? 'alert' : 'info'])<span>{{ $advice['text'] }}</span></p>
                    @endforeach

                    <a class="mfd-link" href="{{ route('farmer.planting-schedules.edit', $plot['edit_id']) }}">{{ __('farmer.season.edit') }}</a>
                </article>
            @endforeach
        </div>
        @if ($season['more'] > 0)
            <p class="mfd-foot">{{ trans_choice('farmer.season.more', $season['more'], ['count' => $season['more']]) }}</p>
        @endif
        <p class="mfd-foot">{{ __('farmer.season.foot') }}</p>
    </section>
@endif
