<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\FieldReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Print (2026-10): Climate Records, Rice Production, Farmer Profiles and Field Reports have a Print button. The print
 * page carries the office header, the print date and the active filters, and lists EVERY row that matches the filters
 * (not only the 25 on screen). A farmer prints their own harvest records from My Farm.
 */
class PrintListsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Municipal Agricultural Office – Lian, Batangas';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:30:00', 'Asia/Manila'));
        Http::fake(['*' => Http::response([], 500)]);
        // The Field Reports print page is checked with its pages switched on (hidden by default: FIELD_REPORTS_UI=false).
        config(['iclimate.field_reports_ui' => true]);
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function reports(int $count, string $barangay, string $status): void
    {
        $mao = $this->mao();
        foreach (range(1, $count) as $i) {
            FieldReport::query()->create(['title' => "{$barangay} {$status} report {$i}", 'barangay' => $barangay, 'issue_type' => 'Flooding',
                'description' => 'Water in the field.', 'status' => $status, 'reported_by' => $mao->id]);
        }
    }

    /** The data rows of the print page's table. */
    private function rows(string $html): int
    {
        return preg_match('#<tbody>(.*?)</tbody>#s', $html, $m) ? substr_count($m[1], '<tr>') : 0;
    }

    private function text(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)[^>]*>.*?</\1>#s', '', $html);

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    }

    public function test_the_four_staff_lists_have_a_print_button_that_keeps_the_filters(): void
    {
        $mao = $this->mao();

        foreach (['climate-records', 'rice-productions', 'farmer-profiles', 'field-reports'] as $module) {
            $html = $this->actingAs($mao)->get(route($module.'.index', ['search' => 'abc', 'page' => 2]))->assertOk()->getContent();

            $this->assertSame(1, preg_match('#<a class="btn btn-outline-light" href="([^"]+)" target="_blank" rel="noopener" data-print-list[^>]*>Print</a>#', $html, $m), $module);
            $url = html_entity_decode($m[1]);
            $this->assertStringStartsWith(route($module.'.index'), $url);
            $this->assertStringContainsString('search=abc', $url);
            $this->assertStringContainsString('print=1', $url);
            $this->assertStringNotContainsString('page=', $url);                 // the print page is never one page of the list
        }

        // Lists that were not asked for have no Print button, and ?print=1 changes nothing there.
        $this->actingAs($mao)->get(route('notifications.index'))->assertOk()->assertDontSee('data-print-list', false);
        $this->actingAs($mao)->get(route('notifications.index', ['print' => 1]))->assertOk()->assertDontSee(self::HEADER);
    }

    public function test_the_print_page_has_the_office_header_the_print_date_and_no_sidebar_or_list_buttons(): void
    {
        $this->reports(2, 'Prenza', FieldReport::STATUS_PENDING);

        $html = $this->actingAs($this->mao())->get(route('field-reports.index', ['print' => 1]))->assertOk()->getContent();
        $text = $this->text($html);

        $this->assertStringContainsString(self::HEADER.' Field Reports', $text);
        $this->assertStringContainsString('Printed: October 7, 2026 9:30 AM', $text);
        $this->assertStringContainsString('Filters: None (all records)', $text);
        $this->assertStringContainsString('Records: 2', $text);
        // A page of its own: no sidebar, top bar, chat launcher, filter form or row actions.
        foreach (['ic-sidebar', 'sidebar-link', 'topbar', 'icAiLauncher', 'name="search"', '>Edit<', '>Delete<', 'Create Field Report', 'pagination'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
        // Its two buttons stay off the paper.
        $this->assertStringContainsString('<div class="print-actions no-print">', $html);
        $this->assertMatchesRegularExpression('#@media print \{[^}]*\}\s*\.no-print \{ display: none !important; \}#s', $html);
        $this->assertStringContainsString('window.print()', $html);
        // "Back to the list" returns to the same filtered list.
        $this->assertStringContainsString('href="'.route('field-reports.index').'">Back to the list</a>', $html);
    }

    public function test_every_filtered_row_is_printed_not_only_the_page_on_screen(): void
    {
        $this->reports(32, 'Prenza', FieldReport::STATUS_PENDING);
        $this->reports(3, 'Cumba', FieldReport::STATUS_RESOLVED);
        $mao = $this->mao();

        // On screen: 25 of 35.
        $this->actingAs($mao)->get(route('field-reports.index'))->assertSee('Showing 1-25 of 35');

        $all = $this->actingAs($mao)->get(route('field-reports.index', ['print' => 1]))->getContent();
        $this->assertSame(35, $this->rows($all));
        $this->assertStringContainsString('Records: 35', $this->text($all));

        $filtered = $this->actingAs($mao)->get(route('field-reports.index', ['print' => 1, 'barangay' => 'Prenza', 'status' => 'Pending', 'search' => 'report']))->getContent();
        $this->assertSame(32, $this->rows($filtered));
        $this->assertStringContainsString('Filters: Search: report · Barangay: Prenza · Status: Pending', $this->text($filtered));
        $this->assertStringContainsString('Prenza Pending report 32', $filtered);
        $this->assertStringNotContainsString('Cumba Resolved report', $filtered);

        // The Unresolved tab is a filter too, and is named.
        $unresolved = $this->actingAs($mao)->get(route('field-reports.index', ['print' => 1, 'unresolved' => 1]))->getContent();
        $this->assertSame(32, $this->rows($unresolved));
        $this->assertStringContainsString('Filters: Status: Unresolved (not yet Resolved)', $this->text($unresolved));

        // Nothing matches: the page says so instead of printing an empty table.
        $none = $this->actingAs($mao)->get(route('field-reports.index', ['print' => 1, 'search' => 'zzz-nothing']))->getContent();
        $this->assertSame(0, $this->rows($none));
        $this->assertStringContainsString('No records match these filters.', $none);
    }

    public function test_farmer_profiles_print_with_their_columns(): void
    {
        foreach (range(1, 27) as $i) {
            FarmerProfile::factory()->create(['full_name' => "Farmer Number {$i}", 'barangay' => $i <= 20 ? 'Prenza' : 'Cumba', 'farm_type' => 'Irrigated']);
        }
        $mao = $this->mao();

        $html = $this->actingAs($mao)->get(route('farmer-profiles.index', ['print' => 1, 'barangay' => 'Prenza']))->assertOk()->getContent();

        $this->assertSame(20, $this->rows($html));
        $this->assertSame(1, preg_match('#<thead>(.*?)</thead>#s', $html, $m));
        $this->assertSame('# Name Barangay Farm Type Total Farm Area Status', $this->text($m[1]));
        $this->assertStringContainsString(self::HEADER.' Farmer Profiles', $this->text($html));
        $this->assertStringContainsString('Filters: Barangay: Prenza', $this->text($html));
        $this->assertStringNotContainsString('farmersByOrganizationChart', $html);                 // no chart on paper
    }

    public function test_climate_records_and_rice_production_print_every_row_of_the_filtered_list(): void
    {
        $mao = $this->mao();

        foreach (['climate-records' => 'Climate Records', 'rice-productions' => 'Rice Production Records'] as $module => $title) {
            $list = $this->actingAs($mao)->get(route($module.'.index'))->assertOk()->getContent();
            $this->assertSame(1, preg_match('#Showing \d+-\d+ of (\d+)#', $list, $m), $module);
            $total = (int) $m[1];

            $print = $this->actingAs($mao)->get(route($module.'.index', ['print' => 1]))->assertOk()->getContent();
            $this->assertStringContainsString(self::HEADER.' '.$title, $this->text($print), $module);
            $this->assertSame($total, $this->rows($print), $module);
            $this->assertStringContainsString('Records: '.number_format($total), $this->text($print), $module);
        }

        // A filter narrows the printed rows exactly as it narrows the list.
        $list = $this->actingAs($mao)->get(route('climate-records.index', ['season' => 'Wet']))->getContent();
        if (preg_match('#Showing \d+-\d+ of (\d+)#', $list, $m) && (int) $m[1] > 0) {
            $print = $this->actingAs($mao)->get(route('climate-records.index', ['season' => 'Wet', 'print' => 1]))->getContent();
            $this->assertSame((int) $m[1], $this->rows($print));
            $this->assertStringContainsString('Filters: Season: Wet', $this->text($print));
            $this->assertStringNotContainsString('<td>Dry</td>', $print);
        }
    }

    public function test_farmers_cannot_reach_the_staff_print_pages_and_print_their_own_harvest_records_from_my_farm(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Prenza']);
        $this->reports(1, 'Prenza', FieldReport::STATUS_PENDING);

        // Staff-only lists stay closed with or without ?print=1.
        $this->actingAs($farmer)->get(route('field-reports.index', ['print' => 1]))->assertForbidden();
        $this->actingAs($farmer)->get(route('farmer-profiles.index', ['print' => 1]))->assertDontSee('Farmer Number');
        // Pages a farmer reads in the farmer design never turn into the staff print page.
        $this->actingAs($farmer)->get(route('climate-records.index', ['print' => 1]))->assertDontSee('data-print-meta', false);

        // My Farm: the farmer's harvest records, with the same office header and the print date, sidebar and buttons hidden on paper.
        $html = $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()->getContent();
        $this->assertStringContainsString(self::HEADER, $html);
        $this->assertStringContainsString('onclick="window.print()"', $html);
        $this->assertStringContainsString('Printed: Oct 7, 2026', $this->text($html));
        $css = (string) file_get_contents(resource_path('css/farmer-records.css'));
        $this->assertMatchesRegularExpression('#@media print \{\s*\.ic-sidebar, nav\.topbar[^{]*\.fmf-no-print[^{]*\{ display: none !important; \}#', $css);

        auth()->logout();
        $this->get(route('field-reports.index', ['print' => 1]))->assertRedirect(route('login'));
    }

    public function test_a_list_printed_straight_from_the_browser_hides_the_sidebar_and_buttons(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertSame(1, preg_match('#@media print \{\s*\.ic-sidebar, nav\.topbar, [^{]*\{ display: none !important; \}#', $css, $m));
        foreach (['.ic-sidebar', 'nav.topbar', '.page-shell .btn', '.pagination', '.action-cluster'] as $hidden) {
            $this->assertStringContainsString($hidden, $m[0], $hidden);
        }
    }
}
