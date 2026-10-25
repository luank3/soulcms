<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Media\Controllers\MediaController;
use SoulCMS\App\Modules\Media\Repositories\MediaRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Support\S3Storage;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class MediaControllerTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testLibraryShowsOnlyCurrentMembersUploadedMediaAndPresignedUrls(): void
    {
        [$container, $previous] = $this->container();
        $connection = $this->connection();
        $connection->query("INSERT INTO users (id, nick, name, email, pass, level, xu, `on`) VALUES (1, 'alice', 'Alice', '', '', 0, 0, 0)");
        $connection->query("INSERT INTO articles_file (id, time, filename, filecate, filesize, passphrase, type, author, status, price, blogid) VALUES (1, 100, 'cat.png', 'png', 20, '0', 's3', 'alice', 'public', 0, 0), (2, 90, 'dog.png', 'png', 30, '0', 's3', 'bob', 'public', 0, 0)");
        $config = $this->config('SoulCMSMediaLibraryTest');
        $session = $this->session('SoulCMSMediaLibraryTest');
        $request = new Request('GET', Uri::fromString('/media/library'), cookies: ['SoulCMSMediaLibraryTest' => session_id()]);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/media-test-views-' . bin2hex(random_bytes(5)), true));
        $storage = new S3Storage($config);
        $this->register($container, $config, $request, $auth, $session, $views, $storage);
        $controller = new MediaController($container, new MediaRepository($connection), $storage, $session);
        $controller->setActionRequest($request);

        try {
            $response = $controller->library($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('cat.png', $response->getBody());
            self::assertStringNotContainsString('dog.png', $response->getBody());
            self::assertStringContainsString('X-Amz-Signature=', $response->getBody());
        } finally {
            Container::swap($previous);
            $this->closeSession();
        }
    }

    #[RunInSeparateProcess]
    public function testUploadRejectsTemporaryFilesThatDidNotComeThroughHttpUpload(): void
    {
        [$container, $previous] = $this->container();
        $connection = $this->connection();
        $connection->query("INSERT INTO users (id, nick, name, email, pass, level, xu, `on`) VALUES (1, 'alice', 'Alice', '', '', 0, 0, 0)");
        $config = $this->config('SoulCMSMediaUploadTest');
        $session = $this->session('SoulCMSMediaUploadTest');
        $csrf = $session->token();
        $request = new Request('POST', Uri::fromString('/media/upload'), headers: ['Accept' => 'application/json'], cookies: ['SoulCMSMediaUploadTest' => session_id()], parsedBody: ['_token' => $csrf], files: [
            'document' => ['name' => 'tiny.png', 'type' => 'image/png', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK, 'size' => filesize(__FILE__)],
        ]);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/media-upload-views-' . bin2hex(random_bytes(5)), true));
        $calls = [];
        $storage = new S3Storage($config, static function (string $method, string $url, array $headers, string $body) use (&$calls): int {
            $calls[] = compact('method', 'url', 'headers', 'body');
            return 200;
        });
        $this->register($container, $config, $request, $auth, $session, $views, $storage);
        $controller = new MediaController($container, new MediaRepository($connection), $storage, $session);
        $controller->setActionRequest($request);

        \SoulCMS\Support\Lang::setLocale('en');
        try {
            $response = $controller->upload($request);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringContainsString('The file is invalid.', $response->getBody());
            self::assertCount(0, $calls);
            self::assertSame(0, (int) $connection->scalar('SELECT COUNT(*) FROM `articles_file`'));
        } finally {
            \SoulCMS\Support\Lang::setLocale('vi');
            Container::swap($previous);
            $this->closeSession();
        }
    }

    #[RunInSeparateProcess]
    public function testPublicDownloadRequiresPublicFreeLibraryMedia(): void
    {
        [$container, $previous] = $this->container();
        $connection = $this->connection();
        $connection->query("INSERT INTO articles_file (id, time, filename, filecate, filesize, passphrase, type, author, status, price, blogid) VALUES (1, 100, 'cat.png', 'png', 20, '0', 's3', 'alice', 'public', 0, 0), (2, 90, 'locked.png', 'png', 30, '0', 's3', 'alice', 'private', 0, 0)");
        $config = $this->config('SoulCMSMediaDownloadTest');
        $session = $this->session('SoulCMSMediaDownloadTest');
        $request = new Request('GET', Uri::fromString('/media/download/1/cat.png'), cookies: ['SoulCMSMediaDownloadTest' => session_id()]);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/media-download-views-' . bin2hex(random_bytes(5)), true));
        $storage = new S3Storage($config);
        $this->register($container, $config, $request, $auth, $session, $views, $storage);
        $controller = new MediaController($container, new MediaRepository($connection), $storage, $session);

        try {
            $download = $request->withAttribute('routeParams', ['id' => '1', 'filename' => 'cat.png']);
            $controller->setActionRequest($download);
            $response = $controller->download($download);
            self::assertSame(302, $response->getStatusCode());
            self::assertStringContainsString('response-content-disposition=inline%3B', (string) $response->getHeader('Location'));
            self::assertStringNotContainsString('X-Amz-Signature=', $response->getBody());

            $locked = $request->withAttribute('routeParams', ['id' => '2', 'filename' => 'locked.png']);
            $controller->setActionRequest($locked);
            self::assertSame(404, $controller->download($locked)->getStatusCode());
        } finally {
            Container::swap($previous);
            $this->closeSession();
        }
    }

    /** @return array{Container,?Container} */
    private function container(): array
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);

        return [$container, $previous];
    }

    private function connection(): Connection
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, pass TEXT, level INTEGER, xu INTEGER, `on` INTEGER, new_mail TEXT)',
            'CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)',
            'CREATE TABLE articles_file (id INTEGER PRIMARY KEY AUTOINCREMENT, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER, mua TEXT)',
        ] as $sql) {
            $connection->query($sql);
        }

        return $connection;
    }

    private function config(string $sessionName): Config
    {
        return new Config(['app' => [
            'url' => ['path' => ''],
            'session' => ['name' => $sessionName],
            'storage' => ['driver' => 's3', 's3' => [
                'endpoint' => 'https://objects.example.test', 'bucket' => 'private-bucket', 'region' => 'test-region-1', 'access_key' => 'key', 'secret_key' => 'secret', 'path_style' => true,
            ]],
            'uploads' => ['max_bytes' => 10000000, 'allowed_extensions' => ['php'], 'allowed_mime_types' => ['text/plain']],
        ]]);
    }

    private function session(string $name): SessionManager
    {
        session_name($name);
        $session = new SessionManager($name);
        $session->start();
        $session->put('uid', 1);
        $_COOKIE[$name] = session_id();

        return $session;
    }

    private function closeSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
    }

    private function register(Container $container, Config $config, Request $request, AuthManager $auth, SessionManager $session, ViewFactory $views, S3Storage $storage): void
    {
        foreach ([
            'config' => $config,
            Config::class => $config,
            'request' => $request,
            Request::class => $request,
            'auth' => $auth,
            AuthManager::class => $auth,
            'session' => $session,
            SessionManager::class => $session,
            'view' => $views,
            ViewFactory::class => $views,
            S3Storage::class => $storage,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
    }
}
