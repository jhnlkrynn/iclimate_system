<?php

namespace App\Services\AI;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\RiceVariety;
use App\Models\User;
use App\Support\ActiveFarm;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use App\Support\VarietyHarvestEstimator;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Single source of truth for a farmer's active farm context in Climora.
 *
 * Reads the active farm from the header "My Farm" switcher (ActiveFarm),
 * supports "Farm N" or barangay name overrides in the question, and computes
 * the crop stage + expected harvest date via VarietyHarvestEstimator.
 */
final class FarmerChatContext
{
    public function __construct(
        public readonly User $user,
        public readonly bool $isFarmer,
        public readonly bool $hasFarm,
        public readonly ?Farm $activeFarm,
        /** @var Collection<int, Farm> */
        public readonly Collection $farms,
        public readonly ?string $barangay,
        public readonly ?string $farmType,
        public readonly ?float $farmArea,
        public readonly ?string $variety,
        public readonly ?CarbonInterface $plantingDate,
        public readonly ?string $association,
        public readonly ?string $cropStage,
        public readonly ?CarbonInterface $expectedHarvestDate,
        public readonly ?CarbonInterface $harvestWindowStart,
        public readonly ?CarbonInterface $harvestWindowEnd,
        public readonly ?int $maturityDays,
        public readonly ?FarmerPlantingSchedule $schedule,
        public readonly bool $incompleteProfile,
        public readonly ?string $overriddenBy = null,
    ) {}

    public static function for(User $user, string $question = ''): self
    {
        $isFarmer = $user->role === User::ROLE_FARMER;
        if (! $isFarmer) {
            $barangay = LianBarangays::canonicalName($user->barangay);

            return new self(
                user: $user,
                isFarmer: false,
                hasFarm: false,
                activeFarm: null,
                farms: collect(),
                barangay: $barangay,
                farmType: null,
                farmArea: null,
                variety: null,
                plantingDate: null,
                association: null,
                cropStage: null,
                expectedHarvestDate: null,
                harvestWindowStart: null,
                harvestWindowEnd: null,
                maturityDays: null,
                schedule: null,
                incompleteProfile: false,
            );
        }

        $farms = ActiveFarm::available() ? ActiveFarm::farms($user) : collect();
        $activeFarm = ActiveFarm::available() ? ActiveFarm::current($user) : null;
        $overriddenBy = null;

        // "Farm 2" / "farm #2" in the question overrides the active farm.
        if ($farms->isNotEmpty() && preg_match('/\bfarm\s*#?\s*(\d+)\b/i', $question, $match)) {
            $index = (int) $match[1] - 1;
            $matched = $farms->values()->get($index);
            if ($matched instanceof Farm) {
                $activeFarm = $matched;
                $overriddenBy = 'farm_number';
            }
        } elseif ($farms->isNotEmpty()) {
            // Also check if a specific farm name is mentioned.
            $lower = mb_strtolower($question);
            foreach ($farms as $candidate) {
                $name = trim((string) $candidate->farm_name);
                if ($name !== '' && mb_strlen($name) >= 3 && str_contains($lower, mb_strtolower($name))) {
                    $activeFarm = $candidate;
                    $overriddenBy = 'farm_name';
                    break;
                }
            }
        }

        $profile = $user->farmerProfile ?: FarmerProfile::query()->where('user_id', $user->id)->first();

        $rawBarangay = $activeFarm?->barangay ?? $profile?->barangay ?? $user->barangay;
        $farmBarangay = self::cleanBarangay($rawBarangay);
        $questionBarangay = $question !== '' ? LianBarangays::namedIn($question) : null;

        if ($questionBarangay !== null) {
            $barangay = $questionBarangay;
            if ($overriddenBy === null && $farms->isNotEmpty()) {
                $matchingFarm = $farms->first(fn (Farm $f): bool => self::cleanBarangay($f->barangay) === $questionBarangay);
                if ($matchingFarm instanceof Farm) {
                    $activeFarm = $matchingFarm;
                }
            }
            $overriddenBy = 'barangay';
        } else {
            $barangay = $farmBarangay;
        }

        $hasFarm = $activeFarm !== null || ($profile !== null && $farmBarangay !== null);
        $incompleteProfile = ! $hasFarm || $farmBarangay === null;

        $farmType = $activeFarm?->farm_type ?: ($profile?->farm_type ?: null);
        $farmArea = ($activeFarm === null || $activeFarm->isApproved())
            ? ($activeFarm?->modelArea() ?? (is_numeric($profile?->farm_area) && (float) $profile->farm_area > 0 ? (float) $profile->farm_area : null))
            : null;

        $association = null;
        if ($activeFarm !== null) {
            if ($activeFarm->associationState() === Farm::ASSOCIATION_MEMBER && $activeFarm->organization) {
                $association = $activeFarm->organization->name;
            } elseif ($activeFarm->association_status === Farm::ASSOCIATION_NOT_MEMBER) {
                $association = 'Not a member';
            }
        }

        $schedule = self::resolveSchedule($user, $activeFarm);
        $variety = $schedule?->riceVariety?->variety_name;
        $plantingDate = $schedule?->planting_date;

        $expectedHarvestDate = null;
        $harvestWindowStart = null;
        $harvestWindowEnd = null;
        $maturityDays = null;
        $cropStage = null;

        if ($schedule !== null && $plantingDate !== null) {
            $estimate = app(VarietyHarvestEstimator::class)->estimate($schedule);
            if ($estimate !== null) {
                $expectedHarvestDate = $estimate['expected_harvest_date'];
                $harvestWindowStart = $estimate['window_start'];
                $harvestWindowEnd = $estimate['window_end'];
                $maturityDays = $estimate['maturity_days_used'];
            } else {
                $maturityDays = (int) config('farmer_dashboard.crop.default_duration_days', 115);
                $expectedHarvestDate = $schedule->scheduled_harvest_date
                    ?? $plantingDate->copy()->addDays($maturityDays);
            }

            if ($schedule->scheduled_harvest_date !== null && $schedule->harvest_date_manual) {
                $expectedHarvestDate = $schedule->scheduled_harvest_date;
            }

            $cropStage = self::computeCropStage($plantingDate, $maturityDays);
        }

        return new self(
            user: $user,
            isFarmer: true,
            hasFarm: $hasFarm,
            activeFarm: $activeFarm,
            farms: $farms,
            barangay: $barangay,
            farmType: $farmType,
            farmArea: $farmArea,
            variety: $variety,
            plantingDate: $plantingDate,
            association: $association,
            cropStage: $cropStage,
            expectedHarvestDate: $expectedHarvestDate,
            harvestWindowStart: $harvestWindowStart,
            harvestWindowEnd: $harvestWindowEnd,
            maturityDays: $maturityDays,
            schedule: $schedule,
            incompleteProfile: $incompleteProfile,
            overriddenBy: $overriddenBy,
        );
    }

    public static function cleanBarangay(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '' || $trimmed === '—' || $trimmed === '-') {
            return null;
        }

        return LianBarangays::canonicalName($trimmed);
    }

    /**
     * Map planting date + maturity duration to seedling / tillering / flowering / harvest.
     */
    public static function computeCropStage(CarbonInterface $plantingDate, int $durationDays): string
    {
        $today = BusinessClock::today()->startOfDay();
        $planting = $plantingDate->toImmutable()->startOfDay();
        $days = (int) $planting->diffInDays($today, false);

        if ($days < 0) {
            return 'seedling';
        }

        $crop = (array) config('farmer_dashboard.crop');
        $plantingDays = (int) ($crop['planting_days'] ?? 21);
        $growingEnd = (int) round($durationDays * (float) ($crop['growing_until'] ?? 0.55));
        $floweringEnd = (int) round($durationDays * (float) ($crop['flowering_until'] ?? 0.78));

        return match (true) {
            $days < $plantingDays => 'seedling',
            $days < $growingEnd => 'tillering',
            $days < $floweringEnd => 'flowering',
            default => 'harvest',
        };
    }

    public function stageLabel(bool $tagalog): string
    {
        if ($this->cropStage === null) {
            return $tagalog ? 'hindi pa nakatala' : 'not recorded';
        }

        return match ($this->cropStage) {
            'seedling' => $tagalog ? 'punla (seedling)' : 'seedling',
            'tillering' => $tagalog ? 'pagsusuwi (tillering)' : 'tillering',
            'flowering' => $tagalog ? 'pamumulaklak (flowering)' : 'flowering',
            'harvest' => $tagalog ? 'pag-ani (harvest)' : 'harvest',
            default => $this->cropStage,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_farmer' => $this->isFarmer,
            'has_farm' => $this->hasFarm,
            'incomplete_profile' => $this->incompleteProfile,
            'farm_id' => $this->activeFarm?->id,
            'farm_name' => $this->activeFarm?->farm_name,
            'barangay' => $this->barangay,
            'farm_type' => $this->farmType,
            'farm_area_hectares' => $this->farmArea,
            'variety' => $this->variety,
            'planting_date' => $this->plantingDate?->toDateString(),
            'association' => $this->association,
            'crop_stage' => $this->cropStage,
            'expected_harvest_date' => $this->expectedHarvestDate?->toDateString(),
            'harvest_window_start' => $this->harvestWindowStart?->toDateString(),
            'harvest_window_end' => $this->harvestWindowEnd?->toDateString(),
            'overridden_by' => $this->overriddenBy,
        ];
    }

    private static function resolveSchedule(User $user, ?Farm $farm): ?FarmerPlantingSchedule
    {
        if (! SchemaCache::hasTable('farmer_planting_schedules')) {
            return null;
        }

        $query = FarmerPlantingSchedule::query()
            ->where('user_id', $user->id)
            ->when($farm !== null && SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id'), function ($q) use ($farm): void {
                $q->where('farm_id', $farm->id);
            });

        if (SchemaCache::hasTable('rice_varieties') && SchemaCache::hasColumn('farmer_planting_schedules', 'rice_variety_id')) {
            $query->with('riceVariety');
        }

        $active = (clone $query)
            ->where(function ($q): void {
                $q->whereNull('progress_status')->orWhere('progress_status', '!=', 'Harvested');
            })
            ->orderByDesc('planting_date')
            ->orderByDesc('id')
            ->first();

        return $active ?? $query->orderByDesc('planting_date')->orderByDesc('id')->first();
    }
}
