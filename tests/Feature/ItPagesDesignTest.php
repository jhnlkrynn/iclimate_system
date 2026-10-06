<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff pages (IT Personnel and MAO Personnel) share one design system: the .mfd / .itd parts of the IT dashboard
 * plus resources/css/it-pages.css. Farmer pages keep the farmer design system.
 */
class ItPagesDesignTest extends TestCase
{
    use RefreshDatabase;

    /** Route name => Georgia page title of the staff page. */
    private const PAGES = [
        'profile.edit' => 'Account',
        'weather-predictions.index' => 'Predictions',
        'community-feed.index' => 'Community Feed',
        'messages.index' => 'Messages',
    ];

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO]);
    }

    public function test_it_pages_share_the_it_dashboard_design_system(): void
    {
        $it = $this->it();

        foreach (self::PAGES + ['system-health.index' => 'System Health'] as $route => $title) {
            $this->actingAs($it)->get(route($route))
                ->assertOk()
                ->assertSee('data-user-role="IT Expert"', false)
                ->assertSee('mfd itd itp', false)
                ->assertSee('class="mfd-header"', false)
                ->assertSee('<h1>'.$title.'</h1>', false);
        }
    }

    public function test_mao_pages_share_the_same_design_system(): void
    {
        $mao = $this->mao();

        foreach (self::PAGES as $route => $title) {
            $this->actingAs($mao)->get(route($route))
                ->assertOk()
                ->assertSee('data-user-role="MAO Personnel"', false)
                ->assertSee('mfd itd itp', false)
                ->assertSee('class="mfd-header"', false)
                ->assertSee('<h1>'.$title.'</h1>', false);
        }
    }

    public function test_staff_styles_are_scoped_to_the_it_and_mao_roles_only(): void
    {
        $css = file_get_contents(resource_path('css/it-pages.css'));

        $this->assertStringContainsString('[data-user-role="IT Expert"]', $css);
        $this->assertStringContainsString('[data-user-role="MAO Personnel"]', $css);
        $this->assertStringNotContainsString('[data-user-role="Farmer"]', $css);
        preg_match_all('/font-size: ([\d.]+)rem/', $css, $sizes);
        foreach ($sizes[1] as $rem) {
            $this->assertGreaterThanOrEqual(0.75, (float) $rem, 'no text under 12px');
        }
    }

    public function test_it_dashboard_carries_the_role_attribute_the_staff_styles_use(): void
    {
        $this->actingAs($this->it())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-user-role="IT Expert"', false)
            ->assertSee('is-it-dashboard', false);
    }

    public function test_account_page_shows_the_account_summary_and_keeps_every_form(): void
    {
        foreach ([$this->it(), $this->mao()] as $user) {
            $this->actingAs($user)->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('Account Summary')
                ->assertSee($user->email)
                ->assertSee($user->role)
                ->assertSee('Profile Information')
                ->assertSee('Update Password')
                ->assertSee('Delete Account')
                ->assertSee('action="'.route('profile.update').'"', false)
                ->assertSee('action="'.route('password.update').'"', false)
                ->assertSee('action="'.route('profile.destroy').'"', false);
        }
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

    public function test_farmers_keep_their_own_page_design(): void
    {
        $farmer = User::factory()->farmer()->create();

        // Community Feed is a staff page now (farmers read Announcements on the Advisories page).
        foreach (array_diff(array_keys(self::PAGES), ['community-feed.index']) as $route) {
            $this->actingAs($farmer)->get(route($route))
                ->assertOk()
                ->assertSee('data-user-role="Farmer"', false)
                ->assertDontSee('mfd itd itp', false)
                ->assertDontSee('class="mfd-header"', false);
        }
    }
}
