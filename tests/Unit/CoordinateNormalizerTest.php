<?php

namespace Tests\Unit;

use App\Support\CoordinateNormalizer;
use PHPUnit\Framework\TestCase;

class CoordinateNormalizerTest extends TestCase
{
    public function test_equivalent_coordinate_spellings_share_one_pair_identity(): void
    {
        $this->assertSame('120.650000', CoordinateNormalizer::format('120.650'));
        $this->assertSame('120.650000', CoordinateNormalizer::format('120.65'));
        $this->assertSame('14.032000,120.650000', CoordinateNormalizer::pair('14.032', '120.650'));
    }

    public function test_distinct_centroids_are_not_collapsed(): void
    {
        $this->assertNotSame(
            CoordinateNormalizer::pair(14.0320331, 120.6807593),
            CoordinateNormalizer::pair(14.0320331, 120.6807599),
        );
    }
}
