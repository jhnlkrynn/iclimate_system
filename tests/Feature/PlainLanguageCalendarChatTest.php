<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlainLanguageCalendarChatTest extends TestCase
{
    use RefreshDatabase;

    private function visible(string $html): string
    {
        return preg_replace('#<(script|style)\b.*?</\1>#is', '', $html);
    }

    public function test_farmer_pages_have_no_technical_wording(): void
    {
        $farmer = User::factory()->farmer()->create();
        FarmerProfile::factory()->for($farmer)->create();
        foreach (['calendar.index', 'farmer.dashboard'] as $name) {
            if (! \Illuminate\Support\Facades\Route::has($name)) {
                continue;
            }
            $res = $this->actingAs($farmer)->get(route($name));
            $res->assertOk();
            $html = $this->visible($res->getContent());
            foreach (['Random Forest', 'Python', 'Open-Meteo', 'Groq', 'machine learning', '.pkl', 'Prediction confidence', 'Predict.py'] as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, $html, "$name leaks $term");
            }
            $this->assertStringNotContainsString('>Weather Prediction<', $html);
        }
    }

    public function test_candidate_page_is_it_expert_only(): void
    {
        $farmer = User::factory()->farmer()->create();
        $this->actingAs($farmer)->get('/testing/candidate-rice-yield')->assertForbidden();
    }
}
