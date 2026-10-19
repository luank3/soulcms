<?php

declare(strict_types=1);

namespace SoulCMS\Http\Session;

use SoulCMS\Foundation\Clock;

/**
 * Session manager with lazy start and hardening.
 *
 * The session is only started when something actually needs it (a logged in
 * user, a CSRF token, a flash message). Anonymous, cacheable GET requests
 * therefore never pay for the session file lock.
 *
 * Hardening
 * ---------
 *  - **Absolute timeout** — the session dies after a fixed lifetime, no
 *    matter how active it is.
 *  - **Idle timeout** — the session dies after a period of inactivity.
 *  - **Fingerprint** — the session is bound to a hash of the user agent and
 *    the accepted language, so a stolen session id is much less useful.
 *  - **Strict mode** — the server only accepts session ids it issued.
 *  - **Strong identifiers** — 48 bytes of entropy per session id.
 */
final class SessionManager
{
    private bool $started = false;

    /** @var array<string,string> */
    private array $ini = [];

    /**
     * @param array<string,mixed> $options
     * @param array<string,string> $ini
     */
    public function __construct(
        private string $name = 'SoulCMS',
        private array $options = [],
        private Clock $clock = new Clock(),
        array $ini = [],
        private int $idleTimeout = 1800,
        private int $absoluteTimeout = 43200,
    ) {
        $this->ini = $ini;
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        if (headers_sent()) {
            return;
        }

        $cookie = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $ini = [
            'session.use_strict_mode' => '1',
            'session.use_only_cookies' => '1',
            'session.use_trans_sid' => '0',
            'session.sid_length' => '48',
            'session.sid_bits_per_character' => '5',
            'session.gc_maxlifetime' => (string) $this->absoluteTimeout,
            'session.cookie_httponly' => '1',
            'session.cookie_samesite' => 'Lax',
        ];

        session_name($this->name);
        session_set_cookie_params(array_merge($cookie, $this->options));

        foreach (array_merge($ini, $this->ini) as $key => $value) {
            @ini_set($key, $value);
        }

        @session_start();

        $this->started = true;

        $this->enforceLifetime();
        $this->enforceFingerprint();
    }

    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Expire the session when it is too old or too idle.
     */
    private function enforceLifetime(): void
    {
        $now = $this->clock->now();

        $created = (int) ($_SESSION['_created'] ?? $now);
        $lastSeen = (int) ($_SESSION['_last_seen'] ?? $now);

        if ($created > 0 && ($now - $created) > $this->absoluteTimeout) {
            $this->destroy();

            return;
        }

        if ($lastSeen > 0 && ($now - $lastSeen) > $this->idleTimeout) {
            $this->destroy();

            return;
        }

        $_SESSION['_created'] = $created ?: $now;
        $_SESSION['_last_seen'] = $now;
    }

    /**
     * Bind the session to the client that created it.
     */
    private function enforceFingerprint(): void
    {
        $fingerprint = $this->fingerprint();
        $stored = $_SESSION['_fingerprint'] ?? null;

        if (!is_string($stored) || $stored === '') {
            $_SESSION['_fingerprint'] = $fingerprint;

            return;
        }

        if (!hash_equals($stored, $fingerprint)) {
            // The session was replayed from a different client: drop it.
            $this->destroy();
        }
    }

    private function fingerprint(): string
    {
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $language = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

        return hash('sha256', $agent . '|' . $language);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function has(string $key): bool
    {
        $this->start();

        return isset($_SESSION[$key]);
    }

    /**
     * Read a value *without* starting the session.
     *
     * This is what the authentication layer uses: an anonymous visitor must
     * not pay for a session file lock just to find out that nobody is
     * logged in.
     */
    public function peek(string $key, mixed $default = null): mixed
    {
        if (!$this->isStarted() && session_status() !== PHP_SESSION_ACTIVE) {
            return $default;
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Rotate the session id. Called whenever the privilege level changes.
     */
    public function regenerate(): void
    {
        $this->start();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['_created'] = $this->clock->now();
        $_SESSION['_last_seen'] = $this->clock->now();
    }

    public function destroy(): void
    {
        $this->start();

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->started = false;
    }

    /**
     * Flash data: available for the next request only.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION['_flash']['new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->start();

        $value = $_SESSION['_flash']['old'][$key] ?? $default;

        unset($_SESSION['_flash']['old'][$key]);

        return $value;
    }

    /**
     * Rotate the flash bag at the beginning of the request.
     */
    public function ageFlashData(): void
    {
        $this->start();

        $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'] ?? [];
        $_SESSION['_flash']['new'] = [];
    }

    /**
     * The CSRF token of the session.
     */
    public function token(): string
    {
        $this->start();

        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || $_SESSION['_csrf'] === '') {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public function validateToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $this->start();

        $expected = $_SESSION['_csrf'] ?? '';

        return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    public function getId(): string
    {
        $this->start();

        return session_id() ?: '';
    }

    public function now(): int
    {
        return $this->clock->now();
    }
}
