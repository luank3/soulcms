<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\ConfigValidator;
use SoulCMS\Support\SmtpMailer;

final class SmtpMailerTest extends TestCase
{
    public function testSendsConfiguredRecipientSubjectAndPlainTextBodyThroughTransport(): void
    {
        $captured = null;
        $mailer = new SmtpMailer($this->config(), static function (array $settings, string $from, string $to, string $subject, string $body) use (&$captured): bool {
            $captured = compact('settings', 'from', 'to', 'subject', 'body');
            return true;
        });

        self::assertTrue($mailer->send('alice@example.test', 'Reset your password', "Open this link:\nhttps://example.test/reset?token=abc"));
        self::assertSame('smtp.example.test', $captured['settings']['host']);
        self::assertSame(587, $captured['settings']['port']);
        self::assertSame('tls', $captured['settings']['encryption']);
        self::assertSame('SoulCMS <sender@example.test>', $captured['from']);
        self::assertSame('alice@example.test', $captured['to']);
        self::assertSame('Reset your password', $captured['subject']);
        self::assertStringContainsString('token=abc', $captured['body']);
    }

    public function testRefusesInvalidRecipientsAndUnconfiguredTransport(): void
    {
        $calls = 0;
        $mailer = new SmtpMailer($this->config(), static function () use (&$calls): bool {
            ++$calls;
            return true;
        });

        self::assertFalse($mailer->send("victim@example.test\r\nBcc: thief@example.test", 'Subject', 'Body'));
        self::assertSame(0, $calls);
        self::assertFalse((new SmtpMailer(new Config()))->send('alice@example.test', 'Subject', 'Body'));
    }

    public function testConfigValidatorRejectsPartialOrInvalidSmtpSettings(): void
    {
        $config = new Config([
            'app' => [
                'env' => 'testing',
                'url' => ['host' => 'localhost'],
                'mail' => ['host' => 'smtp example.test', 'port' => 70000, 'encryption' => 'plain', 'username' => 'mailer', 'password' => '', 'from_address' => 'bad', 'timeout' => 5],
            ],
            'database' => ['default' => 'default', 'connections' => ['default' => ['driver' => 'sqlite', 'database' => ':memory:']]],
        ]);
        $validator = new ConfigValidator($config->all());

        try {
            $validator->validate();
            self::fail('Invalid SMTP configuration should fail validation.');
        } catch (\RuntimeException) {
            self::assertSame([
                'MAIL_HOST must be a hostname without a URL scheme.',
                'MAIL_PORT must be between 1 and 65535.',
                'MAIL_ENCRYPTION must be tls or ssl.',
                'MAIL_USERNAME and MAIL_PASSWORD must both be set or both be empty.',
                'MAIL_FROM_ADDRESS must be a valid email address when SMTP is configured.',
            ], $validator->errors());
        }
    }

    private function config(): Config
    {
        return new Config(['app' => ['mail' => [
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mailer',
            'password' => 'secret',
            'from_address' => 'sender@example.test',
            'from_name' => 'SoulCMS',
            'timeout' => 5,
        ]]]);
    }
}
