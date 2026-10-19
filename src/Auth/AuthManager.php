<?php

declare(strict_types=1);

namespace SoulCMS\Auth;

use SoulCMS\Database\Connection;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;

/**
 * Session and cookie based authentication.
 *
 * The implementation is compatible with SketchCMS: the session key is `uid`,
 * the "remember me" cookies are `cuid` (base64 encoded user id) and `cups`
 * (the plain token, whose bcrypt hash is stored in `users.remember_token`).
 */
final class AuthManager
{
    private ?UserContext $user = null;

    private bool $resolved = false;

    public function __construct(
        private Connection $connection,
        private Request $request,
        private PasswordHasher $hasher,
        private Clock $clock,
        private SessionManager $session,
        private Config $config = new Config(),
    ) {
    }

    public function user(): UserContext
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $this->user = $this->resolve();
        }

        return $this->user ?? new UserContext();
    }

    public function check(): bool
    {
        return $this->user()->isAuthenticated;
    }

    public function id(): int
    {
        return $this->user()->id();
    }

    public function level(): int
    {
        return $this->user()->level();
    }

    private function resolve(): UserContext
    {
        $id = 0;
        $fromSession = false;
        $cookieToken = '';

        // Start only for requests carrying a session cookie, keeping guests sessionless.
        $sessionCookie = $this->request->getCookie((string) $this->config->get('app.session.name', 'SoulCMS'));

        if (is_string($sessionCookie) && $sessionCookie !== '') {
            $this->session->start();
        }

        $sessionId = (int) $this->session->peek('uid', 0);

        if ($sessionId > 0) {
            $id = $sessionId;
            $fromSession = true;
        }

        if (!$fromSession) {
            $cookieId = $this->request->getCookie('cuid');
            $cookieToken = (string) $this->request->getCookie('cups', '');

            if (is_string($cookieId) && $cookieId !== '' && $cookieToken !== '') {
                $decoded = base64_decode($cookieId, true);
                $id = $decoded === false ? 0 : (int) $decoded;
            }
        }

        if ($id <= 0) {
            return new UserContext();
        }

        $row = $this->connection->selectOne('SELECT * FROM `users` WHERE `id` = ? LIMIT 1', [$id]);

        if ($row === null) {
            return new UserContext();
        }

        if (!$fromSession) {
            $rememberToken = (string) ($row['remember_token'] ?? '');

            if ($rememberToken === '' || !password_verify($cookieToken, $rememberToken)) {
                return new UserContext();
            }

            $this->session->put('uid', $id);
        }

        $context = new UserContext(true, $row);

        if ($context->isBanned()) {
            return new UserContext(false, $row);
        }

        $this->touchPresence($id);
        $this->ensureWaifuRow($id);

        return $context;
    }

    private function touchPresence(int $id): void
    {
        $this->connection->query(
            'UPDATE `users` SET `on` = ? WHERE `id` = ?',
            [$this->clock->now(), $id]
        );
    }

    private function ensureWaifuRow(int $id): void
    {
        $exists = $this->connection->selectOne(
            'SELECT `id` FROM `users_waifu` WHERE `user_id` = ? LIMIT 1',
            [$id]
        );

        if ($exists === null) {
            $this->connection->query('INSERT INTO `users_waifu` (`user_id`) VALUES (?)', [$id]);
        }
    }

    /**
     * Authenticate with a nickname or an e-mail address.
     *
     * The failure path is deliberately uniform: an unknown account, a locked
     * account and a wrong password all cost the same time and return the
     * same result, so the endpoint cannot be used to enumerate members.
     */
    public function attempt(string $identifier, string $password): ?UserContext
    {
        $row = $this->connection->selectOne(
            'SELECT * FROM `users` WHERE `nick` = ? OR `email` = ? LIMIT 1',
            [$identifier, $identifier]
        );

        if ($row === null) {
            $this->hasher->burnTime();

            return null;
        }

        $userId = (int) $row['id'];

        if ($this->isLocked($userId)) {
            $this->hasher->burnTime();

            return null;
        }

        $hash = (string) ($row['pass'] ?? '');

        if (!$this->hasher->verify($password, $hash)) {
            $this->registerFailedLogin($userId);

            return null;
        }

        if ($this->hasher->needsRehash($hash)) {
            $this->connection->query(
                'UPDATE `users` SET `pass` = ? WHERE `id` = ?',
                [$this->hasher->hash($password), $userId]
            );
        }

        $this->resetFailedLogins($userId);

        // The privilege level changes: rotate the session id to defeat
        // session fixation.
        $this->session->regenerate();
        $this->session->put('uid', $userId);

        $this->resolved = true;
        $this->user = new UserContext(true, $row);

        return $this->user;
    }

    /**
     * Whether the account is temporarily locked after too many failures.
     */
    public function isLocked(int $userId): bool
    {
        $maxAttempts = (int) $this->config->get('app.security.login_max_attempts', 5);
        $decay = (int) $this->config->get('app.security.login_decay_seconds', 300);

        if ($maxAttempts < 1) {
            return false;
        }

        $row = $this->connection->selectOne(
            'SELECT `failed_logins`, `lock_time` FROM `users` WHERE `id` = ? LIMIT 1',
            [$userId]
        );

        if (!is_array($row)) {
            return false;
        }

        $failed = (int) ($row['failed_logins'] ?? 0);
        $lockedAt = (int) ($row['lock_time'] ?? 0);

        if ($failed < $maxAttempts) {
            return false;
        }

        if ($lockedAt === 0) {
            return true;
        }

        // The lock expires after the decay window.
        if ($this->clock->now() - $lockedAt >= $decay) {
            $this->resetFailedLogins($userId);

            return false;
        }

        return true;
    }

    private function registerFailedLogin(int $userId): void
    {
        $maxAttempts = (int) $this->config->get('app.security.login_max_attempts', 5);
        $decay = (int) $this->config->get('app.security.login_decay_seconds', 300);

        $this->connection->query(
            'UPDATE `users` SET `failed_logins` = `failed_logins` + 1, `lock_time` = ? WHERE `id` = ?',
            [$this->clock->now(), $userId]
        );

        $failed = (int) $this->connection->scalar(
            'SELECT `failed_logins` FROM `users` WHERE `id` = ? LIMIT 1',
            [$userId]
        );

        if ($maxAttempts > 0 && $failed >= $maxAttempts) {
            $this->connection->query(
                'UPDATE `users` SET `lock_time` = ? WHERE `id` = ?',
                [$this->clock->now(), $userId]
            );
        }
    }

    private function resetFailedLogins(int $userId): void
    {
        $this->connection->query(
            'UPDATE `users` SET `failed_logins` = 0, `lock_time` = 0 WHERE `id` = ?',
            [$userId]
        );
    }

    /**
     * Issue a "remember me" token and set the cookies.
     */
    public function issueRememberToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        $this->connection->query(
            'UPDATE `users` SET `remember_token` = ? WHERE `id` = ?',
            [password_hash($token, PASSWORD_BCRYPT), $userId]
        );

        $expires = $this->clock->now() + 60 * 60 * 24 * 30;

        setcookie('cuid', base64_encode((string) $userId), [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        setcookie('cups', $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $token;
    }

    public function logout(): void
    {
        if ($this->session->has('uid')) {
            $this->connection->query(
                'UPDATE `users` SET `remember_token` = NULL WHERE `id` = ?',
                [(int) $this->session->get('uid')]
            );
        }

        $expires = $this->clock->now() - 3600;

        setcookie('cuid', '', ['expires' => $expires, 'path' => '/']);
        setcookie('cups', '', ['expires' => $expires, 'path' => '/']);

        $this->session->forget('uid');

        $this->resolved = true;
        $this->user = new UserContext();
    }

    /**
     * Number of unread conversations for the given user.
     *
     * SketchCMS stores the unread senders as a dot separated list in
     * `users.new_mail`, so the counter is derived from that list.
     */
    public function newMailCount(int $userId): int
    {
        $value = $this->connection->scalar(
            'SELECT `new_mail` FROM `users` WHERE `id` = ? LIMIT 1',
            [$userId]
        );

        if (!is_string($value) || trim($value) === '') {
            return 0;
        }

        return count(array_filter(explode('.', $value), static fn (string $item): bool => trim($item) !== ''));
    }
}
