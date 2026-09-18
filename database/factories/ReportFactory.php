<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        $reportTypeKey = fake()->randomElement(array_keys(Report::TYPES));
        $reportType = Report::TYPES[$reportTypeKey];

        return [
            'report_type' => $reportType,
            'title' => $reportType.' - '.now()->format('Y-m-d H:i'),
            'generated_by' => User::factory()->maoPersonnel(),
            'is_generated' => true,
            'payload' => [
                'report_type' => $reportTypeKey,
                'filters' => [],
                'columns' => [],
                'records' => [],
                'row_count' => 0,
                'generated_at' => now()->toDateTimeString(),
                'format' => 'screen',
            ],
        ];
    }
}
