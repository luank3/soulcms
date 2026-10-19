<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

use RuntimeException;
use SoulCMS\Database\ConnectionConfig;

/**
 * Validates the configuration at boot.
 *
 * A misconfigured application should fail immediately with a readable
 * message, not halfway through the first request with a cryptic error.
 */
final class ConfigValidator
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * @throws RuntimeException when the configuration is not usable
     */
    public function validate(): void
    {
        $this->errors = [];

        $this->checkUrl();
        $this->checkDatabase();
        $this->checkSecurity();

        if ($this->errors !== []) {
            throw new RuntimeException(
                "The application configuration is not usable:\n  - " . implode("\n  - ", $this->errors)
            );
        }
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function checkUrl(): void
    {
        $host = (string) $this->config('app.url.host', '');

        if ($host === '') {
            $this->errors[] = 'app.url.host is empty; set APP_URL_HOST.';
        }
    }

    private function checkDatabase(): void
    {
        $connection = ConnectionConfig::name(new Config($this->config));
        $settings = ConnectionConfig::settings(new Config($this->config));

        if ($settings === []) {
            $this->errors[] = sprintf('database.connections.%s is not defined.', $connection);

            return;
        }

        $driver = (string) ($settings['driver'] ?? '');

        if (!in_array($driver, ['sqlite', 'mysql', 'mariadb', 'pgsql'], true)) {
            $this->errors[] = sprintf(
                'database.connections.%s.driver is "%s"; supported drivers are sqlite, mysql, mariadb and pgsql.',
                $connection,
                $driver
            );

            return;
        }

        $database = (string) ($settings['database'] ?? '');

        if ($database === '') {
            $this->errors[] = sprintf('database.connections.%s.database is empty; set DB_DATABASE.', $connection);
        }

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            if ((string) ($settings['username'] ?? '') === '') {
                $this->errors[] = sprintf('database.connections.%s.username is empty; set DB_USERNAME.', $connection);
            }
        }
    }

    private function checkSecurity(): void
    {
        // The key is required in production, whatever the debug flag says.
        if ((string) $this->config('app.env', 'production') !== 'production') {
            return;
        }

        $key = (string) $this->config('app.key', '');

        if ($key === '') {
            $this->errors[] = 'app.key is empty; set APP_KEY before running in production.';
        } elseif (strlen($key) < 32) {
            $this->errors[] = 'app.key is shorter than 32 characters.';
        }
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }
}
