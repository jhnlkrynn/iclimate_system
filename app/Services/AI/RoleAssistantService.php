<?php

namespace App\Services\AI;

use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Risk\BarangayRiskMapService;
use App\Support\LianBarangays;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RoleAssistantService
{
    public function __construct(
        private readonly BarangayRiskMapService $riskMapService,
    ) {}

    public function roleRestrictedMessage(string $language, string $requiredRoleLabel, string $askerRole = User::ROLE_FARMER): string
    {
        $capabilities = match ($askerRole) {
            User::ROLE_MAO => [
                'en' => 'weather, yield, planting and irrigation guidance, barangay information, advisories, reports, and announcements',
                'tl' => 'panahon, ani, gabay sa pagtatanim at irigasyon, impormasyon ng barangay, advisory, reports, at announcements',
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
                    ? 'Sabihin kung aling barangay sa Lian, Batangas ang gusto mong malaman (hal. Matabungkay, Binubusan, San Diego).'
                    : 'Let me know which barangay in Lian, Batangas you would like information about (e.g. Matabungkay, Binubusan, San Diego).',
                'source_name' => 'Climora AI',
                'confidence' => 70,
            ];
        }

        $production = RiceProduction::query()->where('barangay', $barangay)->latest('year')->take(5)->get();
        // Keep Climora aligned with Heat Map/MAO: seed placeholders are not
        // ratings and must never be presented as calculated risk.
        $heatmap = HeatmapArea::query()->computed()->where('barangay', $barangay)->latest()->first();

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

        // Staleness-aware climate risk line: never present an old
        // HeatmapArea row as unqualified present-tense fact. See
        // HeatmapArea::CURRENT_WINDOW_HOURS and
        // AIOrchestratorService::climateRiskForBarangay() for the same
        // CURRENT/STALE/NO-DATA classification used by Climora.
        $liveDetail = null;
        try {
            $liveDetail = $this->riskMapService->detailFor($barangay);
        } catch (Throwable) {
            $liveDetail = null;
        }
        $riskStatus = ClimateRiskReplies::status($heatmap, $liveDetail);

        // The main concern is only named when the rating has one.
        $riskType = $heatmap?->risk_type ? " ({$heatmap->risk_type})" : '';
        if ($riskStatus === ClimateRiskReplies::CURRENT) {
            $lines[] = $tagalog
                ? "Climate risk: {$heatmap->risk_level}{$riskType}."
                : "Climate risk: {$heatmap->risk_level}{$riskType}.";
        } elseif ($riskStatus === ClimateRiskReplies::STALE) {
            $date = $heatmap->risk_computed_at?->toFormattedDateString() ?? 'an earlier date';
            $lines[] = $tagalog
                ? "Noong {$date}, ang climate risk ay {$heatmap->risk_level}{$riskType} — maaaring hindi na ito kasalukuyang kondisyon."
                : "As of {$date}, climate risk was marked {$heatmap->risk_level}{$riskType}; this may not reflect current conditions.";
        } else {
            $lines[] = ClimateRiskReplies::noData($language);
        }

        return [
            'answer' => implode(' ', $lines),
            'source_name' => 'iClimate Barangay Records',
            'confidence' => $production->isNotEmpty() || $riskStatus !== ClimateRiskReplies::NO_DATA ? 88 : 70,
        ];
    }

    private function barangayNamedIn(string $question): ?string
    {
        // Any Lian barangay, supported or not: a question about Bagong Pook must
        // be answered for Bagong Pook, never for the asker's own barangay.
        return LianBarangays::namedIn($question);
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
}
