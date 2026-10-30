<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\MaoActivityLog;
use App\Models\MaoUserBarangay;
use App\Models\Notification;
use App\Models\User;
use App\Services\Farms\FarmAssociations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Closing the "not declared" gap: the farmer dashboard asks about one farm at a time ("Later" waits 7 days), and the
 * MAO's Undeclared Farms page answers for several farms of one barangay at once ("Set by MAO", no notification).
 */
class FarmAssociationPromptTest extends TestCase
{
    use RefreshDatabase;

    private FarmerOrganization $kapito;

    private FarmerOrganization $prenza;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kapito = FarmerOrganization::query()->create(['name' => 'Saluysoy Farmers Association', 'barangay' => 'Kapito', 'farm_type' => 'Irrigated']);
        $this->prenza = FarmerOrganization::query()->create(['name' => 'Prenza Farmers Association', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated']);
    }

    private function farmer(string $name = 'Juan Dela Cruz'): User
    {
        $farmer = User::factory()->farmer()->create(['name' => $name, 'barangay' => 'Kapito']);
        (new FarmerProfile(['user_id' => $farmer->id, 'full_name' => $name, 'barangay' => 'Kapito', 'farm_type' => 'Irrigated']))->saveQuietly();

        return $farmer;
    }

    private function farm(User $farmer, string $name, string $barangay = 'Kapito', array $more = []): Farm
    {
        $farm = new Farm(['user_id' => $farmer->id, 'farm_name' => $name, 'barangay' => $barangay, 'farm_type' => 'Irrigated']);
        $farm->forceFill($more)->save();   // association_status is not mass assignable

        return $farm;
    }

    private function mao(?string $barangay = null): User
    {
        $mao = User::factory()->maoPersonnel()->create();
        if ($barangay !== null) {
            MaoUserBarangay::query()->create(['user_id' => $mao->id, 'barangay' => $barangay]);
        }

        return $mao;
    }

    // ------------------------------------------------------------------ the farmer dashboard card

    public function test_the_dashboard_asks_about_one_undeclared_farm_at_a_time_and_stops_once_all_are_answered(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertDontSee('data-association-prompt', false);   // no farms

        $north = $this->farm($farmer, 'North', 'Kapito', ['is_primary' => true]);
        $south = $this->farm($farmer, 'South', 'Prenza');
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()
            ->assertSee('data-association-prompt="'.$north->id.'"', false)
            ->assertSee('Is North part of a farmers&#039; association?', false)
            ->assertSee('Saluysoy Farmers Association')->assertDontSee('Prenza Farmers Association');   // this farm's barangay only

        // Answering goes through the farm form's logic: the membership is added.
        $this->actingAs($farmer)->post(route('farmer.association-prompt.store'), ['farm_id' => $north->id, 'association_status' => 'member', 'farmer_organization_id' => $this->prenza->id])
            ->assertSessionHasErrors('farmer_organization_id');   // another barangay's association
        $this->actingAs($farmer)->post(route('farmer.association-prompt.store'), ['farm_id' => $north->id, 'association_status' => 'member', 'farmer_organization_id' => $this->kapito->id])
            ->assertRedirect(route('farmer.dashboard'))->assertSessionHas('success', 'Thank you. North is saved.');
        $this->assertSame(['member', $this->kapito->id, FarmAssociations::SET_BY_FARMER], [$north->fresh()->association_status, (int) $north->fresh()->farmer_organization_id, $north->fresh()->association_set_by]);
        $this->assertTrue(DB::table('farmer_organization_members')->where('farmer_organization_id', $this->kapito->id)->exists());

        // Next farm; "isn't listed" goes to the MAO's Association Requests.
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertSee('data-association-prompt="'.$south->id.'"', false)->assertSee('Prenza Farmers Association');
        $this->actingAs($farmer)->post(route('farmer.association-prompt.store'), ['farm_id' => $south->id, 'association_status' => 'pending_other', 'association_other_name' => 'Bagbag Irrigators']);
        $this->assertSame('pending_other', $south->fresh()->association_status);
        $this->actingAs($this->mao())->get(route('mao.farm-associations.index'))->assertSee('Bagbag Irrigators');

        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertDontSee('data-association-prompt', false);

        // Someone else's farm cannot be answered from here.
        $other = $this->farm($this->farmer('Pedro'), 'Theirs');
        $this->actingAs($farmer)->post(route('farmer.association-prompt.store'), ['farm_id' => $other->id, 'association_status' => 'not_member'])->assertNotFound();
    }

    public function test_the_card_is_in_filipino_for_a_farmer_who_reads_filipino(): void
    {
        $farmer = $this->farmer();
        $farmer->forceFill(['preferred_language' => 'fil'])->save();
        $this->farm($farmer, 'Bukid 1', 'Kapito', ['is_primary' => true]);

        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()
            ->assertSee('Kasapi ba ang Bukid 1 sa isang samahan ng magsasaka?')->assertSee('Mamaya na');
    }

    public function test_later_hides_the_card_for_seven_days(): void
    {
        $farmer = $this->farmer();
        $this->farm($farmer, 'North', 'Kapito', ['is_primary' => true]);

        $this->actingAs($farmer)->post(route('farmer.association-prompt.later'))->assertRedirect(route('farmer.dashboard'))
            ->assertSessionHas('success', 'We will ask again in 7 days.');
        $this->actingAs($farmer->fresh())->get(route('farmer.dashboard'))->assertDontSee('data-association-prompt', false);

        $this->travel(6)->days();
        $this->actingAs($farmer->fresh())->get(route('farmer.dashboard'))->assertDontSee('data-association-prompt', false);
        $this->travel(2)->days();
        $this->actingAs($farmer->fresh())->get(route('farmer.dashboard'))->assertSee('data-association-prompt', false);
    }

    // ------------------------------------------------------------------ MAO: Undeclared Farms

    public function test_the_mao_sees_counts_and_bulk_sets_only_same_barangay_associations_inside_their_coverage(): void
    {
        $juan = $this->farmer();
        $a = $this->farm($juan, 'A');
        $b = $this->farm($this->farmer('Ana'), 'B');
        $c = $this->farm($this->farmer('Rico'), 'C', 'Prenza');
        $answered = $this->farm($this->farmer('Lea'), 'D', 'Kapito', ['association_status' => 'not_member']);
        $kapitoMao = $this->mao('Kapito');

        $page = $this->actingAs($kapitoMao)->get(route('mao.farm-associations.undeclared'))->assertOk();
        $this->assertSame([['barangay' => 'Kapito', 'undeclared' => 2, 'declared' => 1]], $page->viewData('counts')->all());   // Prenza is outside their coverage
        $page->assertSee('Filter the list to one barangay first to set an association.')->assertDontSee('data-undeclared-set', false);
        $this->actingAs($kapitoMao)->get(route('mao.farm-associations.undeclared', ['barangay' => 'Kapito']))
            ->assertSee('data-undeclared-set', false)->assertSee('Saluysoy Farmers Association')->assertDontSee('Prenza Farmers Association')
            ->assertSee('data-undeclared-farm="'.$a->id.'"', false)->assertDontSee('data-undeclared-farm="'.$answered->id.'"', false);

        $set = fn (User $mao, array $ids, string $answer) => $this->actingAs($mao)->from(route('mao.farm-associations.undeclared'))
            ->post(route('mao.farm-associations.undeclared.update'), ['farm_ids' => $ids, 'answer' => $answer]);

        // Only an association of that barangay; a selection across barangays is refused; outside coverage is 403.
        $set($kapitoMao, [$a->id], 'org:'.$this->prenza->id)->assertSessionHas('error', 'Choose an association in Kapito.');
        $head = $this->mao();
        $set($head, [$a->id, $c->id], 'org:'.$this->kapito->id)->assertSessionHas('error', 'The ticked farms are in more than one barangay. Filter the list to one barangay first.');
        $set($kapitoMao, [$c->id], 'not_member')->assertForbidden();
        $set($kapitoMao, [$answered->id], 'not_member')->assertSessionHas('error');   // already answered
        $this->assertNull($a->fresh()->association_status);

        Notification::query()->delete();
        $set($kapitoMao, [$a->id, $b->id], 'org:'.$this->kapito->id)
            ->assertRedirect(route('mao.farm-associations.undeclared', ['barangay' => 'Kapito']))
            ->assertSessionHas('success', '2 farms in Kapito set to Saluysoy Farmers Association.');
        foreach ([$a, $b] as $farm) {
            $farm = $farm->fresh();
            $this->assertSame(['member', $this->kapito->id, FarmAssociations::SET_BY_MAO, $kapitoMao->id], [$farm->association_status, (int) $farm->farmer_organization_id, $farm->association_set_by, (int) $farm->association_set_by_user_id]);
            $this->assertNotNull($farm->association_set_at);
        }
        $this->assertTrue(DB::table('farmer_organization_members')->where('farmer_profile_id', $juan->farmerProfile->id)->where('farmer_organization_id', $this->kapito->id)->exists());
        $this->assertSame(0, Notification::query()->count());   // no notification for a bulk set
        $log = MaoActivityLog::query()->where('user_id', $kapitoMao->id)->latest('id')->firstOrFail();
        $this->assertSame(['edited', 'Kapito', '2 farms in Kapito: Saluysoy Farmers Association'], [$log->action, $log->barangay, $log->record_label]);

        $set($head, [$c->id], 'not_member')->assertSessionHas('success', '1 farm in Prenza set to Not a member.');
        $this->assertSame('not_member', $c->fresh()->association_status);

        // Only MAO.
        $this->actingAs($juan)->get(route('mao.farm-associations.undeclared'))->assertForbidden();
    }

    public function test_the_farmer_sees_set_by_mao_and_can_change_it(): void
    {
        $farmer = $this->farmer();
        $farm = $this->farm($farmer, 'North', 'Kapito', ['is_primary' => true]);
        $this->actingAs($this->mao())->post(route('mao.farm-associations.undeclared.update'), ['farm_ids' => [$farm->id], 'answer' => 'org:'.$this->kapito->id]);

        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()
            ->assertSee('Samahan: Saluysoy Farmers Association')->assertSee('data-association-set-by-mao', false)->assertSee('Set by MAO');
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertDontSee('data-association-prompt', false);   // answered

        // Saving the farm without changing the answer keeps it the MAO's; a different answer is the farmer's own.
        $form = ['farm_name' => 'North', 'barangay' => 'Kapito', 'farm_type' => 'Irrigated', 'farm_size_method' => Farm::SIZE_UNKNOWN];
        $this->actingAs($farmer)->put(route('farmer.farms.update', $farm->id), $form + ['association_status' => 'member', 'farmer_organization_id' => $this->kapito->id])->assertSessionHasNoErrors();
        $this->assertSame(FarmAssociations::SET_BY_MAO, $farm->fresh()->association_set_by);
        $this->actingAs($farmer)->put(route('farmer.farms.update', $farm->id), $form + ['association_status' => 'not_member'])->assertSessionHasNoErrors();
        $farm = $farm->fresh();
        $this->assertSame(['not_member', null, FarmAssociations::SET_BY_FARMER, null], [$farm->association_status, $farm->farmer_organization_id, $farm->association_set_by, $farm->association_set_by_user_id]);
        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertSee('Not a member')->assertDontSee('data-association-set-by-mao', false);
    }
}
