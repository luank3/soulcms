<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\ConfigValidator;
use SoulCMS\Foundation\Events\Dispatcher;
use SoulCMS\Http\Csp;
use SoulCMS\Http\UploadedFile;
use SoulCMS\Support\Validator;

/**
 * The security and correctness guarantees of the hardening pass.
 */
final class HardeningTest extends TestCase
{
    /* ------------------------------------------------------------------
       Passwords
       ------------------------------------------------------------------ */

    public function testPasswordsUseAHashAndNeverThePlainText(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct horse battery staple');

        self::assertNotSame('correct horse battery staple', $hash);
        self::assertTrue($hasher->verify('correct horse battery staple', $hash));
        self::assertFalse($hasher->verify('wrong', $hash));
        self::assertStringNotContainsString('correct horse', $hash);
    }

    public function testAWrongPasswordNeverVerifies(): void
    {
        // Regression: the pepper fallback must not short-circuit to true
        // when no pepper is configured, or any password would be accepted.
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('the-real-password');

        self::assertFalse($hasher->verify('the-wrong-password', $hash));
        self::assertFalse($hasher->verify('', $hash));
        self::assertFalse($hasher->verify('the-real-password', ''));

        $peppered = new PasswordHasher('secret');
        $pepperedHash = $peppered->hash('the-real-password');

        self::assertFalse($peppered->verify('the-wrong-password', $pepperedHash));
    }

    public function testTheLegacySketchCmsHashIsStillAccepted(): void
    {
        $hasher = new PasswordHasher();
        $legacy = $hasher->legacyHash('secret123');

        self::assertTrue($hasher->verify('secret123', $legacy));
        self::assertFalse($hasher->verify('secret124', $legacy));
        self::assertTrue($hasher->needsRehash($legacy), 'A legacy hash must be upgraded.');
    }

    public function testAPepperChangesTheHashButNotTheOutcome(): void
    {
        $plain = new PasswordHasher();
        $peppered = new PasswordHasher('a-server-side-secret');

        $hash = $peppered->hash('secret123');

        self::assertTrue($peppered->verify('secret123', $hash));
        self::assertFalse($plain->verify('secret123', $hash), 'The hash must not verify without the pepper.');

        // A hash created before the pepper was enabled keeps working.
        self::assertTrue($peppered->verify('secret123', $plain->hash('secret123')));
    }

    public function testTheTimingEqualiserBurnsTime(): void
    {
        $hasher = new PasswordHasher();

        $start = microtime(true);
        $hasher->burnTime();
        $elapsed = microtime(true) - $start;

        self::assertGreaterThan(0.001, $elapsed, 'The equaliser must not be free.');
    }

    /* ------------------------------------------------------------------
       Content Security Policy
       ------------------------------------------------------------------ */

    public function testThePolicyUsesAFreshNonceForEveryRequest(): void
    {
        $first = new Csp();
        $second = new Csp();

        self::assertNotSame($first->nonce(), $second->nonce());
        self::assertStringContainsString("'nonce-" . $first->nonce() . "'", $first->build());
        self::assertStringNotContainsString($first->nonce(), $second->build());
    }

    public function testExplicitFrameSourceReplacesTheDefaultFrameBlock(): void
    {
        $policy = (new Csp())->allow('frame-src', 'https://www.youtube-nocookie.com')->build();

        self::assertStringContainsString('frame-src https://www.youtube-nocookie.com', $policy);
        self::assertStringNotContainsString('frame-src \'none\'', $policy);
    }

    public function testThePolicyLocksScriptsDown(): void
    {
        $policy = (new Csp())->build();

        self::assertStringContainsString("script-src 'self' 'nonce-", $policy);
        self::assertStringContainsString("script-src-attr 'none'", $policy);
        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertStringContainsString("base-uri 'self'", $policy);
        self::assertStringContainsString("frame-ancestors 'self'", $policy);
        self::assertStringNotContainsString("'unsafe-eval'", $policy);
    }

    /* ------------------------------------------------------------------
       Redirects
       ------------------------------------------------------------------ */

    public function testForeignHostsAreRefusedBySafeUrl(): void
    {
        // The helper needs the application for its configuration.
        $app = \SoulCMS\Foundation\Application::create(dirname(__DIR__, 2));
        $app->handle(
            new \SoulCMS\Http\Request('GET', new \SoulCMS\Http\Uri('http://localhost', 'localhost', null, '/', '', ''))
        );

        self::assertSame('/', safe_url('https://evil.test/steal'));
        self::assertSame('/', safe_url('//evil.test/steal'));
        self::assertSame('/', safe_url('javascript:alert(1)'));
        self::assertSame('/articles', safe_url('/articles'));
    }

    /* ------------------------------------------------------------------
       Query builder
       ------------------------------------------------------------------ */

    public function testTheQueryBuilderRefusesRawIdentifiers(): void
    {
        $connection = new \SoulCMS\Database\Connection(new \PDO('sqlite::memory:'), \SoulCMS\Database\Driver::Sqlite);
        $grammar = new \SoulCMS\Database\Query\Grammar($connection);

        $this->expectException(\InvalidArgumentException::class);

        $grammar->wrap("id) OR 1=1 --");
    }

    public function testRawExpressionsMustBeExplicit(): void
    {
        $connection = new \SoulCMS\Database\Connection(new \PDO('sqlite::memory:'), \SoulCMS\Database\Driver::Sqlite);
        $grammar = new \SoulCMS\Database\Query\Grammar($connection);

        self::assertSame(
            'COUNT(id) AS total',
            $grammar->wrap(\SoulCMS\Database\Query\Raw::expr('COUNT(id) AS total'))
        );
    }

    /* ------------------------------------------------------------------
       Uploads
       ------------------------------------------------------------------ */

    public function testUploadNamesAreSanitised(): void
    {
        self::assertSame('report.pdf', UploadedFile::sanitiseName('../../report.pdf'));
        self::assertSame('evil.sh.php', UploadedFile::sanitiseName("evil.sh\n.php"));
        self::assertSame('file', UploadedFile::sanitiseName(''));
    }

    public function testAnUploadWithoutAValidTemporaryFileIsRejected(): void
    {
        $file = UploadedFile::fromArray(
            ['name' => 'a.txt', 'tmp_name' => '/etc/passwd', 'error' => UPLOAD_ERR_OK, 'size' => 10],
            ['txt'],
            ['text/plain'],
            1024
        );

        self::assertNull($file, 'A file that was not uploaded must be refused.');
    }

    public function testAnUploadWithADisallowedExtensionIsRejected(): void
    {
        $file = UploadedFile::fromArray(
            ['name' => 'shell.php', 'tmp_name' => '/etc/passwd', 'error' => UPLOAD_ERR_OK, 'size' => 10],
            ['txt', 'png'],
            ['text/plain', 'image/png'],
            1024
        );

        self::assertNull($file);
    }

    /* ------------------------------------------------------------------
       Validation
       ------------------------------------------------------------------ */

    public function testBbcodeBoldAndItalicAreRenderedAfterEscaping(): void
    {
        self::assertSame(
            '<p><strong>bold</strong> and <em>italic</em></p>',
            markdown_to_html('[b]bold[/b] and [i]italic[/i]')
        );
    }

    public function testBbcodeUrlAndImageAcceptOnlySafeUrls(): void
    {
        self::assertSame(
            '<p><a href="https://example.test/path" rel="nofollow noopener">site</a> <img src="https://example.test/pic.png" alt="pic.png" loading="lazy"></p>',
            markdown_to_html('[url=https://example.test/path]site[/url] [img]https://example.test/pic.png[/img]')
        );
        self::assertSame(
            '<p>x</p>',
            markdown_to_html('[url=javascript:alert(1)]x[/url]')
        );
    }

    public function testBbcodeQuoteAndCodeAreRenderedAsText(): void
    {
        self::assertSame(
            '<blockquote><p>quoted &lt;script&gt;</p></blockquote>',
            markdown_to_html('[quote]quoted <script>[/quote]')
        );
        self::assertSame(
            '<p><code>[b]literal[/b]</code></p>',
            markdown_to_html('[code][b]literal[/b][/code]')
        );
    }

    public function testTheConfigurationValidatorRejectsUnknownStorageDriver(): void
    {
        $validator = new ConfigValidator([
            'app' => [
                'url' => ['host' => 'localhost'],
                'env' => 'testing',
                'storage' => ['driver' => 'telegramupload'],
            ],
            'database' => ['default' => 'default', 'connections' => ['default' => ['driver' => 'sqlite', 'database' => '/tmp/x.sqlite']]],
        ]);

        try {
            $validator->validate();
            self::fail('Unknown storage drivers must fail configuration validation.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('app.storage.driver must be local or s3', $exception->getMessage());
        }
    }

    public function testTheConfigurationValidatorRejectsIncompleteS3Storage(): void
    {
        $validator = new ConfigValidator([
            'app' => [
                'url' => ['host' => 'localhost'],
                'env' => 'production',
                'key' => str_repeat('k', 40),
                'storage' => ['driver' => 's3', 's3' => ['endpoint' => 'http://objects.example.test', 'bucket' => 'bucket']],
            ],
            'database' => ['default' => 'default', 'connections' => ['default' => ['driver' => 'sqlite', 'database' => '/tmp/x.sqlite']]],
        ]);

        try {
            $validator->validate();
            self::fail('Incomplete S3 configuration must fail validation.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('S3_ENDPOINT must use HTTPS', $exception->getMessage());
            self::assertStringContainsString('S3_REGION', $exception->getMessage());
            self::assertStringContainsString('S3_ACCESS_KEY', $exception->getMessage());
            self::assertStringContainsString('S3_SECRET_KEY', $exception->getMessage());
        }
    }

    public function testTheValidatorReportsEveryBrokenRule(): void
    {
        $result = (new Validator(['nick' => 'ab', 'email' => 'nope']))->check([
            'nick' => 'required|string:3,32',
            'email' => 'required|email',
        ]);

        self::assertFalse($result['valid']);
        self::assertArrayHasKey('nick', $result['errors']);
        self::assertArrayHasKey('email', $result['errors']);
    }

    public function testTheValidatorPassesCleanData(): void
    {
        $result = (new Validator(['nick' => 'alice', 'email' => 'a@b.test']))->check([
            'nick' => 'required|string:3,32',
            'email' => 'required|email',
        ]);

        self::assertTrue($result['valid']);
        self::assertSame('alice', $result['data']['nick']);
    }

    public function testTheValidatorChecksConfirmation(): void
    {
        $result = (new Validator(['a' => 'x', 'b' => 'y']))->check([
            'a' => 'required|confirmed:b',
        ]);

        self::assertFalse($result['valid']);
    }

    /* ------------------------------------------------------------------
       Configuration
       ------------------------------------------------------------------ */

    public function testTheConfigurationIsValidated(): void
    {
        $validator = new ConfigValidator([
            'app' => ['url' => ['host' => ''], 'env' => 'production', 'key' => ''],
            'database' => ['default' => 'default', 'connections' => ['default' => ['driver' => 'sqlite', 'database' => '']]],
        ]);

        $this->expectException(\RuntimeException::class);

        $validator->validate();
    }

    public function testAValidConfigurationPasses(): void
    {
        $validator = new ConfigValidator([
            'app' => ['url' => ['host' => 'localhost'], 'env' => 'production', 'key' => str_repeat('k', 40)],
            'database' => ['default' => 'default', 'connections' => ['default' => ['driver' => 'sqlite', 'database' => '/tmp/x.sqlite']]],
        ]);

        $validator->validate();

        self::assertSame([], $validator->errors());
    }

    /* ------------------------------------------------------------------
       Events
       ------------------------------------------------------------------ */

    public function testTheDispatcherCallsTheListeners(): void
    {
        $dispatcher = new Dispatcher();
        $seen = [];

        $dispatcher->listen(\stdClass::class, static function (object $event) use (&$seen): void {
            $seen[] = 'first';
        });

        $dispatcher->listen(\stdClass::class, static function (object $event) use (&$seen): void {
            $seen[] = 'second';
        });

        $dispatcher->dispatch(new \stdClass());

        self::assertSame(['first', 'second'], $seen);
    }
}
