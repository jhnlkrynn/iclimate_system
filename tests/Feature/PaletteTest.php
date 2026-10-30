<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** The sage brand palette has one source, resources/palette.json; every copy that cannot read it directly must match it. */
class PaletteTest extends TestCase
{
    private function palette(): array
    {
        return json_decode(File::get(resource_path('palette.json')), true);
    }

    public function test_the_css_variables_match_the_palette(): void
    {
        $css = File::get(resource_path('css/palette.css'));
        foreach ($this->palette()['sage'] as $step => $hex) {
            $rgb = implode(' ', array_map('hexdec', str_split(ltrim($hex, '#'), 2)));
            $this->assertStringContainsString("--sage-{$step}: {$hex};", $css);
            $this->assertStringContainsString("--sage-{$step}-rgb: {$rgb};", $css);
        }
        $this->assertStringContainsString('--palette-ink: '.$this->palette()['ink'].';', $css);
    }

    public function test_the_config_reads_the_palette_and_the_base_is_728c69(): void
    {
        $this->assertSame('#728C69', config('palette.sage.500'));
        $this->assertSame($this->palette()['sage'], config('palette.sage'));
        $this->assertSame('72, 90, 66', config('palette.rgb.700'));
        $this->assertSame('#1F241D', config('palette.ink'));
    }

    public function test_every_css_entry_loads_the_palette(): void
    {
        foreach (['app.css', 'landing.css', 'ui-tokens.css'] as $entry) {
            $this->assertStringContainsString("@import './palette.css';", File::get(resource_path("css/{$entry}")), $entry);
        }
        $this->assertStringContainsString("@import './ui-tokens.css';", File::get(resource_path('css/guest.css')));
    }

    public function test_tailwind_takes_its_colours_from_the_palette(): void
    {
        $config = File::get(base_path('tailwind.config.js'));
        $this->assertStringContainsString("readFileSync(new URL('./resources/palette.json'", $config);
        $this->assertStringContainsString("brand: sage['700']", $config);
        $this->assertStringContainsString("'brand-dark': sage['800']", $config);
    }

    public function test_the_email_theme_uses_the_palette_brand_and_keeps_its_success_button(): void
    {
        $css = File::get(resource_path('views/vendor/mail/html/themes/default.css'));
        $this->assertStringContainsString($this->palette()['sage']['700'], $css);
        $this->assertStringContainsString($this->palette()['sage']['200'], $css);
        $this->assertStringNotContainsString('#17432A', $css);
        $this->assertStringContainsString('#16a34a', $css);   // .button-success is a semantic colour
    }

    public function test_the_landing_views_carry_no_hex_colours(): void
    {
        $files = [resource_path('views/welcome.blade.php'), resource_path('views/components/public-nav.blade.php'),
            resource_path('views/components/landing-heading.blade.php'), ...File::glob(resource_path('views/landing/*.blade.php'))];
        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b(?!-)|rgba?\(\d/', File::get($file), basename($file));
        }
    }

    public function test_the_app_theme_colour_is_the_brand(): void
    {
        $this->assertStringContainsString("<meta name=\"theme-color\" content=\"{{ config('palette.sage.700') }}\">",
            File::get(resource_path('views/layouts/app.blade.php')));
    }
}
