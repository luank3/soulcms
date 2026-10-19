<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Mail\Repositories;

use SoulCMS\Database\Repository\BaseRepository;

/**
 * Data access for the private message table.
 *
 * SketchCMS stores a conversation in a single `sender_receiver` column built
 * from the two (lowercased) nicknames joined by an underscore. The same
 * convention is preserved here so existing mailboxes keep working.
 */
final class MailRepository extends BaseRepository
{
    public const TABLE_MAIL = 'mail';

    public function table(): string
    {
        return self::TABLE_MAIL;
    }

    public static function conversationKey(string $sender, string $receiver): string
    {
        return mb_strtolower($sender) . '_' . mb_strtolower($receiver);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function conversation(string $sender, string $receiver, int $limit = 20, int $offset = 0): array
    {
        return $this->connection->select(
            sprintf(
                'SELECT * FROM `%s` WHERE `sender_receiver` = ? ORDER BY `id` DESC LIMIT %d OFFSET %d',
                self::TABLE_MAIL,
                max(1, $limit),
                max(0, $offset)
            ),
            [self::conversationKey($sender, $receiver)]
        );
    }

    public function countConversation(string $sender, string $receiver): int
    {
        return (int) $this->connection->scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `sender_receiver` = ?', self::TABLE_MAIL),
            [self::conversationKey($sender, $receiver)]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function store(array $data): int
    {
        return $this->connection->insert(self::TABLE_MAIL, $data);
    }

    /**
     * @param list<string> $senders
     * @return list<string>
     */
    public function parseList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(
            explode('.', $raw),
            static fn (string $item): bool => trim($item) !== ''
        ));
    }

    /**
     * Prepend a value to a dot separated list stored in the users table.
     */
    public function pushToUserList(string $table, string $column, string $userColumn, string $value, string $user): void
    {
        $current = $this->connection->scalar(
            sprintf('SELECT `%s` FROM `%s` WHERE `%s` = ? LIMIT 1', $column, $table, $userColumn),
            [$user]
        );

        $list = $this->parseList(is_string($current) ? $current : '');

        if (!in_array($value, $list, true)) {
            array_unshift($list, $value);
        }

        $this->connection->query(
            sprintf('UPDATE `%s` SET `%s` = ? WHERE `%s` = ?', $table, $column, $userColumn),
            [implode('.', $list), $user]
        );
    }

    public function removeFromUserList(string $table, string $column, string $userColumn, string $value, string $user): void
    {
        $current = $this->connection->scalar(
            sprintf('SELECT `%s` FROM `%s` WHERE `%s` = ? LIMIT 1', $column, $table, $userColumn),
            [$user]
        );

        $list = array_values(array_filter(
            $this->parseList(is_string($current) ? $current : ''),
            static fn (string $item): bool => $item !== $value
        ));

        $this->connection->query(
            sprintf('UPDATE `%s` SET `%s` = ? WHERE `%s` = ?', $table, $column, $userColumn),
            [implode('.', $list), $user]
        );
    }
}
