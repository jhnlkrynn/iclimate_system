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

    public function test_admin_dashboard_shows_dynamic_farmer_organization_summary_and_chart_data(): void
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
            ->assertSee('Farmers by Organization')
            ->assertSee('Total Farmers')
            ->assertSee('Total Organizations')
            ->assertSee('Farmers Without Organization')
            ->assertSee('Total Farm Area')
            ->assertSee('7.50 ha')
            ->assertSee('"counts":[1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1]', false);
    }

    public function test_registering_a_farmer_updates_the_admin_organization_chart_count(): void
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
            ->assertSee('"counts":[0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]', false);
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
