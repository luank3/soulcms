<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Support\WaifuDecoration;

final class WaifuDecorationTest extends TestCase
{
    public function testOnlyKnownLocalDecorationFilesAreRendered(): void
    {
        $directory = dirname(__DIR__, 2) . '/public/images';
        mkdir($directory);
        file_put_contents($directory . '/header.png', 'image');
        file_put_contents($directory . '/rleft.png', 'image');
        file_put_contents($directory . '/rright.png', 'image');

        try {
            self::assertSame([
                'header' => '/images/header.png',
                'rleft' => '/images/rleft.png',
                'rright' => '/images/rright.png',
                'close' => false,
            ], WaifuDecoration::fromRow([
                'header' => '/images/header.png',
                'rleft' => '/images/rleft.png',
                'rright' => '/images/rright.png',
                'close' => 0,
            ]));

            self::assertSame([
                'header' => null,
                'rleft' => null,
                'rright' => null,
                'close' => true,
            ], WaifuDecoration::fromRow([
                'header' => 'https://evil.example/image.png',
                'rleft' => '../../secret',
                'rright' => "javascript:alert(1)",
                'close' => 1,
            ]));
            self::assertSame([
                'header' => null,
                'rleft' => null,
                'rright' => null,
                'close' => true,
            ], WaifuDecoration::fromRow([
                'header' => '/images/header.png',
                'rleft' => '/images/rleft.png',
                'rright' => '/images/rright.png',
                'close' => 1,
            ]));
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
