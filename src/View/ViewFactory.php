<?php

declare(strict_types=1);

namespace SoulCMS\View;

use RuntimeException;

/**
 * View factory: the façade the application and the modules talk to.
 */
final class ViewFactory
{
    /** @var array<string,string> */
    private array $namespaces = [];

    public function __construct(private Engine $engine)
    {
    }

    public function engine(): Engine
    {
        return $this->engine;
    }

    /**
     * Register a view namespace, e.g. "blog" => resources/views/blog.
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, '/');
    }

    /**
     * @return array<string,string>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    public function exists(string $name): bool
    {
        try {
            return $this->engine->exists($this->qualify($name));
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function render(string $name, array $data = [], ?string $layout = null): string
    {
        return $this->engine->render($this->qualify($name), $data, $layout === null ? null : $this->qualify($layout));
    }

    /**
     * @param array<string,mixed> $data
     */
    public function display(string $name, array $data = [], ?string $layout = null): void
    {
        echo $this->render($name, $data, $layout);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function share(string|array $key, mixed $value = null): void
    {
        $this->engine->share($key, $value);
    }

    private function qualify(string $name): string
    {
        if (!str_contains($name, '::')) {
            return $name;
        }

        [$namespace, $view] = explode('::', $name, 2);

        if (!isset($this->namespaces[$namespace])) {
            throw new RuntimeException(sprintf('Unknown view namespace "%s".', $namespace));
        }

        return $this->namespaces[$namespace] . '/' . $view;
    }
}
