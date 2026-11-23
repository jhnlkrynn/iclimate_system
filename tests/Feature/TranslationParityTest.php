<?php

namespace Tests\Feature;

use Tests\TestCase;

class TranslationParityTest extends TestCase
{
    public function test_english_and_filipino_translation_files_have_identical_keys(): void
    {
        $enFiles = collect(glob(lang_path('en/*.php')))->map(fn (string $p) => basename($p))->sort()->values()->all();
        $filFiles = collect(glob(lang_path('fil/*.php')))->map(fn (string $p) => basename($p))->sort()->values()->all();

        $this->assertSame($enFiles, $filFiles, 'English and Filipino lang directories must contain the same files.');

        foreach ($enFiles as $file) {
            $en = $this->flatten(require lang_path('en/'.$file));
            $fil = $this->flatten(require lang_path('fil/'.$file));

            $missingInFil = array_keys(array_diff_key($en, $fil));
            $missingInEn = array_keys(array_diff_key($fil, $en));

            $this->assertSame([], $missingInFil, "Missing keys in lang/fil/{$file}: ".implode(', ', $missingInFil));
            $this->assertSame([], $missingInEn, "Missing keys in lang/en/{$file}: ".implode(', ', $missingInEn));
        }
    }

    /**
     * @param  array<mixed>  $array
     * @return array<string, mixed>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $result += $this->flatten($value, $full);
            } else {
                $result[$full] = $value;
            }
        }

        return $result;
    }
}
