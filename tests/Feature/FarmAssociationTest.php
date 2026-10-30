<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\MaoActivityLog;
use App\Models\MaoUserBarangay;
use App\Models\User;
use App\Services\Farms\FarmAssociations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Each farm says which farmers' association it belongs to (one of its own barangay), "my association isn't listed"
 * (the MAO reviews it) or "not a member". farmer_organization_members follows the farms' links.
 */
class FarmAssociationTest extends TestCase
{
    use RefreshDatabase;

    private FarmerOrganization $prenza;

    private FarmerOrganization $kapito;

    private FarmerOrganization $kapitoRainfed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prenza = FarmerOrganization::query()->create(['name' => 'Prenza Farmers Association', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated']);
        $this->kapito = FarmerOrganization::query()->create(['name' => 'Saluysoy Farmers Association', 'barangay' => 'Kapito', 'farm_type' => 'Irrigated']);
        $this->kapitoRainfed = FarmerOrganization::query()->create(['name' => 'Kapito Rainfed Growers', 'barangay' => 'Kapito', 'farm_type' => 'Rainfed']);
    }

    private function farmer(): User
    {
        $farmer = User::factory()->farmer()->create(['name' => 'Juan Dela Cruz', 'barangay' => 'Prenza', 'status' => User::STATUS_ACTIVE]);
        $this->profile($farmer, 'Juan Dela Cruz');

        return $farmer;
    }

    /** A profile without its "Farm 1" (saved quietly, so the profile-to-farm sync does not run). */
    private function profile(User $farmer, string $name, ?int $organizationId = null): FarmerProfile
    {
        $profile = new FarmerProfile(['user_id' => $farmer->id, 'full_name' => $name, 'barangay' => 'Prenza', 'farm_type' => 'Irrigated', 'farmer_organization_id' => $organizationId]);
        $profile->saveQuietly();

        return $profile;
    }

    /** @param  array<string, mixed>  $more */
    private function addFarm(User $farmer, string $barangay, array $more = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($farmer)->post(route('farmer.farms.store'), $more + [
            'farm_name' => 'Farm in '.$barangay, 'barangay' => $barangay, 'farm_type' => 'Irrigated', 'farm_size_value' => '1.5', 'farm_size_unit' => Farm::UNIT_HECTARES,
        ]);
    }

    /** @return list<int> */
    private function memberships(User $farmer): array
    {
        return $farmer->farmerProfile()->first()->organizations()->pluck('farmer_organizations.id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
    }

    private function mao(?string $barangay = null): User
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
        if ($barangay !== null) {
            MaoUserBarangay::query()->create(['user_id' => $mao->id, 'barangay' => $barangay]);
        }

        return $mao;
    }

    public function test_two_farms_in_different_barangays_link_to_different_associations_and_both_are_memberships(): void
    {
        $farmer = $this->farmer();

        $this->addFarm($farmer, 'Prenza', ['association_status' => 'member', 'farmer_organization_id' => $this->prenza->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->addFarm($farmer, 'Kapito', ['association_status' => 'member', 'farmer_organization_id' => $this->kapito->id])->assertSessionHasNoErrors()->assertRedirect();

        $farms = Farm::query()->ownedBy($farmer)->orderBy('id')->get();
        $this->assertSame([$this->prenza->id, $this->kapito->id], $farms->pluck('farmer_organization_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(['member', 'member'], $farms->pluck('association_status')->all());
        $this->assertSame(collect([$this->prenza->id, $this->kapito->id])->sort()->values()->all(), $this->memberships($farmer));
        $this->assertNotNull(DB::table('farmer_organization_members')->where('farmer_organization_id', $this->kapito->id)->value('date_joined'));
        // The first association becomes the primary organization of a farmer who had none.
        $this->assertSame($this->prenza->id, (int) $farmer->farmerProfile()->first()->farmer_organization_id);

        // The farm cards say so.
        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()
            ->assertSee('Samahan: Prenza Farmers Association')->assertSee('Samahan: Saluysoy Farmers Association');
    }

    public function test_the_association_list_only_shows_associations_of_the_farms_barangay(): void
    {
        $farmer = $this->farmer();
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Kapito Farm', 'barangay' => 'Kapito', 'farm_type' => 'Irrigated', 'is_primary' => true]);

        $html = $this->actingAs($farmer)->get(route('farmer.farms.edit', $farm->id))->assertOk()->getContent();
        $picker = substr($html, strpos($html, 'id="farmer_organization_id"'), 600);
        $this->assertStringContainsString('Saluysoy Farmers Association', $picker);
        $this->assertStringNotContainsString('Prenza Farmers Association', $picker);
        // Of the farm's type first: the Rainfed association is left out of an Irrigated farm's list.
        $this->assertStringNotContainsString('Kapito Rainfed Growers', $picker);
        $this->assertSame(['Saluysoy Farmers Association'], FarmAssociations::optionsFor('Kapito', 'Irrigated')->pluck('name')->all());
        $this->assertSame(['Kapito Rainfed Growers', 'Saluysoy Farmers Association'], FarmAssociations::optionsFor('Kapito')->pluck('name')->all());

        // A barangay with no associations offers only "isn't listed" and "No".
        $empty = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Luyahan Farm', 'barangay' => 'Luyahan']);
        $html = $this->actingAs($farmer)->get(route('farmer.farms.edit', $empty->id))->getContent();
        $this->assertMatchesRegularExpression('/<label class="ffm-choice" data-association-member\s+hidden/', $html);

        // An association of another barangay is refused, even when posted directly.
        $this->addFarm($farmer, 'Kapito', ['association_status' => 'member', 'farmer_organization_id' => $this->prenza->id])
            ->assertSessionHasErrors('farmer_organization_id');
    }

    public function test_the_declaration_is_required_when_adding_a_farm(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('farmer.farms.create'))->assertOk()
            ->assertSee('Is this farm part of a farmers&#039; association?', false)
            ->assertSee('Choose the barangay first to see its associations.')
            ->assertSee('name="farm_size_value"', false)
            ->assertSee('name="farm_size_unit"', false)
            ->assertDontSee('How would you like to provide this farm&#039;s size?', false)
            ->assertDontSee('farm_size_method', false);
        $this->actingAs($farmer)->withCookie('farmer_language', 'fil')->get(route('farmer.farms.create', ['lang' => 'fil']))->assertOk();

        $this->addFarm($farmer, 'Prenza')->assertSessionHasErrors('association_status');
        $this->addFarm($farmer, 'Prenza', ['association_status' => 'member'])->assertSessionHasErrors('farmer_organization_id');
        $this->addFarm($farmer, 'Prenza', ['association_status' => 'pending_other'])->assertSessionHasErrors('association_other_name');
        $this->assertSame(0, Farm::query()->ownedBy($farmer)->where('farm_name', 'Farm in Prenza')->count());

        $this->addFarm($farmer, 'Prenza', ['association_status' => 'not_member'])->assertSessionHasNoErrors();
        $this->assertSame('not_member', Farm::query()->ownedBy($farmer)->where('farm_name', 'Farm in Prenza')->value('association_status'));
        $this->assertSame([], $this->memberships($farmer));
    }

    public function test_edit_prefills_exact_map_and_estimate_sizes_but_leaves_unknown_empty(): void
    {
        $farmer = $this->farmer();
        $farms = [
            Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Exact', 'barangay' => 'Prenza', 'farm_size_hectares' => 2.5, 'farm_size_method' => Farm::SIZE_EXACT, 'farm_size_unit' => Farm::UNIT_HECTARES]),
            Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Square meters', 'barangay' => 'Prenza', 'farm_size_hectares' => 2, 'farm_size_method' => Farm::SIZE_EXACT, 'farm_size_unit' => Farm::UNIT_SQUARE_METERS]),
            Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Mapped', 'barangay' => 'Prenza', 'farm_size_hectares' => 2.25, 'farm_size_method' => Farm::SIZE_MAPPED]),
            Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Estimated', 'barangay' => 'Prenza', 'farm_size_method' => Farm::SIZE_ESTIMATED, 'farm_size_estimate_range' => '1_2']),
            Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Unknown', 'barangay' => 'Prenza', 'farm_size_method' => Farm::SIZE_UNKNOWN]),
        ];

        $this->actingAs($farmer)->get(route('farmer.farms.edit', $farms[0]))->assertSee('value="2.5"', false)->assertSee('value="hectares" selected', false);
        $this->actingAs($farmer)->get(route('farmer.farms.edit', $farms[1]))->assertSee('value="20000"', false)->assertSee('value="square_meters" selected', false);
        $this->actingAs($farmer)->get(route('farmer.farms.edit', $farms[2]))->assertSee('value="2.25"', false);
        $this->actingAs($farmer)->get(route('farmer.farms.edit', $farms[3]))->assertSee('value="1.5"', false);
        $this->actingAs($farmer)->get(route('farmer.farms.edit', $farms[4]))->assertSee('id="farm_size_value"', false)->assertSee('value=""', false);
    }

    public function test_a_new_barangay_asks_again_and_the_edit_page_changes_the_association(): void
    {
        $farmer = $this->farmer();
        $this->addFarm($farmer, 'Prenza', ['association_status' => 'member', 'farmer_organization_id' => $this->prenza->id]);
        $farm = Farm::query()->ownedBy($farmer)->sole();
        $form = ['farm_name' => $farm->farm_name, 'farm_type' => 'Irrigated', 'farm_size_value' => '1.5', 'farm_size_unit' => Farm::UNIT_HECTARES];

        // Same barangay: the answer can stay as it is.
        $this->actingAs($farmer)->put(route('farmer.farms.update', $farm->id), $form + ['barangay' => 'Prenza'])->assertSessionHasNoErrors();
        $this->assertSame($this->prenza->id, (int) $farm->fresh()->farmer_organization_id);
        // Another barangay: asked again.
        $this->actingAs($farmer)->put(route('farmer.farms.update', $farm->id), $form + ['barangay' => 'Kapito'])->assertSessionHasErrors('association_status');
        $this->actingAs($farmer)->put(route('farmer.farms.update', $farm->id), $form + ['barangay' => 'Kapito', 'association_status' => 'member', 'farmer_organization_id' => $this->kapito->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->kapito->id, (int) $farm->fresh()->farmer_organization_id);
        // The Prenza membership went with the only farm that used it.
        $this->assertSame([$this->kapito->id], $this->memberships($farmer));

        // A move that does not come from the form (the farm map, an MAO profile edit) clears the answer.
        $farm = $farm->fresh();
        $farm->barangay = 'Prenza';
        $farm->save();
        $this->assertSame('not_declared', $farm->fresh()->associationState());
        $this->assertSame([], $this->memberships($farmer));
    }

    public function test_an_unlisted_association_goes_to_the_mao_queue_and_can_be_linked_added_or_rejected(): void
    {
        $farmer = $this->farmer();
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'A', 'association_status' => 'pending_other', 'association_other_name' => 'Saluysoy FA']);
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'B', 'association_status' => 'pending_other', 'association_other_name' => 'Bagong Samahan ng Kapito']);
        $this->addFarm($farmer, 'Prenza', ['farm_name' => 'C', 'association_status' => 'pending_other', 'association_other_name' => 'Wrong Name']);
        [$a, $b, $c] = Farm::query()->ownedBy($farmer)->whereIn('farm_name', ['A', 'B', 'C'])->orderBy('farm_name')->get()->all();
        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertSee('Waiting for MAO review');

        // A Kapito technologist sees only Kapito's requests, and cannot decide Prenza's.
        $kapitoMao = $this->mao('Kapito');
        $this->actingAs($kapitoMao)->get(route('mao.farm-associations.index'))->assertOk()
            ->assertSee('Saluysoy FA')->assertSee('Bagong Samahan ng Kapito')->assertDontSee('Wrong Name');
        $this->actingAs($kapitoMao)->post(route('mao.farm-associations.reject', $c), ['note' => 'No'])->assertForbidden();

        // Link to an existing association (of the farm's barangay only).
        $this->actingAs($kapitoMao)->post(route('mao.farm-associations.link', $a), ['farmer_organization_id' => $this->prenza->id])->assertSessionHasErrors('farmer_organization_id');
        $this->actingAs($kapitoMao)->post(route('mao.farm-associations.link', $a), ['farmer_organization_id' => $this->kapito->id])->assertSessionHasNoErrors();
        $this->assertSame(['member', $this->kapito->id, null], [$a->fresh()->association_status, (int) $a->fresh()->farmer_organization_id, $a->fresh()->association_other_name]);

        // Add the association and link it.
        $this->actingAs($kapitoMao)->post(route('mao.farm-associations.store', $b), ['name' => 'Bagong Samahan ng Kapito', 'barangay' => 'Kapito', 'farm_type' => 'Irrigated'])->assertSessionHasNoErrors();
        $created = FarmerOrganization::query()->where('name', 'Bagong Samahan ng Kapito')->sole();
        $this->assertSame(['Kapito', 'Irrigated'], [$created->barangay, $created->farm_type]);
        $this->assertSame($created->id, (int) $b->fresh()->farmer_organization_id);
        $this->assertSame(collect([$this->kapito->id, $created->id])->sort()->values()->all(), $this->memberships($farmer));

        // Reject: not a member, with the note on the farmer's card.
        $head = $this->mao();
        $this->actingAs($head)->post(route('mao.farm-associations.reject', $c), ['note' => 'There is no such association in Prenza.'])->assertSessionHasNoErrors();
        $this->assertSame(['not_member', 'There is no such association in Prenza.'], [$c->fresh()->association_status, $c->fresh()->association_note]);
        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertSee('Not a member')->assertSee('MAO note: There is no such association in Prenza.');
        $this->actingAs($head)->get(route('mao.farm-associations.index'))->assertSee('No association requests are waiting.');

        // Every decision is in the MAO Activity Log.
        $this->assertSame(1, MaoActivityLog::query()->where('user_id', $kapitoMao->id)->where('description', 'Created Farmer Association')->count());
        $this->assertSame(2, MaoActivityLog::query()->where('user_id', $kapitoMao->id)->where('description', 'Approved Farm Association')->where('action', 'approved')->count());
        $this->assertTrue(MaoActivityLog::query()->where('user_id', $head->id)->where('description', 'Rejected Farm Association')->where('action', 'rejected')->where('barangay', 'Prenza')->exists());

        // A decided request cannot be decided again.
        $this->actingAs($head)->post(route('mao.farm-associations.reject', $c), ['note' => 'Again'])->assertNotFound();
    }

    public function test_unlinking_one_farm_keeps_the_membership_another_farm_still_uses(): void
    {
        $farmer = $this->farmer();
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'North', 'association_status' => 'member', 'farmer_organization_id' => $this->kapito->id]);
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'South', 'association_status' => 'member', 'farmer_organization_id' => $this->kapito->id]);
        $north = Farm::query()->ownedBy($farmer)->where('farm_name', 'North')->sole();
        $south = Farm::query()->ownedBy($farmer)->where('farm_name', 'South')->sole();
        $form = ['farm_type' => 'Irrigated', 'farm_size_value' => '1.5', 'farm_size_unit' => Farm::UNIT_HECTARES, 'barangay' => 'Kapito'];

        $this->actingAs($farmer)->put(route('farmer.farms.update', $north->id), $form + ['farm_name' => 'North', 'association_status' => 'not_member'])->assertSessionHasNoErrors();
        $this->assertSame([$this->kapito->id], $this->memberships($farmer));

        // Archiving the last farm that uses it lets the membership go.
        $this->actingAs($farmer)->delete(route('farmer.farms.destroy', $south->id))->assertRedirect();
        $this->assertSame([], $this->memberships($farmer));
        $this->assertNull($farmer->farmerProfile()->first()->farmer_organization_id);
    }

    public function test_a_membership_the_mao_entered_without_a_farm_is_left_alone(): void
    {
        $farmer = $this->farmer();
        $farmer->farmerProfile()->first()->syncOrganizations([$this->prenza->id]);
        $this->addFarm($farmer, 'Kapito', ['association_status' => 'not_member']);

        $this->assertSame([$this->prenza->id], $this->memberships($farmer));
    }

    public function test_the_backfill_links_existing_farms_to_the_primary_organization_in_the_same_barangay(): void
    {
        $same = User::factory()->farmer()->create();
        $this->profile($same, 'Same', $this->prenza->id);
        DB::table('farmer_organization_members')->insert(['farmer_profile_id' => $same->farmerProfile->id, 'farmer_organization_id' => $this->prenza->id]);
        $other = User::factory()->farmer()->create();
        $this->profile($other, 'Other', $this->prenza->id);
        $none = User::factory()->farmer()->create();
        $this->profile($none, 'None');
        $answered = User::factory()->farmer()->create();
        $this->profile($answered, 'Answered', $this->prenza->id);

        // Farms as they were before the columns existed (written past the model).
        $farm = fn (User $u, string $barangay): int => DB::table('farms')->insertGetId(['user_id' => $u->id, 'farm_name' => 'Farm 1', 'barangay' => $barangay, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        $sameFarm = $farm($same, 'Prenza');
        $otherFarm = $farm($other, 'Kapito');
        $noneFarm = $farm($none, 'Prenza');
        $answeredFarm = $farm($answered, 'Prenza');
        DB::table('farms')->where('id', $answeredFarm)->update(['association_status' => 'not_member']);
        DB::table('farmer_organization_members')->where('farmer_profile_id', $same->farmerProfile->id)->delete();

        $this->assertSame(['linked' => 1, 'not_declared' => 2, 'no_organization' => 1, 'other_barangay' => 1], FarmAssociations::backfill());

        $this->assertSame([$this->prenza->id, 'member'], [(int) DB::table('farms')->where('id', $sameFarm)->value('farmer_organization_id'), DB::table('farms')->where('id', $sameFarm)->value('association_status')]);
        $this->assertTrue(DB::table('farmer_organization_members')->where('farmer_profile_id', $same->farmerProfile->id)->where('farmer_organization_id', $this->prenza->id)->exists());
        foreach ([$otherFarm, $noneFarm] as $id) {
            $this->assertSame([null, null], [DB::table('farms')->where('id', $id)->value('farmer_organization_id'), DB::table('farms')->where('id', $id)->value('association_status')]);
        }
        $this->assertSame('not_member', DB::table('farms')->where('id', $answeredFarm)->value('association_status'));

        // Safe to run again; the command prints the same counts.
        $this->artisan('farms:link-associations')->expectsTable(['Result', 'Farms'], [
            ['Linked to the primary organization', 0], ['Not declared', 2], ['  no primary organization', 1], ['  primary organization in another barangay', 1],
        ])->assertSuccessful();
    }

    public function test_staff_farmer_pages_list_each_farm_with_its_association(): void
    {
        $farmer = $this->farmer();
        $this->addFarm($farmer, 'Prenza', ['farm_name' => 'North', 'association_status' => 'member', 'farmer_organization_id' => $this->prenza->id]);
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'South', 'association_status' => 'not_member']);
        $profile = $farmer->farmerProfile()->first();

        foreach ([$this->mao(), User::factory()->create(['role' => User::ROLE_IT_EXPERT, 'status' => User::STATUS_ACTIVE])] as $staff) {
            $this->actingAs($staff)->get(route('farmer-profiles.show', ['farmer_profile' => $profile->id, 'modal' => 1]))->assertOk()
                ->assertSee('Association: Prenza Farmers Association')->assertSee('Not an association member');
            $this->actingAs($staff)->get(route('farmer-profiles.show', $profile->id))->assertOk()
                ->assertSee('North')->assertSee('Association: Prenza Farmers Association')->assertSee('Not an association member');
        }
    }

    public function test_the_farmer_is_told_in_their_language_when_the_mao_decides_and_the_rejection_opens_the_farm(): void
    {
        $farmer = $this->farmer();
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'A', 'association_status' => 'pending_other', 'association_other_name' => 'Saluysoy FA']);
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'B', 'association_status' => 'pending_other', 'association_other_name' => 'Bagong Samahan']);
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'C', 'association_status' => 'pending_other', 'association_other_name' => 'Wala']);
        [$a, $b, $c] = Farm::query()->ownedBy($farmer)->whereIn('farm_name', ['A', 'B', 'C'])->orderBy('farm_name')->get()->all();
        $mao = $this->mao();
        $notes = fn () => \App\Models\Notification::query()->where('user_id', $farmer->id)->where('category', 'like', 'farm-association:%')->orderBy('id')->get();

        $this->actingAs($mao)->post(route('mao.farm-associations.link', $a), ['farmer_organization_id' => $this->kapito->id]);
        // The farm type is required (farmer_organizations.farm_type cannot be empty).
        $this->actingAs($mao)->post(route('mao.farm-associations.store', $b), ['name' => 'Bagong Samahan', 'barangay' => 'Kapito'])->assertSessionHasErrors('farm_type');
        $this->actingAs($mao)->post(route('mao.farm-associations.store', $b), ['name' => 'Bagong Samahan', 'barangay' => 'Kapito', 'farm_type' => 'Rainfed'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['Association added to your farm', 'Association added to your farm'], $notes()->pluck('title')->all());
        $this->assertSame('A is now in Saluysoy Farmers Association. The Municipal Agriculture Office checked your request.', $notes()[0]->message);
        $this->assertSame('The Municipal Agriculture Office added Bagong Samahan to the list. B is now in it.', $notes()[1]->message);
        $this->assertSame(['farm-association:'.$a->id, 'farm-association:'.$b->id], $notes()->pluck('category')->all());

        // A farmer who reads in Filipino gets it in Filipino, with the MAO's note.
        $farmer->forceFill(['preferred_language' => 'fil'])->save();
        $this->actingAs($mao)->post(route('mao.farm-associations.reject', $c), ['note' => 'Walang ganitong samahan sa Kapito']);
        $rejected = $notes()->last();
        $this->assertSame('Hindi tinanggap ang hiling na samahan', $rejected->title);
        $this->assertSame('Hindi maidagdag ng Municipal Agriculture Office ang "Wala" para sa C. Paalala: Walang ganitong samahan sa Kapito. Puwede ninyong baguhin ang sagot sa pahina ng bukid.', $rejected->message);
        $this->assertSame('farm-association:'.$c->id, $rejected->category);

        // It opens that farm (My Farms once the farm is removed).
        $link = \App\Services\Farmer\FarmerMissingInfoNotifier::linkFor($rejected->category, 'fil', $farmer);
        $this->assertSame(['url' => route('farmer.farms.edit', $c->id), 'label' => 'Buksan ang bukid'], $link);
        $this->actingAs($farmer)->get(route('notifications.index'))->assertOk()->assertSee(route('farmer.farms.edit', $c->id), false);
        app(\App\Services\Farms\FarmService::class)->archive($c->fresh());
        $this->assertSame(route('farmer.my-farm'), \App\Services\Farmer\FarmerMissingInfoNotifier::linkFor($rejected->category, 'en', $farmer)['url']);
        $this->assertNull(FarmAssociations::notificationLink('setup:farm', 'en', $farmer));
    }

    public function test_restoring_an_archived_farm_brings_its_membership_back_with_the_original_date(): void
    {
        $farmer = $this->farmer();
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'North', 'association_status' => 'member', 'farmer_organization_id' => $this->kapito->id]);
        $this->addFarm($farmer, 'Prenza', ['farm_name' => 'South', 'association_status' => 'member', 'farmer_organization_id' => $this->prenza->id]);
        $profile = $farmer->farmerProfile()->first();
        DB::table('farmer_organization_members')->where('farmer_profile_id', $profile->id)->where('farmer_organization_id', $this->kapito->id)->update(['date_joined' => '2024-03-15']);
        $north = Farm::query()->ownedBy($farmer)->where('farm_name', 'North')->sole();
        $south = Farm::query()->ownedBy($farmer)->where('farm_name', 'South')->sole();
        $service = app(\App\Services\Farms\FarmService::class);
        $joined = fn (int $org): ?string => ($d = DB::table('farmer_organization_members')->where('farmer_profile_id', $profile->id)->where('farmer_organization_id', $org)->value('date_joined')) ? substr((string) $d, 0, 10) : null;

        // Archived: the membership goes, and the farm keeps the date joined.
        $service->archive($north);
        $this->assertSame([$this->prenza->id], $this->memberships($farmer));
        $this->assertSame('2024-03-15', substr((string) $north->fresh()->getRawOriginal('association_joined_on'), 0, 10));

        // Restored: back, with its original date (and the farm no longer holds it).
        $service->restore($north->fresh());
        $this->assertNull($north->fresh()->archived_at);
        $this->assertSame(collect([$this->kapito->id, $this->prenza->id])->sort()->values()->all(), $this->memberships($farmer));
        $this->assertSame('2024-03-15', $joined($this->kapito->id));
        $this->assertNull($north->fresh()->association_joined_on);

        // A membership removed some other way (no date kept) comes back joined today.
        $service->archive($south);
        DB::table('farms')->where('id', $south->id)->update(['association_joined_on' => null]);
        $service->restore($south->fresh());
        $this->assertSame(now()->toDateString(), $joined($this->prenza->id));

        // A farm restored while another farm still uses the association changes nothing.
        $this->addFarm($farmer, 'Kapito', ['farm_name' => 'East', 'association_status' => 'member', 'farmer_organization_id' => $this->kapito->id]);
        $service->archive($north->fresh());
        $this->assertSame('2024-03-15', $joined($this->kapito->id));
        $this->assertNull($north->fresh()->association_joined_on);
        $service->restore($north->fresh());
        $this->assertSame('2024-03-15', $joined($this->kapito->id));
    }
}
