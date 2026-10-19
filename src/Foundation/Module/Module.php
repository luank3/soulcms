<?php

declare(strict_types=1);

namespace SoulCMS\Foundation\Module;

use SoulCMS\Routing\Router;

/**
 * Base class for a CMS module.
 *
 * A module owns a slice of the application: its routes, its controllers, its
 * repositories and its views. Modules are registered by the ModuleManager at
 * boot and can be enabled/disabled from the admin panel.
 */
abstract class Module
{
    /**
     * The unique, lowercase identifier of the module.
     */
    abstract public function name(): string;

    /**
     * Human readable title shown in the admin panel.
     */
    abstract public function title(): string;

    /**
     * A one line description of what the module does.
     */
    public function description(): string
    {
        return '';
    }

    /**
     * The module version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Whether the module is enabled by default.
     */
    public function enabled(): bool
    {
        return true;
    }

    /**
     * The view namespace of the module.
     */
    public function viewNamespace(): string
    {
        return $this->name();
    }

    /**
     * Register the module routes.
     */
    abstract public function routes(Router $router): void;

    /**
     * Extra bindings registered in the container.
     */
    public function register(): void
    {
    }
}
