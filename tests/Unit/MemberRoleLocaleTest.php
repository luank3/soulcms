<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Support\Lang;

final class MemberRoleLocaleTest extends TestCase
{
    public function testMemberRoleLabelsFollowTheActiveLocale(): void
    {
        try {
            Lang::setLocale('en');
            self::assertSame('Administrator', member_role(['level' => 126])['label']);
            self::assertSame('Newbie', member_role(['level' => 0, 'reg' => time()])['label']);

            Lang::setLocale('vi');
            self::assertSame('Quản trị viên', member_role(['level' => 126])['label']);
            self::assertSame('Thành viên mới', member_role(['level' => 0, 'reg' => time()])['label']);
        } finally {
            Lang::setLocale('vi');
        }
    }
}
