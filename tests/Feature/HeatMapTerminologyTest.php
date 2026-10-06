<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The map feature is called "Heat Map" everywhere a user can see it. "Risk Map" is never shown (page text, buttons,
 * headings, aria-labels, JS-rendered modals, translations). Words for the calculated result — "Risk", "Risk Level",
 * "Farm Risk", "Risk Assessment" — stay. Internal names (BarangayRiskMapService, /api/lian/risk-map, risk-map CSS
 * classes) are not user-facing and may keep "risk-map".
 */
class HeatMapTerminologyTest extends TestCase
{
    use RefreshDatabase;

    /** "Risk Map" as words (any case); hyphenated internal names such as risk-map or riskMap do not match. */
    private const RISK_MAP = '/\brisk\s+map\b/i';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        config(['iclimate.python_binary' => '/nonexistent/python']);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'barangay' => $role === User::ROLE_FARMER ? 'Binubusan' : null]);
        if ($role === User::ROLE_FARMER) {
            FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Binubusan']);
        }

        return $user;
    }

    public function test_no_page_shows_risk_map_and_every_map_page_says_heat_map(): void
    {
        $pages = [
            User::ROLE_FARMER => ['/farmer/dashboard', '/heat-map', '/planting-advisories', '/notifications'],
            User::ROLE_MAO => ['/mao/dashboard', '/mao/operations', '/heat-map', '/planting-advisories', '/notifications'],
            User::ROLE_IT_EXPERT => ['/admin/dashboard', '/heat-map', '/notifications'],
        ];

        $problems = [];
        foreach ($pages as $role => $paths) {
            $user = $this->user($role);
            foreach ($paths as $path) {
                $response = $this->actingAs($user)->get($path);
                $this->assertSame(200, $response->getStatusCode(), "{$role} {$path}");
                // Raw HTML, so attributes such as aria-label and title are checked along with the visible text.
                $html = html_entity_decode((string) $response->getContent());
                if (preg_match_all(self::RISK_MAP, $html, $matches)) {
                    $problems[] = "{$role} {$path}: ".implode(', ', array_unique($matches[0]));
                }
            }
        }
        $this->assertSame([], $problems, 'Pages still showing "Risk Map"');
    }

    public function test_heat_map_page_names_the_feature_heat_map_and_keeps_risk_labels(): void
    {
        $this->actingAs($this->user(User::ROLE_MAO))->get('/heat-map')
            ->assertOk()
            ->assertSee('Barangay Heat Map')
            ->assertSee('Lian Municipality Heat Map')
            ->assertSee('Barangay Risk Assessment');

        $this->actingAs($this->user(User::ROLE_MAO))->get('/mao/operations')
            ->assertOk()
            ->assertSee('Heat Map &amp; Forecast', false);
    }

    public function test_views_scripts_and_translations_never_say_risk_map(): void
    {
        $files = Finder::create()->files()
            ->in([resource_path('views'), resource_path('js'), lang_path()])
            ->name(['*.php', '*.js']);

        $found = [];
        foreach ($files as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $i => $line) {
                if (preg_match(self::RISK_MAP, $line)) {
                    $found[] = $file->getRelativePathname().':'.($i + 1);
                }
            }
        }
        $this->assertSame([], $found, '"Risk Map" in user-facing source');
    }

    public function test_ai_insight_and_overview_buttons_say_view_on_heat_map(): void
    {
        $this->assertStringContainsString('>View on Heat Map</button>', file_get_contents(resource_path('js/barangay-insight.js')));
        $this->assertStringContainsString('>View on Heat Map</button>', file_get_contents(resource_path('js/risk-overview.js')));
    }

    public function test_risk_result_labels_are_kept_in_both_languages(): void
    {
        foreach (['en', 'fil'] as $locale) {
            $text = json_encode(require lang_path("{$locale}/farmer.php"), JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString('Heat Map', $text, $locale);
            $this->assertDoesNotMatchRegularExpression(self::RISK_MAP, $text, $locale);
        }
        // The calculated result keeps its "Risk" wording.
        $this->assertSame('Barangay Farm Risk', __('farmer.today.risk', [], 'en'));
        $this->assertSame('Panganib sa Barangay', __('farmer.today.risk', [], 'fil'));
    }
}
