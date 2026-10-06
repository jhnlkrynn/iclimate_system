<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Advisories (2026-10): one list (not a calendar). Each item has a severity colour, the barangay, the valid dates, one
 * sentence of recommendation and one "What to do". Planting is the kind shown first. The MAO's form is filled in from
 * the system's recommendation and publishing alerts the barangay's farmers with a notification that only links here.
 */
class AdvisoriesListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function farmer(string $barangay = 'Prenza', string $language = 'en'): User
    {
        $user = User::factory()->farmer()->create(['barangay' => $barangay, 'preferred_language' => $language, 'status' => User::STATUS_ACTIVE]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay]);

        return $user;
    }

    private function mao(): User
    {
        return User::factory()->maoPersonnel()->create();
    }

    private function advisory(array $overrides = []): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create(array_merge([
            'title' => 'Planting Advisory',
            'content' => 'Fields are ready for planting this week. Rain is expected to stay light.',
            'message' => 'Fields are ready for planting this week. Rain is expected to stay light.',
            'summary' => 'Fields are ready for planting this week. Rain is expected to stay light.',
            'recommended_action' => 'Start transplanting seedlings. Keep the dikes closed.',
            'type' => 'Planting',
            'advisory_type' => 'planting',
            'severity' => 'low',
            'target_scope' => 'municipality',
            'source' => 'MAO-Reviewed Advisory',
            'status' => 'published',
            'valid_from' => CarbonImmutable::parse('2026-10-05 00:00:00', 'Asia/Manila')->utc(),
            'valid_until' => CarbonImmutable::parse('2026-10-12 23:59:59', 'Asia/Manila')->utc(),
            'posted_by' => $this->mao()->id,
        ], $overrides));
    }

    /** The visible words of one list item. */
    private function item(string $html, PlantingAdvisory $advisory): string
    {
        $this->assertSame(1, preg_match('#<li class="adv-item[^"]*" data-adv-item="'.$advisory->id.'">(.*?)</li>#s', $html, $m), 'list item '.$advisory->id);

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $m[1]))));
    }

    // ------------------------------------------------------------ the list

    public function test_each_item_has_severity_barangay_valid_dates_one_recommendation_and_one_what_to_do(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));
        $advisory = $this->advisory(['target_barangay' => 'Prenza', 'target_scope' => 'barangay', 'severity' => 'high']);

        $html = $this->actingAs($this->farmer())->get(route('planting-advisories.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<li class="adv-item is-high" data-adv-item="'.$advisory->id.'">', $html);   // the colour
        $text = $this->item($html, $advisory);
        $this->assertStringStartsWith('High Prenza Valid: Oct 5 – Oct 12, 2026', $text);
        // One sentence each, not the whole message.
        $this->assertStringContainsString('Fields are ready for planting this week.', $text);
        $this->assertStringNotContainsString('Rain is expected to stay light.', $text);
        $this->assertStringContainsString('What to do: Start transplanting seedlings.', $text);
        $this->assertStringNotContainsString('Keep the dikes closed.', $text);

        // A list, not a calendar or a wall of cards: no month grid, no summary cards, no filter form, no refresh button.
        foreach (['fcal-grid', 'fad-summary', 'fad-filter-form', 'Refresh Advisories', 'Safety Reminder'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
    }

    public function test_the_severity_colours_are_green_yellow_and_red_and_the_most_severe_comes_first(): void
    {
        $low = $this->advisory(['severity' => 'low']);
        $moderate = $this->advisory(['severity' => 'moderate', 'title' => 'B']);
        $critical = $this->advisory(['severity' => 'critical', 'title' => 'C']);

        $html = $this->actingAs($this->farmer())->get(route('planting-advisories.index'))->getContent();

        $this->assertStringContainsString('adv-item is-low" data-adv-item="'.$low->id.'"', $html);
        $this->assertStringContainsString('adv-item is-moderate" data-adv-item="'.$moderate->id.'"', $html);
        $this->assertStringContainsString('adv-item is-high" data-adv-item="'.$critical->id.'"', $html);
        $this->assertTrue(strpos($html, 'data-adv-item="'.$critical->id.'"') < strpos($html, 'data-adv-item="'.$moderate->id.'"'));
        $this->assertTrue(strpos($html, 'data-adv-item="'.$moderate->id.'"') < strpos($html, 'data-adv-item="'.$low->id.'"'));
    }

    public function test_planting_is_shown_first_and_other_kinds_are_chips_only_when_in_use(): void
    {
        $planting = $this->advisory();
        $farmer = $this->farmer();

        // Only planting advisories: no chips at all.
        $html = $this->actingAs($farmer)->get(route('planting-advisories.index'))->getContent();
        $this->assertStringNotContainsString('data-adv-types', $html);
        $this->assertStringContainsString('data-adv-item="'.$planting->id.'"', $html);

        // A weather advisory exists: its chip appears; the page still opens on Planting.
        $weather = $this->advisory(['title' => 'Rain', 'type' => 'Climate', 'advisory_type' => 'climate']);
        Cache::flush();
        $html = $this->actingAs($farmer)->get(route('planting-advisories.index'))->getContent();
        $this->assertSame(['planting', 'climate'], $this->chips($html));
        $this->assertStringNotContainsString('data-adv-type="irrigation"', $html);
        $this->assertStringContainsString('data-adv-item="'.$planting->id.'"', $html);
        $this->assertStringNotContainsString('data-adv-item="'.$weather->id.'"', $html);

        $html = $this->actingAs($farmer)->get(route('planting-advisories.index', ['type' => 'climate']))->getContent();
        $this->assertStringContainsString('data-adv-item="'.$weather->id.'"', $html);
        $this->assertStringNotContainsString('data-adv-item="'.$planting->id.'"', $html);
    }

    public function test_without_a_planting_advisory_the_page_opens_on_the_kind_that_has_one(): void
    {
        $weather = $this->advisory(['title' => 'Rain', 'type' => 'Climate', 'advisory_type' => 'climate']);

        $html = $this->actingAs($this->farmer())->get(route('planting-advisories.index'))->getContent();

        $this->assertStringContainsString('data-adv-item="'.$weather->id.'"', $html);
        $this->assertMatchesRegularExpression('#class="adv-chip is-active"\s+aria-current="page"\s+data-adv-type="climate"#', $html);
    }

    /** @return array<int, string> */
    private function chips(string $html): array
    {
        preg_match_all('#data-adv-type="([a-z]+)"#', $html, $m);

        return $m[1];
    }

    // ------------------------------------------------------------ who sees what

    public function test_a_farmer_reads_their_barangay_and_lian_wide_advice_in_their_language(): void
    {
        $own = $this->advisory(['target_barangay' => 'Prenza', 'target_scope' => 'barangay']);
        $lian = $this->advisory(['title' => 'Lian']);
        $other = $this->advisory(['title' => 'Cumba', 'target_barangay' => 'Cumba', 'target_scope' => 'barangay']);
        $draft = $this->advisory(['title' => 'Draft', 'status' => 'pending_review']);

        $html = $this->actingAs($this->farmer('Prenza', 'fil'))->get(route('planting-advisories.index', ['target_barangay' => 'Cumba', 'show' => 'all']))->assertOk()->getContent();

        $this->assertStringContainsString('data-adv-item="'.$own->id.'"', $html);
        $this->assertStringContainsString('data-adv-item="'.$lian->id.'"', $html);
        // ?target_barangay= and ?show= are staff tools: a farmer never reaches another barangay or an unpublished advisory.
        $this->assertStringNotContainsString('data-adv-item="'.$other->id.'"', $html);
        $this->assertStringNotContainsString('data-adv-item="'.$draft->id.'"', $html);
        foreach (['data-adv-show', 'data-adv-filter', 'New Advisory', 'Review and publish', '>Edit<'] as $staffOnly) {
            $this->assertStringNotContainsString($staffOnly, $html, $staffOnly);
        }

        $this->assertStringContainsString('<h1>Mga Payo</h1>', $html);
        $this->assertStringContainsString('Mababa Prenza May bisa: ', $this->item($html, $own));
        $this->assertStringContainsString('Gawin: Start transplanting seedlings.', $this->item($html, $own));
        $this->assertStringContainsString('Buong Lian', $this->item($html, $lian));
    }

    public function test_staff_see_what_waits_for_approval_and_can_pick_a_barangay(): void
    {
        $published = $this->advisory(['target_barangay' => 'Prenza', 'target_scope' => 'barangay']);
        $pending = $this->advisory(['title' => 'Pending', 'status' => 'pending_review', 'target_barangay' => 'Cumba', 'target_scope' => 'barangay']);
        $expired = $this->advisory(['title' => 'Old', 'status' => 'expired']);
        $mao = $this->mao();

        $active = $this->actingAs($mao)->get(route('planting-advisories.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-adv-item="'.$published->id.'"', $active);
        $this->assertStringNotContainsString('data-adv-item="'.$pending->id.'"', $active);
        $this->assertMatchesRegularExpression('#data-adv-show="pending">\s*To approve<span class="fad-count" aria-label="1 to approve">1</span>#', $active);
        $this->assertStringContainsString('New Advisory', $active);

        $toApprove = $this->actingAs($mao)->get(route('planting-advisories.index', ['show' => 'pending']))->getContent();
        $this->assertStringContainsString('data-adv-item="'.$pending->id.'"', $toApprove);
        $this->assertStringContainsString('href="'.route('planting-advisories.edit', $pending).'">Review and publish</a>', $toApprove);
        $this->assertStringNotContainsString('data-adv-item="'.$published->id.'"', $toApprove);

        $all = $this->actingAs($mao)->get(route('planting-advisories.index', ['show' => 'all']))->getContent();
        foreach ([$published, $pending, $expired] as $advisory) {
            $this->assertStringContainsString('data-adv-item="'.$advisory->id.'"', $all);
        }

        $prenza = $this->actingAs($mao)->get(route('planting-advisories.index', ['show' => 'all', 'target_barangay' => 'Prenza']))->getContent();
        $this->assertStringContainsString('data-adv-item="'.$published->id.'"', $prenza);
        $this->assertStringNotContainsString('data-adv-item="'.$pending->id.'"', $prenza);
    }

    // ------------------------------------------------------------ the MAO's form

    public function test_the_form_follows_the_order_barangay_recommendation_what_to_do_valid_until_severity_then_mao_activity(): void
    {
        $html = $this->actingAs($this->mao())->get(route('planting-advisories.create'))->assertOk()->getContent();

        $positions = array_map(fn (string $field) => strpos($html, $field), ['name="target_barangay"', 'name="message"', 'name="recommended_action"', 'name="valid_until"', 'name="severity"', 'name="mao_activity"', 'name="mao_activity_date"']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        foreach (['1. Barangay', '2. Recommendation', '3. What to do', '4. Valid until', '5. Severity', 'MAO Activity', '(optional)', 'Approve and Publish', 'Save for Review'] as $label) {
            $this->assertStringContainsString($label, $html, $label);
        }
        // Planting is the kind; no kind picker, no title, no status list, no "valid from".
        $this->assertStringContainsString('<input type="hidden" name="advisory_type" value="planting">', $html);
        foreach (['name="title"', 'name="valid_from"', '<select class="form-select" name="status"', 'name="advisory_type" required'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }

        // Another kind is offered only for an advisory that already has it.
        $weather = $this->advisory(['type' => 'Climate', 'advisory_type' => 'climate']);
        $this->actingAs($this->mao())->get(route('planting-advisories.edit', $weather))->assertOk()
            ->assertSee('Kind of advisory')->assertSee('<option value="climate" selected>Climate</option>', false);
    }

    public function test_a_new_advisory_opens_filled_in_from_the_systems_recommendation_for_the_barangay(): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $records = [];
        foreach (LianBarangays::all() as $barangay) {
            $records[$barangay] = ['barangay' => $barangay, 'temperature_c' => 28.0, 'fetched_at' => now()->toIso8601String(), 'observed_at' => now()->toIso8601String(),
                'daily' => array_map(fn (int $i) => ['date' => $today->addDays($i)->toDateString(), 'rainfall' => $barangay === 'Prenza' && $i === 1 ? 60.0 : 4.0,
                    'wind_speed_max' => 12.0, 'temperature_max' => 31.0], range(0, 15))];
        }
        Cache::forever(LianBarangayWeatherService::STORE_KEY, ['records' => $records, 'status' => []]);

        $html = $this->actingAs($this->mao())->get(route('planting-advisories.create', ['barangay' => 'Prenza']))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="Prenza" selected>', $html);
        $this->assertMatchesRegularExpression('#name="message"[^>]*>Very heavy or repeated heavy rain is expected#', $html);
        $this->assertMatchesRegularExpression('#name="recommended_action"[^>]*>Clear the drainage canals now\.#', $html);
        $this->assertStringContainsString('<option value="high" selected>High (red)</option>', $html);
        $this->assertStringContainsString('value="'.$today->addDays(6)->toDateString().'"', $html);       // valid for the week
        $this->assertMatchesRegularExpression('#data-adv-prefill-note\s*>#', $html);                       // says it was filled in, to be reviewed
        // Every barangay's recommendation is in the page, so choosing another barangay needs no request.
        $this->assertSame(1, preg_match('#<script type="application/json" data-adv-suggestions>(.*?)</script>#s', $html, $m));
        $suggestions = json_decode($m[1], true);
        $this->assertCount(20, $suggestions);                                                              // 19 barangays + all of Lian
        $this->assertSame('low', $suggestions['Cumba']['severity']);
        $this->assertSame('high', $suggestions['']['severity']);                                           // all of Lian: the highest rating
        Http::assertNothingSent();

        // With nothing saved the form is simply empty.
        Cache::flush();
        $empty = $this->actingAs($this->mao())->get(route('planting-advisories.create', ['barangay' => 'Prenza']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#name="message"[^>]*></textarea>#', $empty);
        $this->assertMatchesRegularExpression('#data-adv-prefill-note\s+hidden\s*>#', $empty);
    }

    // ------------------------------------------------------------ review, approve, publish, notify

    public function test_approve_and_publish_shows_the_advisory_to_the_barangay_and_alerts_only_its_farmers(): void
    {
        $prenza = $this->farmer('Prenza');
        $filipino = $this->farmer('Prenza', 'fil');
        $cumba = $this->farmer('Cumba');
        $mao = $this->mao();

        $response = $this->actingAs($mao)->post(route('planting-advisories.store'), [
            'target_barangay' => 'Prenza',
            'message' => 'Heavy rain is expected on Thursday.',
            'recommended_action' => 'Clear the drainage canals.',
            'valid_until' => now('Asia/Manila')->addDays(6)->toDateString(),
            'severity' => 'moderate',
            'advisory_type' => 'planting',
            'status' => 'published',
        ]);
        $advisory = PlantingAdvisory::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('planting-advisories.show', $advisory))->assertSessionHas('success', 'Advisory published. 2 farmers were notified.');

        $this->assertSame('Planting Advisory – Prenza', $advisory->title);                                 // titled by the system
        $this->assertSame(['published', 'planting', 'Prenza', 'moderate', $mao->id], [$advisory->status, $advisory->advisory_type, $advisory->target_barangay, $advisory->severity, $advisory->approved_by]);
        $this->assertSame(now('Asia/Manila')->addDays(6)->toDateString().' 23:59:59', $advisory->valid_until->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s'));

        // The alert only says there is a new advisory, in each farmer's language; it carries none of the advice.
        $this->assertDatabaseHas('notifications', ['user_id' => $prenza->id, 'title' => 'New advisory for your barangay', 'message' => 'Open Advisories to read it.', 'type' => 'Advisory', 'is_read' => false]);
        $this->assertDatabaseHas('notifications', ['user_id' => $filipino->id, 'title' => 'May bagong payo para sa inyong barangay']);
        $this->assertSame(0, Notification::query()->where('user_id', $cumba->id)->count());
        $this->assertSame(0, Notification::query()->where('message', 'like', '%drainage%')->count());

        $this->actingAs($prenza)->get(route('planting-advisories.index'))->assertSee('Heavy rain is expected on Thursday.');
        $this->actingAs($cumba)->get(route('planting-advisories.index'))->assertDontSee('Heavy rain is expected on Thursday.');
    }

    public function test_save_for_review_keeps_it_from_farmers_until_the_mao_reviews_and_publishes_it_once(): void
    {
        $farmer = $this->farmer('Prenza');
        $mao = $this->mao();

        $this->actingAs($mao)->post(route('planting-advisories.store'), [
            'target_barangay' => 'Prenza', 'message' => 'Draft recommendation.', 'recommended_action' => 'Wait.',
            'valid_until' => now('Asia/Manila')->addDays(3)->toDateString(), 'severity' => 'low', 'status' => 'pending_review',
        ])->assertSessionHas('success', 'Advisory saved for review. Farmers do not see it yet.');
        $advisory = PlantingAdvisory::query()->latest('id')->firstOrFail();
        $this->assertSame('pending_review', $advisory->status);
        $this->assertSame(0, Notification::query()->count());
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertDontSee('Draft recommendation.');

        // Review: the same form, with the text to edit and the two buttons.
        $this->actingAs($mao)->get(route('planting-advisories.edit', $advisory))->assertOk()
            ->assertSee('Review Advisory')->assertSee('Draft recommendation.')->assertSee('Approve and Publish');

        $this->actingAs($mao)->put(route('planting-advisories.update', $advisory), [
            'target_barangay' => 'Prenza', 'message' => 'Reviewed recommendation.', 'recommended_action' => 'Plant now.',
            'valid_until' => now('Asia/Manila')->addDays(3)->toDateString(), 'severity' => 'low', 'status' => 'published',
        ])->assertSessionHas('success', 'Advisory published. 1 farmer was notified.');
        $this->assertSame(['published', $mao->id], [$advisory->fresh()->status, $advisory->fresh()->approved_by]);
        $this->assertSame(1, Notification::query()->where('user_id', $farmer->id)->count());
        Cache::flush();
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertSee('Reviewed recommendation.');

        // Editing a published advisory again does not alert the farmers a second time.
        $this->actingAs($mao)->put(route('planting-advisories.update', $advisory), [
            'target_barangay' => 'Prenza', 'message' => 'Reviewed recommendation, corrected.', 'valid_until' => now('Asia/Manila')->addDays(3)->toDateString(), 'status' => 'published',
        ])->assertSessionHas('success', 'Advisory updated successfully.');
        $this->assertSame(1, Notification::query()->where('user_id', $farmer->id)->count());
    }

    public function test_the_quick_approve_button_alerts_farmers_and_an_advisory_for_all_of_lian_alerts_every_farmer(): void
    {
        $prenza = $this->farmer('Prenza');
        $cumba = $this->farmer('Cumba');
        $advisory = $this->advisory(['status' => 'pending_review']);                                        // for all of Lian

        $this->actingAs($this->mao())->post(route('management.advisories.approve', $advisory))
            ->assertSessionHas('success', 'Advisory published. 2 farmers were notified.');

        foreach ([$prenza, $cumba] as $farmer) {
            $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'title' => 'New advisory for Lian', 'type' => 'Advisory']);
        }
        // Advisories the system publishes by itself never alert anyone.
        $this->advisory(['title' => 'System', 'generated_automatically' => true]);
        $this->assertSame(2, Notification::query()->count());
    }

    public function test_farmers_and_guests_cannot_create_or_publish(): void
    {
        $farmer = $this->farmer();
        $advisory = $this->advisory(['status' => 'pending_review']);

        $this->actingAs($farmer)->get(route('planting-advisories.create'))->assertForbidden();
        $this->actingAs($farmer)->post(route('planting-advisories.store'), ['message' => 'x', 'status' => 'published'])->assertForbidden();
        $this->actingAs($farmer)->get(route('planting-advisories.edit', $advisory))->assertForbidden();
        $this->actingAs($farmer)->put(route('planting-advisories.update', $advisory), ['message' => 'x', 'status' => 'published'])->assertForbidden();
        $this->assertSame('pending_review', $advisory->fresh()->status);

        auth()->logout();
        $this->get(route('planting-advisories.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------ the notification only alerts and links

    public function test_an_advisory_notification_links_to_the_advisories_page(): void
    {
        $farmer = $this->farmer('Prenza', 'fil');
        $note = Notification::query()->create(['user_id' => $farmer->id, 'title' => 'May bagong payo para sa inyong barangay', 'message' => 'Buksan ang Mga Payo para mabasa ito.', 'type' => 'Advisory', 'is_read' => false]);
        $plain = Notification::query()->create(['user_id' => $farmer->id, 'title' => 'Office closed', 'message' => 'Closed on Friday.', 'type' => 'Announcement', 'is_read' => false]);

        $list = $this->actingAs($farmer)->get(route('notifications.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($list, 'data-notif-advisories'));
        $this->assertStringContainsString('href="'.route('planting-advisories.index').'" data-notif-advisories>Buksan ang Mga Payo</a>', $list);

        $this->actingAs($farmer)->get(route('notifications.show', $note))->assertOk()->assertSee('data-notif-advisories', false);
        $this->actingAs($farmer)->get(route('notifications.show', $plain))->assertOk()->assertDontSee('data-notif-advisories', false);

        $feed = collect($this->actingAs($farmer)->getJson(route('notifications.feed'))->assertOk()->json('items'))->keyBy('id');
        $this->assertSame([route('planting-advisories.index'), 'Buksan ang Mga Payo'], [$feed[$note->id]['link_url'], $feed[$note->id]['link_label']]);
        $this->assertNull($feed[$plain->id]['link_url']);
    }

    // ------------------------------------------------------------ the optional MAO Activity

    public function test_the_optional_mao_activity_is_kept_with_the_advisory_and_needs_a_date(): void
    {
        $mao = $this->mao();
        $fields = ['target_barangay' => 'Prenza', 'message' => 'Plant this week.', 'valid_until' => now('Asia/Manila')->addDays(6)->toDateString(), 'severity' => 'low', 'status' => 'published'];

        $this->actingAs($mao)->post(route('planting-advisories.store'), $fields + ['mao_activity' => 'Seed distribution at the barangay hall'])
            ->assertSessionHasErrors(['mao_activity_date']);

        $date = now('Asia/Manila')->addDays(2)->toDateString();
        $this->actingAs($mao)->post(route('planting-advisories.store'), $fields + ['mao_activity' => 'Seed distribution at the barangay hall', 'mao_activity_date' => $date])->assertSessionHasNoErrors();
        $advisory = PlantingAdvisory::query()->latest('id')->firstOrFail();
        $this->assertSame(['mao_activity' => 'Seed distribution at the barangay hall', 'mao_activity_date' => $date], $advisory->metadata);

        $html = $this->actingAs($this->farmer())->get(route('planting-advisories.index'))->getContent();
        $this->assertStringContainsString('MAO activity: Seed distribution at the barangay hall', $this->item($html, $advisory));

        // Clearing the field removes the activity.
        $this->actingAs($mao)->put(route('planting-advisories.update', $advisory), $fields)->assertSessionHasNoErrors();
        $this->assertNull($advisory->fresh()->metadata);
    }
}
