<?php

declare(strict_types=1);

namespace SoulCMS\Support;

use SoulCMS\Database\Connection;
use SoulCMS\Foundation\Paths;

/**
 * A small key/value store for the administration settings.
 *
 * The store writes to the `settings` table when it exists and falls back to a
 * JSON file in the storage directory otherwise. The fallback keeps SoulCMS
 * compatible with an unmodified SketchCMS database, which does not have that
 * table.
 */
final class SettingsStore
{
    private const TABLE = 'settings';

    /** @var array<string,string>|null */
    private ?array $cache = null;

    public function __construct(
        private Connection $connection,
        private string $file = '',
    ) {
        $this->file = $this->file !== '' ? $this->file : Paths::storage('settings.json');
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $values = $this->all();
        $values[$key] = $value;

        $this->cache = $values;

        if ($this->hasTable()) {
            $exists = $this->connection->selectOne(
                sprintf('SELECT `%s` FROM `%s` WHERE `%s` = ? LIMIT 1', 'key', self::TABLE, 'key'),
                [$key]
            );

            if ($exists === null) {
                $this->connection->insert(self::TABLE, ['key' => $key, 'value' => $value]);
            } else {
                $this->connection->update(self::TABLE, ['value' => $value], ['key' => $key]);
            }

            return;
        }

        $this->writeFile($values);
    }

    /**
     * @param array<string,string> $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * @return array<string,string>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if ($this->hasTable()) {
            $values = [];

            foreach ($this->connection->select(sprintf('SELECT `%s`, `%s` FROM `%s`', 'key', 'value', self::TABLE)) as $row) {
                $values[(string) $row['key']] = (string) $row['value'];
            }

            return $this->cache = $values;
        }

        return $this->cache = $this->readFile();
    }

    private function hasTable(): bool
    {
        return $this->connection->tableExists(self::TABLE);
    }

    /**
     * @return array<string,string>
     */
    private function readFile(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $raw = @file_get_contents($this->file);

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $values = [];

        foreach ($decoded as $key => $value) {
            $values[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $values;
    }

    /**
     * @param array<string,string> $values
     */
    private function writeFile(array $values): void
    {
        $directory = dirname($this->file);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($this->file, json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
