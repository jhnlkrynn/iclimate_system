{{-- Same season, past years (App\Services\Analytics\YieldSeasonComparison). Replaced in place when the farm or season changes. --}}
@php
    $Y = fn (string $key, array $replace = []) => __('yield_request.'.$key, $replace);
    $tz = \App\Support\BusinessClock::timezone();
    $C = $comparison;
@endphp
<section class="fad-card mb-3 yc" aria-labelledby="ycTitle" data-yield-comparison
    @if ($C) data-farm-id="{{ $C['farm_id'] }}" data-season="{{ $C['season'] }}" @endif>
    <h2 class="h5 fw-bold mb-1" id="ycTitle">{{ $Y('cmp_title') }}</h2>
    @if (! $C)
        <p class="text-muted mb-0">{{ $Y('cmp_chip_no_usual') }}</p>
    @else
        @php
            $S = \App\Services\Analytics\YieldSeasonComparison::class;
            $isDry = $C['season'] === \App\Support\RiceSeason::DRY;
            $seasonWord = $Y($isDry ? 'cmp_word_dry' : 'cmp_word_wet');
            $label = fn (int $start) => $isDry
                ? $Y('cmp_season_dry', ['start' => $start, 'end' => substr((string) ($start + 1), 2)])
                : $Y('cmp_season_wet', ['year' => $start]);
            $fmtTime = fn ($t) => $t ? $t->timezone($tz)->format('M j, Y g:i A') : null;
            $weatherSource = fn (array $w) => collect($w['sources'])->map(fn ($s) => $Y('cmp_w_'.$s))->implode(' + ');
            $yieldSource = fn (?array $y) => match ($y['source'] ?? null) {
                $S::SOURCE_FARM => $Y('cmp_src_farm', ['count' => $y['count']]),
                $S::SOURCE_BARANGAY => $Y('cmp_src_barangay', ['count' => $y['count']]),
                $S::SOURCE_LIAN => $Y('cmp_src_lian', ['count' => $y['count']]),
                'estimate' => $Y('cmp_src_estimate'),
                default => null,
            };
            $rowUpdated = function (array $row) {
                $stamps = array_filter([$row['weather']['updated_at'], $row['yield']['updated_at'] ?? null]);
                usort($stamps, fn ($a, $b) => $b <=> $a);

                return $stamps[0] ?? null;
            };
            $allRows = [...$C['rows'], $C['current']];
            $est = $C['estimate'];
            $pastCount = count($C['rows']);
            $farmName = collect([$C['farm_name'] ?? null, $C['barangay'] ? \App\Services\Risk\HeatMapPanel::displayName((string) $C['barangay']) : null, $Y(strtolower($C['farm_type']))])->filter()->implode(' · ');

            // Chart geometry (viewBox units)
            $w = 640; $h = 240; $padL = 40; $padR = 12; $padT = 22; $padB = 40;
            $values = array_filter(array_map(fn ($r) => $r['yield']['t_ha'] ?? null, $allRows), fn ($v) => $v !== null);
            $max = max([1.0, ...array_map(fn ($v) => (float) $v, $values), (float) ($C['usual'] ?? 0)]);
            $top = ceil($max + 0.5);
            $plotW = $w - $padL - $padR; $plotH = $h - $padT - $padB;
            $slot = $plotW / count($allRows);
            $barW = min(56, $slot * 0.56);
            $yPos = fn (float $v) => $padT + $plotH - ($v / $top) * $plotH;
            $ticks = range(0, (int) $top, $top > 6 ? 2 : 1);
        @endphp
        <p class="small text-muted mb-3">{{ $Y('cmp_intro', ['count' => $pastCount, 'season' => $seasonWord, 'farm' => $farmName]) }}</p>

        @unless ($C['farm_approved'])
            <p class="small mb-2" data-cmp-not-approved>{{ $Y('cmp_not_approved') }}</p>
        @endunless

        {{-- Summary chip --}}
        <div class="yc-chip mb-3" data-cmp-chip>
            @if ($est && $C['usual'])
                @php $pct = (int) abs($C['diff_pct']); @endphp
                {{ $Y('cmp_chip', ['estimate' => number_format($est['t_ha'], 2), 'usual' => number_format($C['usual'], 1), 'count' => $C['usual_count'], 'season' => $seasonWord]) }}
                · <strong>{{ $pct === 0 ? $Y('cmp_chip_same') : $Y($C['diff_pct'] < 0 ? 'cmp_chip_lower' : 'cmp_chip_higher', ['pct' => $pct]) }}</strong>
            @elseif (! $C['estimate_shown'])
                {{ __('farmer.home.harvest.unvalidated') }}@if ($C['usual']) · {{ $Y('cmp_chip_usual_only', ['usual' => number_format($C['usual'], 1), 'count' => $C['usual_count'], 'season' => $seasonWord]) }}@endif
            @elseif (! $est)
                {{ $Y('cmp_chip_no_estimate') }}@if ($C['usual']) · {{ $Y('cmp_chip_usual_only', ['usual' => number_format($C['usual'], 1), 'count' => $C['usual_count'], 'season' => $seasonWord]) }}@endif
            @else
                {{ $Y('cmp_chip_no_usual') }}
            @endif
        </div>

        {{-- Insight: stored figures only --}}
        @if ($C['insight'])
            <p class="yc-insight mb-3" data-cmp-insight>
                @foreach ($C['insight'] as $line){{ $Y($line['key'], $line['replace']) }} @endforeach
            </p>
        @endif

        {{-- Chart: past actual yields as bars, this season's estimate dashed --}}
        @if ($values)
            <figure class="yc-chart mb-3" data-cmp-chart>
                <div class="yc-chart-scroll">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="{{ $Y('cmp_chart_aria', ['count' => $pastCount, 'season' => $seasonWord]) }}" preserveAspectRatio="xMidYMid meet">
                    @foreach ($ticks as $t)
                        <line class="yc-grid" x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $yPos($t) }}" y2="{{ $yPos($t) }}"/>
                        <text class="yc-axis" x="{{ $padL - 6 }}" y="{{ $yPos($t) + 4 }}" text-anchor="end">{{ $t }}</text>
                    @endforeach
                    @if ($C['usual'])
                        <line class="yc-usual" x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $yPos($C['usual']) }}" y2="{{ $yPos($C['usual']) }}"/>
                    @endif
                    @foreach ($allRows as $i => $row)
                        @php
                            $cx = $padL + $slot * $i + $slot / 2;
                            $v = $row['yield']['t_ha'] ?? null;
                            $isEst = $row['is_current'];
                        @endphp
                        @if ($v !== null)
                            @php $y0 = $yPos((float) $v); $bh = max(1, $padT + $plotH - $y0); @endphp
                            <g @class(['yc-bar', 'is-estimate' => $isEst]) data-cmp-bar="{{ $isEst ? 'estimate' : 'actual' }}">
                                <title>{{ $isEst ? $Y('cmp_estimate') : $Y('cmp_actual') }} · {{ $label($row['start_year']) }}: {{ number_format($v, 2) }} t/ha</title>
                                <rect x="{{ $cx - $barW / 2 }}" y="{{ $y0 }}" width="{{ $barW }}" height="{{ $bh }}" rx="4"/>
                                <text class="yc-val" x="{{ $cx }}" y="{{ $y0 - 6 }}" text-anchor="middle">{{ number_format($v, 2) }}</text>
                            </g>
                        @else
                            <text class="yc-none" x="{{ $cx }}" y="{{ $padT + $plotH - 6 }}" text-anchor="middle">{{ $isEst ? '—' : $Y('cmp_no_record') }}</text>
                        @endif
                        <text class="yc-axis" x="{{ $cx }}" y="{{ $h - $padB + 16 }}" text-anchor="middle">{{ $label($row['start_year']) }}</text>
                        @if ($isEst)
                            <text class="yc-axis yc-est-label" x="{{ $cx }}" y="{{ $h - $padB + 31 }}" text-anchor="middle">{{ $Y('cmp_estimate') }}</text>
                        @endif
                    @endforeach
                    <line class="yc-base" x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $padT + $plotH }}" y2="{{ $padT + $plotH }}"/>
                </svg>
                </div>
                <figcaption class="yc-legend small text-muted">
                    <span><i class="yc-key"></i>{{ $Y('cmp_actual') }}</span>
                    <span><i class="yc-key is-estimate"></i>{{ $Y('cmp_estimate') }}</span>
                    @if ($C['usual'])<span><i class="yc-key is-usual"></i>{{ $Y('cmp_chip_usual_only', ['usual' => number_format($C['usual'], 1), 'count' => $C['usual_count'], 'season' => $seasonWord]) }}</span>@endif
                </figcaption>
            </figure>
        @endif

        {{-- Table (scrolls sideways on a phone) --}}
        <div class="table-responsive yc-table-wrap">
            <table class="table table-sm align-middle mb-0 yc-table" data-cmp-table>
                <thead>
                    <tr>
                        <th scope="col">{{ $Y('cmp_col_season') }}</th>
                        <th scope="col">{{ $Y('cmp_col_rain') }}</th>
                        <th scope="col">{{ $Y('cmp_col_temp') }}</th>
                        <th scope="col">{{ $Y('cmp_col_rainy') }}</th>
                        <th scope="col">{{ $Y('cmp_col_yield') }}</th>
                        <th scope="col">{{ $Y('cmp_col_source') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allRows as $row)
                        @php $wx = $row['weather']; $yl = $row['yield']; $upd = $rowUpdated($row); @endphp
                        <tr @class(['yc-current' => $row['is_current']]) data-cmp-row="{{ $row['is_current'] ? 'current' : 'past' }}" data-start-year="{{ $row['start_year'] }}">
                            <th scope="row" class="text-nowrap">
                                {{ $label($row['start_year']) }}
                                @if ($row['is_current'])<div><span class="badge yc-badge-est">{{ $Y('cmp_this_season') }} · {{ $Y('cmp_estimate') }}</span></div>@endif
                            </th>
                            <td class="text-nowrap">
                                @if ($wx['rain'] === null)
                                    <span class="text-muted">{{ $Y('cmp_no_record') }}</span>
                                @else
                                    {{ number_format($wx['rain']) }} mm
                                    @unless ($wx['complete'])<div class="small text-muted">{{ $Y('cmp_months_partial', ['count' => $wx['months'], 'total' => $wx['months_needed']]) }}</div>@endunless
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if ($wx['tmax'] !== null && $wx['tmin'] !== null)
                                    {{ number_format($wx['tmax'], 1) }}° / {{ number_format($wx['tmin'], 1) }}°C
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                @if ($wx['humidity'] !== null)<div class="small text-muted">{{ $Y('cmp_humidity', ['value' => number_format($wx['humidity'])]) }}</div>@endif
                            </td>
                            <td>{{ $wx['rainy_days'] ?? '—' }}</td>
                            <td class="text-nowrap" data-cmp-yield>
                                @if ($yl)
                                    <strong>{{ number_format($yl['t_ha'], 2) }}</strong>
                                    <span @class(['badge ms-1', 'yc-badge-est' => ! $yl['actual'], 'yc-badge-act' => $yl['actual']])>{{ $yl['actual'] ? $Y('cmp_actual') : $Y('cmp_estimate') }}</span>
                                    @if ($yl['tons'] !== null)<div class="small text-muted">{{ $Y('cmp_tons', ['value' => number_format($yl['tons'], 1)]) }}</div>@endif
                                @elseif ($row['is_current'])
                                    <span class="text-muted">—</span>
                                @else
                                    <span class="text-muted">{{ $Y('cmp_no_record') }}</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($wx['sources'])<div><span class="text-muted">{{ $Y('cmp_weather') }}:</span> {{ $weatherSource($wx) }}</div>@endif
                                @if ($yl)<div><span class="text-muted">{{ $Y('cmp_yield') }}:</span> {{ $yieldSource($yl) }}</div>@endif
                                @if ($upd)<div class="text-muted">{{ $Y('cmp_updated', ['time' => $fmtTime($upd)]) }}</div>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($C['updated_at'])
            <p class="small text-muted mt-2 mb-0" data-cmp-updated>{{ $Y('cmp_updated', ['time' => $fmtTime($C['updated_at'])]) }}</p>
        @endif
    @endif
</section>
