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
        $this->checkMail();
        $this->checkSecurity();
        $this->checkStorage();

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

    private function checkMail(): void
    {
        $mail = new Config(['mail' => $this->config('app.mail', [])]);
        $host = $mail->string('mail.host');
        $username = $mail->string('mail.username');
        $password = $mail->string('mail.password');
        $from = $mail->string('mail.from_address');

        if ($host !== '' && preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
            $this->errors[] = 'MAIL_HOST must be a hostname without a URL scheme.';
        }
        if ($host !== '' && ($mail->int('mail.port') < 1 || $mail->int('mail.port') > 65535)) {
            $this->errors[] = 'MAIL_PORT must be between 1 and 65535.';
        }
        if ($host !== '' && !in_array(strtolower($mail->string('mail.encryption')), ['tls', 'ssl'], true)) {
            $this->errors[] = 'MAIL_ENCRYPTION must be tls or ssl.';
        }
        if (($username === '') !== ($password === '')) {
            $this->errors[] = 'MAIL_USERNAME and MAIL_PASSWORD must both be set or both be empty.';
        }
        if ($host !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[] = 'MAIL_FROM_ADDRESS must be a valid email address when SMTP is configured.';
        }
        if ($host !== '' && ($mail->int('mail.timeout') < 1 || $mail->int('mail.timeout') > 60)) {
            $this->errors[] = 'MAIL_TIMEOUT must be between 1 and 60 seconds.';
        }
    }

    private function checkStorage(): void
    {
        $driver = (string) $this->config('app.storage.driver', 'local');
        if (!in_array($driver, ['local', 's3'], true)) {
            $this->errors[] = 'app.storage.driver must be local or s3.';

            return;
        }

        if ($driver !== 's3') {
            return;
        }

        $s3 = new Config(['s3' => $this->config('app.storage.s3', [])]);
        $endpoint = $s3->string('s3.endpoint');
        $parsed = parse_url($endpoint);
        if (!is_array($parsed) || strtolower((string) ($parsed['scheme'] ?? '')) !== 'https' || empty($parsed['host'])) {
            $this->errors[] = 'S3_ENDPOINT must use HTTPS and include a host.';
        }

        foreach (['bucket' => 'S3_BUCKET', 'region' => 'S3_REGION', 'access_key' => 'S3_ACCESS_KEY', 'secret_key' => 'S3_SECRET_KEY'] as $key => $variable) {
            if ($s3->string('s3.' . $key) === '') {
                $this->errors[] = $variable . ' is required when STORAGE_DRIVER=s3.';
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
