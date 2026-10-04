<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItPagesDesignTest extends TestCase
{
    use RefreshDatabase;

    /** Route name => Georgia page title of the IT page. */
    private const PAGES = [
        'profile.edit' => 'Account',
        'weather-predictions.index' => 'Predictions',
        'system-health.index' => 'System Health',
        'community-feed.index' => 'Community Feed',
        'messages.index' => 'Messages',
    ];

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
    }

    public function test_it_pages_share_the_it_dashboard_design_system(): void
    {
        $it = $this->it();

        foreach (self::PAGES as $route => $title) {
            $this->actingAs($it)->get(route($route))
                ->assertOk()
                ->assertSee('data-user-role="IT Expert"', false)
                ->assertSee('mfd itd itp', false)
                ->assertSee('class="mfd-header"', false)
                ->assertSee('<h1>'.$title.'</h1>', false);
        }
    }

    public function test_it_dashboard_carries_the_role_attribute_the_it_styles_use(): void
    {
        $this->actingAs($this->it())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-user-role="IT Expert"', false)
            ->assertSee('is-it-dashboard', false);
    }

    public function test_account_page_shows_the_account_summary_and_keeps_every_form(): void
    {
        $it = $this->it();

        $this->actingAs($it)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Account Summary')
            ->assertSee($it->email)
            ->assertSee(User::ROLE_IT_EXPERT)
            ->assertSee('Profile Information')
            ->assertSee('Update Password')
            ->assertSee('Delete Account')
            ->assertSee('action="'.route('profile.update').'"', false)
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertSee('action="'.route('profile.destroy').'"', false);
    }

    public function test_system_health_page_summarises_the_checks(): void
    {
        $this->actingAs($this->it())->get(route('system-health.index'))
            ->assertOk()
            ->assertSee('Overall Status')
            ->assertSee('Service Status')
            ->assertSee('Total Checks')
            ->assertSee(route('model-registry.index'), false)
            ->assertSee(route('system-health.index', ['refresh' => 1]), false);
    }

    public function test_other_roles_keep_their_own_page_design(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->farmer()->create();

        foreach (['profile.edit', 'weather-predictions.index', 'community-feed.index', 'messages.index'] as $route) {
            foreach ([$mao, $farmer] as $user) {
                $this->actingAs($user)->get(route($route))
                    ->assertOk()
                    ->assertDontSee('data-user-role="IT Expert"', false)
                    ->assertDontSee('mfd itd itp', false);
            }
        }
    }
}
