<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Support\ReputationRank;

final class ReputationRankTest extends TestCase
{
    public function testReputationRankCombinesMembershipAgePostsAndComments(): void
    {
        self::assertSame(
            ['level' => 2, 'key' => 'participant', 'label' => 'Thành viên', 'score' => 45],
            ReputationRank::forMember(1, 1, 2, 365 * 86400 + 1)
        );
        self::assertSame(
            ['level' => 2, 'key' => 'participant', 'label' => 'Thành viên', 'score' => 32],
            ReputationRank::forMember(0, 9, 5, 0)
        );
    }

    public function testRankClampsFutureRegistrationAndNegativeActivity(): void
    {
        self::assertSame(
            ['level' => 0, 'key' => 'newbie', 'label' => 'Thành viên mới', 'score' => 0],
            ReputationRank::forMember(1000, -5, -10, 0)
        );
    }
}
