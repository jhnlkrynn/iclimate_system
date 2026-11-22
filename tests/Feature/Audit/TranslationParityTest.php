<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Audit 2026-11-23 (M-6): every English string has a Filipino one, in every language file, so a farmer who chose
 * Filipino never meets English text. (English may lack some Filipino keys on purpose: farmer.conditions keeps the
 * weather provider's own English words.)
 */
class TranslationParityTest extends TestCase
{
    public function test_every_english_string_has_a_filipino_translation(): void
    {
        $missing = [];
        foreach (glob(lang_path('en/*.php')) as $file) {
            $name = basename($file);
            $this->assertFileExists(lang_path('fil/'.$name), "lang/fil/{$name} is missing");
            $fil = Arr::dot(require lang_path('fil/'.$name));
            foreach (Arr::dot(require $file) as $key => $english) {
                // An empty list on purpose (farmer.conditions in English) has no strings to translate.
                if ($english === [] || array_key_exists($key, $fil)) {
                    continue;
                }
                $missing[] = basename($name, '.php').'.'.$key;
            }
        }

        $this->assertSame([], $missing, 'English strings without a Filipino translation');
    }

    public function test_placeholders_match_in_both_languages(): void
    {
        $mismatch = [];
        foreach (glob(lang_path('en/*.php')) as $file) {
            $name = basename($file);
            $fil = Arr::dot(require lang_path('fil/'.$name));
            foreach (Arr::dot(require $file) as $key => $english) {
                if (! is_string($english) || ! is_string($fil[$key] ?? null)) {
                    continue;
                }
                preg_match_all('/:([a-z_]+)/', $english, $e);
                preg_match_all('/:([a-z_]+)/', $fil[$key], $f);
                $e = array_values(array_unique($e[1]));
                $f = array_values(array_unique($f[1]));
                sort($e);
                sort($f);
                if ($e !== $f) {
                    $mismatch[] = basename($name, '.php').".{$key} (en: ".implode(',', $e).' / fil: '.implode(',', $f).')';
                }
            }
        }

        $this->assertSame([], $mismatch, 'A :placeholder present in one language only');
    }
}
