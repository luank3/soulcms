<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Media\Repositories;

use SoulCMS\Database\Connection;

final class MediaRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function library(string $author, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        return $this->connection->select(
            'SELECT * FROM `articles_file` WHERE `author` = ? AND `type` = ? AND `blogid` = ? ORDER BY `id` DESC LIMIT ' . $limit,
            [$author, 's3', 0]
        );
    }

    /** @return array<string,mixed>|null */
    public function file(int $id): ?array
    {
        return $this->connection->selectOne('SELECT * FROM `articles_file` WHERE `id` = ? AND `blogid` = ? LIMIT 1', [$id, 0]);
    }

    /** @return array<string,mixed>|null */
    public function publicMedia(int $id): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM `articles_file` WHERE `id` = ? AND `type` = ? AND `blogid` = ? AND `status` = ? AND `price` = ? LIMIT 1',
            [$id, 's3', 0, 'public', 0]
        );
    }

    public function insertMedia(string $author, string $filename, string $extension, int $size): int
    {
        return $this->connection->insert('articles_file', [
            'time' => time(),
            'filename' => $filename,
            'filecate' => $extension,
            'filesize' => $size,
            'passphrase' => '0',
            'type' => 'rejected',
            'author' => $author,
            'status' => 'public',
            'price' => 0,
            'blogid' => 0,
        ]);
    }

    public function markUploaded(int $id): bool
    {
        return $this->connection->update('articles_file', ['type' => 's3'], ['id' => $id, 'type' => 'rejected']) === 1;
    }

    /** @param array<string,mixed> $media */
    public function objectKey(array $media): ?string
    {
        $id = (int) ($media['id'] ?? 0);
        $extension = strtolower((string) ($media['filecate'] ?? ''));
        if (($media['type'] ?? null) !== 's3' || (int) ($media['blogid'] ?? -1) !== 0 || $id < 1 || preg_match('/^[a-z0-9]{1,12}$/', $extension) !== 1) {
            return null;
        }

        return sprintf('media/%d.%s', $id, $extension);
    }

    /** @param array<string,mixed> $media */
    public function pendingObjectKey(array $media): ?string
    {
        if (($media['type'] ?? null) !== 'rejected') {
            return null;
        }

        $media['type'] = 's3';
        return $this->objectKey($media);
    }
}
