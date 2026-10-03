<?php

namespace Tests\Unit;

use App\Models\PlantingAdvisory;
use App\Support\FarmerAdvisoryView;
use PHPUnit\Framework\TestCase;

/** The farmer popup only rewords an advisory: same facts, same numbers, no system wording. */
class FarmerAdvisoryViewTest extends TestCase
{
    private function pagasa(array $attributes = []): PlantingAdvisory
    {
        return new PlantingAdvisory($attributes + [
            'title' => 'PAGASA Batangas Rainfall Advisory',
            'source' => 'PAGASA Online Advisory Page',
            'severity' => 'information',
            'summary' => 'PAGASA Update: Light to moderate rains expected in Batangas. Check field moisture before irrigating or applying fertilizer.',
            'message' => "Official PAGASA Weather Advisory for Batangas.\n\n"
                ."• Weather Situation: Light to moderate rainfall and cloudy skies are expected.\n"
                ."• Farm Guidance: Inspect field moisture before watering or fertilizing.\n"
                ."• Details: ses the likelihood of below-normal rainfall, which may lead to adverse climate impacts\n"
                .'• Source: PAGASA Online Weather Bulletin',
            'recommended_action' => 'Check paddy water levels before watering or fertilizing. Adjust farm tasks according to actual rainfall in your area.',
            'source_url' => 'https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd',
        ]);
    }

    public function test_pagasa_advisory_is_reworded_without_changing_its_meaning(): void
    {
        $view = new FarmerAdvisoryView($this->pagasa());

        $this->assertSame([
            'Light to moderate rains expected in Batangas.',
            'Check field moisture before irrigating or applying fertilizer.',
        ], $view->quickSummary());

        $effects = $view->farmEffects();
        $this->assertSame('Light to moderate rainfall and cloudy skies are expected.', $effects[0]);
        $this->assertContains('Your field may get extra water from the rain.', $effects);
        $this->assertEmpty(preg_grep('/heavy|flood|adverse|below-normal/i', $effects), 'never stronger than the advisory, no raw page text');

        $actions = $view->actions();
        $this->assertSame('Check paddy water levels before watering or fertilizing.', $actions[0]);
        $this->assertContains('Inspect field moisture before watering or fertilizing.', $actions);
        $this->assertContains('Keep checking for new advisories and PAGASA updates.', $actions);

        $this->assertSame('PAGASA', $view->sourceName());
        $this->assertSame('All barangays in Lian', $view->area());
        $this->assertSame('https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd', $view->officialUrl());
        $this->assertNull($view->alertLevel());
    }

    public function test_generated_advisory_drops_system_notes(): void
    {
        $view = new FarmerAdvisoryView(new PlantingAdvisory([
            'title' => '7-Day Heavy Rainfall Warning',
            'source' => 'Open-Meteo + iClimate Rules',
            'severity' => 'high',
            'target_barangay' => 'Prenza',
            'summary' => '7-Day outlook: Heavy rainfall appears within the checked forecast outlook.',
            'message' => '7-Day outlook: Heavy rainfall appears within the checked forecast outlook. Forecast period: 2026-09-12 to 2026-09-18. Horizon: seven-day farm advisory outlook for Lian, Batangas. This advisory is generated from live Lian, Batangas forecast data and iClimate decision-support rules.',
            'recommended_action' => 'Clear drainage canals, secure farm tools and inputs, monitor low-lying fields, and follow official local government or PAGASA warnings.',
        ]));

        $this->assertSame(['Heavy rainfall appears within the checked forecast outlook.'], $view->quickSummary());
        $effects = $view->farmEffects();
        $this->assertEmpty(preg_grep('/Forecast period|Horizon|generated from/i', $effects));
        $this->assertContains('Low-lying fields may flood or hold too much water.', $effects);
        $this->assertSame('Barangay Prenza, Lian', $view->area());
        $this->assertSame('Alert level: High', $view->alertLevel()['label']);
        $this->assertNull($view->officialUrl());
    }

    public function test_weather_values_are_passed_through_unchanged(): void
    {
        $cards = FarmerAdvisoryView::weatherCards([
            ['key' => 'rainfall', 'label' => 'Expected rainfall', 'value' => '0.9 mm', 'detail' => 'total over the period'],
            ['key' => 'probability', 'label' => 'Rain probability', 'value' => '91%', 'detail' => 'highest chance of rain in the period'],
            ['key' => 'temperature', 'label' => 'Maximum temperature', 'value' => null, 'detail' => null],
            ['key' => 'wind', 'label' => 'Strongest wind', 'value' => '5.1 km/h', 'detail' => 'sustained wind · gusts up to 12 km/h'],
        ]);

        $this->assertSame(['Expected Rain', 'Chance of Rain', 'Highest Temperature', 'Wind Speed'], array_column($cards, 'label'));
        $this->assertSame(['0.9 mm', '91%', null, '5.1 km/h'], array_column($cards, 'value'));
        $this->assertSame('Short gusts may reach 12 km/h', $cards[3]['note']);
        $this->assertNull($cards[2]['note']);
    }
}
