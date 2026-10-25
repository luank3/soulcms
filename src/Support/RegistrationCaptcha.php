<?php

declare(strict_types=1);

namespace SoulCMS\Support;

use SoulCMS\Http\Session\SessionManager;

/** One-use, session-bound arithmetic challenge for registration. */
final class RegistrationCaptcha
{
    private const SESSION_KEY = '_registration_captcha';

    public function __construct(private SessionManager $session)
    {
    }

    public function issue(): string
    {
        $left = random_int(2, 9);
        $right = random_int(1, 9);
        $answer = (string) ($left + $right);
        $this->session->put(self::SESSION_KEY, hash_hmac('sha256', $answer, $this->session->token()));

        return sprintf('%d + %d = ?', $left, $right);
    }

    public function verify(string $answer): bool
    {
        $expected = $this->session->peek(self::SESSION_KEY);
        $this->session->forget(self::SESSION_KEY);
        $answer = trim($answer);

        if (!is_string($expected) || $answer === '' || preg_match('/^\d{1,2}$/', $answer) !== 1) {
            return false;
        }

        return hash_equals($expected, hash_hmac('sha256', $answer, $this->session->token()));
    }
}
