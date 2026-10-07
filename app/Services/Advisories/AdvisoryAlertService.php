<?php

namespace App\Services\Advisories;

use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Support\SchemaCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Manuscript – Naicel (2026-10-08), Phase 6: MAO alerts (typhoon, flood, drought, heat or a general farming
 * recommendation). An alert is an ordinary published advisory — one per chosen barangay, or one for all of Lian — so it
 * shows on the farmers' Advisories page as before; the farmers it is for get a bell alert linked to it. Sent now, or
 * scheduled (status "scheduled" until iclimate:publish-scheduled-alerts publishes it).
 */
class AdvisoryAlertService
{
    public const TYPES = ['typhoon' => 'Typhoon', 'flood' => 'Flood', 'drought' => 'Drought', 'heat' => 'Extreme heat', 'general' => 'General recommendation'];

    public const SEVERITIES = [
        PlantingAdvisory::SEVERITY_LOW => 'Low',
        PlantingAdvisory::SEVERITY_MODERATE => 'Moderate',
        PlantingAdvisory::SEVERITY_HIGH => 'High',
        PlantingAdvisory::SEVERITY_CRITICAL => 'Critical',
    ];

    private const PRIORITY = ['low' => 35, 'moderate' => 55, 'high' => 80, 'critical' => 100];

    public function __construct(private readonly AdvisoryPublishedNotifier $notifier) {}

    /**
     * @param  array{title: string, type: string, severity: string, message: string, recommended_action: ?string, barangays: array<int, string>, scheduled_for: ?CarbonInterface}  $data
     * @return array{advisories: Collection<int, PlantingAdvisory>, notified: int, scheduled: bool}
     */
    public function create(array $data, User $author): array
    {
        $batch = (string) Str::uuid();
        $scheduled = $data['scheduled_for'] !== null && $data['scheduled_for']->isFuture();
        $targets = $data['barangays'] === [] ? [null] : $data['barangays'];

        $advisories = collect($targets)->map(fn (?string $barangay) => PlantingAdvisory::query()->create([
            'title' => $data['title'],
            'content' => $data['message'],
            'message' => $data['message'],
            'summary' => Str::limit($data['message'], 180),
            'recommended_action' => $data['recommended_action'],
            'type' => $data['type'] === 'general' ? PlantingAdvisory::TYPE_PLANTING : PlantingAdvisory::TYPE_CLIMATE,
            'advisory_type' => $data['type'] === 'general' ? PlantingAdvisory::TYPE_PLANTING : PlantingAdvisory::TYPE_CLIMATE,
            'severity' => $data['severity'],
            'priority' => self::PRIORITY[$data['severity']] ?? 50,
            'target_barangay' => $barangay,
            'target_scope' => $barangay === null ? 'municipality' : 'barangay',
            'source' => 'MAO Alert',
            'generated_automatically' => false,
            'requires_review' => false,
            'status' => $scheduled ? PlantingAdvisory::STATUS_SCHEDULED : PlantingAdvisory::STATUS_PUBLISHED,
            'scheduled_for' => $scheduled ? $data['scheduled_for'] : null,
            'published_at' => $scheduled ? null : now(),
            'approved_by' => $author->getKey(),
            'approved_at' => now(),
            'posted_by' => $author->getKey(),
            'metadata' => ['alert_batch' => $batch, 'alert_type' => $data['type'], 'scope_type' => $barangay === null ? 'municipality' : 'barangay'],
        ]));

        $notified = $scheduled ? 0 : $advisories->sum(fn (PlantingAdvisory $a) => $this->notify($a, $data['type']));

        return ['advisories' => $advisories, 'notified' => $notified, 'scheduled' => $scheduled];
    }

    /** Publishes the scheduled alerts whose time has come. @return int how many advisories were published */
    public function publishDue(): int
    {
        $due = PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_for')->where('scheduled_for', '<=', now())->get();

        foreach ($due as $advisory) {
            $advisory->update(['status' => PlantingAdvisory::STATUS_PUBLISHED, 'published_at' => now()]);
            $this->notify($advisory, (string) ($advisory->metadata['alert_type'] ?? 'general'));
        }

        return $due->count();
    }

    /** Cancels a scheduled alert batch before it is sent. */
    public function cancel(string $batch): int
    {
        return $this->batchQuery($batch)->where('status', PlantingAdvisory::STATUS_SCHEDULED)->get()
            ->each(fn (PlantingAdvisory $a) => $a->update(['status' => PlantingAdvisory::STATUS_ARCHIVED, 'scheduled_for' => null]))
            ->count();
    }

    /**
     * Sent and scheduled alerts, newest first, one row per alert (a batch across several barangays is one row), with how
     * many farmers got it and how many read it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function history(int $limit = 30): Collection
    {
        $alerts = PlantingAdvisory::query()->where('source', 'MAO Alert')->latest('id')->limit($limit * 5)
            ->get(['id', 'title', 'severity', 'target_barangay', 'status', 'scheduled_for', 'published_at', 'created_at', 'metadata', 'posted_by']);
        $tracked = SchemaCache::hasColumn('notifications', 'planting_advisory_id');
        $counts = $tracked && $alerts->isNotEmpty()
            ? Notification::query()->whereIn('planting_advisory_id', $alerts->pluck('id'))
                ->selectRaw('planting_advisory_id, count(*) as delivered, sum(case when is_read then 1 else 0 end) as read_count')
                ->groupBy('planting_advisory_id')->get()->keyBy('planting_advisory_id')
            : collect();
        $authors = User::query()->whereIn('id', $alerts->pluck('posted_by')->filter()->unique())->pluck('name', 'id');

        return $alerts->groupBy(fn (PlantingAdvisory $a) => $a->metadata['alert_batch'] ?? 'single-'.$a->id)
            ->map(function (Collection $group, string $batch) use ($counts, $authors): array {
                $first = $group->first();
                $delivered = $group->sum(fn ($a) => (int) ($counts[$a->id]->delivered ?? 0));
                $read = $group->sum(fn ($a) => (int) ($counts[$a->id]->read_count ?? 0));

                return [
                    'batch' => $batch,
                    'title' => $first->title,
                    'type' => self::TYPES[$first->metadata['alert_type'] ?? 'general'] ?? 'General recommendation',
                    'severity' => self::SEVERITIES[$first->severity] ?? ucfirst((string) $first->severity),
                    'recipients' => $group->pluck('target_barangay')->filter()->isEmpty() ? 'All farmers' : $group->pluck('target_barangay')->filter()->sort()->implode(', '),
                    'status' => $group->pluck('status')->unique()->implode(', '),
                    'scheduled_for' => $first->scheduled_for,
                    'sent_at' => $first->published_at,
                    'created_at' => $first->created_at,
                    'delivered' => $delivered,
                    'read' => $read,
                    'read_percent' => $delivered > 0 ? (int) round($read / $delivered * 100) : null,
                    'author' => $authors[$first->posted_by] ?? null,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['created_at'])->take($limit)->values();
    }

    private function notify(PlantingAdvisory $advisory, string $type): int
    {
        $category = in_array($type, ['typhoon', 'flood', 'drought', 'heat'], true) ? Notification::CATEGORY_WEATHER : null;

        return (int) rescue(fn (): int => $this->notifier->notify($advisory, $category), 0);
    }

    private function batchQuery(string $batch)
    {
        return PlantingAdvisory::query()->where('source', 'MAO Alert')
            ->where(fn ($q) => \App\Support\JsonColumn::where($q, 'metadata', 'alert_batch', '=', $batch));
    }
}
