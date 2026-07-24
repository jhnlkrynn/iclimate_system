<?php

namespace App\Services;

use App\Models\PlantingAdvisory;
use Illuminate\Support\Str;

class TyphoonSafetyService
{
    public function activeEvent(): ?array
    {
        $advisory = PlantingAdvisory::query()
            ->active()
            ->where(function ($query) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->latest('valid_from')
            ->latest()
            ->take(50)
            ->get()
            ->first(fn (PlantingAdvisory $advisory): bool => $this->isTyphoonAdvisory($advisory));

        if ($advisory) {
            return [
                'key' => 'advisory-'.$advisory->id,
                'title' => $advisory->title,
                'source' => $advisory->source ?: 'iClimate advisory',
                'severity' => $advisory->severityLabel(),
                'valid_until' => $advisory->valid_until,
                'reason' => Str::limit($advisory->summary ?: $advisory->message ?: $advisory->content, 160),
            ];
        }

        return null;
    }

    private function isTyphoonAdvisory(PlantingAdvisory $advisory): bool
    {
        $text = collect([
            $advisory->title,
            $advisory->summary,
            $advisory->recommended_action,
            data_get($advisory->metadata, 'event_type'),
            data_get($advisory->metadata, 'hazard'),
        ])
            ->filter()
            ->implode(' ');

        return preg_match('/\b(typhoon|tropical\s+cyclone|tropical\s+storm|severe\s+tropical\s+storm|super\s+typhoon|tcws|tropical\s+cyclone\s+wind\s+signal|storm\s+signal|signal\s+no\.?)\b/i', $text) === 1;
    }
}
