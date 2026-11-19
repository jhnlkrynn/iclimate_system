<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\User;
use App\Support\ModuleAccess;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Tests\TestCase;

class Phase1AccountHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(SystemSettings::path());
        SystemSettings::flush();
        parent::tearDown();
    }

    public function test_must_change_blocks_all_http_methods_and_json_but_open_routes_remain_open(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO, 'must_change_password' => true]);
        $middleware = new EnsurePasswordChanged;
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $request = Request::create('/phase1-hardening', $method);
            $request->setUserResolver(fn () => $user);
            $request->setLaravelSession(app('session')->driver());
            $this->assertSame(302, $middleware->handle($request, fn () => response()->noContent())->getStatusCode());
        }
        $json = Request::create('/phase1-hardening', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $json->setUserResolver(fn () => $user);
        $json->setLaravelSession(app('session')->driver());
        $this->assertSame(409, $middleware->handle($json, fn () => response()->noContent())->getStatusCode());
    }



    public function test_default_password_command_is_dry_run_unless_apply_is_requested(): void
    {
        $hash = Hash::make('temporary-shared-password');
        $first = User::factory()->create(['password' => $hash, 'must_change_password' => false]);
        $second = User::factory()->create(['password' => $hash, 'must_change_password' => false]);
        Artisan::call('iclimate:flag-default-passwords');
        $this->assertFalse($first->fresh()->must_change_password);
        $this->assertFalse($second->fresh()->must_change_password);
        $this->assertStringNotContainsString($hash, Artisan::output());
        Artisan::call('iclimate:flag-default-passwords', ['--apply' => true]);
        $this->assertTrue($first->fresh()->must_change_password);
        $this->assertTrue($second->fresh()->must_change_password);
        $this->assertStringNotContainsString($hash, Artisan::output());
    }
}
