<?php

declare(strict_types=1);

namespace SoulCMS\Database;

use SoulCMS\Foundation\Config;

/**
 * Resolves the database connection configuration.
 *
 * The application and the configuration validator must agree on *which*
 * connection is in use, otherwise the validator can pass a configuration the
 * application then fails to connect with. This class is the single place
 * where that decision is made.
 */
final class ConnectionConfig
{
    /**
     * The name of the connection in use.
     */
    public static function name(Config $config): string
    {
        $name = (string) $config->get('database.default', 'default');

        return $name === '' ? 'default' : $name;
    }

    /**
     * The settings of the connection in use.
     *
     * @return array<string,mixed>
     */
    public static function settings(Config $config): array
    {
        $connections = (array) $config->get('database.connections', []);
        $name = self::name($config);

        $settings = (array) ($connections[$name] ?? []);

        if ($settings === []) {
            $settings = (array) ($connections['default'] ?? []);
        }

        return $settings;
    }

    /**
     * The driver of the connection in use.
     */
    public static function driver(Config $config): string
    {
        $settings = self::settings($config);

        return (string) ($settings['driver'] ?? 'sqlite');
    }
}
