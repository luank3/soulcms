<?php

declare(strict_types=1);

namespace SoulCMS\Foundation\Module;

use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Paths;
use SoulCMS\Routing\Router;
use SoulCMS\Support\SettingsStore;
use Throwable;

/**
 * Discovers and boots the application modules.
 *
 * Modules live in `app/Modules/<Name>` and expose a `Module` subclass. The
 * manager keeps the discovery result cached in memory and can persist the
 * enabled/disabled state in the database.
 */
final class ModuleManager
{
    /** @var array<string,Module> */
    private array $modules = [];

    /** @var array<string,bool>|null */
    private ?array $enabledState = null;

    public function __construct(
        private Container $container,
        private string $path,
    ) {
    }

    /**
     * Discover the modules on disk.
     *
     * @return array<string,Module>
     */
    public function discover(): array
    {
        if ($this->modules !== []) {
            return $this->modules;
        }

        if (!is_dir($this->path)) {
            return $this->modules;
        }

        $directories = glob($this->path . '/*', GLOB_ONLYDIR) ?: [];
        sort($directories);

        foreach ($directories as $directory) {
            $classFile = $directory . '/Module.php';

            if (!is_file($classFile)) {
                continue;
            }

            $class = 'SoulCMS\\App\\Modules\\' . basename($directory) . '\\Module';

            if (!class_exists($class)) {
                require_once $classFile;
            }

            if (!class_exists($class)) {
                continue;
            }

            $module = $this->container->get($class);

            if ($module instanceof Module) {
                $this->modules[$module->name()] = $module;
            }
        }

        return $this->modules;
    }

    /**
     * @return array<string,Module>
     */
    public function all(): array
    {
        return $this->discover();
    }

    public function get(string $name): ?Module
    {
        return $this->discover()[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        $state = $this->state();

        $enabled = [];

        foreach ($this->discover() as $name => $module) {
            if ($state[$name] ?? $module->enabled()) {
                $enabled[] = $name;
            }
        }

        return $enabled;
    }

    public function isEnabled(string $name): bool
    {
        return in_array($name, $this->enabled(), true);
    }

    /**
     * Register the routes of every enabled module.
     */
    public function registerRoutes(Router $router): void
    {
        foreach ($this->enabled() as $name) {
            $module = $this->discover()[$name];
            $module->routes($router);
        }
    }

    /**
     * Run the `register()` hook of every enabled module.
     */
    public function boot(): void
    {
        $views = $this->container->has('view') ? $this->container->get('view') : null;

        foreach ($this->enabled() as $name) {
            $module = $this->discover()[$name];

            if ($views instanceof \SoulCMS\View\ViewFactory) {
                $views->addNamespace($module->viewNamespace(), $this->path . '/' . $name . '/Views');
            }

            $module->register();
        }
    }

    /**
     * Persist the enabled/disabled state of the modules.
     *
     * @param array<string,bool> $state
     */
    public function saveState(array $state): void
    {
        $this->enabledState = $state;
    }

    /**
     * @return array<string,bool>
     */
    public function state(): array
    {
        if ($this->enabledState !== null) {
            return $this->enabledState;
        }

        $this->enabledState = [];

        if ($this->container->has(SettingsStore::class)) {
            /** @var SettingsStore $settings */
            $settings = $this->container->get(SettingsStore::class);
            $raw = $settings->get('modules_enabled');

            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);

                if (is_array($decoded)) {
                    $this->enabledState = array_map('boolval', $decoded);
                }
            }
        }

        return $this->enabledState;
    }

    public static function defaultPath(): string
    {
        return Paths::modules();
    }
}
