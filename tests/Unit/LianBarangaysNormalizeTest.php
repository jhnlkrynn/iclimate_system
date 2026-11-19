<?php

namespace Tests\Unit;

use App\Support\LianBarangays;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The shared barangay-name normalizer (LianBarangays::canonicalName) used by every map layer and the yield averages. */
class LianBarangaysNormalizeTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?string}> */
    public static function names(): array
    {
        return [
            'poblacion without dot' => ['BARANGAY 1 POB', 'Barangay 1 (Pob.)'],
            'poblacion 4' => ['BARANGAY 4 POB', 'Barangay 4 (Pob.)'],
            'brgy dot space' => ['BRGY. 1', 'Barangay 1 (Pob.)'],
            'brgy dot' => ['BRGY.1', 'Barangay 1 (Pob.)'],
            'official' => ['Barangay 3 (Pob.)', 'Barangay 3 (Pob.)'],
            'typo luyahan' => ['LUYAHN', 'Luyahan'],
            'typo balibago' => ['BALIBGO', 'Balibago'],
            'typo matabungkay' => ['Matabunkay', 'Matabungkay'],
            'spacing' => ['PUTINGKAHOY', 'Puting-Kahoy'],
            'no number guess' => ['Barangay 6', null],
            'not in Lian' => ['Calatagan', null],
            'dash' => ['—', null],
            'blank' => ['', null],
        ];
    }

    #[DataProvider('names')]
    public function test_names_resolve_to_the_official_barangay(string $input, ?string $expected): void
    {
        $this->assertSame($expected, LianBarangays::canonicalName($input));
    }
}
