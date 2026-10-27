<?php

namespace Tests\Unit;

use App\Support\RiceVarieties\NsicCell;
use Tests\TestCase;

/** Cells of the NSIC list, checked against the client's PDF export of the same list. */
class NsicCellTest extends TestCase
{
    public function test_a_1_typed_for_i_is_read_as_intermediate_and_says_so(): void
    {
        // NSIC 2024 Rc 764SR, deadhearts: "YSB - 1" (its whiteheads cell reads "YSB - I"; "1" is not a code of the list).
        $this->assertSame(
            [['label' => 'Yellow Stem Borer', 'code' => 'I', 'note' => 'written "1" in the NSIC list', 'station' => null, 'kind' => 'Yellow Stem Borer']],
            NsicCell::reactions('YSB - 1'),
        );
        $this->assertSame('I', NsicCell::reactions('1')[0]['code']);
        $this->assertSame('intermediate', NsicCell::reactionLevel(NsicCell::reactions('YSB - 1')[0]['code']));
        // Real codes are unchanged, and a "1" inside a label is not a code.
        $this->assertSame(['I', 'MR'], array_column(NsicCell::reactions('WSB - I; YSB - MR'), 'code'));
        $this->assertSame([['label' => 'NCT 1', 'code' => 'S', 'note' => null, 'station' => 'NCT 1', 'kind' => null]], NsicCell::reactions('NCT 1 - S'));
    }

    // ------------------------------------------------------------------ results by testing station

    /** @return array<int, array{0: string|null, 1: string|null, 2: string|null}> station, kind, code of each entry */
    private function stations(string $cell): array
    {
        return array_map(fn (array $e): array => [$e['station'], $e['kind'], $e['code']], NsicCell::reactions($cell));
    }

    public function test_mestiso_40_blast_is_split_by_station_and_summarised_by_its_worst_result(): void
    {
        // NSIC 2011 Rc 266H (Mestiso 40), blast.
        $cell = 'PhilRice CES - I PhilRice Midsayap - S PhilRice Isabela - I UPLB - I VSU - I';

        $this->assertSame([
            ['PhilRice CES', null, 'I'], ['PhilRice Midsayap', null, 'S'], ['PhilRice Isabela', null, 'I'], ['UPLB', null, 'I'], ['VSU', null, 'I'],
        ], $this->stations($cell));
        $this->assertTrue(NsicCell::isByStation(NsicCell::reactions($cell)));
        $this->assertSame(['code' => 'S', 'varies' => true, 'sites' => 5], NsicCell::summary(NsicCell::reactions($cell)));
    }

    public function test_mestiso_40_and_42_other_station_cells(): void
    {
        // Bacterial leaf blight, Mestiso 40 and 42: I at all six stations — one result, not "varies by site".
        $blb = NsicCell::reactions('PhilRice CES - I PhilRice Midsayap - I PhilRice Isabela - I UPLB - I VSU - I WESVIARC - I');
        $this->assertSame(['code' => 'I', 'varies' => false, 'sites' => 6], NsicCell::summary($blb));

        // Mestiso 42 blast: R at UPLB, I elsewhere — the worst (I) is shown.
        $this->assertSame(['code' => 'I', 'varies' => true, 'sites' => 5], NsicCell::summary(NsicCell::reactions('PhilRice CES - I PhilRice Midsayap - I PhilRice Isabela - I UPLB - R VSU - I')));

        // Sheath blight (both): S beats I.
        $this->assertSame('S', NsicCell::summary(NsicCell::reactions('PhilRice CES - S PhilRice Midsayap - I PhilRice Isabela - S UPLB - S VSU - S WESVIARC - I'))['code']);

        // Mestiso 40 stem borer (whiteheads): station and borer kind kept apart; MR is worse than R but better than I.
        $whiteheads = 'PhilRice Agusan (WSB) - MR PhilRice Midsayap (WSB) - MR PhilRice Isabela (YSB) - MR PhilRice CES (YSB) - I VSU (YSB) - MR';
        $this->assertSame([
            ['PhilRice Agusan', 'White Stem Borer', 'MR'], ['PhilRice Midsayap', 'White Stem Borer', 'MR'], ['PhilRice Isabela', 'Yellow Stem Borer', 'MR'],
            ['PhilRice CES', 'Yellow Stem Borer', 'I'], ['VSU', 'Yellow Stem Borer', 'MR'],
        ], $this->stations($whiteheads));
        $this->assertSame(['code' => 'I', 'varies' => true, 'sites' => 5], NsicCell::summary(NsicCell::reactions($whiteheads)));
        // Mestiso 40 deadhearts: MR is worse than R.
        $this->assertSame(['code' => 'MR', 'varies' => true, 'sites' => 2], NsicCell::summary(NsicCell::reactions('PhilRice Midsayap (WSB) - MR PhilRice Isabela (YSB) - R')));

        // Tungro (both): the test kind and the station.
        $this->assertSame([
            ['PhilRice CES', 'Induced', 'S'], ['UPLB', 'Induced', 'S'], ['PhilRice Midsayap', 'Modified', 'S'], ['IRRI', 'Modified', 'S'],
        ], $this->stations('Induced (PhilRice CES) - S Induced (UPLB) - S Modified (PhilRice Midsayap) - S Modified (IRRI) - S'));
    }

    public function test_a_code_run_into_the_next_station_is_still_split(): void
    {
        $this->assertSame([['VSU', 'Yellow Stem Borer', 'R'], ['PhilRice Midsayap', 'White Stem Borer', 'I']], $this->stations('VSU (YSB) - RPhilRice Midsayap (WSB) - I'));
        $this->assertSame([['VSU', null, 'S'], ['WESVIARC', null, 'I']], $this->stations('VSU - SWESVIARC - I'));
    }

    public function test_kind_only_cells_are_not_by_station(): void
    {
        // "WSB - I; YSB - MR" keeps its two chips; a single code has no summary row either.
        $this->assertFalse(NsicCell::isByStation(NsicCell::reactions('WSB - I; YSB - MR')));
        $this->assertFalse(NsicCell::isByStation(NsicCell::reactions('MS (Screenhouse)')));
        $this->assertSame('S', NsicCell::summary([['code' => 'R'], ['code' => 'MS'], ['code' => 'S'], ['code' => 'I']])['code']);    // S > MS > I > MR > R
        $this->assertSame('MS', NsicCell::summary([['code' => 'MR'], ['code' => 'MS'], ['code' => 'I']])['code']);
        $this->assertNull(NsicCell::summary([['code' => null]]));
    }

    public function test_a_yield_range_counts_as_its_middle_and_keeps_both_ends(): void
    {
        // NSIC 2022 Rc 688H: only the low end of each range was read before (6695 kg/ha instead of 7955).
        $cell = NsicCell::yield('Central Luzon: 5918-8573 kg/ha; Western Mindanao: 7472-9853 kg/ha');
        $this->assertSame([
            ['label' => 'Central Luzon', 'kg' => 7246, 'min' => 5918, 'max' => 8573],
            ['label' => 'Western Mindanao', 'kg' => 8663, 'min' => 7472, 'max' => 9853],
        ], $cell['values']);
        $this->assertSame(7955, $cell['kg']);

        $this->assertSame(['kg' => 5050, 'min' => 4500, 'max' => 5600], array_intersect_key(NsicCell::yield('4500-5600')['values'][0], array_flip(['kg', 'min', 'max'])));
        $this->assertSame(7112, NsicCell::yield('Central Luzon: 4738 - 9485')['kg']);          // spaces around the dash
        $this->assertSame(4797, NsicCell::yield('National: 2615-6979')['kg']);
        $this->assertSame(5440, NsicCell::yield('1399-9480 kg/ha.')['kg']);
    }

    public function test_a_stated_average_wins_over_its_range_and_yield_range_is_not_a_region(): void
    {
        // NSIC 2022 Rc 690H.
        $cell = NsicCell::yield('Yield Range: 5602-8465 with average yield of 7300 at Central Luzon');

        $this->assertSame(7300, $cell['kg']);
        $this->assertSame([['label' => 'Central Luzon', 'kg' => 7300, 'min' => 5602, 'max' => 8465]], $cell['values']);
    }

    public function test_plain_and_regional_yields_are_unchanged(): void
    {
        $this->assertSame(['kg' => 5630, 'raw' => '5630', 'values' => [['label' => null, 'kg' => 5630]]], NsicCell::yield(5630));
        $this->assertSame(8309, NsicCell::yield('Southern Luzon (SL): 7888 , Zamboanga Peninsula and Western Mindanao (ZPWM): 8729')['kg']);
        $this->assertSame(5200, NsicCell::yield('5.2')['kg']);                                // tons per hectare
        $this->assertSame(['kg' => null, 'raw' => null, 'values' => []], NsicCell::yield(null));
    }
}
