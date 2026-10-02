@php $plan = $f['plan']; $multi = $plan && (count($plan['plots']) + $plan['more']) > 1; @endphp
@if ($plan)
    <section class="mfd-card" aria-labelledby="mfdPlanTitle" data-mfd-plan>
        <div class="mfd-card-head">
            <h2 id="mfdPlanTitle">My Crop Plan</h2>
            <a class="mfd-link" href="{{ route('calendar.index') }}">Open Calendar</a>
        </div>

        @if (count($plan['reminders']) > 0)
            <ul class="mfd-reminders" aria-label="Reminders">
                @foreach ($plan['reminders'] as $reminder)
                    <li><span aria-hidden="true">🔔</span> <strong>{{ $reminder['title'] }}</strong> <span class="mfd-muted">{{ $reminder['when'] }}@if ($multi) · {{ $reminder['plot'] }}@endif</span></li>
                @endforeach
            </ul>
        @endif

        @if (count($plan['advice']) > 0)
            <ul class="mfd-plan-advice" aria-label="Advice for the weather ahead">
                @foreach ($plan['advice'] as $advice)
                    <li class="is-{{ $advice['severity'] }}"><span aria-hidden="true">{{ $advice['severity'] === 'warning' ? '⚠' : 'ⓘ' }}</span> <span>{{ $advice['text'] }}@if ($multi) <span class="mfd-muted">({{ $advice['plot'] }})</span>@endif</span></li>
                @endforeach
            </ul>
        @endif

        <div class="mfd-plots">
            @foreach ($plan['plots'] as $plot)
                <article class="mfd-plot" aria-label="{{ $plot['label'] }}">
                    <h3>{{ $plot['label'] }}</h3>
                    <p class="mfd-line"><span class="mfd-muted">Now:</span> <strong>{{ $plot['stage'] }}</strong></p>
                    <ol class="mfd-timeline">
                        @foreach ($plot['milestones'] as $m)
                            <li class="is-{{ $m['state'] }}" @if ($m['state'] === 'current') aria-current="step" @endif>
                                <span class="mfd-step-mark" aria-hidden="true">{{ $m['state'] === 'done' ? '✓' : ($m['state'] === 'current' ? '●' : '○') }}</span>
                                <span class="mfd-step-body">
                                    <strong>{{ $m['label'] }}</strong>
                                    <span class="mfd-muted">{{ $m['start']->format('M j') }}@if ($m['end']->ne($m['start'])) – {{ $m['end']->format('M j') }}@endif</span>
                                </span>
                                <span class="mfd-step-state">{{ ['done' => 'Done', 'current' => 'Now', 'upcoming' => 'Coming up'][$m['state']] }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <a class="mfd-link" href="{{ route('farmer.planting-schedules.edit', $plot['edit_id']) }}">Edit this plan</a>
                </article>
            @endforeach
        </div>
        @if ($plan['more'] > 0)
            <p class="mfd-foot">And {{ $plan['more'] }} more {{ \Illuminate\Support\Str::plural('plot', $plan['more']) }} in your calendar.</p>
        @endif
        <p class="mfd-foot">ⓘ The steps follow a typical rice calendar counted from your own planting and harvest dates. Follow the Municipal Agriculture Office advisories first.</p>
    </section>
@endif
