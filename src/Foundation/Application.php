<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\ConnectionConfig;
use SoulCMS\Database\ConnectionFactory;
use SoulCMS\App\Listeners\NotifyPostAuthor;
use SoulCMS\Events\CommentPosted;
use SoulCMS\Foundation\Events\Dispatcher;
use SoulCMS\Foundation\Module\ModuleManager;
use SoulCMS\Http\Csp;
use SoulCMS\Http\ErrorHandler;
use SoulCMS\Http\Kernel;
use SoulCMS\Http\Middleware\CompressionMiddleware;
use SoulCMS\Http\Middleware\ConditionalGetMiddleware;
use SoulCMS\Http\Middleware\CsrfMiddleware;
use SoulCMS\Http\Middleware\RateLimitMiddleware;
use SoulCMS\Http\Middleware\RequestIdMiddleware;
use SoulCMS\Http\Middleware\SecurityHeadersMiddleware;
use SoulCMS\Http\Middleware\StaticAssetMiddleware;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\UrlRules;
use SoulCMS\Routing\RouteCache;
use SoulCMS\Routing\Router;
use SoulCMS\Support\Lang;
use SoulCMS\Support\Logger;
use SoulCMS\Support\RateLimiter;
use SoulCMS\Support\SettingsStore;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

/**
 * The application container and bootstrapper.
 *
 * The application is created once per process (or once per request in a
 * classic PHP-FPM setup) and wires every service together.
 */
final class Application
{
    private Container $container;

    private ?Kernel $kernel = null;

    private float $bootTime;

    public function __construct(private string $basePath)
    {
        $this->bootTime = microtime(true);

        Paths::setBase($this->basePath);

        Env::load(Paths::join('.env'));

        $this->container = Container::getInstance();
        $this->container->singleton(self::class, fn (): self => $this);

        // The container must be able to resolve itself, otherwise the
        // autowiring would create a fresh (empty) instance for every
        // consumer that type-hints it.
        $this->container->singleton(Container::class, static fn (Container $c): Container => $c);

        $this->registerConfig();
        $this->registerServices();
    }

    public static function create(?string $basePath = null): self
    {
        $basePath = $basePath ?? dirname(__DIR__, 2);

        return new self($basePath);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function bootTime(): float
    {
        return $this->bootTime;
    }

    /**
     * The files the routing table is built from. Their modification times
     * decide whether the compiled route table is still valid.
     *
     * @return list<string>
     */
    public static function routeSources(): array
    {
        $sources = [Paths::join('routes', 'web.php')];

        foreach (glob(Paths::modules('*') . '/Module.php') ?: [] as $file) {
            $sources[] = $file;
        }

        return $sources;
    }

    private function registerConfig(): void
    {
        $config = new Config();

        $files = glob(Paths::config('*.php')) ?: [];
        sort($files);

        $items = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $items[$name] = require $file;
        }

        $local = Paths::config('local.php');

        if (is_file($local)) {
            $items = Arr::merge($items, (array) require $local);
        }

        $config->set($items);
        Config::swap($config);

        // The interface speaks one language.
        Lang::setLocale((string) $config->get('app.locale', 'vi'));

        // Fail fast: a misconfigured application must not start.
        (new ConfigValidator($items))->validate();

        $this->container->instance('config', $config);
        $this->container->singleton(Config::class, static fn (): Config => $config);
    }

    private function registerServices(): void
    {
        $container = $this->container;

        $container->singleton(Clock::class, static fn (): Clock => new Clock());

        $container->singleton(PasswordHasher::class, static function (Container $c): PasswordHasher {
            /** @var Config $config */
            $config = $c->get('config');

            return new PasswordHasher((string) $config->get('app.security.password_pepper', ''));
        });

        $container->singleton(Logger::class, static function (Container $c): Logger {
            /** @var Config $config */
            $config = $c->get('config');

            return new Logger(
                Paths::logs('soulcms.log'),
                $config->bool('app.debug', false),
                $config->get('app.log_channel', 'stack')
            );
        });

        $container->singleton(Dispatcher::class, static function (Container $c): Dispatcher {
            $dispatcher = new Dispatcher();

            $dispatcher->listen(CommentPosted::class, NotifyPostAuthor::class);

            return $dispatcher;
        });

        $container->singleton('events', static function (Container $c): Dispatcher {
            /** @var Dispatcher $dispatcher */
            $dispatcher = $c->get(Dispatcher::class);

            return $dispatcher;
        });

        $container->singleton(UrlRules::class, static function (Container $c): UrlRules {
            /** @var SettingsStore $settings */
            $settings = $c->get(SettingsStore::class);
            $raw = (string) $settings->get('url_rules', '{}');
            $decoded = json_decode($raw, true);

            return new UrlRules(is_array($decoded) ? $decoded : []);
        });

        $container->singleton('logger', static function (Container $c): Logger {
            /** @var Logger $logger */
            $logger = $c->get(Logger::class);

            return $logger;
        });

        $container->singleton('db', static function (Container $c): Connection {
            /** @var Config $config */
            $config = $c->get('config');

            $factory = new ConnectionFactory();

            return $factory->create(ConnectionConfig::settings($config));
        });

        $container->singleton(Connection::class, static function (Container $c): Connection {
            /** @var Connection $connection */
            $connection = $c->get('db');

            return $connection;
        });

        $container->singleton('view', static function (Container $c): ViewFactory {
            /** @var Config $config */
            $config = $c->get('config');

            $engine = new Engine(
                Paths::views(),
                Paths::cache('views'),
                $config->bool('app.debug', false)
            );

            return new ViewFactory($engine);
        });

        $container->singleton(ViewFactory::class, static function (Container $c): ViewFactory {
            /** @var ViewFactory $view */
            $view = $c->get('view');

            return $view;
        });

        $container->singleton('router', static function (Container $c): Router {
            /** @var Config $config */
            $config = $c->get('config');

            $router = new Router();

            $cache = new RouteCache(
                Paths::cache('routes.php'),
                $config->bool('app.debug', false)
            );

            $sources = self::routeSources();

            $collection = $cache->load($sources);
            $router = new Router($collection);

            if ($collection === null) {
                $container = $c;

                require Paths::join('routes', 'web.php');

                $router->freeze();

                // Keep the cache warm for the next request.
                $cache->save($router->getCollection(), $sources);
            }

            return $router;
        });

        $container->singleton(Router::class, static function (Container $c): Router {
            /** @var Router $router */
            $router = $c->get('router');

            return $router;
        });

        $container->singleton('modules', static function (Container $c): ModuleManager {
            /** @var Config $config */
            $config = $c->get('config');

            return new ModuleManager($c, (string) $config->get('app.modules_path', ModuleManager::defaultPath()));
        });

        $container->singleton(ModuleManager::class, static function (Container $c): ModuleManager {
            /** @var ModuleManager $manager */
            $manager = $c->get('modules');

            return $manager;
        });

        $container->singleton('request', static fn (): Request => Request::createFromGlobals());

        $container->singleton(Request::class, static function (Container $c): Request {
            /** @var Request $request */
            $request = $c->get('request');

            return $request;
        });

        $container->singleton(SessionManager::class, static function (Container $c): SessionManager {
            /** @var Config $config */
            $config = $c->get('config');

            return new SessionManager(
                (string) $config->get('app.session.name', 'SoulCMS'),
                [
                    'lifetime' => (int) $config->get('app.session.lifetime', 86400),
                    'samesite' => (string) $config->get('app.session.same_site', 'Lax'),
                ],
                $c->get(Clock::class),
                [],
                (int) $config->get('app.session.idle_timeout', 1800),
                (int) $config->get('app.session.absolute_timeout', 43200)
            );
        });

        $container->singleton('session', static function (Container $c): SessionManager {
            /** @var SessionManager $session */
            $session = $c->get(SessionManager::class);

            return $session;
        });

        $container->singleton(RateLimiter::class, static fn (): RateLimiter => RateLimiter::default());

        $container->singleton(SettingsStore::class, static function (Container $c): SettingsStore {
            /** @var Connection $connection */
            $connection = $c->get('db');

            return new SettingsStore($connection);
        });

        /*
         * The global middleware stack is bound explicitly so that the
         * configuration can be injected into it.
         */
        $container->singleton(Csp::class, static function (Container $c): Csp {
            $csp = new Csp();

            /** @var Config $config */
            $config = $c->get('config');

            foreach ((array) $config->get('app.security.csp_extra', []) as $directive => $sources) {
                foreach ((array) $sources as $source) {
                    $csp->allow((string) $directive, (string) $source);
                }
            }

            return $csp;
        });

        $container->singleton(RequestIdMiddleware::class, static function (): RequestIdMiddleware {
            return new RequestIdMiddleware();
        });

        $container->singleton('csp', static function (Container $c): Csp {
            /** @var Csp $csp */
            $csp = $c->get(Csp::class);

            return $csp;
        });

        $container->singleton(SecurityHeadersMiddleware::class, static function (Container $c): SecurityHeadersMiddleware {
            /** @var Config $config */
            $config = $c->get('config');
            /** @var Csp $csp */
            $csp = $c->get(Csp::class);

            $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

            return new SecurityHeadersMiddleware(
                $csp->build($https),
                (array) $config->get('app.security.headers', [])
            );
        });

        $container->singleton(StaticAssetMiddleware::class, static function (): StaticAssetMiddleware {
            return new StaticAssetMiddleware(Paths::join('public'));
        });

        $container->singleton(CompressionMiddleware::class, static function (Container $c): CompressionMiddleware {
            /** @var Config $config */
            $config = $c->get('config');

            return new CompressionMiddleware(
                (int) $config->get('app.compression.threshold', 1024),
                (int) $config->get('app.compression.level', 5)
            );
        });

        $container->singleton(CsrfMiddleware::class, static function (Container $c): CsrfMiddleware {
            /** @var Config $config */
            $config = $c->get('config');

            return new CsrfMiddleware(
                $c->get(SessionManager::class),
                (array) $config->get('app.security.csrf_exempt', [])
            );
        });

        $container->singleton(RateLimitMiddleware::class, static function (Container $c): RateLimitMiddleware {
            /** @var Config $config */
            $config = $c->get('config');
            $settings = (array) $config->get('app.security.rate_limit', []);

            return new RateLimitMiddleware(
                $c->get(RateLimiter::class),
                (int) ($settings['max_attempts'] ?? 30),
                (int) ($settings['decay_seconds'] ?? 60),
                ['POST', 'PUT', 'PATCH', 'DELETE']
            );
        });

        $container->singleton(ConditionalGetMiddleware::class, static function (Container $c): ConditionalGetMiddleware {
            /** @var Config $config */
            $config = $c->get('config');

            return new ConditionalGetMiddleware($config->bool('app.cache.conditional_get', true));
        });

        $container->singleton('auth', static function (Container $c): AuthManager {
            /** @var Config $config */
            $config = $c->get('config');

            return new AuthManager(
                $c->get('db'),
                $c->get('request'),
                $c->get(PasswordHasher::class),
                $c->get(Clock::class),
                $c->get(SessionManager::class),
                $c->get('config')
            );
        });

        $container->singleton(AuthManager::class, static function (Container $c): AuthManager {
            /** @var AuthManager $auth */
            $auth = $c->get('auth');

            return $auth;
        });
    }

    public function kernel(): Kernel
    {
        if ($this->kernel === null) {
            $this->kernel = new Kernel(
                $this->container,
                $this->container->get('router'),
                $this->container->get(ErrorHandler::class)
            );
        }

        return $this->kernel;
    }

    /**
     * Handle a request and return the response (without sending it).
     */
    public function handle(Request $request): Response
    {
        $this->bootModules();

        return $this->kernel()->handle($request);
    }

    /**
     * Handle the current request and send the response.
     */
    public function run(?Request $request = null): void
    {
        $request = $request ?? $this->container->get('request');

        $response = $this->handle($request);
        $response->send();
    }

    private bool $modulesBooted = false;

    private function bootModules(): void
    {
        if ($this->modulesBooted) {
            return;
        }

        $this->modulesBooted = true;

        /** @var ModuleManager $manager */
        $manager = $this->container->get('modules');
        $manager->boot();
    }
}
