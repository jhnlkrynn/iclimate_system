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

    public function test_views_do_not_introduce_literal_en_fil_labels_or_untranslated_aria_labels(): void
    {
        $checkedFiles = array_merge(
            [resource_path('views/components/ai-chat-widget.blade.php')],
            glob(resource_path('views/layouts/*.blade.php')) ?: [],
        );

        foreach ($checkedFiles as $path) {
            $contents = file_get_contents($path) ?: '';
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            $this->assertDoesNotMatchRegularExpression(
                '/<(option|button|span|a)\b[^>]*>\s*(EN|FIL)\s*<\/\1>/i',
                $contents,
                "Literal EN/FIL UI text found in {$relative}."
            );

            preg_match_all('/aria-label="([^"]+)"/', $contents, $matches);
            foreach ($matches[1] as $label) {
                if (str_contains($label, '{{') || str_contains($label, '${')) {
                    continue;
                }
                $this->fail("Untranslated literal aria-label=\"{$label}\" found in {$relative}.");
            }
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
