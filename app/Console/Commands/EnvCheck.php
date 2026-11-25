<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnvCheck extends Command
{
    protected $signature = 'iclimate:env-check
        {--env-file= : Path to the .env file to check (defaults to base_path(".env"))}
        {--example-file= : Path to the .env.example file (defaults to base_path(".env.example"))}';

    protected $description = 'Compare .env against .env.example (missing keys, invalid quoting, deprecated keys, and production safety flags) without printing secret values.';

    public function handle(): int
    {
        $envPath = $this->option('env-file') ?: base_path('.env');
        $examplePath = $this->option('example-file') ?: base_path('.env.example');

        if (! is_file($examplePath)) {
            $this->error("Reference file not found: {$examplePath}");

            return self::FAILURE;
        }
        if (! is_file($envPath)) {
            $this->error("Environment file not found: {$envPath}");

            return self::FAILURE;
        }

        $example = $this->parseEnvFile($examplePath);
        $actual = $this->parseEnvFile($envPath);

        $missing = array_values(array_diff(array_keys($example['values']), array_keys($actual['values'])));
        $warnings = [];
        $errors = [];

        foreach ($actual['unquoted_special'] as $key) {
            $errors[] = "{$key}: value contains spaces or parentheses without quotes (must be quoted).";
        }
        if (array_key_exists('MAIL_ENCRYPTION', $actual['values']) && ! array_key_exists('MAIL_SCHEME', $actual['values'])) {
            $warnings[] = 'MAIL_ENCRYPTION is deprecated in Laravel 12; use MAIL_SCHEME instead.';
        }

        $appEnv = strtolower((string) ($actual['values']['APP_ENV'] ?? ''));
        $isProd = $appEnv === 'production';

        if ($isProd) {
            if (strtolower((string) ($actual['values']['APP_DEBUG'] ?? '')) === 'true') {
                $errors[] = 'APP_DEBUG=true in production (must be false).';
            }
            if (strtolower((string) ($actual['values']['LOG_LEVEL'] ?? '')) === 'debug') {
                $warnings[] = 'LOG_LEVEL=debug in production (use warning or info to avoid logging sensitive details).';
            }
            $secureCookie = strtolower((string) ($actual['values']['SESSION_SECURE_COOKIE'] ?? ''));
            if ($secureCookie !== 'true') {
                $warnings[] = 'SESSION_SECURE_COOKIE is not true in production (set true for HTTPS).';
            }
            $httpOnly = strtolower((string) ($actual['values']['SESSION_HTTP_ONLY'] ?? 'true'));
            if ($httpOnly !== 'true') {
                $errors[] = 'SESSION_HTTP_ONLY must be true in production.';
            }
            if (($actual['values']['DB_TIMEZONE'] ?? '') !== '+08:00') {
                $warnings[] = 'DB_TIMEZONE is not +08:00 (timestamps may shift in production).';
            }
        }

        if ($missing !== []) {
            $this->warn('Missing keys vs .env.example ('.count($missing).'): '.implode(', ', $missing));
        } else {
            $this->info('All .env.example keys are present.');
        }

        foreach ($warnings as $warning) {
            $this->warn('WARN: '.$warning);
        }
        foreach ($errors as $error) {
            $this->error('ERROR: '.$error);
        }

        if ($errors === [] && $warnings === [] && $missing === []) {
            $this->info('Environment check passed cleanly.');
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{values: array<string, string>, unquoted_special: list<string>}
     */
    private function parseEnvFile(string $path): array
    {
        $values = [];
        $unquotedSpecial = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
                continue;
            }
            [$rawKey, $rawVal] = explode('=', $trimmed, 2);
            $key = trim($rawKey);
            if ($key === '' || ! preg_match('/^[A-Z0-9_]+$/', $key)) {
                continue;
            }

            $val = trim($rawVal);
            $isQuoted = (str_starts_with($val, '"') && str_ends_with($val, '"'))
                || (str_starts_with($val, "'") && str_ends_with($val, "'"));

            if (! $isQuoted && $val !== '' && preg_match('/[\s()]/', $val)) {
                $unquotedSpecial[] = $key;
            }

            $values[$key] = trim($val, "\"'");
        }

        return ['values' => $values, 'unquoted_special' => $unquotedSpecial];
    }
}
