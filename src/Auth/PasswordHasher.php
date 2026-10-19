<?php

declare(strict_types=1);

namespace SoulCMS\Auth;

/**
 * Password hashing.
 *
 * New passwords are hashed with Argon2id when the extension is available
 * (memory hard, resistant to GPU cracking), falling back to bcrypt. The
 * legacy SketchCMS hash (a character substitution followed by an MD5 digest)
 * is still accepted and transparently upgraded on the first successful login.
 *
 * An optional *pepper* — a server side secret that never touches the
 * database — can be enabled with `APP_PASSWORD_PEPPER`. Hashes created
 * before the pepper was enabled keep working, because verification tries the
 * peppered form first and the plain form second.
 */
final class PasswordHasher
{
    private const ARGON_OPTIONS = [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 2,
    ];

    private const BCRYPT_OPTIONS = ['cost' => 12];

    public function __construct(private string $pepper = '')
    {
    }

    /**
     * The algorithm actually in use.
     */
    public function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt';
    }

    public function hash(string $password): string
    {
        $password = $this->pepper($password);

        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
        }

        return password_hash($password, PASSWORD_BCRYPT, self::BCCRYPT_OPTIONS);
    }

    public function verify(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        // Modern hashes.
        if (str_starts_with($hash, '$')) {
            if ($this->verifyWithPepper($password, $hash)) {
                return true;
            }

            // A hash created before the pepper was enabled. This is only
            // attempted when a pepper is actually in use; otherwise the two
            // candidates are identical and the check has already failed.
            return $this->pepper !== '' && $this->verifyWithPepper($password, $hash, false);
        }

        // The legacy SketchCMS hash.
        return hash_equals($hash, $this->legacyHash($password));
    }

    /**
     * Whether the stored hash should be replaced with a stronger one.
     */
    public function needsRehash(string $hash): bool
    {
        if (!str_starts_with($hash, '$')) {
            return true;
        }

        if (defined('PASSWORD_ARGON2ID') && str_starts_with($hash, '$argon2id$')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
        }

        if (str_starts_with($hash, '$2y$')) {
            return password_needs_rehash($hash, PASSWORD_BCRYPT, self::BCRYPT_OPTIONS);
        }

        return true;
    }

    /**
     * A hash of a value the user could never supply, used to keep the
     * response time of a failed login constant.
     */
    public function dummyHash(): string
    {
        return '$2y$12$' . str_repeat('.', 53);
    }

    /**
     * Burn roughly the same amount of time as a real verification, so that
     * "unknown user" and "wrong password" cannot be told apart by timing.
     */
    public function burnTime(): void
    {
        password_verify('timing-equaliser', '$2y$12$' . str_repeat('.', 53));
    }

    private function verifyWithPepper(string $password, string $hash, bool $usePepper = true): bool
    {
        $candidate = $usePepper ? $this->pepper($password) : $password;

        return password_verify($candidate, $hash);
    }

    private function pepper(string $password): string
    {
        return $this->pepper === '' ? $password : hash_hmac('sha256', $password, $this->pepper);
    }

    /**
     * Reproduce the legacy SketchCMS hash (character substitution + MD5).
     */
    public function legacyHash(string $password): string
    {
        return md5($this->legacySubstitute($password));
    }

    /**
     * Reverse of the legacy substitution, used by the migration tooling.
     */
    public function legacyDecode(string $value): string
    {
        return strtr($value, array_flip($this->legacyMap()));
    }

    private function legacySubstitute(string $value): string
    {
        return strtr($value, $this->legacyMap());
    }

    /**
     * @return array<string,string>
     */
    private function legacyMap(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = array_merge(
            array_combine(range('0', '4'), array_reverse(range('5', '9'))),
            array_combine(range('5', '9'), array_reverse(range('0', '4'))),
            array_combine(range('a', 'm'), range('z', 'n')),
            array_combine(range('z', 'n'), range('a', 'm')),
            array_combine(range('A', 'M'), range('Z', 'N')),
            array_combine(range('Z', 'N'), range('A', 'M'))
        );

        return $map;
    }
}
