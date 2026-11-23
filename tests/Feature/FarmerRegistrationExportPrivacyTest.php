<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class FarmerRegistrationExportPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function makeFarmerRegistrationReport(User $author): Report
    {
        return Report::query()->create([
            'report_type' => 'Farmer Registration Report',
            'title' => 'Farmer Registration Test',
            'generated_by' => $author->id,
            'is_generated' => true,
            'payload' => [
                'type_key' => 'farmer_registration',
                'columns' => [
                    'full_name' => 'Full Name',
                    'barangay' => 'Barangay',
                    'contact_number' => 'Contact Number',
                    'rsbsa_no' => 'RSBSA No.',
                ],
                'records' => [
                    [
                        'full_name' => 'Juan Dela Cruz',
                        'barangay' => 'Prenza',
                        'contact_number' => '09171234567',
                        'rsbsa_no' => '04-10-14-001-009876',
                    ],
                ],
                'summary' => [],
            ],
        ]);
    }

    public function test_non_head_mao_and_it_get_masked_contact_and_rsbsa_in_csv_xlsx_pdf_and_exports_are_logged(): void
    {
        $mao = User::factory()->maoPersonnel()->create(['is_mao_head' => false]);
        $maoTwo = User::factory()->maoPersonnel()->create(['is_mao_head' => false]);
        $report = $this->makeFarmerRegistrationReport($mao);

        // Non-head MAO CSV export: masked + logged with user and columns
        $csv = $this->actingAs($mao)->get('/reports/export?report='.$report->id);
        $csv->assertOk();
        $csvBody = $csv->streamedContent();
        $this->assertStringNotContainsString('09171234567', $csvBody);
        $this->assertStringNotContainsString('04-10-14-001-009876', $csvBody);
        $this->assertStringContainsString('*******4567', $csvBody);
        $this->assertStringContainsString('***************9876', $csvBody);

        $csvLog = SystemLog::query()->where('action', 'Exported Report CSV')->latest('id')->first();
        $this->assertNotNull($csvLog);
        $this->assertSame($mao->id, $csvLog->user_id);
        $this->assertStringContainsString('contact_number', $csvLog->details);
        $this->assertStringContainsString('rsbsa_no', $csvLog->details);

        // Second non-head MAO XLSX export: masked + logged with user and columns
        $xlsx = $this->actingAs($maoTwo)->get(route('reports.export.xlsx', ['report' => $report->id]));
        $xlsx->assertOk();
        $xlsxPath = $xlsx->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($xlsxPath) === true);
        $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringNotContainsString('09171234567', $sheetXml);
        $this->assertStringNotContainsString('04-10-14-001-009876', $sheetXml);
        $this->assertStringContainsString('*******4567', $sheetXml);
        $this->assertStringContainsString('***************9876', $sheetXml);

        $xlsxLog = SystemLog::query()->where('action', 'Exported Report Excel')->latest('id')->first();
        $this->assertNotNull($xlsxLog);
        $this->assertSame($maoTwo->id, $xlsxLog->user_id);
        $this->assertStringContainsString('contact_number', $xlsxLog->details);

        // Non-head MAO PDF export: logged with user and columns (and masked rows passed to view)
        $pdf = $this->actingAs($mao)->get(route('reports.export.pdf', ['report' => $report->id]));
        $pdf->assertOk();
        $pdfLog = SystemLog::query()->where('action', 'Exported Report PDF')->latest('id')->first();
        $this->assertNotNull($pdfLog);
        $this->assertSame($mao->id, $pdfLog->user_id);
        $this->assertStringContainsString('rsbsa_no', $pdfLog->details);
    }

    public function test_mao_head_receives_unmasked_farmer_registration_exports(): void
    {
        $head = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $report = $this->makeFarmerRegistrationReport($head);

        $csv = $this->actingAs($head)->get('/reports/export?report='.$report->id);
        $csv->assertOk();
        $csvBody = $csv->streamedContent();
        $this->assertStringContainsString('09171234567', $csvBody);
        $this->assertStringContainsString('04-10-14-001-009876', $csvBody);
    }
}
