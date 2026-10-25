<?php

declare(strict_types=1);

use SoulCMS\Foundation\Env;
use SoulCMS\Foundation\Paths;

return [
    /*
    |--------------------------------------------------------------------------
    | Application identity
    |--------------------------------------------------------------------------
    */
    'name' => Env::string('APP_NAME', 'SoulCMS'),
    'description' => Env::string('APP_DESCRIPTION', 'SoulCMS - a fast, multi-database content management system.'),
    'keyword' => Env::string('APP_KEYWORDS', 'open source, cms, php, soulcms, sqlite, mysql, postgresql'),
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    */
    'env' => Env::string('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'key' => Env::string('APP_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | URL
    |--------------------------------------------------------------------------
    */
    'url' => [
        'scheme' => Env::string('APP_URL_SCHEME', 'http'),
        'host' => Env::string('APP_URL_HOST', 'localhost'),
        'path' => rtrim(Env::string('APP_URL_PATH', ''), '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localisation & time
    |--------------------------------------------------------------------------
    */
    'timezone' => Env::string('APP_TIMEZONE', 'UTC'),
    'locale' => Env::string('APP_LOCALE', 'vi'),
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    */
    'base_path' => Paths::base(),
    'modules_path' => Paths::modules(),
    'storage_path' => Paths::storage(),

    /*
    |--------------------------------------------------------------------------
    | Global middleware stack (executed in order)
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        SoulCMS\Http\Middleware\LocaleMiddleware::class,
        SoulCMS\Http\Middleware\RequestIdMiddleware::class,
        SoulCMS\Http\Middleware\SecurityHeadersMiddleware::class,
        // Compression sits outside the static handler: the asset middleware
        // answers directly, so anything above it still gets to post-process
        // the response.
        SoulCMS\Http\Middleware\CompressionMiddleware::class,
        SoulCMS\Http\Middleware\StaticAssetMiddleware::class,
        SoulCMS\Http\Middleware\CsrfMiddleware::class,
        SoulCMS\Http\Middleware\RateLimitMiddleware::class,
        SoulCMS\Http\Middleware\ConditionalGetMiddleware::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    */
    'session' => [
        'name' => Env::string('SESSION_NAME', 'SoulCMS'),
        'lifetime' => (int) Env::int('SESSION_LIFETIME', 86400),
        'same_site' => Env::string('SESSION_SAME_SITE', 'Lax'),
        // A session dies after this long, even when it is in use.
        'absolute_timeout' => (int) Env::int('SESSION_ABSOLUTE_TIMEOUT', 43200),
        // A session dies after this long without activity.
        'idle_timeout' => (int) Env::int('SESSION_IDLE_TIMEOUT', 1800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'rate_limit' => [
            'enabled' => Env::bool('RATE_LIMIT_ENABLED', true),
            'max_attempts' => (int) Env::int('RATE_LIMIT_MAX_ATTEMPTS', 30),
            'decay_seconds' => (int) Env::int('RATE_LIMIT_DECAY_SECONDS', 60),
        ],
        // An account is locked after this many failed logins.
        'login_max_attempts' => (int) Env::int('LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) Env::int('LOGIN_DECAY_SECONDS', 300),
        // A server side secret mixed into every password hash. Enable it
        // with APP_PASSWORD_PEPPER; hashes made before it was enabled keep
        // working.
        'password_pepper' => Env::string('APP_PASSWORD_PEPPER', ''),
        'csrf_exempt' => ['api.', 'headless.'],
        // Extra Content Security Policy sources, e.g. ["img-src" => ["https:"]].
        'csp_extra' => ['frame-src' => ['https://www.youtube-nocookie.com']],
        'headers' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */
    'storage' => [
        // Use "s3" for a private S3-compatible bucket; local storage remains the default.
        'driver' => Env::string('STORAGE_DRIVER', 'local'),
        's3' => [
            'endpoint' => Env::string('S3_ENDPOINT', ''),
            'bucket' => Env::string('S3_BUCKET', ''),
            'region' => Env::string('S3_REGION', ''),
            'access_key' => Env::string('S3_ACCESS_KEY', ''),
            'secret_key' => Env::string('S3_SECRET_KEY', ''),
            'path_style' => Env::bool('S3_PATH_STYLE', true),
        ],
    ],

    'uploads' => [
        'max_bytes' => (int) Env::int('UPLOAD_MAX_BYTES', 10 * 1024 * 1024),
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif',
            'pdf', 'txt', 'md', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'mp3', 'mp4', 'ogg', 'webm',
        ],
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
            'application/pdf', 'text/plain', 'text/markdown',
            'application/zip', 'application/x-zip-compressed',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'audio/mpeg', 'audio/ogg', 'video/mp4', 'video/webm',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    */
    'content' => [
        'per_page' => (int) Env::int('CONTENT_PER_PAGE', 10),
        'post_list_order_by' => ['id', 'time', 'update_time', 'view'],
        'post_list_sort' => ['asc', 'desc'],
        'user_list_order_by' => ['level', 'xu', 'reg'],
        'user_list_sort' => ['asc', 'desc'],
        'system_bot' => 'SystemBOT',
        'news_category' => 1,
    ],

    'mail' => [
        'host' => Env::string('MAIL_HOST', ''),
        'port' => (int) Env::int('MAIL_PORT', 587),
        'encryption' => Env::string('MAIL_ENCRYPTION', 'tls'),
        'username' => Env::string('MAIL_USERNAME', ''),
        'password' => Env::string('MAIL_PASSWORD', ''),
        'from_address' => Env::string('MAIL_FROM_ADDRESS', ''),
        'from_name' => Env::string('MAIL_FROM_NAME', Env::string('APP_NAME', 'SoulCMS')),
        'timeout' => (int) Env::int('MAIL_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'log_channel' => Env::string('LOG_CHANNEL', 'stack'),
    'log_level' => Env::string('LOG_LEVEL', 'info'),
];
