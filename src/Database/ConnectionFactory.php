<?php

declare(strict_types=1);

namespace SoulCMS\Database;

use PDO;
use SoulCMS\Database\Exception\DatabaseException;
use Throwable;

/**
 * Builds PDO connections for every supported driver.
 *
 * The factory is responsible for translating a driver-neutral configuration
 * array into a driver specific DSN, and for applying the per-driver
 * performance pragmas / session settings.
 */
final class ConnectionFactory
{
    /**
     * @param array<string,mixed> $config
     */
    public function create(array $config): Connection
    {
        $driver = Driver::fromName((string) ($config['driver'] ?? 'sqlite'));

        $pdo = new PDO(
            $this->buildDsn($driver, $config),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            $this->buildOptions($config)
        );

        $connection = new Connection($pdo, $driver);
        $this->applySessionSettings($connection, $driver, $config);

        return $connection;
    }

    /**
     * @param array<string,mixed> $config
     */
    private function buildDsn(Driver $driver, array $config): string
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = $config['port'] ?? null;
        $database = (string) ($config['database'] ?? '');

        return match ($driver) {
            Driver::Sqlite => 'sqlite:' . ($database !== '' ? $database : ':memory:'),
            Driver::MySql, Driver::MariaDb => sprintf(
                'mysql:host=%s;%sdbname=%s;charset=%s',
                $host,
                $port !== null && $port !== '' ? 'port=' . (int) $port . ';' : '',
                $database,
                (string) ($config['charset'] ?? 'utf8mb4')
            ),
            Driver::Postgres => sprintf(
                'pgsql:host=%s;%sdbname=%s%s',
                $host,
                $port !== null && $port !== '' ? 'port=' . (int) $port . ';' : '',
                $database,
                isset($config['sslmode']) ? ';sslmode=' . (string) $config['sslmode'] : ''
            ),
        };
    }

    /**
     * @param array<string,mixed> $config
     * @return array<int,mixed>
     */
    private function buildOptions(array $config): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if (isset($config['options']) && is_array($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $options[(int) $key] = $value;
            }
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $config
     */
    private function applySessionSettings(Connection $connection, Driver $driver, array $config): void
    {
        $pdo = $connection->pdo();

        try {
            if ($driver === Driver::Sqlite) {
                $pdo->exec('PRAGMA journal_mode = ' . (string) ($config['journal_mode'] ?? 'WAL'));
                $pdo->exec('PRAGMA synchronous = ' . (string) ($config['synchronous'] ?? 'NORMAL'));
                $pdo->exec('PRAGMA foreign_keys = ON');
                $pdo->exec('PRAGMA temp_store = MEMORY');
                $pdo->exec('PRAGMA cache_size = -16000');
                $pdo->exec('PRAGMA mmap_size = 268435456');
                $pdo->exec('PRAGMA busy_timeout = 5000');
            } elseif ($driver === Driver::Postgres) {
                $pdo->exec("SET TIME ZONE 'UTC'");
            } else {
                $pdo->exec("SET time_zone = '+00:00'");
            }
        } catch (Throwable $exception) {
            throw DatabaseException::fromQuery($exception, 'session settings');
        }
    }
}
