<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

final class FileStorageMetadataTest extends TestCase
{
    public function testStorageTypeUpdatesOnlyAllowedValues(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY, type TEXT)');
        $connection->query("INSERT INTO articles_file VALUES (1, 'rejected')");
        $articles = new ArticleRepository($connection);

        $articles->updateFileStorageType(1, 's3');

        self::assertSame('s3', $connection->scalar('SELECT `type` FROM `articles_file` WHERE `id` = 1'));
        try {
            $articles->updateFileStorageType(1, 'https://attacker.example');
            self::fail('Unexpected storage types must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertSame('s3', $connection->scalar('SELECT `type` FROM `articles_file` WHERE `id` = 1'));
        }
    }

    public function testS3ObjectKeyIsDerivedFromExistingFileMetadata(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_post (id INTEGER PRIMARY KEY, author TEXT, title TEXT, slug TEXT, content TEXT, time INTEGER, blocked INTEGER, sticked INTEGER, update_time INTEGER, category INTEGER, view INTEGER, buyfile INTEGER, `like` TEXT)');
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER)');
        $connection->query("INSERT INTO articles_file VALUES (23, 1, 'report.pdf', 'pdf', 100, '0', 's3', 'alice', 'private', 0, 7), (24, 1, 'old.pdf', 'pdf', 100, '0', 'normal', 'alice', 'private', 0, 7)");
        $articles = new ArticleRepository($connection);

        self::assertSame('articles/7/23.pdf', $articles->s3ObjectKey($articles->fileDetail(23) ?? []));
        self::assertNull($articles->s3ObjectKey($articles->fileDetail(24) ?? []));
    }
}
