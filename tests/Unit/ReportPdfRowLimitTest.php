<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportExtrasController;
use PHPUnit\Framework\TestCase;

/**
 * Verification 2026-11-18: an 871-row report PDF exhausted PHP-FPM's 128 MB and answered 500. The PDF now holds a
 * bounded number of cells (measured ~110 MB peak at 250 rows x 6 columns) and points to Excel for every row.
 */
class ReportPdfRowLimitTest extends TestCase
{
    public function test_the_pdf_row_limit_follows_the_cell_budget(): void
    {
        $this->assertSame(250, ReportExtrasController::pdfRowLimit(6));
        $this->assertSame(250, ReportExtrasController::pdfRowLimit(3), 'narrow tables are not given more than the measured case');
        $this->assertSame(125, ReportExtrasController::pdfRowLimit(12));
        $this->assertSame(50, ReportExtrasController::pdfRowLimit(100), 'very wide tables still show a first page of rows');
        $this->assertLessThanOrEqual(ReportExtrasController::PDF_CELL_LIMIT, 8 * ReportExtrasController::pdfRowLimit(8));
    }
}
