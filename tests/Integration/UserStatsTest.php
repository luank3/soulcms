<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
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
use SoulCMS\Support\Lang;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class UserStatsTest extends TestCase
{
    public function testCountsAllPostsAndCommentsForAnAuthor(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, time INTEGER, blocked INTEGER, sticked INTEGER, update_time INTEGER, category INTEGER, view INTEGER, author TEXT, buyfile INTEGER, `like` TEXT)');
        $connection->query('CREATE TABLE articles_cmt (id INTEGER PRIMARY KEY, blogid TEXT, author TEXT, comment TEXT, time INTEGER)');
        $connection->query("INSERT INTO articles_post (id, title, author) VALUES (1, 'First', 'alice'), (2, 'Second', 'alice'), (3, 'Other', 'bob')");
        $connection->query("INSERT INTO articles_cmt (blogid, author, comment, time) VALUES ('1', 'alice', 'comment 1', 1), ('1', 'alice', 'comment 2', 2), ('2', 'alice', 'comment 3', 3), ('3', 'bob', 'other author', 4)");

        $stats = (new ArticleRepository($connection))->statsByAuthor('alice');

        self::assertSame(['posts' => 2, 'comments' => 3], $stats);
    }

    public function testReputationActivityCountsRecentPostsAndCommentsOnly(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, author TEXT, time INTEGER)');
        $connection->query('CREATE TABLE articles_cmt (id INTEGER PRIMARY KEY, author TEXT, time INTEGER)');
        $connection->query("INSERT INTO articles_post VALUES (1, 'alice', 1000), (2, 'alice', 699), (3, 'bob', 1000)");
        $connection->query("INSERT INTO articles_cmt VALUES (1, 'alice', 900), (2, 'alice', 500), (3, 'bob', 900)");

        self::assertSame(
            ['posts' => 1, 'comments' => 1],
            (new UserRepository($connection))->reputationActivity('alice', 700)
        );
    }

    public function testProfileShowsPostAndCommentTotals(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $config = new Config(['app' => ['url' => ['path' => '']]]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $container->instance('config', $config);
        $container->instance('auth', new AuthManager(
            $connection,
            new Request('GET', Uri::fromString('/')),
            new PasswordHasher(),
            new Clock(),
            new SessionManager(),
            $config,
        ));
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-user-stats-views', true));
        $container->instance('view', $views);

        Lang::setLocale('en');
        try {
            $html = $views->render('user/detail', [
                'profile' => [
                    'id' => 1,
                    'nick' => 'alice',
                    'name' => 'Alice',
                    'reg' => 0,
                    'level' => 1,
                    'karma' => 0,
                    'xu' => 0,
                    'reg' => time() - 365 * 86400,
                    'email' => '',
                ],
                'waifu' => null,
                'posts' => [],
                'stats' => ['posts' => 12, 'comments' => 34],
                'reputation' => ['level' => 2, 'key' => 'participant', 'label' => 'Thành viên', 'score' => 32],
                'is_owner' => false,
                'is_online' => false,
            ]);
        } finally {
            Lang::setLocale('vi');
            Container::swap($previous);
        }

        self::assertStringContainsString('Posts', $html);
        self::assertStringContainsString('Reputation score', $html);
        self::assertStringContainsString('32', $html);
        self::assertStringContainsString('12', $html);
        self::assertStringContainsString('34', $html);
    }
}
