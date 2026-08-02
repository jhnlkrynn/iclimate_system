<?php

namespace App\Services\AI;

use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RoleAssistantService
{
    public function roleRestrictedMessage(string $language, string $requiredRoleLabel, string $askerRole = User::ROLE_FARMER): string
    {
        $capabilities = match ($askerRole) {
            User::ROLE_MAO => [
                'en' => 'weather, yield, planting and irrigation guidance, barangay information, advisories, reports, and announcements',
                'tl' => 'panahon, ani, gabay sa pagtatanim at irigasyon, impormasyon ng barangay, advisory, reports, at announcements',
            ],
            User::ROLE_IT_EXPERT => [
                'en' => 'weather, yield, planting and irrigation guidance, and system status information',
                'tl' => 'panahon, ani, gabay sa pagtatanim at irigasyon, at system status',
            ],
            default => [
                'en' => 'weather, yield, planting, irrigation, advisories, and your own profile',
                'tl' => 'panahon, ani, pagtatanim, irigasyon, advisory, at sariling profile',
            ],
        };

        if (str_contains($language, 'Tagalog')) {
            return "Ang impormasyong iyan ay para lamang sa mga {$requiredRoleLabel} account. Ang {$askerRole} account mo ay puwedeng magtanong tungkol sa {$capabilities['tl']}.";
        }

        return "That information is restricted to {$requiredRoleLabel} accounts. Your {$askerRole} account can ask about {$capabilities['en']} instead.";
    }

    public function maoAnswer(User $user, string $question, string $intent, string $language): ?array
    {
        $text = str($question)->lower()->toString();
        $tagalog = str_contains($language, 'Tagalog');

        if (str_contains($text, 'lowest') || str_contains($text, 'pinakamababa')) {
            return $this->barangayRanking($tagalog, ascending: true);
        }

        if (str_contains($text, 'highest') || str_contains($text, 'pinakamataas')) {
            return $this->barangayRanking($tagalog, ascending: false);
        }

        if (str_contains($text, 'how many farmer') || str_contains($text, 'ilang farmer') || str_contains($text, 'farmer count') || str_contains($text, 'registered farmer')) {
            $count = User::query()->where('role', User::ROLE_FARMER)->count();

            return [
                'answer' => $tagalog
                    ? "May {$count} rehistradong Farmer account sa iClimate ngayon."
                    : "There are currently {$count} registered Farmer accounts in iClimate.",
                'source_name' => 'iClimate User Records',
                'confidence' => 92,
            ];
        }

        if (str_contains($text, 'advisor')) {
            $count = PlantingAdvisory::query()->where('status', 'Published')->count();

            return [
                'answer' => $tagalog
                    ? "May {$count} published na planting advisory sa ngayon."
                    : "There are currently {$count} published planting advisories.",
                'source_name' => 'iClimate Planting Advisories',
                'confidence' => 90,
            ];
        }

        if (str_contains($text, 'summary') || str_contains($text, 'production') || str_contains($text, 'buod') || str_contains($text, 'produksyon')) {
            return $this->productionSummary($question, $tagalog);
        }

        return null;
    }

    public function itAnswer(string $question, string $intent, string $language): ?array
    {
        $text = str($question)->lower()->toString();
        $tagalog = str_contains($language, 'Tagalog');

        if (str_contains($text, 'user') || str_contains($text, 'ilang user')) {
            return $this->userCounts($tagalog);
        }

        if (str_contains($text, 'database') || str_contains($text, 'db status')) {
            return $this->databaseStatus($tagalog);
        }

        if (str_contains($text, 'api') || str_contains($text, 'farming ai') || str_contains($text, 'model')) {
            return $this->apiStatus($tagalog);
        }

        if (str_contains($text, 'backup')) {
            return [
                'answer' => $tagalog
                    ? 'Wala pang automated backup system ang iClimate sa ngayon. Mag-manual backup ng database (hal. mysqldump) at ng storage/app directory nang regular hanggang magkaroon ng automated solution.'
                    : 'iClimate does not currently have an automated backup system configured. Back up the database (e.g. via mysqldump) and the storage/app directory manually on a regular schedule until an automated solution is set up.',
                'source_name' => 'iClimate System Status',
                'confidence' => 90,
            ];
        }

        if (str_contains($text, 'error') || str_contains($text, 'log')) {
            return $this->errorLogSummary($tagalog);
        }

        if (str_contains($text, 'maintenance') || str_contains($text, 'server status') || str_contains($text, 'system status')) {
            return $this->maintenanceStatus($tagalog);
        }

        return null;
    }

    public function barangayAnswer(User $user, string $question, string $language): ?array
    {
        $tagalog = str_contains($language, 'Tagalog');
        $text = str($question)->lower()->toString();

        if (! $this->barangayNamedIn($question)) {
            if (str_contains($text, 'lowest') || str_contains($text, 'pinakamababa')) {
                return $this->barangayRanking($tagalog, ascending: true);
            }

            if (str_contains($text, 'highest') || str_contains($text, 'pinakamataas')) {
                return $this->barangayRanking($tagalog, ascending: false);
            }
        }

        $barangay = $this->barangayNamedIn($question) ?? $user->barangay;

        if (! $barangay) {
            return [
                'answer' => $tagalog
                    ? 'Sabihin kung aling barangay sa Lian, Batangas ang gusto mong malaman (hal. Matabungkay, Lumaniag, Bagong Pook).'
                    : 'Let me know which barangay in Lian, Batangas you would like information about (e.g. Matabungkay, Lumaniag, Bagong Pook).',
                'source_name' => 'Climora AI',
                'confidence' => 70,
            ];
        }

        $production = RiceProduction::query()->where('barangay', $barangay)->latest('year')->take(5)->get();
        $heatmap = HeatmapArea::query()->where('barangay', $barangay)->latest()->first();

        $lines = [];

        if ($production->isNotEmpty()) {
            $avgYield = round((float) $production->avg('yield_per_hectare'), 2);
            $totalProduction = round((float) $production->sum('total_production'), 2);
            $lines[] = $tagalog
                ? "Average yield sa Barangay {$barangay}: {$avgYield} tons/hectare (mula sa {$production->count()} record), kabuuang produksyon: {$totalProduction} tons."
                : "Barangay {$barangay} average yield: {$avgYield} tons/hectare (from {$production->count()} records), total production: {$totalProduction} tons.";
        } else {
            $lines[] = $tagalog
                ? "Wala pang rice production record para sa Barangay {$barangay}."
                : "No rice production records are available yet for Barangay {$barangay}.";
        }

        if ($heatmap) {
            $lines[] = $tagalog
                ? "Climate risk: {$heatmap->risk_level} ({$heatmap->risk_type})."
                : "Climate risk: {$heatmap->risk_level} ({$heatmap->risk_type}).";
        }

        return [
            'answer' => implode(' ', $lines),
            'source_name' => 'iClimate Barangay Records',
            'confidence' => $production->isNotEmpty() || $heatmap ? 88 : 70,
        ];
    }

    private function barangayNamedIn(string $question): ?string
    {
        $text = str($question)->lower()->toString();

        foreach (LianBarangays::all() as $barangay) {
            if (str_contains($text, strtolower($barangay))) {
                return $barangay;
            }
        }

        return null;
    }

    private function barangayRanking(bool $tagalog, bool $ascending): array
    {
        $query = RiceProduction::query()
            ->select('barangay')
            ->selectRaw('AVG(yield_per_hectare) as avg_yield')
            ->groupBy('barangay')
            ->havingRaw('AVG(yield_per_hectare) is not null');

        $row = $ascending ? $query->orderBy('avg_yield')->first() : $query->orderByDesc('avg_yield')->first();

        if (! $row) {
            return [
                'answer' => $tagalog
                    ? 'Wala pang sapat na rice production records para makagawa ng barangay ranking.'
                    : 'There are not enough rice production records yet to rank barangays.',
                'source_name' => 'iClimate Production Records',
                'confidence' => 70,
            ];
        }

        $avgYield = round((float) $row->avg_yield, 2);
        $label = $ascending
            ? ($tagalog ? 'pinakamababang average yield' : 'lowest average yield')
            : ($tagalog ? 'pinakamataas na average yield' : 'highest average yield');

        return [
            'answer' => $tagalog
                ? "Ang Barangay {$row->barangay} ang may {$label}: {$avgYield} tons/hectare."
                : "Barangay {$row->barangay} has the {$label}: {$avgYield} tons/hectare.",
            'source_name' => 'iClimate Production Records',
            'confidence' => 90,
        ];
    }

    private function productionSummary(string $question, bool $tagalog): array
    {
        $query = RiceProduction::query();

        if (preg_match('/(20\d{2})/', $question, $match)) {
            $query->where('year', (int) $match[1]);
        }

        if (str_contains(strtolower($question), 'wet') || str_contains(strtolower($question), 'tag-ulan')) {
            $query->where('season', 'Wet');
        } elseif (str_contains(strtolower($question), 'dry') || str_contains(strtolower($question), 'tag-init')) {
            $query->where('season', 'Dry');
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return [
                'answer' => $tagalog
                    ? 'Wala pang rice production records na tumutugma sa hinihinging season o taon.'
                    : 'There are no rice production records matching that season or year yet.',
                'source_name' => 'iClimate Production Records',
                'confidence' => 70,
            ];
        }

        $avgYield = round((float) $records->avg('yield_per_hectare'), 2);
        $totalProduction = round((float) $records->sum('total_production'), 2);
        $totalArea = round((float) $records->sum('area_hectares'), 2);
        $barangayCount = $records->pluck('barangay')->unique()->count();

        return [
            'answer' => $tagalog
                ? "Production summary: average yield {$avgYield} tons/hectare, kabuuang produksyon {$totalProduction} tons mula sa {$totalArea} hectares, sa {$barangayCount} barangay ({$records->count()} records)."
                : "Production summary: average yield {$avgYield} tons/hectare, total production {$totalProduction} tons across {$totalArea} hectares, spanning {$barangayCount} barangays ({$records->count()} records).",
            'source_name' => 'iClimate Production Records',
            'confidence' => 88,
        ];
    }

    private function userCounts(bool $tagalog): array
    {
        $total = User::count();
        $active = User::query()->where('status', User::STATUS_ACTIVE)->count();
        $inactive = User::query()->where('status', User::STATUS_INACTIVE)->count();
        $farmers = User::query()->where('role', User::ROLE_FARMER)->count();
        $mao = User::query()->where('role', User::ROLE_MAO)->count();
        $it = User::query()->where('role', User::ROLE_IT_EXPERT)->count();

        return [
            'answer' => $tagalog
                ? "May {$total} kabuuang user account ({$active} active, {$inactive} inactive): {$farmers} Farmer, {$mao} MAO Personnel, {$it} IT Expert."
                : "There are {$total} total user accounts ({$active} active, {$inactive} inactive): {$farmers} Farmer, {$mao} MAO Personnel, {$it} IT Expert.",
            'source_name' => 'iClimate User Records',
            'confidence' => 94,
        ];
    }

    private function databaseStatus(bool $tagalog): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo();
            $ms = round((microtime(true) - $start) * 1000, 1);

            return [
                'answer' => $tagalog
                    ? "Konektado ang database, tumutugon sa {$ms} ms."
                    : "The database is connected and responding in {$ms} ms.",
                'source_name' => 'iClimate System Status',
                'confidence' => 95,
            ];
        } catch (Throwable $exception) {
            return [
                'answer' => $tagalog
                    ? 'Hindi maka-konekta sa database ngayon: '.$exception->getMessage()
                    : 'Could not connect to the database right now: '.$exception->getMessage(),
                'source_name' => 'iClimate System Status',
                'confidence' => 95,
            ];
        }
    }

    private function apiStatus(bool $tagalog): array
    {
        $url = rtrim((string) config('services.farming_ai.url'), '/').'/health';

        try {
            $response = Http::timeout(3)->get($url);

            if ($response->successful()) {
                $models = implode(', ', (array) $response->json('models_loaded', []));

                return [
                    'answer' => $tagalog
                        ? "Online ang Farming AI API. Naka-load na models: {$models}."
                        : "The Farming AI API is online. Loaded models: {$models}.",
                    'source_name' => 'iClimate System Status',
                    'confidence' => 93,
                ];
            }

            return [
                'answer' => $tagalog
                    ? "Tumugon ang Farming AI API pero may error (HTTP {$response->status()})."
                    : "The Farming AI API responded but with an error (HTTP {$response->status()}).",
                'source_name' => 'iClimate System Status',
                'confidence' => 85,
            ];
        } catch (Throwable) {
            return [
                'answer' => $tagalog
                    ? 'Hindi ma-reach ang Farming AI API ngayon. Fallback rule-based predictions ang ginagamit habang offline ito.'
                    : 'The Farming AI API could not be reached right now. Fallback rule-based predictions are used while it is offline.',
                'source_name' => 'iClimate System Status',
                'confidence' => 88,
            ];
        }
    }

    private function errorLogSummary(bool $tagalog): array
    {
        $path = storage_path('logs/laravel.log');
        $lines = $this->tailLogLines($path);

        if (empty($lines)) {
            return [
                'answer' => $tagalog
                    ? 'Walang nahanap na log file o walang laman ito.'
                    : 'No log file was found or it is currently empty.',
                'source_name' => 'iClimate System Status',
                'confidence' => 80,
            ];
        }

        $counts = [];

        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})[^\]]*\]\s+\S+\.(ERROR|WARNING|CRITICAL|EMERGENCY|ALERT)/', $line, $match)) {
                $key = $match[1].' '.$match[2];
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        if (empty($counts)) {
            return [
                'answer' => $tagalog
                    ? 'Walang error, warning, o critical entries sa pinakabagong bahagi ng system log.'
                    : 'No error, warning, or critical entries were found in the most recent portion of the system log.',
                'source_name' => 'iClimate System Status',
                'confidence' => 85,
            ];
        }

        $summary = collect($counts)
            ->map(fn (int $count, string $key): string => "{$key}: {$count}")
            ->take(8)
            ->implode(', ');

        return [
            'answer' => $tagalog
                ? "Buod ng error log (pinakabagong bahagi lamang, hindi kumpletong kasaysayan): {$summary}."
                : "Error log summary (most recent portion only, not the full history): {$summary}.",
            'source_name' => 'iClimate System Status',
            'confidence' => 85,
        ];
    }

    private function maintenanceStatus(bool $tagalog): array
    {
        $down = app()->isDownForMaintenance();
        $logCount = SystemLog::count();

        return [
            'answer' => $tagalog
                ? ($down
                    ? "Nasa maintenance mode ang system ngayon. May {$logCount} naitalang system log entries."
                    : "Hindi nasa maintenance mode ang system -- normal ang operasyon. May {$logCount} naitalang system log entries.")
                : ($down
                    ? "The system is currently in maintenance mode. There are {$logCount} recorded system log entries."
                    : "The system is not in maintenance mode -- it is operating normally. There are {$logCount} recorded system log entries."),
            'source_name' => 'iClimate System Status',
            'confidence' => 92,
        ];
    }

    /**
     * Read only the last chunk of a log file rather than the whole file, so this stays
     * cheap even as the log grows well beyond its current size.
     *
     * @return array<int, string>
     */
    private function tailLogLines(string $path, int $maxBytes = 200_000): array
    {
        if (! is_file($path)) {
            return [];
        }

        $size = filesize($path);

        if ($size === false || $size === 0) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        fseek($handle, max(0, $size - $maxBytes));
        $chunk = fread($handle, $maxBytes) ?: '';
        fclose($handle);

        $lines = explode("\n", $chunk);

        if ($size > $maxBytes) {
            array_shift($lines);
        }

        return $lines;
    }
}
