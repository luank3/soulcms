<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SoulCMS\Foundation\Str;

final class StrTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string}>
     */
    public static function slugProvider(): array
    {
        return [
            ['Chuyển hệ thống sang SoulCMS', 'chuyen-he-thong-sang-soulcms'],
            ['Tối ưu truy vấn với chỉ mục', 'toi-uu-truy-van-voi-chi-muc'],
            ['SQLite thực sự đủ dùng?', 'sqlite-thuc-su-du-dung'],
            ['Hướng dẫn viết bài viết hay', 'huong-dan-viet-bai-viet-hay'],
            ['Plain ASCII title', 'plain-ascii-title'],
            ['  nhiều   khoảng trắng  ', 'nhieu-khoang-trang'],
        ];
    }

    #[DataProvider('slugProvider')]
    public function testSlugTransliteratesVietnamese(string $input, string $expected): void
    {
        self::assertSame($expected, Str::slug($input));
    }

    public function testSlugNeverEmitsWarnings(): void
    {
        $errors = [];

        set_error_handler(static function (int $severity, string $message) use (&$errors): bool {
            $errors[] = $message;

            return true;
        });

        try {
            Str::slug('Chuyển hệ thống ✨ sang ✨ SoulCMS');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $errors);
    }

    public function testSlugFallsBackWhenTheResultIsEmpty(): void
    {
        self::assertSame('item', Str::slug('***'));
    }

    public function testEscapingIsSafe(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', Str::e('<script>alert(1)</script>'));
        self::assertSame('Tom &amp; Jerry', Str::e('Tom & Jerry'));
        self::assertSame('', Str::e(null));
    }

    public function testCaseHelpers(): void
    {
        self::assertSame('HelloWorld', Str::studly('hello world'));
        self::assertSame('helloWorld', Str::camel('hello_world'));
        self::assertSame('hello-world', Str::kebab('HelloWorld'));
        self::assertSame('user_id', Str::snake('userId'));
    }
}
