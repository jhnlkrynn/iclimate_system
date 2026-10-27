<?php

namespace Database\Factories;

use App\Models\RiceVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiceVariety> */
class RiceVarietyFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->numberBetween(2010, 2026);
        $number = fake()->unique()->numberBetween(100, 999);

        return [
            'import_key' => 'nsic '.$year.' rc '.$number,
            'source_no' => $number,
            'year_approved' => $year,
            'nsic_reg_no' => 'NSIC '.$year.' Rc '.$number,
            'variety_name' => 'Test Rice '.$number,
            'line_name' => 'PR'.$number.'-1-2',
            'variety_type' => 'Inbred',
            'owner_breeder' => 'PhilRice',
            'yield_tpr_ds' => 5200, 'yield_tpr_ws' => 4600,
            'maturity_tpr_ds' => 112, 'maturity_tpr_ds_min' => 108, 'maturity_tpr_ds_max' => 116,
            'maturity_tpr_ws' => 110, 'maturity_tpr_ws_min' => 106, 'maturity_tpr_ws_max' => 114,
            'height_tpr_ds' => 98, 'height_tpr_ws' => 104,
            'tillers_tpr_ds' => 14, 'tillers_tpr_ws' => 13,
            'culm_strength' => 'Strong',
            'reactions' => ['pests' => ['glh' => ['raw' => 'I', 'entries' => [['label' => null, 'code' => 'I', 'note' => null]]]], 'diseases' => []],
            'amylose_content' => 21.4,
            'adaptation' => 'National recommendation in irrigated lowland areas.',
            'farm_irrigated' => true,
            'farm_rainfed' => false,
            'remarks' => 'Intermediate amylose content. Good milling recovery (70.1%). Long and slender grain.',
            'is_active' => true,
            'source' => RiceVariety::SOURCE_IMPORT,
        ];
    }

    /** The NSIC Rc 480 (GSR 8) row of the NSIC list: wet season transplanted only, plus a seed-production range. */
    public function rc480(): static
    {
        return $this->state(fn (): array => [
            'import_key' => 'nsic 2016 rc 480', 'source_no' => 350, 'year_approved' => 2016, 'nsic_reg_no' => 'NSIC 2016 Rc 480',
            'variety_name' => 'GSR 8', 'line_name' => 'GSR IR1-8-S6-S3-Y2', 'owner_breeder' => 'IRRI',
            'yield_tpr_ds' => null, 'yield_tpr_ws' => 3224,
            'maturity_tpr_ds' => null, 'maturity_tpr_ds_min' => null, 'maturity_tpr_ds_max' => null,
            'maturity_tpr_ws' => 107, 'maturity_tpr_ws_min' => null, 'maturity_tpr_ws_max' => null,
            'maturity_seed_production' => 117, 'maturity_seed_production_min' => 115, 'maturity_seed_production_max' => 118,
            'height_tpr_ds' => null, 'height_tpr_ws' => 96, 'tillers_tpr_ds' => null, 'tillers_tpr_ws' => 94,
            'amylose_content' => 15.6,
            'adaptation' => 'National recommendation in rainfed lowland and saline prone areas (dual adaptation).',
            'farm_irrigated' => false, 'farm_rainfed' => true,
        ]);
    }

    /** One of the 1955-1975 rows: no trial figures at all. */
    public function withoutMaturity(): static
    {
        return $this->state(fn (): array => [
            'nsic_reg_no' => 'Philippine Seed Board', 'variety_type' => null,
            'maturity_tpr_ds' => null, 'maturity_tpr_ds_min' => null, 'maturity_tpr_ds_max' => null,
            'maturity_tpr_ws' => null, 'maturity_tpr_ws_min' => null, 'maturity_tpr_ws_max' => null,
            'yield_tpr_ds' => null, 'yield_tpr_ws' => null,
            'adaptation' => 'Early Lowland', 'farm_irrigated' => false, 'farm_rainfed' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
