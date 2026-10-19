<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Support\RateLimiter;

final class RateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/soulcms-rl-' . bin2hex(random_bytes(6));

        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*/*') ?: [];

        foreach ($files as $file) {
            @unlink($file);
        }

        @rmdir($this->directory . '/5b');
        @rmdir($this->directory);
    }

    public function testTheFileStoreCreatesItsShardDirectory(): void
    {
        $limiter = new RateLimiter($this->directory, false);

        self::assertTrue($limiter->hit('key', 2, 60));
        self::assertTrue($limiter->hit('key', 2, 60));
        self::assertFalse($limiter->hit('key', 2, 60));
    }

    public function testDifferentKeysHaveIndependentBuckets(): void
    {
        $limiter = new RateLimiter($this->directory, false);

        self::assertTrue($limiter->hit('a', 1, 60));
        self::assertFalse($limiter->hit('a', 1, 60));
        self::assertTrue($limiter->hit('b', 1, 60));
    }

    public function testClearResetsTheBucket(): void
    {
        $limiter = new RateLimiter($this->directory, false);

        $limiter->hit('key', 1, 60);
        self::assertFalse($limiter->hit('key', 1, 60));

        $limiter->clear('key');

        self::assertTrue($limiter->hit('key', 1, 60));
    }
}
