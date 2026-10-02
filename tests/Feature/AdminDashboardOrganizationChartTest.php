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

    public function test_admin_dashboard_no_longer_has_a_farmer_database_section(): void
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

        // The farmer database (area, average size, organizations) belongs on the MAO dashboard, not here.
        $response->assertOk()
            ->assertDontSee('Farmer Database')
            ->assertDontSee('Registered Farm Area')
            ->assertDontSee('Average Farm Size')
            ->assertDontSee('Farmers by Organization')
            ->assertDontSee('"counts":[1,1,0,0,0', false);

        // What IT does see is whether the records are complete: here the one farmer without an organization.
        $response->assertViewHas('it', fn (array $it) => $it['integrity']['without_org'] === 1
            && $it['integrity']['farmer_profiles'] === 3);
    }

    public function test_registering_a_farmer_updates_the_integrity_counts(): void
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
            ->assertViewHas('it', fn (array $it) => $it['integrity']['farmer_profiles'] === 1
                && $it['integrity']['farmer_accounts'] === 1
                && $it['integrity']['without_org'] === 0);
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
