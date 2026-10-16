<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DatabaseQueryRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);
    }

    public function test_it_dashboard_query_count_does_not_grow_with_more_accounts(): void
    {
        $it = User::factory()->itExpert()->create();

        $small = $this->measure(fn () => $this->actingAs($it)->get('/admin/dashboard')->assertOk());

        User::factory()->farmer()->count(25)->create();
        Cache::flush();
        $large = $this->measure(fn () => $this->actingAs($it)->get('/admin/dashboard')->assertOk());

        $this->assertLessThanOrEqual(5, $large - $small, "IT Dashboard query count grew from {$small} to {$large}");
    }

    public function test_mao_and_farmer_dashboard_query_counts_do_not_grow_with_more_users(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();

        $maoSmall = $this->measure(fn () => $this->actingAs($mao)->get('/mao/dashboard')->assertOk());
        $farmerSmall = $this->measure(fn () => $this->actingAs($farmer)->get('/farmer/dashboard')->assertOk());

        User::factory()->farmer()->count(25)->create();
        Notification::factory()->count(25)->create(['user_id' => $farmer->id]);
        Cache::flush();

        $maoLarge = $this->measure(fn () => $this->actingAs($mao)->get('/mao/dashboard')->assertOk());
        $farmerLarge = $this->measure(fn () => $this->actingAs($farmer)->get('/farmer/dashboard')->assertOk());

        $this->assertLessThanOrEqual(5, $maoLarge - $maoSmall, "MAO Dashboard query count grew from {$maoSmall} to {$maoLarge}");
        $this->assertLessThanOrEqual(5, $farmerLarge - $farmerSmall, "Farmer Dashboard query count grew from {$farmerSmall} to {$farmerLarge}");
    }

    public function test_prepared_mao_operations_query_count_does_not_grow_with_more_users(): void
    {
        $small = $this->measure(fn () => app(DashboardController::class)->maoOperationsData(Request::create('/mao/operations')));

        User::factory()->farmer()->count(25)->create();
        Cache::flush();
        $large = $this->measure(fn () => app(DashboardController::class)->maoOperationsData(Request::create('/mao/operations')));

        $this->assertLessThanOrEqual(5, $large - $small, "Prepared MAO Operations query count grew from {$small} to {$large}");
    }

    private function measure(callable $work): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void { $count++; });

        try {
            $work();
        } finally {
            Event::forget(\Illuminate\Database\Events\QueryExecuted::class);
        }

        return $count;
    }
}
