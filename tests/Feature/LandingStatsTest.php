<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\RiceVariety;
use App\Models\User;
use App\Support\LandingStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The landing page's numbers band: real counts, cached for the day. */
class LandingStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_approved_farmers_active_farms_their_barangays_and_active_varieties(): void
    {
        $farmer = User::factory()->farmer()->create();
        $other = User::factory()->farmer()->create();
        User::factory()->farmer()->create(['status' => User::STATUS_PENDING]);
        User::factory()->farmer()->create(['status' => User::STATUS_DELETED]);
        User::factory()->maoPersonnel()->create();

        Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North', 'barangay' => 'Prenza', 'is_primary' => true]);
        Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'South', 'barangay' => 'PRENZA']);   // same barangay, other spelling
        Farm::query()->create(['user_id' => $other->id, 'farm_name' => 'Home', 'barangay' => 'Kapito', 'is_primary' => true]);
        Farm::query()->create(['user_id' => $other->id, 'farm_name' => 'Old', 'barangay' => 'Luyahan', 'archived_at' => now()]);

        RiceVariety::factory()->count(3)->create();
        RiceVariety::factory()->create(['is_active' => false]);

        $this->assertSame(['farmers' => 2, 'farms' => 3, 'barangays' => 2, 'varieties' => 3], LandingStats::count());
    }

    public function test_the_counts_are_cached_for_the_day_and_shown_on_the_landing_page(): void
    {
        User::factory()->farmer()->create();
        Cache::forget(LandingStats::CACHE_KEY);

        $this->assertSame(1, LandingStats::get()['farmers']);
        User::factory()->farmer()->create();
        $this->assertSame(1, LandingStats::get()['farmers'], 'a new account shows the next day');

        $this->get('/')->assertOk()
            ->assertSee('id="numbers"', false)
            ->assertSee('data-stat="farmers"', false)
            ->assertSeeInOrder(['Registered farmers', 'Farms on record', 'Barangays with farms', 'Rice varieties listed']);
    }
}
