<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\User\Repositories;

use SoulCMS\Database\Repository\BaseRepository;

/**
 * Data access for the `users` and `users_waifu` tables.
 */
final class UserRepository extends BaseRepository
{
    public const TABLE_USERS = 'users';

    public const TABLE_WAIFU = 'users_waifu';

    public function table(): string
    {
        return self::TABLE_USERS;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByNick(string $nick): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM `users` WHERE `nick` = ? LIMIT 1',
            [$nick]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM `users` WHERE `email` = ? LIMIT 1',
            [$email]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByIdentifier(string $identifier): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM `users` WHERE `nick` = ? OR `email` = ? LIMIT 1',
            [$identifier, $identifier]
        );
    }

    /**
     * Fetch several members at once, keyed by nickname.
     *
     * Used to hydrate a list of posts without the N+1 query pattern.
     *
     * @param list<string> $nicks
     * @return array<string,array<string,mixed>>
     */
    public function byNick(array $nicks): array
    {
        $nicks = array_values(array_unique(array_filter($nicks, static fn (string $nick): bool => $nick !== '')));

        if ($nicks === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($nicks), '?'));

        $rows = $this->connection->select(
            sprintf('SELECT * FROM `users` WHERE `nick` IN (%s)', $placeholders),
            $nicks
        );

        $members = [];

        foreach ($rows as $row) {
            $members[mb_strtolower((string) $row['nick'])] = $row;
        }

        return $members;
    }

    public function nickExists(string $nick): bool
    {
        return $this->connection->selectOne(
            'SELECT `id` FROM `users` WHERE `nick` = ? LIMIT 1',
            [$nick]
        ) !== null;
    }

    public function emailExists(string $email): bool
    {
        return $this->connection->selectOne(
            'SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1',
            [$email]
        ) !== null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function register(array $data): int
    {
        return $this->connection->insert(self::TABLE_USERS, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateUser(int $id, array $data): int
    {
        return $this->connection->update(self::TABLE_USERS, $data, ['id' => $id]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function waifu(int $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM `users_waifu` WHERE `user_id` = ? LIMIT 1',
            [$userId]
        );
    }

    public function ensureWaifu(int $userId): void
    {
        if ($this->waifu($userId) === null) {
            $this->connection->query(
                'INSERT INTO `users_waifu` (`user_id`) VALUES (?)',
                [$userId]
            );
        }
    }

    /**
     * The list of members shown on the member page.
     *
     * @return list<array<string,mixed>>
     */
    public function listUsers(int $limit = 20, int $offset = 0, string $orderBy = 'level', string $sort = 'desc'): array
    {
        $allowed = ['level', 'xu', 'reg', 'karma'];
        $orderBy = in_array($orderBy, $allowed, true) ? $orderBy : 'level';
        $sort = strtoupper($sort) === 'ASC' ? 'asc' : 'desc';

        return $this->connection->queryBuilder()
            ->from(self::TABLE_USERS)
            ->select('id', 'nick', 'name', 'email', 'avatar', 'cover', 'level', 'reg', 'on', 'karma', 'status')
            ->orderBy($orderBy, $sort)
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function countUsers(): int
    {
        return (int) $this->connection->scalar('SELECT COUNT(*) FROM `users`');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function onlineUsers(int $limit = 20, int $window = 300): array
    {
        return $this->connection->select(
            'SELECT `id`, `nick`, `avatar`, `level` FROM `users` WHERE `on` > ? ORDER BY `on` DESC LIMIT ' . max(1, $limit),
            [time() - $window]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updatePassword(int $id, string $hash): int
    {
        return $this->updateUser($id, ['pass' => $hash]);
    }

    public function registerFailedLogin(int $id): int
    {
        $this->connection->query(
            'UPDATE `users` SET `failed_logins` = `failed_logins` + 1 WHERE `id` = ?',
            [$id]
        );

        $value = $this->connection->scalar('SELECT `failed_logins` FROM `users` WHERE `id` = ? LIMIT 1', [$id]);

        return (int) $value;
    }

    public function resetFailedLogins(int $id): int
    {
        return $this->updateUser($id, ['failed_logins' => 0, 'lock_time' => 0]);
    }
}
