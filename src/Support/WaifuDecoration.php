<?php

declare(strict_types=1);

namespace SoulCMS\Support;

use SoulCMS\Foundation\Paths;

final class WaifuDecoration
{
    /** @return array{header:?string,rleft:?string,rright:?string,close:bool} */
    public static function fromRow(?array $row): array
    {
        return [
            'header' => ($row['close'] ?? 0) ? null : self::asset((string) ($row['header'] ?? '')),
            'rleft' => ($row['close'] ?? 0) ? null : self::asset((string) ($row['rleft'] ?? '')),
            'rright' => ($row['close'] ?? 0) ? null : self::asset((string) ($row['rright'] ?? '')),
            'close' => (int) ($row['close'] ?? 0) !== 0,
        ];
    }

    private static function asset(string $path): ?string
    {
        if (preg_match('#^/images/[A-Za-z0-9_-][A-Za-z0-9_.-]{0,99}\.(?:png|jpe?g|gif|webp|svg)$#i', $path) !== 1) {
            return null;
        }

        $root = realpath(Paths::join('public'));
        $asset = realpath(Paths::join('public', ltrim($path, '/')));
        if ($root === false || $asset === false || !str_starts_with($asset, $root . DIRECTORY_SEPARATOR) || !is_file($asset)) {
            return null;
        }

        return $path;
    }
}
