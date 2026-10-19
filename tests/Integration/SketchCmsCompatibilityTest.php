<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

/**
 * Proves that SoulCMS can serve a SketchCMS database as-is.
 *
 * The fixture below is the *original* SketchCMS DDL (extracted from the
 * upstream `system/configs/schema.json`). It is translated to SQLite only so
 * that the test can run without a MySQL server; the table and column layout
 * is untouched.
 */
final class SketchCmsCompatibilityTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->connection = new Connection($pdo, Driver::Sqlite);

        foreach ($this->sketchCmsSchema() as $table => $mysqlDdl) {
            $this->connection->pdo()->exec($this->toSqlite($mysqlDdl));
        }
    }

    public function testTheOriginalSchemaCanBeCreatedVerbatim(): void
    {
        $tables = $this->connection->listTables();

        self::assertContains('users', $tables);
        self::assertContains('articles_post', $tables);
        self::assertContains('articles_post_tags', $tables);
        self::assertCount(11, $tables);
    }

    public function testSoulCMSReadsSketchCmsRows(): void
    {
        $this->connection->pdo()->exec(
            "INSERT INTO `users` (`id`, `nick`, `name`, `pass`, `level`, `reg`, `avatar`, `cover`)
             VALUES (1, 'admin', 'admin', 'x', 127, 1600000000, '15', '8')"
        );

        $this->connection->pdo()->exec(
            "INSERT INTO `articles_category` (`id`, `community`, `name`, `slug`, `content`, `keyword`)
             VALUES (1, 1, 'Tin tuc', 'news', 'Tin tuc moi', 'tin tuc')"
        );

        $this->connection->pdo()->exec(
            "INSERT INTO `articles_post`
             (`id`, `title`, `slug`, `content`, `time`, `blocked`, `sticked`, `update_time`, `category`, `view`, `author`, `buyfile`)
             VALUES (1, 'Bai viet mau', 'bai-viet-mau', 'Noi dung', 1600000000, 0, 0, 1600000001, 1, 5, 'admin', 0)"
        );

        $this->connection->pdo()->exec(
            "INSERT INTO `articles_cmt` (`id`, `time`, `comment`, `author`, `blogid`)
             VALUES (1, 1600000002, 'Binh luan', 'admin', '1')"
        );

        $this->connection->pdo()->exec(
            "INSERT INTO `articles_tags` (`id`, `name`, `slug`, `count`) VALUES (1, 'php', 'php', 1)"
        );

        $this->connection->pdo()->exec(
            "INSERT INTO `articles_post_tags` (`post_id`, `tag_id`) VALUES (1, 1)"
        );

        $this->connection->pdo()->exec(
            "INSERT INTO `mail` (`id`, `sender_receiver`, `nick`, `content`, `time`, `update_time`, `type`, `view`)
             VALUES (1, 'admin_admin', 'admin', 'Tin nhan', 1600000003, 1600000003, 'private', 'no')"
        );

        $users = new UserRepository($this->connection);
        $articles = new ArticleRepository($this->connection);

        $user = $users->findByNick('admin');
        self::assertNotNull($user);
        self::assertSame(127, (int) $user['level']);

        $post = $articles->postDetail(1);
        self::assertNotNull($post);
        self::assertSame('Bai viet mau', $post['title']);
        self::assertSame('admin', $post['author']);

        $posts = $articles->postList(1, 10, 'update_time', 'desc', 0);
        self::assertCount(1, $posts);
        self::assertSame('Tin tuc', $posts[0]['category_name']);

        self::assertSame(1, $articles->countCommentsInPost(1));
        self::assertSame(1, $articles->countPostsInCategory(1));
        self::assertCount(1, $articles->commentList(1, 10, 0));
        self::assertCount(1, $articles->tagsForPost(1));

        $sticked = $articles->postListSticked(5);
        self::assertIsArray($sticked);
    }

    public function testLegacyPasswordHashesAreAccepted(): void
    {
        $hasher = new PasswordHasher();

        // The hash SketchCMS would have produced for "secret123".
        $legacy = $hasher->legacyHash('secret123');

        $this->connection->pdo()->exec(
            "INSERT INTO `users` (`id`, `nick`, `name`, `pass`, `level`, `reg`)
             VALUES (2, 'legacy', 'legacy', '" . $legacy . "', 0, 1600000000)"
        );

        $users = new UserRepository($this->connection);
        $row = $users->findByNick('legacy');

        self::assertNotNull($row);
        self::assertTrue($hasher->verify('secret123', (string) $row['pass']));
    }

    /**
     * The original SketchCMS DDL, verbatim.
     *
     * @return array<string,string>
     */
    private function sketchCmsSchema(): array
    {
        $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;';

        return [
            'chat' => "CREATE TABLE `chat` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`time` int NOT NULL,
`comment` text COLLATE utf8mb4_general_ci NOT NULL
,
PRIMARY KEY (`id`)
)" . $suffix,

            'articles_category' => "CREATE TABLE `articles_category` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`community` int NOT NULL DEFAULT '1',
`name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`slug` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`content` text COLLATE utf8mb4_general_ci NOT NULL,
`keyword` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`meta_data` text COLLATE utf8mb4_general_ci,
PRIMARY KEY (`id`)
)" . $suffix,

            'articles_chap' => "CREATE TABLE `articles_chap` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`slug` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`content` text COLLATE utf8mb4_general_ci NOT NULL,
`time` int NOT NULL,
`box` int NOT NULL,
`author` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
PRIMARY KEY (`id`)
)" . $suffix,

            'articles_cmt' => "CREATE TABLE `articles_cmt` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`time` int NOT NULL,
`comment` text COLLATE utf8mb4_general_ci NOT NULL,
`author` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`blogid` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
PRIMARY KEY (`id`)
)" . $suffix,

            'articles_file' => "CREATE TABLE `articles_file` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`time` int NOT NULL,
`filename` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`filecate` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`filesize` int NOT NULL,
`passphrase` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '0',
`type` varchar(10) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal',
`author` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'sei',
`status` varchar(10) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'private',
`price` int NOT NULL DEFAULT '0',
`saleoff` int DEFAULT NULL,
`condition` int DEFAULT NULL,
`mua` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
`blogid` int DEFAULT NULL,
PRIMARY KEY (`id`)
)" . $suffix,

            'articles_tags' => "CREATE TABLE `articles_tags` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`slug` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`count` int NOT NULL DEFAULT '0',
PRIMARY KEY (`id`),
UNIQUE KEY `slug` (`slug`)
)" . $suffix,

            'articles_post_tags' => "CREATE TABLE `articles_post_tags` (
`post_id` int UNSIGNED NOT NULL,
`tag_id` int UNSIGNED NOT NULL,
PRIMARY KEY (`post_id`, `tag_id`)
)" . $suffix,

            'articles_post' => "CREATE TABLE `articles_post` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`title` text COLLATE utf8mb4_general_ci NOT NULL,
`slug` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`content` text COLLATE utf8mb4_general_ci NOT NULL,
`meta_data` text COLLATE utf8mb4_general_ci,
`time` int NOT NULL,
`blocked` int NOT NULL DEFAULT '0',
`sticked` int NOT NULL DEFAULT '0',
`update_time` int NOT NULL,
`category` int NOT NULL,
`view` int NOT NULL DEFAULT '1',
`author` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`buyfile` int NOT NULL DEFAULT '0',
`like` text COLLATE utf8mb4_general_ci,
PRIMARY KEY (`id`)
)" . $suffix,

            'mail' => "CREATE TABLE `mail` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`sender_receiver` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
`nick` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
`content` text COLLATE utf8mb4_general_ci,
`time` int DEFAULT NULL,
`update_time` int DEFAULT NULL,
`type` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
`view` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'no',
PRIMARY KEY (`id`)
)" . $suffix,

            'users' => "CREATE TABLE `users` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
`nick` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`pass` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`reset_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
`reset_expires` int DEFAULT NULL,
`failed_logins` int NOT NULL DEFAULT '0',
`lock_time` int NOT NULL DEFAULT '0',
`sex` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'boy',
`avatar` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '15',
`cover` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '8',
`new_mail` text COLLATE utf8mb4_general_ci,
`mail_list` text COLLATE utf8mb4_general_ci,
`blocklist` text COLLATE utf8mb4_general_ci,
`level` int NOT NULL DEFAULT '0',
`xu` int NOT NULL DEFAULT '500',
`tiemluc` int NOT NULL DEFAULT '0',
`reg` int NOT NULL DEFAULT '0',
`on` int NOT NULL DEFAULT '0',
`login` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
`status` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
`karma` int NOT NULL DEFAULT '0',
`time_shoutbox` int NOT NULL DEFAULT '0',
`sft_time` int NOT NULL DEFAULT '0',
`sft_level` int NOT NULL DEFAULT '1',
`karma_daily_time` int NOT NULL DEFAULT '0',
`karma_level` int NOT NULL DEFAULT '0',
`remember_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
PRIMARY KEY (`id`)
)" . $suffix,

            'users_waifu' => "CREATE TABLE `users_waifu` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`user_id` int NOT NULL,
`header` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '/images/header.png',
`rleft` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '/images/rleft.png',
`rright` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '/images/rright.png',
`close` int NOT NULL DEFAULT '0',
PRIMARY KEY (`id`)
)" . $suffix,
        ];
    }

    /**
     * Translate the MySQL DDL to SQLite, keeping the structure identical.
     */
    private function toSqlite(string $ddl): string
    {
        $ddl = (string) preg_replace('/ENGINE=InnoDB[^;]*;/i', ';', $ddl);
        $ddl = (string) preg_replace('/\s+COLLATE utf8mb4_general_ci/i', '', $ddl);
        $ddl = (string) preg_replace('/`id` int UNSIGNED NOT NULL AUTO_INCREMENT/i', '`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $ddl);
        $ddl = (string) preg_replace('/UNIQUE KEY `slug` \(`slug`\)/i', 'UNIQUE (`slug`)', $ddl);

        // The inline PRIMARY KEY replaces the table level declaration.
        $ddl = (string) preg_replace('/,\s*\nPRIMARY KEY \(`id`\)/i', '', $ddl);

        return $ddl;
    }
}
