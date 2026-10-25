<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Foundation\Config;
use SoulCMS\Support\S3Storage;

final class S3StorageTest extends TestCase
{
    public function testUploadSignsPutAndSendsFileBytesToTheConfiguredBucket(): void
    {
        $captured = null;
        $calls = 0;
        $storage = new S3Storage($this->config(), static function (string $method, string $url, array $headers, string $body) use (&$captured, &$calls): int {
            ++$calls;
            $captured = compact('method', 'url', 'headers', 'body');
            return 200;
        });
        $path = tempnam(sys_get_temp_dir(), 'soul-s3-');
        self::assertNotFalse($path);
        file_put_contents($path, 'file bytes');

        try {
            self::assertTrue($storage->putFile($path, 'articles/42/9.pdf', 'application/pdf'));
            self::assertSame(1, $calls);
            self::assertSame('PUT', $captured['method']);
            self::assertSame('https://objects.example.test/media-bucket/articles/42/9.pdf', $captured['url']);
            self::assertSame('file bytes', $captured['body']);
            self::assertSame('application/pdf', $captured['headers']['content-type']);
            self::assertSame(hash('sha256', 'file bytes'), $captured['headers']['x-amz-content-sha256']);
            self::assertStringContainsString('AWS4-HMAC-SHA256 Credential=AKID/', $captured['headers']['Authorization']);
        } finally {
            unlink($path);
        }
    }

    public function testPresignedDownloadUrlIsScopedAndHonorsExpiryAndFilename(): void
    {
        $storage = new S3Storage($this->config());

        $url = $storage->presignedGetUrl('articles/42/9.pdf', 300, 'report final.pdf');

        self::assertStringStartsWith('https://objects.example.test/media-bucket/articles/42/9.pdf?', $url);
        self::assertStringContainsString('X-Amz-Algorithm=AWS4-HMAC-SHA256', $url);
        self::assertStringContainsString('X-Amz-Expires=300', $url);
        self::assertStringContainsString('response-content-disposition=attachment%3B%20filename%3D%22report%20final.pdf%22', $url);
        self::assertMatchesRegularExpression('/X-Amz-Signature=[a-f0-9]{64}/', $url);
    }

    public function testVirtualHostedUploadUsesBucketSubdomain(): void
    {
        $capturedUrl = '';
        $config = $this->config();
        $config->set(['app' => ['storage' => ['s3' => [
            'endpoint' => 'https://objects.example.test',
            'bucket' => 'media-bucket',
            'region' => 'test-region-1',
            'access_key' => 'AKID',
            'secret_key' => 'test-secret',
            'path_style' => false,
        ]]]]);
        $storage = new S3Storage($config, static function (string $method, string $url, array $headers, string $body) use (&$capturedUrl): int {
            $capturedUrl = $url;
            return 200;
        });
        $path = tempnam(sys_get_temp_dir(), 'soul-s3-');
        self::assertNotFalse($path);
        file_put_contents($path, 'content');

        try {
            self::assertTrue($storage->putFile($path, 'articles/4/2.txt', 'text/plain'));
            self::assertStringStartsWith('https://media-bucket.objects.example.test/articles/4/2.txt', $capturedUrl);
        } finally {
            unlink($path);
        }
    }

    public function testPresignedUrlRejectsDownloadNamesThatCouldBreakContentDisposition(): void
    {
        $storage = new S3Storage($this->config());
        $url = $storage->presignedGetUrl('articles/42/9.pdf', 300, "report.pdf\"; filename*=UTF-8''other.exe", 'pdf');

        self::assertNotNull($url);
        self::assertStringNotContainsString('other.exe', $url);
        self::assertStringContainsString('response-content-disposition=attachment%3B%20filename%3D%22report.pdf-%20filename-UTF-8-other.pdf%22', $url);
    }

    public function testPathStyleCanBeDisabledForVirtualHostedS3(): void
    {
        $config = $this->config();
        $config->set(['app' => ['storage' => ['s3' => [
            'endpoint' => 'https://objects.example.test',
            'bucket' => 'media-bucket',
            'region' => 'test-region-1',
            'access_key' => 'AKID',
            'secret_key' => 'test-secret',
            'path_style' => false,
        ]]]]);
        $storage = new S3Storage($config, static fn (): int => 200);
        $path = tempnam(sys_get_temp_dir(), 'soul-s3-');
        self::assertNotFalse($path);
        file_put_contents($path, 'content');

        try {
            self::assertTrue($storage->putFile($path, 'articles/4/2.txt', 'text/plain'));
        } finally {
            unlink($path);
        }
    }

    public function testStorageConfigurationValidationRejectsMissingCredentialsAndHttpEndpoint(): void
    {
        $missingCredentials = new Config(['app' => ['storage' => ['s3' => [
            'endpoint' => 'https://objects.example.test',
            'bucket' => 'bucket',
            'region' => 'region-1',
            'access_key' => 'key',
        ]]]]);
        self::assertFalse((new S3Storage($missingCredentials))->isConfigured());

        $insecure = $this->config();
        $insecure->set(['app' => ['storage' => ['s3' => [
            'endpoint' => 'http://objects.example.test',
            'bucket' => 'bucket',
            'region' => 'region-1',
            'access_key' => 'key',
            'secret_key' => 'secret',
            'path_style' => true,
        ]]]]);
        self::assertFalse((new S3Storage($insecure))->isConfigured());
    }

    public function testStorageRejectsInvalidKeysAndUnavailableConfiguration(): void
    {
        $storage = new S3Storage($this->config());
        $path = tempnam(sys_get_temp_dir(), 'soul-s3-');
        self::assertNotFalse($path);
        file_put_contents($path, 'x');

        try {
            self::assertFalse($storage->putFile($path, '../outside.txt', 'text/plain'));
            self::assertNull($storage->presignedGetUrl('../outside.txt', 300, 'file.txt'));
        } finally {
            unlink($path);
        }

        $unconfigured = new Config();
        $unconfigured->set(['app' => ['storage' => ['s3' => ['endpoint' => 'https://objects.example.test', 'bucket' => 'media-bucket']]]]);
        self::assertNull((new S3Storage($unconfigured))->presignedGetUrl('articles/1/1.txt', 300, 'file.txt'));
        self::assertFalse((new S3Storage(new Config()))->putFile('/missing', 'articles/1/1.txt', 'text/plain'));
    }

    private function config(): Config
    {
        return new Config([
            'app' => [
                'storage' => [
                    's3' => [
                        'endpoint' => 'https://objects.example.test',
                        'bucket' => 'media-bucket',
                        'region' => 'test-region-1',
                        'access_key' => 'AKID',
                        'secret_key' => 'test-secret',
                        'path_style' => true,
                    ],
                ],
            ],
        ]);
    }
}
