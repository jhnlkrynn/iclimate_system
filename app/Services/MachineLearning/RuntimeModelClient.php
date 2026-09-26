<?php

namespace App\Services\MachineLearning;

use App\Models\MlModel;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Talks to the Python ML service about which rice-yield model it has loaded.
 * Error messages are deliberately generic: they reach the IT Expert UI and the
 * audit log, and must not carry hosts, paths or stack traces.
 */
class RuntimeModelClient
{
    /**
     * What the service reports it has loaded (filename, sha256, version,
     * sklearn_version, loaded_at).
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get($this->baseUrl().'/model-info');
        } catch (Throwable) {
            throw new RuntimeException('The prediction service could not be reached.');
        }

        $data = $response->successful() ? $response->json() : null;

        if (! is_array($data) || ($data['loaded'] ?? false) !== true) {
            throw new RuntimeException('The prediction service did not report a loaded model.');
        }

        return $data;
    }

    /**
     * Ask the service to load and swap in $model, then confirm through
     * model-info that it is really the one being served.
     *
     * @return array<string, mixed> the verified model-info
     */
    public function switchTo(MlModel $model): array
    {
        $token = (string) config('services.farming_ai.admin_token', '');

        if ($token === '') {
            throw new RuntimeException('Model switching is not configured for the prediction service.');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->withHeaders(['X-Model-Admin-Token' => $token])
                ->post($this->baseUrl().'/model/switch', array_filter([
                    'path' => $model->file_path,
                    'version' => $model->version,
                    'sha256' => $model->compatibility['sha256'] ?? null,
                ], fn ($value) => $value !== null));
        } catch (Throwable) {
            throw new RuntimeException('The prediction service could not be reached.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('The prediction service refused or failed to load the model.');
        }

        $info = $this->info();

        if (! $this->matches($model, $info)) {
            throw new RuntimeException('The prediction service is not running the expected model.');
        }

        return $info;
    }

    /**
     * True when the service's loaded model is $model: same checksum when the
     * registry knows one, otherwise same file name and version.
     *
     * @param  array<string, mixed>  $info
     */
    public function matches(MlModel $model, array $info): bool
    {
        if (($info['loaded'] ?? false) !== true) {
            return false;
        }

        $sha = $model->compatibility['sha256'] ?? null;

        if ($sha !== null) {
            return strcasecmp((string) ($info['sha256'] ?? ''), (string) $sha) === 0;
        }

        return ($info['filename'] ?? null) === $model->fileName();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.farming_ai.url'), '/');
    }

    private function timeout(): int
    {
        return (int) config('services.farming_ai.model_switch_timeout', 30);
    }
}
