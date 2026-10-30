<?php

namespace Tests\Feature;

use App\Models\FieldReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The IT Users list sorts by name A–Z (ignoring case, id as tie-breaker) by default, with a Sort select for
 * Name Z–A, Newest and Oldest. Other CrudController modules keep their newest-first order.
 */
class UserListSortTest extends TestCase
{
    use RefreshDatabase;

    private User $it;

    protected function setUp(): void
    {
        parent::setUp();

        $this->it = User::factory()->create(['name' => 'Mara IT', 'role' => User::ROLE_IT_EXPERT, 'status' => User::STATUS_ACTIVE]);
        foreach (['charlie Cruz', 'Ana Reyes', 'bea Santos', 'Ana Reyes', 'Diego Lim'] as $name) {
            User::factory()->farmer()->create(['name' => $name, 'status' => User::STATUS_ACTIVE]);
        }
    }

    /** @return list<string> */
    private function listedNames(array $query = []): array
    {
        $response = $this->actingAs($this->it)->get(route('users.index', $query))->assertOk();

        return collect($response->viewData('records')->items())->pluck('name')->all();
    }

    public function test_the_first_page_lists_users_by_name_a_to_z_ignoring_case(): void
    {
        $this->assertSame(['Ana Reyes', 'Ana Reyes', 'bea Santos', 'charlie Cruz', 'Diego Lim', 'Mara IT'], $this->listedNames());

        // Same names keep a stable order (by id), so paging never repeats or skips one.
        $ids = collect($this->actingAs($this->it)->get(route('users.index'))->viewData('records')->items())
            ->where('name', 'Ana Reyes')->pluck('id')->all();
        $this->assertSame(collect($ids)->sort()->values()->all(), $ids);
    }

    public function test_the_sort_select_changes_the_order(): void
    {
        $this->assertSame(['Mara IT', 'Diego Lim', 'charlie Cruz', 'bea Santos', 'Ana Reyes', 'Ana Reyes'], $this->listedNames(['sort' => 'name_desc']));
        $this->assertSame(['Diego Lim', 'Ana Reyes', 'bea Santos', 'Ana Reyes', 'charlie Cruz', 'Mara IT'], $this->listedNames(['sort' => 'newest']));
        $this->assertSame(['Mara IT', 'charlie Cruz', 'Ana Reyes', 'bea Santos', 'Ana Reyes', 'Diego Lim'], $this->listedNames(['sort' => 'oldest']));
        // An unknown value falls back to Name A–Z.
        $this->assertSame('Ana Reyes', $this->listedNames(['sort' => 'bogus'])[0]);
    }

    public function test_the_filter_bar_shows_the_sort_select_with_name_a_to_z_chosen(): void
    {
        $this->actingAs($this->it)->get(route('users.index'))->assertOk()
            ->assertSee('name="sort"', false)
            ->assertSee('<option value="name_asc" selected>Name A–Z</option>', false);

        $this->actingAs($this->it)->get(route('users.index', ['sort' => 'newest']))->assertOk()
            ->assertSee('<option value="newest" selected>Newest</option>', false);
    }

    public function test_search_filters_and_paging_keep_the_order_and_the_count(): void
    {
        foreach (range(1, 30) as $i) {
            User::factory()->farmer()->create(['name' => 'Zed Farmer '.$i, 'email' => "zed{$i}@example.test", 'status' => User::STATUS_INACTIVE]);
        }

        $records = $this->actingAs($this->it)->get(route('users.index', ['search' => 'Reyes']))->viewData('records');
        $this->assertSame(['Ana Reyes', 'Ana Reyes'], collect($records->items())->pluck('name')->all());

        $records = $this->actingAs($this->it)->get(route('users.index', ['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE]))->viewData('records');
        $this->assertSame(['Ana Reyes', 'Ana Reyes', 'bea Santos', 'charlie Cruz', 'Diego Lim'], collect($records->items())->pluck('name')->all());

        // 36 users: page 1 shows 1–25, page 2 shows 26–36, and the links keep ?sort.
        $response = $this->actingAs($this->it)->get(route('users.index', ['sort' => 'name_desc']))->assertSee('Showing 1-25 of 36');
        $this->assertStringContainsString('sort=name_desc', $response->viewData('records')->nextPageUrl());

        $all = User::query()->orderByRaw('LOWER(name) DESC')->orderByDesc('id')->pluck('id')->all();
        $page2 = $this->actingAs($this->it)->get(route('users.index', ['sort' => 'name_desc', 'page' => 2]))
            ->assertSee('Showing 26-36 of 36')->viewData('records');
        $this->assertSame(array_slice($all, 25), collect($page2->items())->pluck('id')->all());
    }

    public function test_other_crud_modules_still_list_newest_first(): void
    {
        config(['iclimate.field_reports_ui' => true]);   // Field Reports pages are hidden by default
        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
        foreach (['Alpha canal', 'Zulu flood', 'Mike drainage'] as $title) {
            FieldReport::query()->create(['title' => $title, 'barangay' => 'Binubusan', 'issue_type' => 'Flooding', 'status' => FieldReport::STATUS_PENDING, 'reported_by' => $mao->id]);
        }

        $response = $this->actingAs($mao)->get(route('field-reports.index'))->assertOk()->assertDontSee('name="sort"', false);
        $this->assertSame(['Mike drainage', 'Zulu flood', 'Alpha canal'], collect($response->viewData('records')->items())->pluck('title')->all());

        $print = $this->actingAs($mao)->get(route('field-reports.index', ['print' => 1]))->assertOk();
        $this->assertSame(['Mike drainage', 'Zulu flood', 'Alpha canal'], collect($print->viewData('rows'))->pluck('title')->all());
    }
}
