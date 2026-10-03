<?php

namespace App\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Keeps pages loading when the compiled assets are missing (public/build not built yet).
 * Without this, every page using @vite returns a 500; now the page renders and a warning is logged.
 */
class SafeVite extends Vite
{
    private bool $warned = false;

    public function __invoke($entrypoints, $buildDirectory = null)
    {
        try {
            return parent::__invoke($entrypoints, $buildDirectory);
        } catch (ViteManifestNotFoundException $e) {
            if (! $this->warned) {
                $this->warned = true;
                Log::warning($e->getMessage().' — run "npm install && npm run build" to compile the CSS/JS assets.');
            }

            return new HtmlString('');
        }
    }
}
