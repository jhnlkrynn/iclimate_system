<?php

namespace App\Services\Advisories;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PagasaAdvisoryService
{
    public function fetchAndStore(bool $force = false): array
    {
        if (! (bool) config('services.pagasa.enabled', true)) {
            return $this->summary(message: 'PAGASA online advisory fetching is disabled.');
        }

        $cacheKey = 'pagasa-advisories:last-fetch';

        if (! $force && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $summary = $this->summary(message: 'PAGASA online advisory pages checked.');

        foreach ($this->sources() as $source) {
            $summary['sources_checked']++;

            try {
                $response = Http::timeout((int) config('services.pagasa.timeout', 12))
                    ->accept('text/html')
                    ->get($source['url']);

                if (! $response->successful()) {
                    $summary['errors'][] = "PAGASA online page returned HTTP {$response->status()}: {$source['url']}";

                    continue;
                }

                $candidate = $this->candidateFromHtml($response->body(), $source);

                if (! $candidate) {
                    $summary['sources_without_lian_batangas_match']++;

                    continue;
                }

                if (PlantingAdvisory::query()->where('generation_key', $candidate['generation_key'])->exists()) {
                    $summary['advisories_skipped_as_duplicates']++;

                    continue;
                }

                PlantingAdvisory::query()->create($candidate);
                $summary['advisories_created']++;
            } catch (Throwable $exception) {
                $summary['errors'][] = "Unable to fetch PAGASA online page {$source['url']}: {$exception->getMessage()}";
            }
        }

        $summary['message'] = "{$summary['advisories_created']} PAGASA online advisories created, {$summary['advisories_skipped_as_duplicates']} duplicates skipped.";

        Cache::put($cacheKey, $summary, now()->addMinutes((int) config('services.pagasa.cache_minutes', 30)));

        return $summary;
    }

    private function candidateFromHtml(string $html, array $source): ?array
    {
        $text = $this->normalizeText($html);
        $location = $this->matchedLocation($text);

        if (! $location) {
            return null;
        }

        $excerpt = $this->excerptAroundLocationOrWarning($text);
        $severity = $this->severityFrom($excerpt);
        $validFrom = now();
        $validUntil = $source['horizon'] === 'weekly' ? now()->addDays(7) : now()->addHours(6);
        $title = $this->titleFrom($excerpt, $location, $source['horizon']);
        $message = $this->messageFrom($excerpt, $source['url']);
        $horizonLabel = $source['horizon'] === 'weekly' ? 'PAGASA Online Weekly Outlook' : 'PAGASA Online Advisory';
        $sourceName = $source['horizon'] === 'weekly'
            ? PlantingAdvisory::SOURCE_PAGASA_WEEKLY_OUTLOOK
            : PlantingAdvisory::SOURCE_PAGASA_ONLINE_ADVISORY;
        $matchedBarangays = $this->matchedBarangays($excerpt);
        $targetBarangay = count($matchedBarangays) === 1 ? $matchedBarangays[0] : null;

        return [
            'title' => $title,
            'content' => $message,
            'type' => 'Climate',
            'advisory_type' => PlantingAdvisory::TYPE_CLIMATE,
            'summary' => Str::limit($message, 180),
            'message' => $message,
            'recommended_action' => $this->recommendedAction($severity),
            'severity' => $severity,
            'priority' => $this->priorityForSeverity($severity),
            'target_barangay' => $targetBarangay,
            'target_scope' => $targetBarangay ? 'barangay' : 'municipality',
            'source' => $sourceName,
            'source_url' => $source['url'],
            'generation_key' => $this->generationKey($source, $excerpt),
            'generated_automatically' => true,
            'requires_review' => false,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'published_at' => now(),
            'metadata' => [
                'official_source' => true,
                'source_type' => 'PAGASA online web page',
                'source_name' => $sourceName,
                'pagasa_source_url' => $source['url'],
                'pagasa_location_match' => $location,
                'pagasa_barangay_matches' => $matchedBarangays,
                'pagasa_targeting_note' => $targetBarangay
                    ? "PAGASA text matched one Lian barangay: {$targetBarangay}."
                    : 'PAGASA text did not identify exactly one Lian barangay, so the advisory remains municipality-wide for all Lian barangays.',
                'source_excerpt' => $excerpt,
                'advisory_horizon' => $source['horizon'],
                'advisory_horizon_label' => $horizonLabel,
                'advisory_horizon_description' => 'official PAGASA online advisory filtered for Lian, Batangas',
                'disclaimer' => 'This advisory is based on official PAGASA online content detected for Lian or Batangas. Follow PAGASA online advisories, LGU, and disaster risk reduction instructions during severe weather.',
            ],
            'posted_by' => $this->systemUserId(),
        ];
    }

    private function normalizeText(string $html): string
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function matchedLocation(string $text): ?string
    {
        $lowerText = Str::lower($text);

        foreach ((array) config('services.pagasa.location_keywords', ['Lian', 'Batangas']) as $keyword) {
            $keyword = trim((string) $keyword);

            if ($keyword !== '' && Str::contains($lowerText, Str::lower($keyword))) {
                return Str::contains(Str::lower($keyword), 'lian') ? 'Lian, Batangas' : 'Batangas';
            }
        }

        return null;
    }

    private function matchedBarangays(string $text): array
    {
        $normalizedText = $this->normalizeForMatching($text);

        return collect(LianBarangays::all())
            ->filter(fn (string $barangay): bool => $this->textContainsBarangay($normalizedText, $barangay))
            ->values()
            ->all();
    }

    private function textContainsBarangay(string $normalizedText, string $barangay): bool
    {
        foreach ($this->barangayAliases($barangay) as $alias) {
            if (preg_match('/\b'.preg_quote($this->normalizeForMatching($alias), '/').'\b/u', $normalizedText)) {
                return true;
            }
        }

        return false;
    }

    private function barangayAliases(string $barangay): array
    {
        $aliases = [$barangay, str_replace('-', ' ', $barangay)];

        if (preg_match('/Barangay\s+(\d+)/i', $barangay, $matches)) {
            $number = $matches[1];
            $aliases[] = "Barangay {$number}";
            $aliases[] = "Brgy {$number}";
            $aliases[] = "Brgy. {$number}";
        }

        return array_values(array_unique($aliases));
    }

    private function normalizeForMatching(string $value): string
    {
        $value = Str::lower($value);
        $value = str_replace(['-', '.', ',', '(', ')'], ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function excerptAroundLocationOrWarning(string $text): string
    {
        $lowerText = Str::lower($text);
        $keywords = ['lian', 'batangas', 'heavy rainfall warning', 'rainfall warning', 'thunderstorm warning', 'weather advisory'];
        $positions = collect($keywords)
            ->map(fn (string $keyword) => strpos($lowerText, $keyword))
            ->filter(fn ($position) => $position !== false)
            ->values();

        $position = (int) ($positions->min() ?: 0);
        $start = max(0, $position - 500);

        return trim(Str::limit(substr($text, $start, 1400), 1200));
    }

    private function severityFrom(string $excerpt): string
    {
        $lowerExcerpt = Str::lower($excerpt);

        return match (true) {
            Str::contains($lowerExcerpt, ['red warning', 'red rainfall', 'emergency']) => PlantingAdvisory::SEVERITY_CRITICAL,
            Str::contains($lowerExcerpt, ['orange warning', 'orange rainfall', 'flooding is threatening']) => PlantingAdvisory::SEVERITY_HIGH,
            Str::contains($lowerExcerpt, ['yellow warning', 'heavy rainfall', 'thunderstorm']) => PlantingAdvisory::SEVERITY_MODERATE,
            default => PlantingAdvisory::SEVERITY_INFORMATION,
        };
    }

    private function titleFrom(string $excerpt, string $location, string $horizon): string
    {
        $lowerExcerpt = Str::lower($excerpt);
        $place = $location === 'Lian, Batangas' ? 'Lian' : 'Batangas';

        return match (true) {
            Str::contains($lowerExcerpt, 'heavy rainfall') => "PAGASA {$place} Heavy Rainfall Advisory",
            Str::contains($lowerExcerpt, 'rainfall') => "PAGASA {$place} Rainfall Advisory",
            Str::contains($lowerExcerpt, 'thunderstorm') => "PAGASA {$place} Thunderstorm Advisory",
            $horizon === 'weekly' => "PAGASA {$place} Weekly Weather Outlook",
            default => "PAGASA {$place} Weather Advisory",
        };
    }

    private function messageFrom(string $excerpt, string $url): string
    {
        return "Official PAGASA online advisory page detected for Lian/Batangas. Details from PAGASA online page: {$excerpt} Source URL: {$url}";
    }

    private function recommendedAction(string $severity): string
    {
        if (in_array($severity, [PlantingAdvisory::SEVERITY_HIGH, PlantingAdvisory::SEVERITY_CRITICAL], true)) {
            return 'Monitor PAGASA online advisories and LGU announcements closely, avoid risky field work during severe weather, secure farm materials, and prepare drainage or flood response actions where needed.';
        }

        return 'Review the PAGASA online advisory, adjust farm activities based on actual field conditions, and continue monitoring official updates for Lian, Batangas.';
    }

    private function priorityForSeverity(string $severity): int
    {
        return [
            PlantingAdvisory::SEVERITY_INFORMATION => 25,
            PlantingAdvisory::SEVERITY_LOW => 35,
            PlantingAdvisory::SEVERITY_MODERATE => 60,
            PlantingAdvisory::SEVERITY_HIGH => 85,
            PlantingAdvisory::SEVERITY_CRITICAL => 100,
        ][$severity] ?? 50;
    }

    private function generationKey(array $source, string $excerpt): string
    {
        $period = $source['horizon'] === 'weekly'
            ? now()->startOfDay()->toDateTimeString()
            : now()->startOfHour()->toDateTimeString();

        return hash('sha256', implode('|', [
            'pagasa',
            $source['url'],
            $source['horizon'],
            $period,
        ]));
    }

    private function sources(): array
    {
        $regionalUrls = (array) config('services.pagasa.regional_forecast_urls', []);

        if ($regionalUrls === []) {
            $regionalUrls = [(string) config('services.pagasa.regional_forecast_url')];
        }

        $sources = collect($regionalUrls)
            ->filter(fn ($url): bool => trim((string) $url) !== '')
            ->unique()
            ->map(fn ($url): array => [
                'url' => (string) $url,
                'horizon' => 'online',
            ])
            ->values()
            ->all();

        $sources[] = [
            'url' => (string) config('services.pagasa.weekly_outlook_url'),
            'horizon' => 'weekly',
        ];

        return array_values(array_filter($sources, fn (array $source): bool => trim($source['url']) !== ''));
    }

    private function summary(string $message): array
    {
        return [
            'ok' => true,
            'message' => $message,
            'sources_checked' => 0,
            'sources_without_lian_batangas_match' => 0,
            'advisories_created' => 0,
            'advisories_skipped_as_duplicates' => 0,
            'errors' => [],
        ];
    }

    private function systemUserId(): int
    {
        $userId = User::query()
            ->whereIn('role', [User::ROLE_MAO, User::ROLE_IT_EXPERT])
            ->orderBy('id')
            ->value('id') ?? User::query()->orderBy('id')->value('id');

        if ($userId) {
            return (int) $userId;
        }

        return (int) User::query()->firstOrCreate(
            ['email' => 'system@iclimate.local'],
            [
                'name' => 'iClimate System',
                'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_IT_EXPERT,
                'status' => User::STATUS_ACTIVE,
            ],
        )->id;
    }
}
