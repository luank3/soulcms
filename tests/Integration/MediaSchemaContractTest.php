<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Media\Repositories\MediaRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Support\SettingsStore;

final class MediaSchemaContractTest extends TestCase
{
    public function testMediaLibraryUsesOnlyExistingArticlesFileColumns(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY AUTOINCREMENT, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, saleoff INTEGER, condition TEXT, mua TEXT, blogid INTEGER)');
        $media = new MediaRepository($connection);
        $media->insertMedia('alice', 'photo.jpg', 'jpg', 120);
        $media->markUploaded(1);

        self::assertSame([
            'id' => 1,
            'type' => 's3',
            'author' => 'alice',
            'blogid' => 0,
            'price' => 0,
            'status' => 'public',
        ], $connection->selectOne('SELECT `id`, `type`, `author`, `blogid`, `price`, `status` FROM `articles_file` WHERE `id` = 1'));
        self::assertFalse($connection->tableExists('settings'));
    }
}
