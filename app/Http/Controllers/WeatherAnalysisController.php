<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WeatherImpactAnalysis;
use App\Services\Climate\WeatherAnalysisService;
use App\Services\SystemAuditLogger;
use App\Support\FarmerLanguage;
use App\Support\RiceSeason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manuscript – Naicel (2026-10-08), Phase 4.
 *  - Weather Analysis (farmers and MAO, a tab of Predictions): monthly rainfall, temperature, humidity and wind with
 *    year / season / month filters and a wet vs dry comparison. MAO also sees climate risk signals and climate vs yield.
 *  - Weather Impact Analysis (MAO): which climate variable moved most with rice yield; save a run, see past runs.
 * Both read stored data only (WeatherAnalysisService).
 */
class WeatherAnalysisController extends Controller
{
    /** The manuscript's study period; the page opens on it. */
    public const FROM_YEAR = 2020;

    public function __construct(private readonly WeatherAnalysisService $analysis) {}

    public function weather(Request $request): View
    {
        $farmer = $request->user()->role === User::ROLE_FARMER;
        $locale = $farmer ? FarmerLanguage::resolve($request) : 'en';
        app()->setLocale($locale);

        $all = $this->analysis->months();
        $years = $all->pluck('year')->unique()->sort()->values();
        $fromYear = $years->filter(fn ($y) => $y >= self::FROM_YEAR)->first() ?? $years->first();
        $year = in_array((int) $request->query('year'), $years->all(), true) ? (int) $request->query('year') : null;
        $season = in_array($request->query('season'), [RiceSeason::WET, RiceSeason::DRY], true) ? $request->query('season') : null;
        $month = (int) $request->query('month') >= 1 && (int) $request->query('month') <= 12 ? (int) $request->query('month') : null;

        $shown = $this->analysis->filter($all, $year, $season, $month, $year ? null : $fromYear);
        $label = fn (object $m) => (__('weather_analysis.month_names')[$m->month] ? mb_substr(__('weather_analysis.month_names')[$m->month], 0, 3) : $m->month).' '.$m->year;
        $sources = $shown->pluck('source')->filter()->unique()->values();

        return view('analysis.weather', [
            'isFarmer' => $farmer,
            'locale' => $locale,
            'years' => $years->filter(fn ($y) => $y >= $fromYear)->values(),
            'fromYear' => $fromYear,
            'toYear' => $years->last(),
            'filters' => ['year' => $year, 'season' => $season, 'month' => $month],
            'count' => $shown->count(),
            'chart' => [
                'labels' => $shown->map($label)->all(),
                'rainfall' => $shown->pluck('rainfall')->all(),
                'temperature' => $shown->pluck('temperature')->all(),
                'humidity' => $shown->pluck('humidity')->all(),
                'wind_speed' => $shown->pluck('wind_speed')->all(),
            ],
            'seasons' => $this->analysis->seasonComparison($this->analysis->filter($all, $year, null, $month, $year ? null : $fromYear)),
            'yearly' => $this->analysis->yearly($shown),
            'indicators' => $farmer ? null : $this->analysis->indicators($shown),
            'pairs' => $farmer ? collect() : $this->analysis->yieldPairs($shown),
            'source' => $sources->isEmpty() ? '—' : $sources->implode(', '),
            'stationWind' => $sources->contains(ClimateRecordController::AMBULONG_SOURCE),
        ]);
    }

    public function impact(Request $request): View
    {
        $filters = $this->impactFilters($request);

        return view('analysis.impact', [
            'filters' => $filters,
            'result' => $this->analysis->impact($filters['season'], $filters['from_year'], $filters['to_year']),
            'years' => $this->analysis->months()->pluck('year')->unique()->sort()->values(),
            'history' => WeatherImpactAnalysis::query()->with('author:id,name')->latest()->limit(20)->get(),
            'saved' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $filters = $this->impactFilters($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $result = $this->analysis->impact($filters['season'], $filters['from_year'], $filters['to_year']);

        $analysis = WeatherImpactAnalysis::query()->create([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'filters' => $filters,
            'results' => $result,
            'sample_size' => $result['pairs'],
            'strongest_variable' => $result['strongest'] ? $result['strongest']['label'].' ('.$result['strongest']['basis_label'].')' : null,
            'impact_level' => $result['strongest']['impact'] ?? null,
            'created_by' => $request->user()->getKey(),
        ]);
        SystemAuditLogger::forModel('saved', $analysis, $request);

        return redirect()->route('analysis.impact.show', $analysis)->with('success', 'Analysis saved.');
    }

    public function show(WeatherImpactAnalysis $analysis): View
    {
        return view('analysis.impact', [
            'filters' => $analysis->filters,
            'result' => $analysis->results,
            'years' => $this->analysis->months()->pluck('year')->unique()->sort()->values(),
            'history' => WeatherImpactAnalysis::query()->with('author:id,name')->latest()->limit(20)->get(),
            'saved' => $analysis,
        ]);
    }

    /** @return array{season: ?string, from_year: ?int, to_year: ?int} */
    private function impactFilters(Request $request): array
    {
        $data = $request->validate([
            'season' => ['nullable', Rule::in([RiceSeason::WET, RiceSeason::DRY])],
            'from_year' => ['nullable', 'integer', 'between:2000,2100'],
            'to_year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $from = isset($data['from_year']) ? (int) $data['from_year'] : null;
        $to = isset($data['to_year']) ? (int) $data['to_year'] : null;
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['season' => $data['season'] ?? null, 'from_year' => $from, 'to_year' => $to];
    }
}
