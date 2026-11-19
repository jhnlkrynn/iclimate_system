<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\User;
use App\Services\AI\ClimateRiskReplies;
use App\Services\AI\GroqChatService;
use App\Services\Risk\BarangayRiskMapService;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression cover for the climate-risk response contract (ClimateRiskReplies):
 * a named barangay is answered for that barangay in both languages regardless of
 * the asker's own barangay, a missing or placeholder rating is always no_data with
 * the shared wording, and a genuine rating keeps its level.
 *
 * The asker's barangay is pinned to every value UserFactory can pick, because the
 * original failure only appeared when the random pick (Lumaniag) did not resolve.
 */
class ClimoraRiskContractTest extends TestCase
{
    use RefreshDatabase;

    private const ENGLISH_NO_DATA = 'No calculated farm-risk rating is available for this barangay yet.';

    private const TAGALOG_NO_DATA = 'Wala pang available na farm-risk rating para sa barangay na ito.';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.groq.enabled', false);
        $this->mock(BarangayRiskMapService::class, function ($mock): void {
            $mock->shouldReceive('detailFor')->andReturn(['risk_level' => 'High']);
        });
    }

    /** @return array<string, array{0: ?string}> */
    public static function askerBarangays(): array
    {
        return [
            'Binubusan' => ['Binubusan'],
            'Lumaniag (unsupported)' => ['Lumaniag'],
            'Malaruhatan' => ['Malaruhatan'],
            'Matabungkay' => ['Matabungkay'],
            'Prenza' => ['Prenza'],
            'no barangay' => [null],
        ];
    }

    #[DataProvider('askerBarangays')]
    public function test_missing_rating_is_no_data_in_both_languages_whatever_the_askers_barangay(?string $askerBarangay): void
    {
        HeatmapArea::query()->where('barangay', 'Cumba')->delete();
        $user = $this->farmer($askerBarangay);

        foreach ([
            'Is Cumba high risk right now?' => self::ENGLISH_NO_DATA,
            'What is the climate risk in Cumba?' => self::ENGLISH_NO_DATA,
            'Ano ang climate risk sa Cumba?' => self::TAGALOG_NO_DATA,
            'Mataas ba ang panganib sa Cumba ngayon?' => self::TAGALOG_NO_DATA,
        ] as $question => $expected) {
            $response = $this->ask($user, $question);

            $response->assertOk()->assertJsonPath('chat.prediction_result.status', 'no_data');
            $this->assertStringContainsString($expected, (string) $response->json('chat.answer'), $question);
            $this->assertNoInventedLevel((string) $response->json('chat.answer'), $question);
        }
    }

    #[DataProvider('askerBarangays')]
    public function test_placeholder_rows_are_never_treated_as_ratings_in_either_language(?string $askerBarangay): void
    {
        $user = $this->farmer($askerBarangay);

        foreach (['Bagong Pook', 'Barangay 1 (Pob.)', 'Cumba', 'Malaruhatan'] as $barangay) {
            $this->assertNotNull(HeatmapArea::query()->where('barangay', $barangay)->first(), $barangay.' seed row');

            foreach (["What is the climate risk in {$barangay}?" => self::ENGLISH_NO_DATA, "Ano ang climate risk sa {$barangay}?" => self::TAGALOG_NO_DATA] as $question => $expected) {
                $response = $this->ask($user, $question);

                $response->assertOk()->assertJsonPath('chat.prediction_result.status', 'no_data');
                $this->assertStringContainsString($expected, (string) $response->json('chat.answer'), $question);
                $this->assertNoInventedLevel((string) $response->json('chat.answer'), $question);
            }
        }
    }

    public function test_genuine_rating_keeps_its_level_in_both_languages(): void
    {
        $this->computeRating('Malaruhatan', 'High', 'Heat');
        $user = $this->farmer('Prenza');

        $english = $this->ask($user, 'What is the climate risk in Malaruhatan?');
        $english->assertOk()
            ->assertJsonPath('chat.prediction_result.status', 'current')
            ->assertJsonPath('chat.prediction_result.data.0.risk_level', 'High');
        $this->assertStringContainsString('Malaruhatan is marked High risk for Heat', (string) $english->json('chat.answer'));

        $tagalog = $this->ask($user, 'Ano ang climate risk sa Malaruhatan?');
        $tagalog->assertOk()
            ->assertJsonPath('chat.prediction_result.status', 'current')
            ->assertJsonPath('chat.prediction_result.data.0.risk_level', 'High');
        $this->assertStringContainsString('Ang Malaruhatan ay naka-markang High risk para sa Heat', (string) $tagalog->json('chat.answer'));
    }

    public function test_repeated_alternating_requests_keep_status_and_language(): void
    {
        HeatmapArea::query()->where('barangay', 'Cumba')->delete();
        $this->computeRating('Malaruhatan', 'High', 'Heat');
        $user = $this->farmer('Lumaniag');

        foreach (range(1, 3) as $round) {
            $tl = $this->ask($user, 'Ano ang climate risk sa Cumba?');
            $this->assertSame('no_data', $tl->json('chat.prediction_result.status'), "round {$round}");
            $this->assertStringContainsString(self::TAGALOG_NO_DATA, (string) $tl->json('chat.answer'), "round {$round}");

            $en = $this->ask($user, 'Is Cumba high risk right now?');
            $this->assertSame('no_data', $en->json('chat.prediction_result.status'), "round {$round}");
            $this->assertStringContainsString(self::ENGLISH_NO_DATA, (string) $en->json('chat.answer'), "round {$round}");

            $current = $this->ask($user, 'Is Malaruhatan high risk right now?');
            $this->assertSame('current', $current->json('chat.prediction_result.status'), "round {$round}");
            $this->assertSame('High', $current->json('chat.prediction_result.data.0.risk_level'), "round {$round}");
        }
    }

    public function test_lian_wide_question_without_ratings_uses_the_shared_no_data_wording(): void
    {
        // A barangay outside Lian (Nasugbu) does not resolve, so an unnamed question falls to the Lian-wide branch.
        // (Lumaniag was used here while only 9 barangays were supported; all 19 are supported since 2026-11-20.)
        $user = $this->farmer('Nasugbu');

        $english = $this->ask($user, 'What is the climate risk right now?');
        $english->assertOk()->assertJsonPath('chat.prediction_result.status', 'no_data');
        $this->assertSame(ClimateRiskReplies::noData('English', forBarangay: false), $english->json('chat.answer'));

        $tagalog = $this->ask($user, 'Ano ang climate risk ngayon?');
        $tagalog->assertOk()->assertJsonPath('chat.prediction_result.status', 'no_data');
        $this->assertSame(ClimateRiskReplies::noData('Tagalog', forBarangay: false), $tagalog->json('chat.answer'));
    }

    public function test_lian_wide_rating_is_attributed_to_its_barangay_not_the_askers_area(): void
    {
        $this->computeRating('Matabungkay', 'High', 'Typhoon');
        $user = $this->farmer('Nasugbu');

        $response = $this->ask($user, 'What is the climate risk right now?');

        $response->assertOk()->assertJsonPath('chat.prediction_result.status', 'current');
        $this->assertStringContainsString('Matabungkay has High weather risk for Typhoon', (string) $response->json('chat.answer'));
        $this->assertStringNotContainsString('Your area', (string) $response->json('chat.answer'));
    }

    public function test_ai_rewording_never_replaces_the_no_data_answer(): void
    {
        config()->set('ai.groq.phrase_system_answers', true);
        $this->mock(GroqChatService::class, function ($mock): void {
            $mock->shouldReceive('groundedResponse')->andReturn('Cumba is Low risk right now.');
            $mock->shouldIgnoreMissing();
        });
        HeatmapArea::query()->where('barangay', 'Cumba')->delete();

        $response = $this->ask($this->farmer('Prenza'), 'Is Cumba high risk right now?');

        $response->assertOk()->assertJsonPath('chat.prediction_result.status', 'no_data');
        $this->assertStringContainsString(self::ENGLISH_NO_DATA, (string) $response->json('chat.answer'));
        $this->assertStringNotContainsString('Low risk', (string) $response->json('chat.answer'));
    }

    public function test_role_assistant_answers_for_the_named_unsupported_barangay_in_both_languages(): void
    {
        $service = app(\App\Services\AI\RoleAssistantService::class);
        $user = $this->farmer('Malaruhatan');
        $this->computeRating('Malaruhatan', 'High', 'Heat');

        $english = $service->barangayAnswer($user, 'Tell me about barangay Cumba', 'English');
        $this->assertStringContainsString('Barangay Cumba', $english['answer']);
        $this->assertStringContainsString(self::ENGLISH_NO_DATA, $english['answer']);
        $this->assertStringNotContainsString('Climate risk: High', $english['answer']);

        $tagalog = $service->barangayAnswer($user, 'Ano ang lagay ng barangay Cumba?', 'Tagalog');
        $this->assertStringContainsString(self::TAGALOG_NO_DATA, $tagalog['answer']);
        $this->assertStringNotContainsString('Climate risk: High', $tagalog['answer']);
    }

    public function test_named_in_recognises_any_lian_barangay_without_false_matches(): void
    {
        $this->assertSame('Cumba', LianBarangays::namedIn('Ano ang climate risk sa Cumba?'));
        $this->assertSame('Matabungkay', LianBarangays::namedIn('Is MATABUNGKAY high risk?'));
        $this->assertSame('Puting-Kahoy', LianBarangays::namedIn('risk in puting kahoy'));
        $this->assertSame('Barangay 1 (Pob.)', LianBarangays::namedIn('What is the risk in Barangay 1 (Pob.)?'));
        $this->assertSame('Barangay 3 (Pob.)', LianBarangays::namedIn('risk sa poblacion 3'));
        $this->assertNull(LianBarangays::namedIn('Predict yield for 1 hectare in the wet season'));
        $this->assertNull(LianBarangays::namedIn('What is the climate risk right now?'));
        $this->assertNull(LianBarangays::namedIn(''));
    }

    public function test_barangay_names_are_not_language_evidence(): void
    {
        $detector = app(\App\Services\AI\IntentDetectionService::class);

        // " po" in "Pook" and "ani" in "Lumaniag" used to tip these to Tagalog.
        $this->assertSame('English', $detector->detectLanguage('What is the climate risk in Bagong Pook?'));
        $this->assertSame('English', $detector->detectLanguage('What is the climate risk in Lumaniag?'));
        $this->assertSame('Tagalog', $detector->detectLanguage('Ano ang climate risk sa Bagong Pook?'));
        $this->assertSame('Tagalog', $detector->detectLanguage('Ano ang climate risk sa Cumba?'));
        $this->assertSame('Tagalog', $detector->detectLanguage('Kailan uulan po?'));
    }

    private function assertNoInventedLevel(string $answer, string $context): void
    {
        foreach (['Low', 'Moderate', 'High', 'Severe'] as $level) {
            $this->assertStringNotContainsString($level.' risk', $answer, $context);
            $this->assertStringNotContainsString('naka-markang '.$level, $answer, $context);
        }
    }

    private function computeRating(string $barangay, string $level, string $type): void
    {
        HeatmapArea::query()->where('barangay', $barangay)->update([
            'risk_level' => $level, 'risk_type' => $type, 'risk_score' => 0.9,
            'rainfall_status' => 'Low rainfall',
            'planting_advisory' => 'Check the field.',
            'description' => 'Computed from live weather and production data.',
            'risk_computed_at' => now(),
        ]);
    }

    private function farmer(?string $barangay): User
    {
        return User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => $barangay]);
    }

    private function ask(User $user, string $question)
    {
        return $this->actingAs($user)->postJson(route('ai-chat.message'), ['question' => $question]);
    }
}
