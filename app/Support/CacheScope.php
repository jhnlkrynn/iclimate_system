<?php

namespace App\Support;

use App\Models\AdvisoryFeedback;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmBoundary;
use App\Models\FarmerOrganization;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FeedComment;
use App\Models\FeedMedia;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\FieldReport;
use App\Models\HeatmapArea;
use App\Models\MlModel;
use App\Models\MonthlyWeatherPrediction;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Models\WeatherForecastFeedback;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Which cached pages depend on which data: the one place cache invalidation is decided.
 *
 * Data is grouped into areas (weather, risk, advisories, ...). Every area has a version number. A cached page or
 * payload ("consumer") lists the areas it reads, and its cache key carries those areas' versions (token()). A real
 * change to a row bumps only its model's areas, so only the caches that read that data are rebuilt; everything else
 * stays warm. A weather refresh no longer retires User Management, and a notification no longer retires every
 * dashboard.
 *
 * Rows that belong to one person (their notifications, profile, plans) also retire that person's private caches
 * (PageCache::flushUser), so their own pages show the change while other people's stay warm.
 *
 * A page build may itself write data (a weather snapshot). Those writes still retire the other caches that read the
 * data, but not the value being built, which already contains them: see snapshot() / settledToken().
 */
final class CacheScope
{
    /**
     * Model => the data areas its rows belong to. 'area' => [columns] is an area a row joins only when created,
     * deleted or when one of those columns changes (an unread flag flipping never touches its text).
     */
    public const MODEL_AREAS = [
        User::class => ['users'],
        FarmerProfile::class => ['farmers'],
        FarmerOrganization::class => ['farmers'],
        FarmBoundary::class => ['farmers'],
        \App\Models\Farm::class => ['farmers'],
        FarmerPlantingSchedule::class => ['planting'],
        ClimateRecord::class => ['climate'],
        RiceProduction::class => ['rice', 'sample-text' => ['remarks', 'created_at']],
        PlantingAdvisory::class => ['advisories', 'sample-text' => ['title', 'content', 'created_at']],
        AdvisoryFeedback::class => ['advisory-feedback'],
        FeedPost::class => ['community-posts'],
        FeedMedia::class => ['community-posts'],
        FeedComment::class => ['community-activity'],
        FeedReaction::class => ['community-activity'],
        Notification::class => ['notifications', 'notification-rows' => ['created_at'], 'sample-text' => ['title', 'message', 'created_at']],
        Report::class => ['reports'],
        FieldReport::class => ['field-reports'],
        HeatmapArea::class => ['risk'],
        ExternalWeatherData::class => ['weather'],
        WeatherForecastFeedback::class => ['weather-feedback'],
        MonthlyWeatherPrediction::class => ['predictions'],
        MlModel::class => ['ml-models'],
        AnalyticsRun::class => ['analytics'],
        TyphoonSafetyResponse::class => ['typhoon'],
    ];

    /**
     * Areas that are not table rows: the PAGASA / local weather check status shown on the Advisories page
     * (bumped by those refreshes themselves).
     */
    public const OTHER_AREAS = ['advisory-sources'];

    /**
     * Columns whose change is bookkeeping, not information a page shows: "fetched again" with the same values.
     * 'column.key' ignores one key inside a JSON column.
     */
    public const IGNORED_CHANGES = [
        ExternalWeatherData::class => ['fetched_at', 'raw_response'],
        PlantingAdvisory::class => ['metadata.fetched_at'],
    ];

    /** Rows owned by one person (user_id): that person's private caches are retired too. */
    public const OWNED_BY_USER = [
        Notification::class, AdvisoryFeedback::class, FarmerProfile::class, FarmBoundary::class, \App\Models\Farm::class,
        FarmerPlantingSchedule::class, TyphoonSafetyResponse::class,
    ];

    /**
     * Cached page / payload => the areas it reads. Found by reading each builder (2026-10-06).
     * A name that is not listed here depends on every area, so a cache added later is never served stale.
     */
    public const CONSUMERS = [
        // Farmer Home (FarmerDashboardService, five parts from saved data): current and barangay weather, the week's risk
        // and risk detail, advisories (also the typhoon event). The farmer's own rows come through the per-user version.
        'farmer-dashboard' => ['advisories', 'risk', 'weather'],
        // DashboardOverviewService::farmer() / DashboardKpiService::farmer(): the older farmer overview parts.
        'farmer-overview' => ['users', 'advisories', 'rice', 'risk', 'community-posts', 'weather', 'predictions'],
        // MAO dashboard payload + its overview / KPI parts.
        'mao-dashboard' => ['users', 'farmers', 'planting', 'climate', 'rice', 'advisories', 'advisory-feedback', 'field-reports',
            'reports', 'risk', 'weather', 'predictions', 'typhoon'],
        // MAO Operations (the whole assembled page): the MAO dashboard's data plus weather feedback, community posts,
        // the notification total (rows, not read flags) and the planting-schedule list.
        'mao-operations' => ['users', 'farmers', 'planting', 'climate', 'rice', 'advisories', 'advisory-feedback', 'field-reports',
            'reports', 'risk', 'weather', 'predictions', 'typhoon', 'weather-feedback', 'community-posts', 'notification-rows'],
        // IT dashboard payload, insights, overview, KPIs: a whole-system view.
        'it-dashboard' => ['users', 'farmers', 'planting', 'climate', 'rice', 'advisories', 'community-posts', 'notifications',
            'reports', 'field-reports', 'risk', 'weather', 'predictions', 'ml-models', 'analytics'],
        // Includes the read-only Announcements (community posts) shown to farmers.
        'advisories-index' => ['users', 'advisories', 'advisory-sources', 'advisory-feedback', 'weather', 'risk', 'analytics', 'typhoon', 'community-posts'],
        'advisories-summary' => ['advisories', 'weather', 'advisory-sources'],
        'calendar' => ['users', 'farmers', 'planting', 'advisories', 'community-posts'],
        'community-feed' => ['users', 'community-posts', 'community-activity'],
        'system-health' => ['weather', 'predictions', 'ml-models', 'analytics'],
        'crud-user-options' => ['users'],
        'farmer-organization-options' => ['farmers'],
        'ml-active-model' => ['ml-models'],
        // Where-the-data-comes-from: record counts and newest dates (notification rows, not their read flags).
        'provenance' => ['users', 'climate', 'rice', 'advisories', 'notification-rows', 'risk', 'weather', 'predictions', 'analytics'],
        // Seed-row detection reads only the text and creation time of rice / notification / advisory rows (sample-text),
        // plus the climate values and analytics runs it also screens.
        'sample-data:batches' => ['sample-text'],
        'sample-data:rice_productions' => ['sample-text'],
        'sample-data:notifications' => ['sample-text'],
        'sample-data:planting_advisories' => ['sample-text'],
        'sample-data:climate_records' => ['sample-text', 'climate'],
        'sample-data:analytics_runs' => ['sample-text', 'analytics'],
    ];

    /**
     * Short-lived caches under a fixed key (no version in the key): forgotten when one of their areas changes. As
     * before, a StaleCache entry may show its last good copy once more while it is rebuilt.
     */
    public const FIXED_KEYS = [
        'dashboard:shared-stats' => ['users', 'climate', 'rice', 'advisories', 'community-posts', 'notifications'],
        'dashboard:mao-aggregates' => ['users', 'farmers', 'rice', 'advisories', 'community-posts', 'reports'],
        'dashboard:admin-aggregates' => ['users', 'farmers', 'climate', 'rice', 'advisories', 'community-posts', 'notifications', 'reports', 'predictions'],
        'dashboard:farmer-organizations' => ['users', 'farmers'],
        'dashboard:recent-operational-data' => ['farmers', 'climate', 'rice', 'advisories', 'advisory-feedback', 'community-posts', 'reports', 'risk'],
        'dashboard:high-risk-areas' => ['risk', 'advisory-feedback'],
        'dashboard:mao-action-items' => ['advisory-feedback'],
        'reports:filter-barangays' => ['farmers', 'rice', 'advisories'],
        'reports:source-stats' => ['farmers', 'climate', 'rice', 'advisories', 'community-posts'],
    ];

    /** Areas whose rows the request-scoped DashboardData copy holds. */
    private const DASHBOARD_DATA_AREAS = ['rice', 'risk'];

    /** @var array<string, int> bumps this PHP process made, per area */
    private static array $ownBumps = [];

    /** @return list<string> every area, model-backed or not */
    public static function areas(): array
    {
        $areas = self::OTHER_AREAS;
        foreach (self::MODEL_AREAS as $model => $_) {
            $areas = array_merge($areas, self::modelAreas($model));
        }

        return array_values(array_unique($areas));
    }

    /**
     * The areas a change to this model touches. $changedColumns null = an insert, a delete or a bulk write: every area.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>|null  $changedColumns
     * @return list<string>
     */
    public static function modelAreas(string $model, ?array $changedColumns = null): array
    {
        if (! isset(self::MODEL_AREAS[$model])) {
            return [];
        }

        $areas = [];
        foreach (self::MODEL_AREAS[$model] as $key => $value) {
            if (is_int($key)) {
                $areas[] = $value;                                     // always
            } elseif ($changedColumns === null || array_intersect($value, $changedColumns) !== []) {
                $areas[] = $key;                                       // only when one of its columns changed
            }
        }

        return $areas;
    }

    /** @return list<string> the areas a consumer reads (all of them for a name that is not mapped) */
    public static function areasFor(string $consumer): array
    {
        return self::CONSUMERS[$consumer] ?? self::areas();
    }

    /** The cache-key part that changes whenever data the consumer reads changes. */
    public static function token(string $consumer): string
    {
        return self::tokenFrom(self::versions(self::areasFor($consumer)));
    }

    /**
     * Before building a value: what its token is made of, and which of the changes from here on are this process's own.
     *
     * @return array{versions: array<string, int>, own: array<string, int>}
     */
    public static function snapshot(string $consumer): array
    {
        $areas = self::areasFor($consumer);

        return ['versions' => self::versions($areas), 'own' => array_intersect_key(self::$ownBumps, array_flip($areas))];
    }

    /** @param array{versions: array<string, int>, own: array<string, int>} $snapshot  The token the snapshot was taken at. */
    public static function tokenAt(array $snapshot): string
    {
        return self::tokenFrom($snapshot['versions']);
    }

    /**
     * After building: the token to store the value under. When every change since the snapshot was made by this
     * process (the build's own writes, which the value already contains), that is the current token, so the next
     * request finds it. When anyone else changed the data meanwhile, the value may predate that change: it keeps
     * the token it was started under and the next request rebuilds.
     *
     * @param  array{versions: array<string, int>, own: array<string, int>}  $snapshot
     */
    public static function settledToken(array $snapshot): string
    {
        $now = self::versions(array_keys($snapshot['versions']));

        foreach ($snapshot['versions'] as $area => $before) {
            $ownSince = (self::$ownBumps[$area] ?? 0) - ($snapshot['own'][$area] ?? 0);
            if ($now[$area] - $before !== $ownSince) {
                return self::tokenFrom($snapshot['versions']);
            }
        }

        return self::tokenFrom($now);
    }

    /** Retire every cache that reads these areas. */
    public static function bump(string ...$areas): void
    {
        foreach (array_unique($areas) as $area) {
            $key = self::versionKey($area);
            Cache::add($key, 1);
            Cache::increment($key);
            self::$ownBumps[$area] = (self::$ownBumps[$area] ?? 0) + 1;
        }

        foreach (self::FIXED_KEYS as $key => $dependsOn) {
            if (array_intersect($dependsOn, $areas) !== []) {
                Cache::forget($key);
            }
        }

        if (array_intersect(self::DASHBOARD_DATA_AREAS, $areas) !== []) {
            app(DashboardData::class)->forget();
        }
    }

    /** Retire everything (a deliberate full reset, e.g. after removing sample data). */
    public static function bumpAll(): void
    {
        self::bump(...self::areas());
    }

    /**
     * A row was created, changed or deleted: retire the caches that read it. Bulk writes that skip model events
     * (query-builder inserts) call this with the model class.
     *
     * @param  class-string<Model>  $model
     * @param  iterable<int>  $ownerIds  people whose private caches show these rows
     * @param  list<string>|null  $changedColumns  for an update: the columns that changed (null: insert / delete / bulk)
     */
    public static function changed(string $model, iterable $ownerIds = [], ?array $changedColumns = null): void
    {
        self::bump(...(isset(self::MODEL_AREAS[$model]) ? self::modelAreas($model, $changedColumns) : self::areas()));

        foreach ($ownerIds as $userId) {
            PageCache::flushUser((int) $userId);
        }
    }

    /** True when a saved row really changed something a page shows (not only bookkeeping such as a fetch time). */
    public static function isMeaningfulChange(Model $record): bool
    {
        // This save was the insert. (wasRecentlyCreated alone stays true for later updates of the same instance;
        // an insert records no changes, an update does.)
        if ($record->wasRecentlyCreated && $record->getChanges() === []) {
            return true;
        }

        $ignored = self::IGNORED_CHANGES[$record::class] ?? [];
        $changes = array_diff_key($record->getChanges(), array_flip([$record->getUpdatedAtColumn() ?? 'updated_at']));

        foreach (array_keys($changes) as $column) {
            if (in_array($column, $ignored, true)) {
                continue;
            }

            // PostgreSQL returns numeric columns as text in the column's scale ("24.50"); the same reading saved again
            // arrives as 24.5. Eloquent calls that a change; the number is the same, so no page shows anything new.
            if (self::sameNumber($record->getRawOriginal($column), $record->getAttributes()[$column] ?? null)) {
                continue;
            }

            $jsonKeys = array_values(array_filter(array_map(
                fn (string $path): ?string => str_starts_with($path, $column.'.') ? substr($path, strlen($column) + 1) : null,
                $ignored,
            )));
            if ($jsonKeys !== [] && self::sameJsonExcept($record->getRawOriginal($column), $record->getAttributes()[$column] ?? null, $jsonKeys)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** Model events: the one place a data change reaches the caches (registered by AppServiceProvider). */
    public static function listen(): void
    {
        foreach (array_keys(self::MODEL_AREAS) as $model) {
            $owned = in_array($model, self::OWNED_BY_USER, true) || $model === User::class;
            $owner = static fn (Model $record): array => $model === User::class
                ? [(int) $record->getKey()]
                : (($record->user_id ?? null) ? [(int) $record->user_id] : []);

            $model::saved(static function (Model $record) use ($model, $owned, $owner): void {
                if (self::isMeaningfulChange($record)) {
                    $inserted = $record->wasRecentlyCreated && $record->getChanges() === [];
                    self::changed($model, $owned ? $owner($record) : [], $inserted ? null : array_keys($record->getChanges()));
                }
            });
            $model::deleted(static function (Model $record) use ($model, $owned, $owner): void {
                self::changed($model, $owned ? $owner($record) : []);
            });
        }
    }

    /** @param list<string> $areas  @return array<string, int> */
    private static function versions(array $areas): array
    {
        if ($areas === []) {
            return [];
        }

        $keys = array_map(self::versionKey(...), $areas);
        $values = Cache::many($keys);

        $versions = [];
        foreach ($areas as $i => $area) {
            $versions[$area] = (int) ($values[$keys[$i]] ?? 1);
        }
        ksort($versions);

        return $versions;
    }

    /** @param array<string, int> $versions */
    private static function tokenFrom(array $versions): string
    {
        ksort($versions);

        return substr(md5(json_encode($versions)), 0, 12);
    }

    private static function versionKey(string $area): string
    {
        return 'cache-scope:'.$area.':version';
    }

    /**
     * True only when both values are numbers and the same number ("24.50" and 24.5, "0.00" and 0, "91.0" and 91).
     * Text is never compared this way, and a string with a leading zero ("024") is a code, not a measurement.
     */
    private static function sameNumber(mixed $before, mixed $after): bool
    {
        if (! is_numeric($before) || ! is_numeric($after)) {
            return false;
        }

        foreach ([$before, $after] as $value) {
            if (is_string($value) && preg_match('/^\s*[+-]?0\d/', $value) === 1) {
                return false;
            }
        }

        return self::canonicalNumber($before) === self::canonicalNumber($after);
    }

    /** "24.50" / 24.5 / "+24.5" -> "24.5"; "0.00" / -0.0 -> "0". Float noise past 14 decimals is dropped. */
    private static function canonicalNumber(int|float|string $value): string
    {
        $text = is_string($value) && ! preg_match('/[eE]/', $value)
            ? ltrim(trim($value), '+')
            : sprintf('%.14F', (float) $value);

        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }
        if ($text === '-0' || $text === '' || $text === '-') {
            $text = '0';
        }

        return $text;
    }

    /** @param list<string> $keys */
    private static function sameJsonExcept(mixed $before, mixed $after, array $keys): bool
    {
        $decode = static fn (mixed $value): mixed => is_string($value) ? json_decode($value, true) : $value;
        $before = $decode($before);
        $after = $decode($after);

        if (! is_array($before) || ! is_array($after)) {
            return false;
        }

        foreach ($keys as $key) {
            unset($before[$key], $after[$key]);
        }

        return $before == $after;
    }
}
