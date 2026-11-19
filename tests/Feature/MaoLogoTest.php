<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The current shared shell uses the municipal MAO/Lian seal asset on public and role pages. */
class MaoLogoTest extends TestCase
{
    use RefreshDatabase;

    private function assertMaoLogo(string $html, string $page): void
    {
        $this->assertStringContainsString('images/mao-lian-seal.png', $html, "{$page} does not show the MAO logo");
        $this->assertStringContainsString('alt="Municipal Agriculture Office logo"', $html, "{$page}: MAO logo has no alt text");
        $this->assertStringNotContainsString('mao-logo.png', $html, "{$page} uses the removed logo asset");
    }

    public function test_the_logo_file_is_a_square_transparent_png(): void
    {
        $path = public_path('images/mao-logo.png');
        $this->assertFileExists($path);
        [$width, $height, $type] = getimagesize($path);
        $this->assertSame([512, 512, IMAGETYPE_PNG], [$width, $height, $type]);

        if (! extension_loaded('gd')) {
            $this->markTestIncomplete('GD is needed to check the transparent background.');
        }
        $corner = imagecolorat(imagecreatefrompng($path), 0, 0);
        $this->assertSame(127, ($corner >> 24) & 0x7F, 'the corner of the logo is fully transparent');
    }

    public function test_public_pages_show_the_mao_logo_instead_of_the_seal(): void
    {
        // /login and /register only open the landing card (/#login, /#register).
        $this->get(route('login'))->assertRedirect(url('/').'#login');
        $this->get(route('register'))->assertRedirect(url('/').'#register');
        $this->assertMaoLogo($this->get('/')->assertOk()->getContent(), '/');
    }

    public function test_each_role_dashboard_shows_the_mao_logo_instead_of_the_seal(): void
    {
        foreach ([User::ROLE_FARMER => 'farmer.dashboard', User::ROLE_MAO => 'mao.dashboard'] as $role => $dashboard) {
            $html = $this->actingAs(User::factory()->create(['role' => $role]))->get(route($dashboard))->assertOk()->getContent();
            $this->assertMaoLogo($html, $dashboard);
            $this->assertStringContainsString('class="gov-mao-logo" src="'.asset('images/mao-lian-seal.png').'" alt="Municipal Agriculture Office logo" width="46" height="46"', $html);
        }
    }
}
