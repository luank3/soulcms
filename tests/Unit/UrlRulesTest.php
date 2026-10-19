<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Http\UrlRules;

final class UrlRulesTest extends TestCase
{
    public function testRewriteMapsAPathToItsTarget(): void
    {
        $rules = $this->rules([
            'rewrite' => ['/tin-tuc' => ['target' => '/articles?category=2', 'method' => 'GET|POST']],
        ]);

        self::assertSame('/articles?category=2', $rules->rewrite('/tin-tuc', 'GET'));
        self::assertNull($rules->rewrite('/khong-ton-tai', 'GET'));
    }

    public function testRewriteRespectsTheAllowedMethods(): void
    {
        $rules = $this->rules([
            'rewrite' => ['/tin-tuc' => ['target' => '/articles', 'method' => 'GET']],
        ]);

        self::assertSame('/articles', $rules->rewrite('/tin-tuc', 'GET'));
        self::assertNull($rules->rewrite('/tin-tuc', 'POST'));
    }

    public function testWildcardMethodAllowsEveryMethod(): void
    {
        $rules = $this->rules([
            'rewrite' => ['/tin-tuc' => ['target' => '/articles', 'method' => '*']],
        ]);

        self::assertSame('/articles', $rules->rewrite('/tin-tuc', 'POST'));
    }

    public function testDisabledPathsAreBlocked(): void
    {
        $rules = $this->rules(['disable' => ['/register', '/admin']]);

        self::assertTrue($rules->isDisabled('/register'));
        self::assertTrue($rules->isDisabled('/admin'));
        self::assertFalse($rules->isDisabled('/login'));
    }

    public function testPathsAreNormalised(): void
    {
        $rules = $this->rules(['disable' => ['register'], 'rewrite' => ['tin-tuc/' => ['target' => '/articles']]]);

        self::assertTrue($rules->isDisabled('/register'));
        self::assertSame('/articles', $rules->rewrite('/tin-tuc', 'GET'));
    }

    public function testAnEmptyConfigurationCostsNothing(): void
    {
        $rules = $this->rules([]);

        self::assertFalse($rules->isDisabled('/anything'));
        self::assertNull($rules->rewrite('/anything', 'GET'));
    }

    public function testMalformedConfigurationIsIgnored(): void
    {
        $rules = $this->rules(['rewrite' => ['/a' => ['no-target' => 1]]]);

        self::assertNull($rules->rewrite('/a', 'GET'));
    }

    public function testAPlainStringIsAcceptedAsTheTarget(): void
    {
        $rules = $this->rules(['rewrite' => ['/b' => '/articles']]);

        self::assertSame('/articles', $rules->rewrite('/b', 'GET'));
    }

    /**
     * @param array<string,mixed> $config
     */
    private function rules(array $config): UrlRules
    {
        return new UrlRules($config);
    }
}
