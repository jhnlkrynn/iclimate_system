<?php

namespace Tests\Feature;

use App\Events\WeatherFetched;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Weather\FarmerDashboardWeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The farmer's weather "Updated" time follows every successful fetch, also one that returned the same values
 * (before 2026-11-12 it then stayed at the older fetch until the 5-minute poll).
 */
class WeatherFetchTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_event_carries_only_the_fetch_time_in_utc_for_farmer_and_mao_dashboards(): void
    {
        Event::fake([WeatherFetched::class]);

        WeatherFetched::announce('current', CarbonImmutable::parse('2026-11-12 08:15:30', 'Asia/Manila'));
        WeatherFetched::announce('current', null);            // nothing fetched: nothing announced
        WeatherFetched::announce('current', 'not a time');

        Event::assertDispatchedTimes(WeatherFetched::class, 1);
        Event::assertDispatched(WeatherFetched::class, function (WeatherFetched $event): bool {
            $this->assertSame(['kind' => 'current', 'fetched_at' => '2026-11-12T00:15:30+00:00'], $event->broadcastWith());
            $this->assertSame('weather.fetched', $event->broadcastAs());
            $this->assertSame(['private-dashboard.farmer', 'private-dashboard.mao'], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));

            return true;
        });
    }

    public function test_a_prepared_home_shows_the_latest_fetch_time_but_never_an_older_or_future_one(): void
    {
        $built = CarbonImmutable::now('Asia/Manila')->subMinutes(9);
        $payload = ['weather' => ['state' => 'ok', 'temperature' => 29.0, 'updated_at' => $built, 'stale' => false], 'risk' => ['state' => 'nodata']];
        $with = function (?CarbonImmutable $fetched) use ($payload): array {
            $weather = $this->mock(FarmerDashboardWeatherService::class);
            $weather->shouldReceive('lastFetchedAt')->andReturn($fetched);
            $this->app->forgetInstance(FarmerDashboardService::class);

            return $this->app->make(FarmerDashboardService::class)->withLatestFetchTimes($payload);
        };

        $newer = CarbonImmutable::now('UTC')->subMinutes(3);
        $out = $with($newer);
        $this->assertTrue($out['weather']['updated_at']->equalTo($newer), 'a later successful fetch moves the time');
        $this->assertSame(29.0, $out['weather']['temperature'], 'only the time moves');

        $this->assertTrue($with($built->subHour())['weather']['updated_at']->equalTo($built), 'never backwards');
        $this->assertTrue($with(CarbonImmutable::now()->addHours(8))['weather']['updated_at']->equalTo($built), 'never a future time');
        $this->assertTrue($with(null)['weather']['updated_at']->equalTo($built));
    }
}
