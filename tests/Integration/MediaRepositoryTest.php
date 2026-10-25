<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Media\Repositories\MediaRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

final class MediaRepositoryTest extends TestCase
{
    public function testLibraryListsOnlyTheMembersS3Media(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER, mua TEXT)');
        $connection->query("INSERT INTO articles_file VALUES (1, 100, 'cover.png', 'png', 20, '0', 's3', 'alice', 'public', 0, 0, NULL), (2, 90, 'other.png', 'png', 30, '0', 's3', 'bob', 'public', 0, 0, NULL), (3, 80, 'rejected.png', 'png', 40, '0', 'rejected', 'alice', 'public', 0, 0, NULL), (4, 70, 'attachment.pdf', 'pdf', 50, '0', 's3', 'alice', 'public', 0, 7, NULL)");
        $media = new MediaRepository($connection);

        self::assertSame([1], array_column($media->library('alice'), 'id'));
    }

    public function testPublicMediaRequiresPublishedFreeS3MediaAndRejectsAttachments(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY, filename TEXT, filecate TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER)');
        $connection->query("INSERT INTO articles_file VALUES (1, 'cover.png', 'png', 's3', 'alice', 'public', 0, 0), (2, 'private.png', 'png', 's3', 'bob', 'private', 0, 0), (3, 'paid.png', 'png', 's3', 'alice', 'public', 10, 0), (4, 'attachment.pdf', 'pdf', 's3', 'alice', 'public', 0, 7), (5, 'rejected.png', 'png', 'rejected', 'alice', 'public', 0, 0)");
        $media = new MediaRepository($connection);

        self::assertSame('cover.png', $media->publicMedia(1)['filename']);
        foreach ([2, 3, 4, 5] as $id) {
            self::assertNull($media->publicMedia($id));
        }
    }

    public function testCreateMediaUsesOnlyTheSharedMediaMetadataFields(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY AUTOINCREMENT, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER, mua TEXT)');
        $media = new MediaRepository($connection);

        $id = $media->insertMedia('alice', 'cover.png', 'png', 20);

        self::assertSame(1, $id);
        self::assertSame(['filename' => 'cover.png', 'filecate' => 'png', 'filesize' => 20, 'type' => 'rejected', 'author' => 'alice', 'status' => 'public', 'price' => 0, 'blogid' => 0], $connection->selectOne('SELECT `filename`, `filecate`, `filesize`, `type`, `author`, `status`, `price`, `blogid` FROM `articles_file` WHERE `id` = ?', [$id]));
        $media->markUploaded($id);
        self::assertSame('s3', $connection->scalar('SELECT `type` FROM `articles_file` WHERE `id` = ?', [$id]));
    }
}
