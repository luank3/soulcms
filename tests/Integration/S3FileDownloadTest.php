<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Controllers\ArticleController;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Articles\Services\FilePurchaseService;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Events\Dispatcher;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Support\S3Storage;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class S3FileDownloadTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testFileBuyPostRequiresCsrfAndEnablesSignedDownloadForBuyer(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSPaidFilePurchaseTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-paid-file-session-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS paid file purchase test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

        $connection = $this->connection();
        $connection->query("INSERT INTO users (id, nick, name, email, level, xu, `on`, new_mail) VALUES (1, 'alice', 'Alice', '', 0, 100, 0, ''), (2, 'buyer', 'Buyer', '', 0, 100, 0, '')");
        $connection->query("INSERT INTO articles_post (id, title, slug, content, time, update_time, category, author, blocked, sticked, view, buyfile) VALUES (7, 'Post', 'post', '', 1, 1, 1, 'alice', 0, 0, 0, 0)");
        $connection->query("INSERT INTO articles_file (id, time, filename, filecate, filesize, passphrase, type, author, status, price, blogid, mua) VALUES (23, 1, 'report.pdf', 'pdf', 100, '0', 's3', 'alice', 'private', 10, 7, NULL)");
        $config = $this->config($sessionName);
        $session = new SessionManager($sessionName);
        $session->put('uid', 2);
        $csrf = $session->token();
        $request = new Request('POST', Uri::fromString('/view-file/23'), server: [
            'HTTP_USER_AGENT' => 'SoulCMS paid file purchase test',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ], parsedBody: ['action' => 'buy', '_token' => 'forged']);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-paid-file-view-' . bin2hex(random_bytes(5)), true));
        $articles = new ArticleRepository($connection);
        $users = new UserRepository($connection);
        $purchases = new FilePurchaseService($connection, $articles, $users);
        $storage = new S3Storage($config);
        $this->registerServices($container, $request, $config, $session, $auth, $views, $purchases, $storage);
        $controller = $this->controller($container, $connection, $articles, $users, $session);

        try {
            $forged = $request->withAttribute('routeParams', ['id' => '23']);
            $controller->setActionRequest($forged);
            self::assertSame(403, $controller->fileDetail($forged)->getStatusCode());
            self::assertSame(100, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 2'));
            self::assertNull($connection->scalar('SELECT `mua` FROM `articles_file` WHERE `id` = 23'));

            $purchase = new Request('POST', Uri::fromString('/view-file/23'), server: [
                'HTTP_USER_AGENT' => 'SoulCMS paid file purchase test',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ], parsedBody: ['action' => 'buy', '_token' => $csrf]);
            $controller->setActionRequest($purchase->withAttribute('routeParams', ['id' => '23']));
            self::assertSame(302, $controller->fileDetail($purchase)->getStatusCode());
            self::assertSame(90, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 2'));
            self::assertSame('buyer', $connection->scalar('SELECT `mua` FROM `articles_file` WHERE `id` = 23'));
            self::assertFalse($purchases->purchase(23, 2));

            $get = new Request('GET', Uri::fromString('/view-file/23'), server: [
                'HTTP_USER_AGENT' => 'SoulCMS paid file purchase test',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ]);
            $controller->setActionRequest($get->withAttribute('routeParams', ['id' => '23']));
            $downloadPage = $controller->fileDetail($get)->getBody();
            self::assertStringContainsString('X-Amz-Expires=300', $downloadPage);
            self::assertStringContainsString('Tải xuống', $downloadPage);
            self::assertStringContainsString('Bạn đã mua tệp này.', $downloadPage);
            self::assertStringContainsString('class="file-purchasers mt-5"', $downloadPage);
            self::assertStringContainsString('>Buyer</a>', $downloadPage);
        } finally {
            Container::swap($previous);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            $_COOKIE = [];
            foreach (glob($sessionPath . '/*') ?: [] as $entry) {
                unlink($entry);
            }
            rmdir($sessionPath);
        }
    }

    #[RunInSeparateProcess]
    public function testAnonymousCannotBuyAndPrivatePaidFileHasNoDownloadUrl(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $sessionName = 'SoulCMSAnonymousPaidFileTest';
        $sessionPath = sys_get_temp_dir() . '/soulcms-anonymous-paid-file-' . bin2hex(random_bytes(6));
        mkdir($sessionPath, 0700);
        session_save_path($sessionPath);
        $_COOKIE = [];
        $_SERVER['HTTP_USER_AGENT'] = 'SoulCMS anonymous file test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $connection = $this->connection();
        $connection->query("INSERT INTO users (id, nick, name, email, level, xu, `on`, new_mail) VALUES (1, 'alice', 'Alice', '', 0, 0, 0, '')");
        $connection->query("INSERT INTO articles_post (id, title, slug, content, time, update_time, category, author, blocked, sticked, view, buyfile) VALUES (7, 'Post', 'post', '', 1, 1, 1, 'alice', 0, 0, 0, 0)");
        $connection->query("INSERT INTO articles_file (id, time, filename, filecate, filesize, passphrase, type, author, status, price, blogid, mua) VALUES (23, 1, 'report.pdf', 'pdf', 100, '0', 's3', 'alice', 'private', 10, 7, NULL)");
        $config = $this->config($sessionName);
        $session = new SessionManager($sessionName);
        $request = new Request('POST', Uri::fromString('/view-file/23'), parsedBody: ['action' => 'buy']);
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), $session, $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-anonymous-file-view-' . bin2hex(random_bytes(5)), true));
        $articles = new ArticleRepository($connection);
        $users = new UserRepository($connection);
        $purchases = new FilePurchaseService($connection, $articles, $users);
        $this->registerServices($container, $request, $config, $session, $auth, $views, $purchases, new S3Storage($config));
        $controller = $this->controller($container, $connection, $articles, $users, $session);

        try {
            $post = $request->withAttribute('routeParams', ['id' => '23']);
            $controller->setActionRequest($post);
            self::assertSame(302, $controller->fileDetail($post)->getStatusCode());
            self::assertSame(0, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 1'));
            self::assertNull($connection->scalar('SELECT `mua` FROM `articles_file` WHERE `id` = 23'));

            $get = new Request('GET', Uri::fromString('/view-file/23'));
            $controller->setActionRequest($get->withAttribute('routeParams', ['id' => '23']));
            $body = $controller->fileDetail($get)->getBody();
            self::assertStringNotContainsString('X-Amz-Signature=', $body);
            self::assertStringNotContainsString('Mua tệp', $body);
        } finally {
            Container::swap($previous);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            $_COOKIE = [];
            foreach (glob($sessionPath . '/*') ?: [] as $entry) {
                unlink($entry);
            }
            rmdir($sessionPath);
        }
    }

    private function connection(): Connection
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, pass TEXT, level INTEGER, xu INTEGER, `on` INTEGER DEFAULT 0, new_mail TEXT)');
        $connection->query('CREATE TABLE users_waifu (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, meta_data TEXT, time INTEGER, blocked INTEGER, sticked INTEGER, update_time INTEGER, category INTEGER, view INTEGER, author TEXT, buyfile INTEGER, `like` TEXT)');
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY AUTOINCREMENT, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER, mua TEXT)');
        foreach ([
            'articles_category' => 'id INTEGER PRIMARY KEY, name TEXT, slug TEXT',
            'articles_chap' => 'id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, time INTEGER, box INTEGER, author TEXT',
            'articles_cmt' => 'id INTEGER PRIMARY KEY, blogid TEXT, author TEXT, comment TEXT, time INTEGER',
            'articles_tags' => 'id INTEGER PRIMARY KEY, name TEXT, slug TEXT, count INTEGER',
            'articles_post_tags' => 'post_id INTEGER, tag_id INTEGER',
        ] as $table => $columns) {
            $connection->query(sprintf('CREATE TABLE `%s` (%s)', $table, $columns));
        }

        return $connection;
    }

    private function config(string $sessionName): Config
    {
        return new Config(['app' => ['name' => 'SoulCMS', 'env' => 'testing', 'url' => ['path' => ''], 'session' => ['name' => $sessionName], 'storage' => ['s3' => [
            'endpoint' => 'https://objects.example.test', 'bucket' => 'private-bucket', 'region' => 'test-region-1', 'access_key' => 'test-key', 'secret_key' => 'test-secret', 'path_style' => true,
        ]]]]);
    }

    private function controller(Container $container, Connection $connection, ArticleRepository $articles, UserRepository $users, SessionManager $session): ArticleController
    {
        return new ArticleController($container, $articles, $users, $connection, new Dispatcher(), $session);
    }

    private function registerServices(Container $container, Request $request, Config $config, SessionManager $session, AuthManager $auth, ViewFactory $views, FilePurchaseService $purchases, S3Storage $storage): void
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
            FilePurchaseService::class => $purchases,
            S3Storage::class => $storage,
            'csp' => new \SoulCMS\Http\Csp(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
    }
}
