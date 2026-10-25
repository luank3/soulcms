<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Foundation\Config;
use SoulCMS\Support\S3Storage;

final class S3InlineMediaTest extends TestCase
{
    public function testInlineDispositionIsOptInForImagePreviews(): void
    {
        $storage = new S3Storage(new Config(['app' => ['storage' => ['s3' => [
            'endpoint' => 'https://objects.example.test',
            'bucket' => 'private-bucket',
            'region' => 'test-region-1',
            'access_key' => 'key',
            'secret_key' => 'secret',
            'path_style' => true,
        ]]]]));

        $download = $storage->presignedGetUrl('media/1.png', 300, 'image.png', 'png');
        $preview = $storage->presignedGetUrl('media/1.png', 300, 'image.png', 'png', true);

        self::assertStringContainsString('response-content-disposition=attachment%3B', $download);
        self::assertStringContainsString('response-content-disposition=inline%3B', $preview);
    }
}
