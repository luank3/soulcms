<?php

declare(strict_types=1);

namespace SoulCMS\Support;

use Closure;
use SoulCMS\Foundation\Config;

final class SmtpMailer
{
    /** @var Closure(array<string,mixed>,string,string,string,string):bool */
    private Closure $transport;

    /** @param null|callable(array<string,mixed>,string,string,string,string):bool $transport */
    public function __construct(private Config $config, ?callable $transport = null)
    {
        $this->transport = $transport === null
            ? self::sendSmtp(...)
            : Closure::fromCallable($transport);
    }

    public function isConfigured(): bool
    {
        return $this->settings() !== null;
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $settings = $this->settings();
        $from = trim((string) $this->config->get('app.mail.from_address', ''));
        $fromName = trim((string) $this->config->get('app.mail.from_name', 'SoulCMS'));

        if ($settings === null || filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if (preg_match('/[\r\n]/', $subject . $to . $from . $fromName) === 1) {
            return false;
        }

        try {
            return ($this->transport)($settings, $fromName . ' <' . $from . '>', $to, $subject, $body);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{host:string,port:int,encryption:string,username:string,password:string,timeout:int}|null */
    private function settings(): ?array
    {
        $host = trim((string) $this->config->get('app.mail.host', ''));
        $port = (int) $this->config->get('app.mail.port', 0);
        $encryption = strtolower(trim((string) $this->config->get('app.mail.encryption', '')));
        $username = (string) $this->config->get('app.mail.username', '');
        $password = (string) $this->config->get('app.mail.password', '');
        $timeout = (int) $this->config->get('app.mail.timeout', 10);

        if ($host === '' || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1
            || $port < 1 || $port > 65535 || !in_array($encryption, ['tls', 'ssl'], true)
            || $timeout < 1 || $timeout > 60) {
            return null;
        }

        if (($username === '') !== ($password === '')) {
            return null;
        }

        return compact('host', 'port', 'encryption', 'username', 'password', 'timeout');
    }

    /** @param array{host:string,port:int,encryption:string,username:string,password:string,timeout:int} $settings */
    private static function sendSmtp(array $settings, string $from, string $to, string $subject, string $body): bool
    {
        $prefix = $settings['encryption'] === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $prefix . $settings['host'] . ':' . $settings['port'],
            $errorCode,
            $errorMessage,
            $settings['timeout'],
            STREAM_CLIENT_CONNECT
        );
        if (!is_resource($socket)) {
            return false;
        }

        stream_set_timeout($socket, $settings['timeout']);
        try {
            if (!self::expect($socket, [220])) {
                return false;
            }
            self::command($socket, 'EHLO soulcms', [250]);
            if ($settings['encryption'] === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return false;
                }
                self::command($socket, 'EHLO soulcms', [250]);
            }

            if ($settings['username'] !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($settings['username']), [334]);
                self::command($socket, base64_encode($settings['password']), [235]);
            }
            self::command($socket, 'MAIL FROM:<' . self::address($from) . '>', [250]);
            self::command($socket, 'RCPT TO:<' . self::address($to) . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $headers = [
            'From: ' . self::encodedDisplayName($from),
            'To: <' . self::address($to) . '>',
                'Subject: ' . self::encodedSubject($subject),
                'Date: ' . date(DATE_RFC2822),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", self::normaliseBody($body));
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            fwrite($socket, $message . "\r\n.\r\n");
            if (!self::expect($socket, [250])) {
                return false;
            }
            self::command($socket, 'QUIT', [221]);

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket @param list<int> $codes */
    private static function command($socket, string $command, array $codes): void
    {
        fwrite($socket, $command . "\r\n");
        if (!self::expect($socket, $codes)) {
            throw new \RuntimeException('SMTP server rejected a command.');
        }
    }

    /** @param resource $socket @param list<int> $codes */
    private static function expect($socket, array $codes): bool
    {
        $line = '';
        do {
            $line = (string) fgets($socket, 515);
        } while (strlen($line) >= 4 && $line[3] === '-');

        return preg_match('/^(\d{3}) /', $line, $match) === 1 && in_array((int) $match[1], $codes, true);
    }

    private static function encodedDisplayName(string $from): string
    {
        if (preg_match('/^([^<>]+) <([^<>]+)>$/', $from, $match) !== 1) {
            return '<' . self::address($from) . '>';
        }

        $name = trim($match[1]);
        $encoded = preg_match('/[^\\x20-\\x7E]/', $name) === 1
            ? '=?UTF-8?B?' . base64_encode($name) . '?='
            : '"' . str_replace(['\\\\', '"'], ['\\\\\\\\', '\\"'], $name) . '"';

        return $encoded . ' <' . $match[2] . '>';
    }

    private static function address(string $value): string
    {
        if (preg_match('/<([^<>]+)>/', $value, $match) === 1) {
            return $match[1];
        }

        return $value;
    }

    private static function header(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private static function encodedSubject(string $subject): string
    {
        return preg_match('/[^\x20-\x7E]/', $subject) === 1
            ? '=?UTF-8?B?' . base64_encode(self::header($subject)) . '?='
            : self::header($subject);
    }

    private static function normaliseBody(string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", $body);
    }
}
