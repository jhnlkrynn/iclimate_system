<?php

namespace App\Services\Advisories;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Support\DashboardData;
use App\Support\PageCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use App\Support\JsonColumn;

class PagasaAdvisoryService
{
    public const CHECK_KEY = 'pagasa-advisories:last-fetch';

    public function __construct(private readonly PagasaBulletinParser $parser) {}

    /**
     * @param  int|null  $budgetSeconds  Total time the PAGASA requests may take (a web request passes one so it ends
     *                                   before PHP's max_execution_time; null = no limit, e.g. the scheduled command).
     *                                   Sources not reached in time are skipped and listed in 'errors'.
     */
    public function fetchAndStore(bool $force = false, ?int $budgetSeconds = null): array
    {
        $deadline = $budgetSeconds !== null ? microtime(true) + $budgetSeconds : null;
        $remaining = fn (): float => $deadline === null ? INF : $deadline - microtime(true);
        $cached = Cache::get(self::CHECK_KEY);
        $previous = $cached ?? Cache::get('pagasa-advisories:status');
        if (! $force && is_array($cached)) {
            return $cached;
        }
        $summary = [
            'ok' => false, 'message' => 'PAGASA sources checked.', 'sources_checked' => 0,
            'sources_succeeded' => 0, 'sources_without_lian_batangas_match' => 0,
            'bulletins_fetched' => 0, 'relevant_bulletins' => 0,
            'advisories_created' => 0, 'advisories_skipped_as_duplicates' => 0,
            'checked_at' => now()->toIso8601String(),
            'last_successful_check' => $previous['last_successful_check'] ?? null, 'errors' => [],
        ];
        if (! config('services.pagasa.enabled', true)) {
            $summary['errors'][] = 'PAGASA fetching is disabled.';
        } else {
            // Prevent concurrent scheduler/manual fetches creating duplicate rows.
            $lock = Cache::lock('pagasa-advisories:fetch-lock', 120);
            if (! $lock->get()) {
                return $previous ?? ($summary + ['refreshing' => true]);
            }
            try {
                foreach ($this->sources() as $source) {
                    // Not enough time left for a useful request: report the source instead of running out of time.
                    if ($remaining() < 3) {
                        $summary['errors'][] = 'PAGASA '.$source['url'].' was not checked this time (time limit reached).';

                        continue;
                    }
                    $summary['sources_checked']++;
                    try {
                        $timeout = (int) max(1, min((int) config('services.pagasa.timeout', 12), floor($remaining() - 1)));
                        $response = Http::connectTimeout(min(5, $timeout))
                            ->timeout($timeout)
                            // A retry only when it can still finish inside the budget.
                            ->retry(2, 800, fn () => $remaining() > $timeout + 2, throw: false)
                            ->withUserAgent('iClimate/1.0 (agricultural advisory reader)')
                            ->accept('text/html')->get($source['url']);
                        if (! $response->successful()) {
                            throw new \RuntimeException('HTTP '.$response->status());
                        }
                        $bulletins = $this->parser->parse($response->body(), $source);
                        $summary['sources_succeeded']++;
                        $summary['bulletins_fetched'] += count($bulletins);
                        $relevant = 0;
                        foreach ($bulletins as $bulletin) {
                            if ($bulletin['published'] && $bulletin['published']->gt(now()->addMinutes(5))) {
                                continue;
                            }
                            $familyKey = hash('sha256', parse_url($source['url'], PHP_URL_PATH).'|'.$bulletin['family']);
                            if ($bulletin['published']) {
                                // A new warning replaces the earlier warning even if its
                                // updated affected-area list no longer includes Lian.
                                JsonColumn::where(PlantingAdvisory::query()->fromPagasaOnline()->published(), 'metadata', 'bulletin_family', '=', $familyKey)
                                    ->where('published_at', '<', $bulletin['published'])
                                    ->update(['status' => PlantingAdvisory::STATUS_ARCHIVED]);
                            }
                            if (! $bulletin['scope'] || ($bulletin['until'] && $bulletin['until']->lte(now()))) {
                                continue;
                            }
                            if ($bulletin['published'] && JsonColumn::where(PlantingAdvisory::query()->fromPagasaOnline()->published(), 'metadata', 'bulletin_family', '=', $familyKey)
                                ->where('published_at', '>', $bulletin['published'])->exists()) {
                                continue;
                            }
                            $relevant++;
                            $candidate = $this->candidate($bulletin, $source, $familyKey);
                            $existing = PlantingAdvisory::query()->where('generation_key', $candidate['generation_key'])->first();
                            if ($existing) {
                                // Fetch time changes; publication and validity never become "now".
                                $existing->update(['metadata' => array_merge($existing->metadata ?? [], ['fetched_at' => $summary['checked_at']])]);
                                $summary['advisories_skipped_as_duplicates']++;
                            } else {
                                PlantingAdvisory::query()->create($candidate);
                                $summary['advisories_created']++;
                            }
                        }
                        $summary['relevant_bulletins'] += $relevant;
                        if ($relevant === 0) {
                            $summary['sources_without_lian_batangas_match']++;
                        }
                        Log::info('PAGASA source checked.', ['url' => $source['url'], 'status' => $response->status(),
                            'bulletins' => count($bulletins), 'relevant_to_lian' => $relevant]);
                    } catch (Throwable $exception) {
                        $summary['errors'][] = 'Unable to read PAGASA '.$source['url'].': '.$exception->getMessage();
                        Log::warning('PAGASA fetch or parsing failed.', ['url' => $source['url'], 'error' => $exception->getMessage()]);
                    }
                }
            } finally {
                $lock->release();
            }
        }
        $summary['ok'] = $summary['sources_succeeded'] > 0;
        $summary['complete'] = $summary['ok'] && $summary['errors'] === [];
        if ($summary['ok']) {
            $summary['last_successful_check'] = $summary['checked_at'];
        }
        $summary['message'] = "{$summary['advisories_created']} PAGASA online advisories created, {$summary['advisories_skipped_as_duplicates']} duplicates skipped.";
        // Retain diagnostics beyond the short request cooldown, so a stale check
        // is not mistaken for "never checked" after the cache TTL passes.
        Cache::forever('pagasa-advisories:status', $summary);
        Cache::put(self::CHECK_KEY, $summary, now()->addMinutes((int) config('services.pagasa.cache_minutes', 10)));
        DashboardData::bump();
        PageCache::flushShared();
        Log::info('PAGASA advisory refresh completed.', $summary);

        return $summary;
    }

    private function candidate(array $bulletin, array $source, string $familyKey): array
    {
        $scope = $bulletin['scope'];
        $severity = match (true) {
            (bool) preg_match('/red (?:warning|rainfall)|emergency/i', $bulletin['text']) => 'critical',
            (bool) preg_match('/orange (?:warning|rainfall)|flooding is threatening/i', $bulletin['text']) => 'high',
            (bool) preg_match('/yellow warning|heavy rainfall|thunderstorm advisory/i', $bulletin['text']) => 'moderate',
            default => 'information',
        };
        $action = match ($severity) {
            'critical' => 'Pause outdoor farm work. Move equipment to higher ground and follow PAGASA and LGU instructions.',
            'high', 'moderate' => 'Avoid field work during thunderstorms, postpone spraying before rain, and check drainage.',
            default => 'Check field moisture and the latest forecast before irrigating, fertilizing, or spraying.',
        };
        $barangays = $scope['barangays'];
        // Multiple named barangays remain a restricted coverage list, not all-Lian.
        $target = count($barangays) === 1 ? $barangays[0] : null;
        $key = hash('sha256', implode('|', ['pagasa', $bulletin['family'], $scope['scope_name'],
            $bulletin['published']?->toIso8601String() ?? 'undated', $bulletin['text']]));

        return [
            'title' => $bulletin['title'], 'content' => $bulletin['text'],
            'type' => 'Climate', 'advisory_type' => 'climate',
            'summary' => Str::limit($bulletin['text'], 500), 'message' => $bulletin['text'],
            'recommended_action' => $action, 'severity' => $severity,
            'priority' => ['critical' => 100, 'high' => 85, 'moderate' => 60, 'information' => 25][$severity],
            'target_barangay' => $target, 'target_scope' => $barangays ? 'barangay' : 'municipality',
            'source' => $source['horizon'] === 'weekly' ? PlantingAdvisory::SOURCE_PAGASA_WEEKLY_OUTLOOK : PlantingAdvisory::SOURCE_PAGASA_ONLINE_ADVISORY,
            'source_url' => $source['url'], 'generation_key' => $key,
            'generated_automatically' => true, 'requires_review' => false, 'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => $bulletin['from'] ?? $bulletin['published'] ?? now(),
            // Undated plain-text content is an observation, not a newly issued
            // bulletin; use a bounded fallback lifetime and keep publication null.
            'valid_until' => $bulletin['until'] ?? ($bulletin['published']?->copy() ?? now())->addHours(6),
            'published_at' => $bulletin['published'],
            'metadata' => [
                'official_source' => true, 'is_official' => true, 'source_type' => 'pagasa', 'source_name' => 'PAGASA',
                'scope_type' => $scope['scope_type'], 'scope_name' => $scope['scope_name'],
                'pagasa_location_match' => $scope['scope_type'] === 'barangay' || $scope['scope_type'] === 'municipality' ? 'Lian, Batangas' : 'Batangas',
                'pagasa_barangay_matches' => $barangays, 'pagasa_source_url' => $source['url'],
                'source_excerpt' => $bulletin['text'], 'bulletin_family' => $familyKey,
                'advisory_horizon' => $source['horizon'], 'fetched_at' => now()->toIso8601String(),
                'publication_time_available' => $bulletin['published'] !== null,
            ],
            'posted_by' => $this->systemUserId(),
        ];
    }

    private function sources(): array
    {
        $urls = (array) config('services.pagasa.regional_forecast_urls', []);
        if ($urls === []) {
            $urls = [(string) config('services.pagasa.regional_forecast_url')];
        }
        $sources = collect($urls)->filter()->unique()->map(fn ($url) => ['url' => $url, 'horizon' => 'online'])->values()->all();
        if ($weekly = config('services.pagasa.weekly_outlook_url')) {
            $sources[] = ['url' => $weekly, 'horizon' => 'weekly'];
        }

        return array_values(array_filter($sources, fn ($source) => preg_match('#^https://(?:[a-z0-9-]+\.)?pagasa\.dost\.gov\.ph/#i', $source['url'])));
    }

    private function systemUserId(): int
    {
        return (int) (User::query()->whereIn('role', [User::ROLE_MAO, User::ROLE_IT_EXPERT])->orderBy('id')->value('id')
            ?? User::query()->orderBy('id')->value('id')
            ?? User::query()->firstOrCreate(['email' => 'system@iclimate.local'], [
                'name' => 'iClimate System', 'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_IT_EXPERT, 'status' => User::STATUS_ACTIVE,
            ])->id);
    }
}
