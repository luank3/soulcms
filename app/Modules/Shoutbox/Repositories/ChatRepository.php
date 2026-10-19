<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Shoutbox\Repositories;

use SoulCMS\Database\Repository\BaseRepository;

/**
 * Data access for the shoutbox (chat) table.
 */
final class ChatRepository extends BaseRepository
{
    public const TABLE_CHAT = 'chat';

    public function table(): string
    {
        return self::TABLE_CHAT;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function messages(int $limit = 50, int $offset = 0): array
    {
        return $this->connection->select(
            sprintf('SELECT * FROM `%s` ORDER BY `id` DESC LIMIT %d OFFSET %d', self::TABLE_CHAT, max(1, $limit), max(0, $offset))
        );
    }

    /**
     * The messages posted after a given id, oldest first.
     *
     * Used by the chat poller so it only transfers what is new.
     *
     * @return list<array<string,mixed>>
     */
    public function messagesAfter(int $afterId, int $limit = 50): array
    {
        return $this->connection->select(
            sprintf(
                'SELECT * FROM `%s` WHERE `id` > ? ORDER BY `id` ASC LIMIT %d',
                self::TABLE_CHAT,
                max(1, $limit)
            ),
            [$afterId]
        );
    }

    public function countMessages(): int
    {
        return (int) $this->connection->scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::TABLE_CHAT));
    }

    /**
     * @param array<string,mixed> $data
     */
    public function store(array $data): int
    {
        return $this->connection->insert(self::TABLE_CHAT, $data);
    }

    public function deleteAll(): int
    {
        return $this->connection->query(sprintf('DELETE FROM `%s`', self::TABLE_CHAT))->rowCount();
    }
}
