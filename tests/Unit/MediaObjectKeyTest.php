<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Media\Repositories\MediaRepository;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use PDO;

final class MediaObjectKeyTest extends TestCase
{
    public function testLibraryObjectKeyCannotEscapeItsNamespaceOrPointToAnArticle(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $media = new MediaRepository($connection);

        self::assertSame('media/12.png', $media->pendingObjectKey(['id' => 12, 'filecate' => 'png', 'type' => 'rejected', 'blogid' => 0]));
        self::assertSame('media/12.png', $media->objectKey(['id' => 12, 'filecate' => 'png', 'type' => 's3', 'blogid' => 0]));
        self::assertNull($media->pendingObjectKey(['id' => 12, 'filecate' => '../png', 'type' => 'rejected', 'blogid' => 0]));
        self::assertNull($media->objectKey(['id' => 12, 'filecate' => 'png', 'type' => 's3', 'blogid' => 7]));
    }
}
