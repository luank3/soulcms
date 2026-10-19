<?php

declare(strict_types=1);

use SoulCMS\Foundation\Env;
use SoulCMS\Foundation\Paths;

$sqliteDatabase = Env::string('DB_DATABASE', Paths::storage('soulcms.sqlite'));

/*
|--------------------------------------------------------------------------
| Database connections
|--------------------------------------------------------------------------
|
| SoulCMS speaks four dialects out of the box: SQLite, MySQL, MariaDB and
| PostgreSQL. The connection used by the application is the "default" one;
| additional connections can be declared below and resolved by name.
|
*/
return [
    /*
     | The name of the connection in use. "default" is the connection below;
     | additional connections can be declared and selected here.
     */
    'default' => Env::string('DB_CONNECTION_NAME', 'default'),

    'connections' => [

        'default' => [
            'driver' => Env::string('DB_CONNECTION', 'sqlite'),
            'host' => Env::string('DB_HOST', '127.0.0.1'),
            'port' => Env::int('DB_PORT', 0) ?: null,
            'database' => $sqliteDatabase,
            'username' => Env::string('DB_USERNAME', ''),
            'password' => Env::string('DB_PASSWORD', ''),
            'charset' => Env::string('DB_CHARSET', 'utf8mb4'),
            'collation' => Env::string('DB_COLLATION', 'utf8mb4_general_ci'),
            'sslmode' => Env::string('DB_SSLMODE', ''),
            'journal_mode' => Env::string('DB_SQLITE_JOURNAL_MODE', 'WAL'),
            'synchronous' => Env::string('DB_SQLITE_SYNCHRONOUS', 'NORMAL'),
        ],

        /*
        | Example of a second connection (used by the media module to store
        | uploads in a separate database when required):
        |
        | 'media' => [
        |     'driver' => 'pgsql',
        |     'host' => '127.0.0.1',
        |     'port' => 5432,
        |     'database' => 'soulcms_media',
        |     'username' => 'soulcms',
        |     'password' => 'secret',
        | ],
        */
    ],
];
