<?php
// Builds the deployed model's inputs for each MAO barangay-season record with the APPLICATION's feature code
// (App\Support\YieldModelClimateWindow). Target-month weather = the observed Ambulong month (hindcast).
use App\Support\RiceSeason;
use App\Support\YieldModelClimateWindow;
use Carbon\CarbonImmutable;

$station = [];
foreach (app(\App\Services\Climate\AmbulongHistoricalClimateDataset::class)->load()['observations'] as $o) {
    $station[sprintf('%04d-%02d', $o['year'], $o['month'])] = $o;
}
$rows = DB::table('historical_rice_productions')->orderBy('id')->get();
$out = []; $skipped = ['incomplete' => 0, 'no_weather' => 0];
foreach ($rows as $r) {
    if (! is_numeric($r->area_planted_hectares) || ! is_numeric($r->average_yield_t_per_hectare)) { $skipped['incomplete']++; continue; }
    $y = (int) $r->season_year;
    $months = strtoupper(trim($r->season)) === 'WS'
        ? array_map(fn ($m) => sprintf('%04d-%02d', $y, $m), range(5, 10))
        : array_merge([sprintf('%04d-11', $y), sprintf('%04d-12', $y)], array_map(fn ($m) => sprintf('%04d-%02d', $y + 1, $m), range(1, 4)));
    $features = [];
    foreach ($months as $ym) {
        $obs = $station[$ym] ?? null;
        $w = YieldModelClimateWindow::features(CarbonImmutable::parse($ym.'-01'), $obs ? ['tmax' => $obs['tmax'], 'tmin' => $obs['tmin'], 'rainfall' => $obs['rainfall'], 'temperature' => $obs['temperature']] : []);
        if (! $obs || $w['missing_months'] !== [] || $w['seasonal_missing_months'] !== [] || in_array(null, $w['features'], true)) { $features = null; break; }
        $features[] = $w['features'] + ['rainfall' => $obs['rainfall'], 'temp_avg' => round((float) $obs['temperature'], 2),
            'area' => (float) $r->area_planted_hectares, 'season' => RiceSeason::forTrainedYieldModel((int) substr($ym, 5, 2))];
    }
    if ($features === null) { $skipped['no_weather']++; continue; }
    $out[] = ['barangay' => $r->barangay, 'season' => $r->season, 'year' => $y, 'observed' => (float) $r->average_yield_t_per_hectare, 'months' => $features];
}
// usage (tinker): $path = 'eval-features.json'; require 'eval_app_features.php';
file_put_contents($path, json_encode(['records' => $out, 'skipped' => $skipped]));
echo count($out), ' records; skipped ', json_encode($skipped), PHP_EOL;
