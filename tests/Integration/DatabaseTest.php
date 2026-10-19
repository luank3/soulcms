<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Database\Installer;
use SoulCMS\Database\Query\QueryBuilder;
use SoulCMS\Database\Schema\SchemaLoader;

/**
 * Exercises the portable schema against a real (in memory) SQLite database.
 */
final class DatabaseTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $this->connection = new Connection($pdo, Driver::Sqlite);
    }

    public function testInstallerCreatesEverySketchCmsTable(): void
    {
        $created = (new Installer($this->connection))->install();

        self::assertContains('users', $created);
        self::assertContains('articles_post', $created);
        self::assertContains('articles_category', $created);
        self::assertContains('articles_chap', $created);
        self::assertContains('articles_cmt', $created);
        self::assertContains('articles_file', $created);
        self::assertContains('articles_tags', $created);
        self::assertContains('articles_post_tags', $created);
        self::assertContains('chat', $created);
        self::assertContains('mail', $created);
        self::assertContains('users_waifu', $created);
    }

    public function testSchemaMatchesTheSketchCmsColumnLayout(): void
    {
        (new Installer($this->connection))->install();

        $expected = [
            'users' => ['id', 'email', 'nick', 'name', 'pass', 'reset_token', 'reset_expires', 'failed_logins',
                'lock_time', 'sex', 'avatar', 'cover', 'new_mail', 'mail_list', 'blocklist', 'level', 'xu',
                'tiemluc', 'reg', 'on', 'login', 'status', 'karma', 'time_shoutbox', 'sft_time', 'sft_level',
                'karma_daily_time', 'karma_level', 'remember_token'],
            'articles_post' => ['id', 'title', 'slug', 'content', 'meta_data', 'time', 'blocked', 'sticked',
                'update_time', 'category', 'view', 'author', 'buyfile', 'like'],
            'articles_category' => ['id', 'community', 'name', 'slug', 'content', 'keyword', 'meta_data'],
            'articles_chap' => ['id', 'title', 'slug', 'content', 'time', 'box', 'author'],
            'articles_cmt' => ['id', 'time', 'comment', 'author', 'blogid'],
            'articles_file' => ['id', 'time', 'filename', 'filecate', 'filesize', 'passphrase', 'type', 'author',
                'status', 'price', 'saleoff', 'condition', 'mua', 'blogid'],
            'articles_tags' => ['id', 'name', 'slug', 'count'],
            'articles_post_tags' => ['post_id', 'tag_id'],
            'chat' => ['id', 'name', 'time', 'comment'],
            'mail' => ['id', 'sender_receiver', 'nick', 'content', 'time', 'update_time', 'type', 'view'],
            'users_waifu' => ['id', 'user_id', 'header', 'rleft', 'rright', 'close'],
        ];

        foreach ($expected as $table => $columns) {
            $actual = $this->connection->listColumns($table);

            self::assertSame($columns, $actual, sprintf('Column mismatch on table "%s".', $table));
        }
    }

    public function testInstallerIsIdempotent(): void
    {
        $installer = new Installer($this->connection);

        $installer->install();
        $second = $installer->install();

        self::assertSame([], $second);
        self::assertSame([], $installer->verify());
    }

    public function testQueryBuilderProducesPortableSql(): void
    {
        (new Installer($this->connection))->install();

        $this->connection->insert('articles_category', [
            'community' => 1,
            'name' => 'News',
            'slug' => 'news',
            'content' => 'General news',
            'keyword' => 'news',
        ]);

        $postId = $this->connection->insert('articles_post', [
            'title' => 'Hello',
            'slug' => 'hello',
            'content' => 'World',
            'time' => 1000,
            'update_time' => 2000,
            'category' => 1,
            'author' => 'admin',
        ]);

        $rows = $this->connection->queryBuilder()
            ->from('articles_post')
            ->select('articles_post.*', 'articles_category.name AS category_name')
            ->leftJoin('articles_category', 'articles_category.id', '=', 'articles_post.category')
            ->where('articles_post.author', 'admin')
            ->orderBy('articles_post.update_time', 'desc')
            ->limit(10)
            ->get();

        self::assertCount(1, $rows);
        self::assertSame('Hello', $rows[0]['title']);
        self::assertSame('News', $rows[0]['category_name']);
        self::assertGreaterThan(0, $postId);
    }

    public function testQueryBuilderSupportsWhereInAndPagination(): void
    {
        (new Installer($this->connection))->install();

        foreach (['a', 'b', 'c'] as $index => $nick) {
            $this->connection->insert('users', [
                'nick' => $nick,
                'name' => $nick,
                'pass' => 'x',
                'level' => $index,
            ]);
        }

        $query = $this->connection->queryBuilder()->from('users')->whereIn('nick', ['a', 'c']);

        self::assertCount(2, $query->get());

        $page = $this->connection->queryBuilder()->from('users')->orderBy('id', 'asc')->forPage(2, 2);

        self::assertCount(1, $page->get());
    }

    public function testLegacySketchCmsHashIsAcceptedAndUpgraded(): void
    {
        $hasher = new PasswordHasher();

        $legacy = $hasher->legacyHash('secret123');

        self::assertTrue($hasher->verify('secret123', $legacy));
        self::assertFalse($hasher->verify('wrong', $legacy));
        self::assertTrue($hasher->needsRehash($legacy));

        $modern = $hasher->hash('secret123');

        self::assertTrue($hasher->verify('secret123', $modern));
        self::assertFalse($hasher->needsRehash($modern));
    }

    public function testSchemaLoaderExposesTheSketchCmsTables(): void
    {
        $names = array_map(static fn ($table): string => $table->name, SchemaLoader::load());

        self::assertContains('users', $names);
        self::assertContains('articles_post', $names);
        self::assertCount(11, $names);
    }

    public function testConnectionQuotesReservedIdentifiers(): void
    {
        self::assertSame('`condition`', $this->connection->quoteIdentifier('condition'));
    }
}
