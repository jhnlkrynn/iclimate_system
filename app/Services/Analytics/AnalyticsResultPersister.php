<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsRun;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\User;

class AnalyticsResultPersister
{
    public function persist(AnalyticsRun $run, array $result): array
    {
        return match ($run->type) {
            'report_analytics' => $this->persistReport($run, $result),
            'heatmap_risk' => $this->persistHeatmap($result),
            'advisory_ai' => $this->persistAdvisories($run, $result),
            default => $result,
        };
    }

    private function persistReport(AnalyticsRun $run, array $result): array
    {
        $typeKey = (string) data_get($result, 'report_type');
        $label = Report::TYPES[$typeKey] ?? 'Analytics Report';
        $generatedAt = now();
        $payload = [
            'report_type' => $typeKey,
            'filters' => (array) data_get($result, 'filters', []),
            'columns' => (array) data_get($result, 'columns', []),
            'records' => array_values((array) data_get($result, 'records', [])),
            'row_count' => (int) data_get($result, 'row_count', 0),
            'generated_at' => $generatedAt->toDateTimeString(),
            'format' => 'screen',
            'analytics_run_uuid' => $run->uuid,
            'processor' => 'python',
        ];

        $report = Report::query()->create([
            'report_type' => $label,
            'title' => $label.' - '.$generatedAt->format('Y-m-d H:i'),
            'generated_by' => $run->requested_by,
            'is_generated' => true,
            'payload' => $payload,
        ]);

        return $result + ['report_id' => $report->id, 'report' => $payload];
    }

    private function persistHeatmap(array $result): array
    {
        foreach ((array) data_get($result, 'areas', []) as $area) {
            if (blank($area['barangay'] ?? null)) {
                continue;
            }

            HeatmapArea::query()->updateOrCreate(
                ['barangay' => $area['barangay']],
                collect($area)->only((new HeatmapArea)->getFillable())->all(),
            );
        }

        return $result;
    }

    private function persistAdvisories(AnalyticsRun $run, array $result): array
    {
        $postedBy = $run->requested_by
            ?? User::query()->whereIn('role', [User::ROLE_MAO, User::ROLE_IT_EXPERT])->value('id');

        foreach ((array) data_get($result, 'advisories', []) as $advisory) {
            $generationKey = (string) ($advisory['generation_key'] ?? '');
            if ($generationKey === '' || ! $postedBy) {
                continue;
            }

            PlantingAdvisory::query()->firstOrCreate(
                ['generation_key' => $generationKey],
                [
                    'title' => $advisory['title'],
                    'content' => $advisory['message'],
                    'message' => $advisory['message'],
                    'summary' => $advisory['summary'],
                    'recommended_action' => $advisory['recommended_action'],
                    'type' => $advisory['advisory_type'],
                    'advisory_type' => $advisory['advisory_type'],
                    'severity' => $advisory['severity'],
                    'priority' => $advisory['priority'],
                    'target_scope' => 'municipality',
                    'source' => 'iClimate Python Analytics',
                    'status' => PlantingAdvisory::STATUS_PENDING_REVIEW,
                    'generated_automatically' => true,
                    'requires_review' => true,
                    'valid_from' => $advisory['valid_from'] ?? now(),
                    'valid_until' => $advisory['valid_until'] ?? now()->addDays(7),
                    'posted_by' => $postedBy,
                    'metadata' => ['processor' => 'python', 'analytics_run_uuid' => $run->uuid],
                ],
            );
        }

        return $result;
    }
}
