<?php

namespace App\Services\Advisories;

use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Support\LianBarangays;
use Illuminate\Support\Facades\Cache;

/**
 * When the MAO publishes an advisory, the farmers it is for get one short alert ("New advisory for your barangay")
 * that links to the Advisories page. The alert carries no advice of its own: the advisory is read in one place.
 * Advisories the system publishes by itself (PAGASA checks, forecast rules) never notify, so farmers are not flooded.
 */
class AdvisoryPublishedNotifier
{
    /** @return int how many farmers were alerted */
    public function notify(PlantingAdvisory $advisory): int
    {
        $target = LianBarangays::canonicalName($advisory->target_barangay);

        $farmers = User::query()
            ->with('farmerProfile:id,user_id,barangay')
            ->where('role', User::ROLE_FARMER)
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->filter(fn (User $farmer): bool => $target === null || LianBarangays::canonicalName($farmer->registeredBarangay()) === $target)
            ->values();

        if ($farmers->isEmpty()) {
            return 0;
        }

        $now = now();
        Notification::query()->insert($farmers->map(function (User $farmer) use ($target, $now): array {
            $locale = $farmer->preferred_language === 'fil' ? 'fil' : 'en';

            return [
                'user_id' => $farmer->id,
                'title' => trans($target ? 'farmer.advisories_page.alert_title' : 'farmer.advisories_page.alert_title_lian', [], $locale),
                'message' => trans('farmer.advisories_page.alert_message', [], $locale),
                'type' => 'Advisory',
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all());

        foreach ($farmers as $farmer) {
            Cache::forget('sidebar:unread-notifications:'.$farmer->id);
            FarmerDashboardService::forgetCached($farmer);
        }

        return $farmers->count();
    }
}
