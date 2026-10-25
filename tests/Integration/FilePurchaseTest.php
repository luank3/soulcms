<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Articles\Services\FilePurchaseService;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;

final class FilePurchaseTest extends TestCase
{
    public function testOnlyRecordedBuyersPassOwnershipCheck(): void
    {
        [$connection, $service] = $this->makeService();
        self::assertFalse($service->hasPurchased(10, 'buyer'));
        self::assertTrue($service->purchase(10, 2));
        self::assertTrue($service->hasPurchased(10, 'BUYER'));
        self::assertFalse($service->hasPurchased(10, 'other'));
    }

    public function testPurchaseRefusesWhenExistingBuyerListHasNoRoomInSchemaField(): void
    {
        [$connection, $service] = $this->makeService();
        $tooLong = str_repeat('x', 252);
        $connection->query('UPDATE `articles_file` SET `mua` = ? WHERE `id` = 10', [$tooLong]);

        self::assertFalse($service->purchase(10, 2));
        self::assertSame(50, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 2'));
        self::assertSame($tooLong, $connection->scalar('SELECT `mua` FROM `articles_file` WHERE `id` = 10'));
    }

    public function testPurchaseDebitsPointsAndRecordsBuyerExactlyOnce(): void
    {
        [$connection, $service] = $this->makeService();

        self::assertTrue($service->purchase(10, 2));
        self::assertSame(40, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 2'));
        self::assertSame('buyer', $connection->scalar('SELECT `mua` FROM `articles_file` WHERE `id` = 10'));
        self::assertFalse($service->purchase(10, 2));
        self::assertSame(40, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 2'));
    }

    public function testPurchaseDoesNotChangeBalanceWhenFundsOrFileAreInvalid(): void
    {
        [$connection, $service] = $this->makeService(4);

        self::assertFalse($service->purchase(10, 2));
        self::assertFalse($service->purchase(11, 2));
        self::assertSame(4, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 2'));
        self::assertNull($connection->scalar('SELECT `mua` FROM `articles_file` WHERE `id` = 10'));
    }

    public function testFreeFilesAndAuthorsCannotUsePaidPurchase(): void
    {
        [$connection, $service] = $this->makeService();
        $connection->query("INSERT INTO articles_file VALUES (11, 1, 'free.pdf', 'pdf', 100, '0', 's3', 'author', 'public', 0, 7, NULL), (12, 1, 'public-paid.pdf', 'pdf', 100, '0', 's3', 'author', 'public', 10, 7, NULL)");

        self::assertFalse($service->purchase(11, 2));
        self::assertFalse($service->purchase(12, 2));
        self::assertFalse($service->purchase(10, 1));
        self::assertSame(100, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 1'));
        self::assertSame(50, (int) $connection->scalar('SELECT `xu` FROM `users` WHERE `id` = 2'));
    }

    /** @return array{Connection,FilePurchaseService} */
    private function makeService(int $buyerBalance = 50): array
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $connection->query('CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, xu INTEGER)');
        $connection->query('CREATE TABLE articles_file (id INTEGER PRIMARY KEY, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER, mua TEXT)');
        $connection->query("INSERT INTO users VALUES (1, 'author', 100), (2, 'buyer', ?)", [$buyerBalance]);
        $connection->query("INSERT INTO articles_file VALUES (10, 1, 'paid.pdf', 'pdf', 100, '0', 's3', 'author', 'private', 10, 7, NULL)");

        return [
            $connection,
            new FilePurchaseService($connection, new ArticleRepository($connection), new UserRepository($connection)),
        ];
    }
}
