<?php

declare(strict_types=1);

namespace SoulCMS\Support;

/** Calculates a display-only reputation rank from existing member activity. */
final class ReputationRank
{
    /**
     * @return array{level:int,key:string,label:string,score:int}
     */
    public static function forMember(int $registeredAt, int $posts, int $comments, ?int $now = null): array
    {
        $ageDays = $registeredAt > 0
            ? max(0, intdiv(max(0, ($now ?? time()) - $registeredAt), 86400))
            : 0;
        $posts = max(0, $posts);
        $comments = max(0, $comments);
        $score = min(100, min(40, $ageDays) + min(30, $posts * 3) + min(30, $comments));

        if ($score >= 60) {
            return ['level' => 3, 'key' => 'active', 'label' => 'Tích cực', 'score' => $score];
        }
        if ($score >= 25) {
            return ['level' => 2, 'key' => 'participant', 'label' => 'Thành viên', 'score' => $score];
        }
        if ($score >= 10) {
            return ['level' => 1, 'key' => 'newbie', 'label' => 'Thành viên mới', 'score' => $score];
        }

        return ['level' => 0, 'key' => 'newbie', 'label' => 'Thành viên mới', 'score' => $score];
    }
}
