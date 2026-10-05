<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_is_idempotent_and_does_not_create_demo_production_data(): void
    {
        $this->seed();
        $firstHeatmapCount = HeatmapArea::query()->count();

        $this->assertSame(count(LianBarangays::all()), $firstHeatmapCount);
        $this->assertSame(0, RiceProduction::query()->count());

        $this->seed();

        $this->assertSame($firstHeatmapCount, HeatmapArea::query()->count());
        $this->assertSame($firstHeatmapCount, HeatmapArea::query()->distinct('barangay')->count('barangay'));
        $this->assertSame(0, RiceProduction::query()->count());
    }
}
