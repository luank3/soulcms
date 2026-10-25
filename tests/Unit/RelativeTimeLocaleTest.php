<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Support\Lang;

final class RelativeTimeLocaleTest extends TestCase
{
    public function testRelativeTimeAndPaginationFollowTheActiveLocale(): void
    {
        try {
            Lang::setLocale('en');
            self::assertSame('45 seconds ago', time_ago(time() - 45));
            self::assertStringContainsString('aria-label="Pagination"', paging('/articles', 2, 4));
            self::assertStringContainsString('Page 2 of 4', paging('/articles', 2, 4));

            Lang::setLocale('vi');
            self::assertSame('45 giây trước', time_ago(time() - 45));
            self::assertStringContainsString('aria-label="Phân trang"', paging('/articles', 2, 4));
            self::assertStringContainsString('Trang 2 / 4', paging('/articles', 2, 4));
        } finally {
            Lang::setLocale('vi');
        }
    }
}
