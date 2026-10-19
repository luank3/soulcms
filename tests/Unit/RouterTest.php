<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Routing\Route;
use SoulCMS\Routing\RouteCollection;
use SoulCMS\Routing\RouteCompiler;
use SoulCMS\Routing\Router;

final class RouterTest extends TestCase
{
    public function testStaticRouteIsMatchedExactly(): void
    {
        $router = new Router();
        $router->get('/about', static fn (): string => 'about', 'about');
        $router->freeze();

        $match = $router->dispatch($this->request('GET', '/about'));

        self::assertNotNull($match);
        self::assertSame([], $match['parameters']);
        self::assertSame('about', $match['name']);
    }

    public function testParameterisedRouteCapturesValues(): void
    {
        $router = new Router();
        $router->get('/articles/{slug}', static fn (): string => 'post', 'post');
        $router->freeze();

        $match = $router->dispatch($this->request('GET', '/articles/hello-world'));

        self::assertNotNull($match);
        self::assertSame(['slug' => 'hello-world'], $match['parameters']);
    }

    public function testCustomConstraintIsEnforced(): void
    {
        $router = new Router();
        $router->get('/articles/{id:[0-9]+}', static fn (): string => 'post', 'post');
        $router->freeze();

        self::assertNotNull($router->dispatch($this->request('GET', '/articles/12')));
        self::assertNull($router->dispatch($this->request('GET', '/articles/abc')));
    }

    public function testSketchCmsStyleAnonymousParameterIsSupported(): void
    {
        $router = new Router();
        $router->add('/articles/post-{:id}/upload', static fn (): string => 'upload', 'GET|POST', 'upload');
        $router->freeze();

        $match = $router->dispatch($this->request('POST', '/articles/post-42/upload'));

        self::assertNotNull($match);
        self::assertSame(['id' => '42'], $match['parameters']);
    }

    public function testMoreSpecificRouteWins(): void
    {
        $router = new Router();
        $router->get('/articles/{slug}', static fn (): string => 'plain', 'plain');
        $router->get('/articles/{slug}.html', static fn (): string => 'html', 'html');
        $router->freeze();

        $html = $router->dispatch($this->request('GET', '/articles/hello.html'));
        $plain = $router->dispatch($this->request('GET', '/articles/hello'));

        self::assertSame('html', $html['name']);
        self::assertSame('plain', $plain['name']);
        self::assertSame(['slug' => 'hello'], $plain['parameters']);
    }

    public function testStaticRouteWinsOverParameterisedRoute(): void
    {
        $router = new Router();
        $router->get('/articles/post', static fn (): string => 'static', 'static');
        $router->get('/articles/{slug}', static fn (): string => 'dynamic', 'dynamic');
        $router->freeze();

        self::assertSame('static', $router->dispatch($this->request('GET', '/articles/post'))['name']);
    }

    public function testMethodMismatchIsReportedAsNotAllowed(): void
    {
        $router = new Router();
        $router->add('/submit', static fn (): string => 'submit', 'POST', 'submit');
        $router->freeze();

        self::assertNull($router->dispatch($this->request('GET', '/submit')));
        self::assertSame(['POST'], $router->allowedMethods('/submit'));
    }

    public function testHeadFallsBackToGet(): void
    {
        $router = new Router();
        $router->get('/page', static fn (): string => 'page', 'page');
        $router->freeze();

        self::assertNotNull($router->dispatch($this->request('HEAD', '/page')));
    }

    public function testGroupAppliesPrefixAndMiddleware(): void
    {
        $router = new Router();
        $router->group('/admin', ['AuthMiddleware'], static function (Router $router): void {
            $router->get('/users', static fn (): string => 'users', 'users');
        });
        $router->freeze();

        $match = $router->dispatch($this->request('GET', '/admin/users'));

        self::assertNotNull($match);
        self::assertSame(['AuthMiddleware'], $match['middleware']);
    }

    public function testNamedRouteUrlGeneration(): void
    {
        $router = new Router();
        $router->get('/articles/{id}', static fn (): string => 'post', 'articles.show');
        $router->freeze();

        self::assertSame('/articles/7', $router->url('articles.show', ['id' => 7]));
    }

    public function testCompilerProducesAnchoredPattern(): void
    {
        $compiled = RouteCompiler::compile('/articles/{id:[0-9]+}');

        self::assertSame(['id'], $compiled['variables']);
        self::assertSame(1, preg_match($compiled['regex'], '/articles/12'));
        self::assertSame(0, preg_match($compiled['regex'], '/articles/abc'));
    }

    public function testCollectionSerialisationRoundTrip(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/a/{x}', ['GET'], static fn (): string => 'a', [], [], 'a'));
        $collection->freeze();

        $restored = RouteCollection::fromArray($collection->toArray());

        self::assertSame(1, $restored->count());
        self::assertNotNull($restored->get('a'));
        self::assertSame(['x' => '1'], $restored->match('GET', '/a/1')['parameters']);
    }

    private function request(string $method, string $path): \SoulCMS\Http\Request
    {
        return new \SoulCMS\Http\Request(
            $method,
            new \SoulCMS\Http\Uri('http://localhost', 'localhost', null, $path, '', ''),
            [],
            [],
            [],
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1']
        );
    }
}
