{{-- Transparency record for one saved rice-yield estimate (App\Support\YieldEstimateRecord, owner request 2026-11-20). --}}
@php $R = fn (string $k, array $r = []) => __('farmer.predictions.record.'.$k, $r); @endphp
<details class="wpx-more ytr" data-yield-record>
    <summary>{{ $R('open') }}</summary>
    <div class="ytr-body">
        <dl class="ytr-facts">
            <div><dt>{{ $R('made_by') }}</dt><dd>{{ $R('model', ['model' => $record['model']]) }}</dd></div>
            @if ($record['version'] !== '')<div><dt>{{ $R('version') }}</dt><dd><code>{{ $record['version'] }}</code></dd></div>@endif
            @if ($record['ran_at'])<div><dt>{{ $R('ran_at') }}</dt><dd>{{ $record['ran_at']->locale(app()->getLocale())->translatedFormat('M j, Y · g:i A') }}</dd></div>@endif
            <div><dt>{{ $R('for_month') }}</dt><dd>{{ $record['target_month'] }}</dd></div>
            @if ($record['range'])<div><dt>{{ $R('range') }}</dt><dd>{{ $R('range_value', ['low' => number_format($record['range'][0], 2), 'high' => number_format($record['range'][1], 2)]) }}</dd></div>@endif
            <div><dt>{{ $R('farm_type') }}</dt><dd>{{ $record['farm_type'] }}</dd></div>
        </dl>

        <h3 class="ytr-title">{{ $R('inputs_title') }}</h3>
        <ul class="ytr-inputs">
            @foreach ($record['inputs'] as $in)
                <li>
                    <span class="ytr-label">{{ $in['label'] }}</span>
                    <strong class="ytr-value">{{ $in['value'] }}</strong>
                    <span class="ytr-source">{{ $in['source'] }}</span>
                </li>
            @endforeach
        </ul>

        <h3 class="ytr-title">{{ $R('checks_title') }}</h3>
        <ul class="ytr-checks">
            <li><span>{{ $R('check_model') }}</span><strong>{{ $record['fallback'] ? $R('no') : $R('yes') }}</strong></li>
            <li><span>{{ $R('check_range') }}</span><strong>{{ $record['within_range'] ? $R('yes') : $R('no') }}</strong></li>
        </ul>

        @if ($record['forecast_at'] && $record['weather_model'] !== '')
            <p class="fad-support ytr-note">{{ $R('forecast_from', ['at' => $record['forecast_at']->locale(app()->getLocale())->translatedFormat('M j, Y'), 'model' => $record['weather_model'], 'period' => $record['weather_trained'] ?? '—']) }}</p>
        @endif
    </div>
</details>
