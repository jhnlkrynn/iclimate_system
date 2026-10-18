<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where Log out is: farmers at the bottom of the menu drawer (layouts/sidebar.blade.php), MAO and IT in the account
 * menu (layouts/navigation.blade.php). Both are the same POST form (layouts/partials/logout-form.blade.php).
 */
class LogoutPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function page(User $user): string
    {
        return $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();
    }

    /** The markup between two strings of the page. */
    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, "Missing {$start}");

        return substr($html, $from, strpos($html, $end, $from) - $from);
    }

    private function drawer(string $html): string
    {
        return $this->between($html, 'id="mobileSidebar"', 'BatStateU ARASOF-Nasugbu');
    }

    private function accountMenu(string $html): string
    {
        return $this->between($html, 'ic-account-menu', '</nav>');
    }

    public function test_a_farmer_logs_out_from_the_bottom_of_the_drawer_not_the_account_menu(): void
    {
        $html = $this->page(User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE]));

        $foot = $this->between($this->drawer($html), 'class="ic-sb-foot"', 'class="sidebar-foot"');
        $this->assertStringContainsString('action="'.route('logout').'"', $foot);
        $this->assertStringContainsString('method="POST"', $foot);
        $this->assertStringContainsString('name="_token"', $foot);
        $this->assertStringContainsString('class="sidebar-link ic-sb-logout" type="submit"', $foot);
        $this->assertStringContainsString('<span class="sidebar-label">Log out</span>', $foot);
        $this->assertStringContainsString('data-logout-confirm', $foot);

        $menu = $this->accountMenu($html);
        $this->assertStringContainsString('Profile, photo and password', $menu);
        $this->assertStringNotContainsString(route('logout'), $menu);
        $this->assertStringNotContainsString('Log out', $menu);
    }

    public function test_the_farmer_drawer_label_follows_their_language(): void
    {
        $html = $this->page(User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE, 'preferred_language' => 'fil']));

        $this->assertStringContainsString('<span class="sidebar-label">Mag-log out</span>', $this->drawer($html));
    }

    public function test_mao_and_it_log_out_from_the_account_menu_not_the_drawer(): void
    {
        foreach ([User::factory()->maoPersonnel(), User::factory()->itExpert()] as $factory) {
            $html = $this->page($factory->create(['status' => User::STATUS_ACTIVE]));

            $menu = $this->accountMenu($html);
            $this->assertStringContainsString('action="'.route('logout').'"', $menu);
            $this->assertStringContainsString('class="dropdown-item ic-account-logout" type="submit"', $menu);
            $this->assertStringNotContainsString(route('logout'), $this->drawer($html));
            $this->assertStringNotContainsString('ic-sb-logout', $this->drawer($html));
        }
    }

    public function test_the_drawer_form_logs_a_farmer_out(): void
    {
        $farmer = User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE]);

        $this->actingAs($farmer)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }
}
