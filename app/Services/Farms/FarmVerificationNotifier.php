<?php

namespace App\Services\Farms;

use App\Mail\FarmVerificationMail;
use App\Models\Farm;
use App\Models\Notification;
use App\Models\User;
use App\Services\Accounts\AccountEmails;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
use Illuminate\Support\Facades\Mail;

/**
 * In-app (EN/FIL) and email notifications for farm verification events.
 */
final class FarmVerificationNotifier
{
    public function submitted(Farm $farm): void
    {
        $farmer = $farm->user;
        if (! $farmer) {
            return;
        }

        $fil = $this->isFilipino($farmer);
        $name = $farm->farm_name;

        Notification::query()->create([
            'user_id' => $farmer->id,
            'title' => $fil ? 'Ipinadala para sa pagsusuri ng MAO' : 'Farm sent for MAO review',
            'message' => $fil
                ? "Ang iyong bukid na \"{$name}\" ay ipinadala para sa pagsusuri ng MAO (Pending)."
                : "Your farm \"{$name}\" was sent for MAO review.",
            'type' => 'Announcement',
            'category' => 'farm',
            'is_read' => false,
        ]);

        // Notify MAO staff covering this barangay (plus MAO Head).
        $canonical = LianBarangays::canonicalName($farm->barangay);
        $maoUsers = User::query()
            ->where('role', User::ROLE_MAO)
            ->whereIn('status', [User::STATUS_ACTIVE, User::STATUS_APPROVED])
            ->with('maoBarangays')
            ->get()
            ->filter(function (User $mao) use ($canonical): bool {
                if ($mao->isMaoHead()) {
                    return true;
                }
                $covered = $mao->coveredBarangays();

                return $covered === [] || $canonical === null || in_array($canonical, $covered, true);
            });

        foreach ($maoUsers as $mao) {
            Notification::query()->create([
                'user_id' => $mao->id,
                'title' => 'New farm pending verification',
                'message' => "{$farmer->name} submitted \"{$name}\" (".($canonical ?: 'Lian').') for MAO verification.',
                'type' => 'Announcement',
                'category' => 'farm_verification',
                'is_read' => false,
            ]);
        }
    }

    public function approved(Farm $farm): void
    {
        $farmer = $farm->user;
        if (! $farmer) {
            return;
        }

        $fil = $this->isFilipino($farmer);
        $name = $farm->farm_name;

        Notification::query()->create([
            'user_id' => $farmer->id,
            'title' => $fil ? 'Aprubado na ang iyong bukid' : 'Farm approved',
            'message' => $fil
                ? "Inaprubahan ng MAO ang iyong bukid na \"{$name}\". Aktibo na ito sa iyong dashboard."
                : "The MAO approved your farm \"{$name}\". It is now active on your dashboard.",
            'type' => 'Announcement',
            'category' => 'farm',
            'is_read' => false,
        ]);

        $email = AccountEmails::recipient($farmer);
        if ($email !== null) {
            rescue(fn () => Mail::to($email)->send(new FarmVerificationMail($farm, Farm::VERIFICATION_APPROVED, null, $fil ? 'fil' : 'en')), null, true);
        }
    }

    public function rejected(Farm $farm, string $reason): void
    {
        $farmer = $farm->user;
        if (! $farmer) {
            return;
        }

        $fil = $this->isFilipino($farmer);
        $name = $farm->farm_name;
        $editUrl = route('farmer.farms.edit', $farm);

        Notification::query()->create([
            'user_id' => $farmer->id,
            'title' => $fil ? 'Hindi naaprubahan ang iyong bukid' : 'Farm needs updates',
            'message' => $fil
                ? "Hindi naaprubahan ang iyong bukid na \"{$name}\". Dahilan: {$reason}. Ayusin at ipasa muli: {$editUrl}"
                : "Your farm \"{$name}\" was rejected. Reason: {$reason}. Fix and resubmit: {$editUrl}",
            'type' => 'Announcement',
            'category' => 'farm',
            'is_read' => false,
        ]);

        $email = AccountEmails::recipient($farmer);
        if ($email !== null) {
            rescue(fn () => Mail::to($email)->send(new FarmVerificationMail($farm, Farm::VERIFICATION_REJECTED, $reason, $fil ? 'fil' : 'en')), null, true);
        }
    }

    private function isFilipino(User $farmer): bool
    {
        $pref = $farmer->getAttribute('preferred_language');

        return FarmerLanguage::isSupported($pref) ? $pref === 'fil' : false;
    }
}
