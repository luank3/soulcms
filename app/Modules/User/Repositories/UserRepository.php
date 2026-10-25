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
     * @return list<string>
     */
    public function blocklist(int $userId): array
    {
        $value = $this->connection->scalar(
            'SELECT `blocklist` FROM `users` WHERE `id` = ? LIMIT 1',
            [$userId]
        );

        return $this->parseBlocklist(is_string($value) ? $value : '');
    }

    /**
     * @return list<array{nick:string,name:string}>
     */
    public function blockedMembers(int $userId): array
    {
        $blocklist = $this->blocklist($userId);
        if ($blocklist === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($blocklist), '?'));
        $rows = $this->connection->select(
            sprintf('SELECT `nick`, `name` FROM `users` WHERE LOWER(`nick`) IN (%s)', $placeholders),
            array_map('mb_strtolower', $blocklist)
        );
        $members = [];

        foreach ($rows as $row) {
            $members[mb_strtolower((string) $row['nick'])] = $row;
        }

        return array_map(static function (string $nick) use ($members): array {
            $member = $members[mb_strtolower($nick)] ?? null;
            return ['nick' => $nick, 'name' => (string) ($member['name'] ?? $nick)];
        }, $blocklist);
    }

    public function blockUser(int $userId, string $nick): void
    {
        $list = $this->blocklist($userId);
        $key = mb_strtolower($nick);
        $existing = array_map('mb_strtolower', $list);

        if (!in_array($key, $existing, true)) {
            $list[] = $nick;
        }

        $this->updateUser($userId, ['blocklist' => implode('.', $list)]);
    }

    public function unblockUser(int $userId, string $nick): void
    {
        $key = mb_strtolower($nick);
        $list = array_values(array_filter(
            $this->blocklist($userId),
            static fn (string $item): bool => mb_strtolower($item) !== $key
        ));

        $this->updateUser($userId, ['blocklist' => implode('.', $list)]);
    }

    public function isBlockedBy(int $userId, string $nick): bool
    {
        $key = mb_strtolower($nick);

        foreach ($this->blocklist($userId) as $blocked) {
            if (mb_strtolower($blocked) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Counts recent post/comment activity for a profile without writing to users.
     *
     * @return array{posts:int,comments:int}
     */
    public function reputationActivity(string $nick, int $since): array
    {
        $row = $this->connection->selectOne(
            'SELECT (SELECT COUNT(*) FROM `articles_post` WHERE `author` = ? AND `time` >= ?) AS posts, '
            . '(SELECT COUNT(*) FROM `articles_cmt` WHERE `author` = ? AND `time` >= ?) AS comments',
            [$nick, $since, $nick, $since]
        );

        return [
            'posts' => (int) ($row['posts'] ?? 0),
            'comments' => (int) ($row['comments'] ?? 0),
        ];
    }

    private function parseBlocklist(string $value): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode('.', $value)),
            static fn (string $nick): bool => $nick !== ''
        )));
    }

    public function purchaseAccount(int $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT `id`, `nick`, `xu` FROM `users` WHERE `id` = ? LIMIT 1',
            [$userId]
        );
    }

    public function creditPoints(int $userId, int $amount): void
    {
        if ($amount > 0) {
            $this->connection->query(
                'UPDATE `users` SET `xu` = `xu` + ? WHERE `id` = ?',
                [$amount, $userId]
            );
        }
    }

    public function debitPointsIfSufficient(int $userId, int $amount): bool
    {
        if ($amount < 1) {
            return false;
        }

        return $this->connection->query(
            'UPDATE `users` SET `xu` = `xu` - ? WHERE `id` = ? AND `xu` >= ?',
            [$amount, $userId, $amount]
        )->rowCount() === 1;
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
    public function findByResetToken(string $token, int $now): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM `users` WHERE `reset_token` = ? AND `reset_expires` > ? LIMIT 1',
            [$token, $now]
        );
    }

    public function clearResetToken(int $userId, string $token): void
    {
        $this->connection->query(
            'UPDATE `users` SET `reset_token` = NULL, `reset_expires` = NULL WHERE `id` = ? AND `reset_token` = ?',
            [$userId, $token]
        );
    }

    public function consumeResetToken(int $userId, string $token, int $now, string $passwordHash): bool
    {
        return $this->connection->query(
            'UPDATE `users` SET `pass` = ?, `reset_token` = NULL, `reset_expires` = NULL WHERE `id` = ? AND `reset_token` = ? AND `reset_expires` > ?',
            [$passwordHash, $userId, $token, $now]
        )->rowCount() === 1;
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

    public function emailExistsForOtherUser(string $email, int $userId): bool
    {
        return $this->connection->selectOne(
            'SELECT `id` FROM `users` WHERE `email` = ? AND `id` <> ? LIMIT 1',
            [$email, $userId]
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

    public function setWaifuClosed(int $userId, bool $closed): bool
    {
        if ($this->waifu($userId) === null) {
            return false;
        }

        return $this->connection->update(self::TABLE_WAIFU, ['close' => $closed ? 1 : 0], ['user_id' => $userId]) === 1;
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
    public function onlineUsers(int $limit = 20, int $window = 300, ?int $now = null): array
    {
        return $this->connection->select(
            'SELECT `id`, `nick`, `avatar`, `level` FROM `users` WHERE `on` > ? ORDER BY `on` DESC LIMIT ' . max(1, $limit),
            [($now ?? time()) - max(0, $window)]
        );
    }

    public function isOnline(int $userId, int $window = 300, ?int $now = null): bool
    {
        $lastSeen = $this->connection->scalar(
            'SELECT `on` FROM `users` WHERE `id` = ? LIMIT 1',
            [$userId]
        );

        return $lastSeen !== null && (int) $lastSeen > ($now ?? time()) - max(0, $window);
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
