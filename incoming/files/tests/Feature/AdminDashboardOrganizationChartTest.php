<?php

namespace Tests\Feature;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Security\CaptchaService;
use Database\Seeders\FarmerOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardOrganizationChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_a_compact_dynamic_farmer_database_summary_without_the_chart(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);

        $admin = User::factory()->itExpert()->create();
        $balibago = FarmerOrganization::query()->where('name', 'Balibago Farmers Association')->firstOrFail();
        $cuaco = FarmerOrganization::query()->where('name', 'Cuaco Irrigator\'s Association')->firstOrFail();

        FarmerProfile::factory()->create([
            'farmer_organization_id' => $balibago->id,
            'barangay' => $balibago->barangay,
            'farm_area' => 1.25,
            'farm_type' => $balibago->farm_type,
        ]);
        FarmerProfile::factory()->create([
            'farmer_organization_id' => $cuaco->id,
            'barangay' => $cuaco->barangay,
            'farm_area' => 2.75,
            'farm_type' => $cuaco->farm_type,
        ]);
        FarmerProfile::factory()->create([
            'farmer_organization_id' => null,
            'barangay' => 'Prenza',
            'farm_area' => 3.50,
            'farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Farmer Database')
            ->assertSee('Total Farmers')
            ->assertSee('Without Organization')
            ->assertSee('Registered Farm Area')
            ->assertSee('7.50')
            ->assertSee('View Farmer Analytics')
            ->assertSee(route('farmer-profiles.index'), false)
            // The detailed 16-organization chart no longer lives on the IT dashboard.
            ->assertDontSee('Farmers by Organization')
            ->assertDontSee('"counts":[1,1,0,0,0', false);

        $response->assertViewHas('it', function (array $it) {
            $f = $it['farmers'];

            return $f['total_farmers'] === 3
                && $f['organizations'] === FarmerOrganization::query()->count()
                && $f['without_organization'] === 1
                && abs($f['farm_area'] - 7.5) < 0.001
                && abs($f['average_area'] - 2.5) < 0.001;
        });
    }

    public function test_registering_a_farmer_updates_the_admin_farmer_database_summary(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);

        $admin = User::factory()->itExpert()->create();
        $organization = FarmerOrganization::query()
            ->where('name', 'Cuaco Irrigator\'s Association')
            ->firstOrFail();

        $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Organization Chart Farmer',
            'email' => 'organization-chart-farmer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'farmer_organization_id' => $organization->id,
            'captcha_answer' => '7',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('farmer_profiles', [
            'farmer_organization_id' => $organization->id,
            'barangay' => 'Binubusan',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('it', fn (array $it) => $it['farmers']['total_farmers'] === 1 && $it['farmers']['without_organization'] === 0);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function captchaSession(string $context, string $answer): array
    {
        return [
            CaptchaService::SESSION_KEY => [
                'context' => $context,
                'question' => 'What is 3 + 4?',
                'answer_hash' => Hash::make($answer),
                'created_at' => now()->timestamp,
            ],
        ];
    }
}
