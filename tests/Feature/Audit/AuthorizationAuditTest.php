<?php

namespace Tests\Feature\Audit;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit 2026-11-22: role access and other people's data.
 *   - a farmer cannot open, change, map, remove, restore or make primary another farmer's farm;
 *   - every page that is only for MAO staff refuses a farmer, and every farmer-only page refuses MAO staff;
 *   - signed-out visitors are sent to the login page.
 */
class AuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();                                       // no page in this sweep may reach an outside service
    }

    private function farmOf(User $owner, array $extra = []): Farm
    {
        return Farm::query()->create($extra + ['user_id' => $owner->id, 'farm_name' => 'Owner Farm', 'barangay' => 'Prenza',
            'farm_size_hectares' => 1.5, 'farm_size_method' => Farm::SIZE_EXACT, 'farm_type' => 'Irrigated', 'is_primary' => true]);
    }

    private function square(): array
    {
        return [['lat' => 14.0402, 'lng' => 120.6522], ['lat' => 14.0402, 'lng' => 120.6531], ['lat' => 14.0411, 'lng' => 120.6531], ['lat' => 14.0411, 'lng' => 120.6522]];
    }

    public function test_a_farmer_cannot_touch_another_farmers_farm(): void
    {
        $owner = User::factory()->farmer()->create();
        $intruder = User::factory()->farmer()->create();
        $farm = $this->farmOf($owner);
        $this->farmOf($intruder, ['farm_name' => 'My Own Farm']);
        $as = $this->actingAs($intruder);

        $as->get(route('farmer.farms.edit', $farm))->assertNotFound();
        $as->get(route('farmer.farms.map', $farm))->assertNotFound();
        $as->put(route('farmer.farms.update', $farm), ['farm_name' => 'Taken', 'barangay' => 'Prenza', 'farm_type' => 'Rainfed'])->assertNotFound();
        $as->put(route('farmer.farms.map.update', $farm), ['boundary_coordinates' => $this->square()])->assertNotFound();
        $as->delete(route('farmer.farms.map.destroy', $farm))->assertNotFound();
        $as->post(route('farmer.farms.primary', $farm))->assertNotFound();
        $as->delete(route('farmer.farms.destroy', $farm))->assertNotFound();
        $as->post(route('farmer.farms.select'), ['farm_id' => $farm->id])->assertSessionHasErrors();

        $farm->refresh();
        $this->assertSame('Owner Farm', $farm->farm_name);
        $this->assertNull($farm->archived_at ?? null);
        $this->assertFalse($farm->boundary()->exists());
    }

    public function test_a_farmer_cannot_restore_another_farmers_archived_farm(): void
    {
        $owner = User::factory()->farmer()->create();
        $intruder = User::factory()->farmer()->create();
        $farm = $this->farmOf($owner, ['archived_at' => now()]);

        $this->actingAs($intruder)->post(route('farmer.farms.restore', $farm))->assertNotFound();
        $this->assertNotNull($farm->fresh()->archived_at);
    }

    public function test_another_farmers_farm_never_appears_on_my_pages(): void
    {
        $owner = User::factory()->farmer()->create();
        $intruder = User::factory()->farmer()->create();
        $this->farmOf($owner, ['farm_name' => 'Secret Owner Farm']);
        $this->farmOf($intruder, ['farm_name' => 'Intruder Farm']);

        foreach (['farmer.dashboard', 'farmer.my-farm'] as $page) {
            $html = $this->actingAs($intruder)->get(route($page))->assertOk()->getContent();
            $this->assertStringNotContainsString('Secret Owner Farm', $html, $page);
        }
    }

    /** @return array<int, array{uri: string, roles: list<string>}> GET routes without parameters and with a role check. */
    private function roleRoutes(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
                continue;
            }
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                    $out[] = ['uri' => '/'.ltrim($route->uri(), '/'), 'roles' => explode(',', substr($middleware, 5))];
                }
            }
        }

        return $out;
    }

    private function assertRefused(string $uri, User $user): void
    {
        $response = $this->actingAs($user)->get($uri);
        $status = $response->getStatusCode();
        // Refused = an error status, or a redirect elsewhere (RedirectFarmers sends a farmer to their own page with 303).
        $this->assertTrue(in_array($status, [301, 302, 303, 307, 401, 403, 404], true),
            "{$user->role} opened {$uri}: HTTP {$status}");
        if ($response->isRedirect()) {
            $this->assertStringNotContainsString($uri, (string) $response->headers->get('Location'), "{$uri} redirected to itself");
        }
    }

    public function test_every_mao_only_page_refuses_a_farmer(): void
    {
        $farmer = User::factory()->farmer()->create();
        $checked = 0;
        foreach ($this->roleRoutes() as $route) {
            if (! in_array(User::ROLE_FARMER, $route['roles'], true)) {
                $this->assertRefused($route['uri'], $farmer);
                $checked++;
            }
        }
        $this->assertGreaterThan(10, $checked, 'MAO-only pages found');
    }

    public function test_every_farmer_only_page_refuses_mao_staff(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $checked = 0;
        foreach ($this->roleRoutes() as $route) {
            if (! in_array(User::ROLE_MAO, $route['roles'], true)) {
                $this->assertRefused($route['uri'], $mao);
                $checked++;
            }
        }
        $this->assertGreaterThan(5, $checked, 'farmer-only pages found');
    }

    public function test_signed_out_visitors_are_sent_to_login_from_every_role_page(): void
    {
        foreach ($this->roleRoutes() as $route) {
            $response = $this->get($route['uri']);
            $this->assertTrue($response->isRedirect() || in_array($response->getStatusCode(), [401, 403], true), $route['uri']);
        }
    }
}
