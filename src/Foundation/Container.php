<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * A small, dependency-free service container with constructor autowiring.
 *
 * Design notes
 * ------------
 * - Services are lazily created on first resolution and then memoised.
 * - Circular dependencies are detected and reported with a readable stack.
 * - `make()` creates a fresh instance every time (used for request-scoped
 *   objects), `get()` returns a shared singleton.
 */
final class Container
{
    /** @var array<string,Closure> */
    private array $factories = [];

    /** @var array<string,mixed> */
    private array $instances = [];

    /** @var array<string,true> */
    private array $resolving = [];

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function swap(?self $container): void
    {
        self::$instance = $container;
    }

    /**
     * @param class-string $id
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @param class-string $id
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Register an already-constructed object.
     */
    public function instance(string $id, mixed $object): void
    {
        $this->instances[$id] = $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * Resolve a shared instance.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $object = ($this->factories[$id])($this);
            $this->instances[$id] = $object;

            return $object;
        }

        if (!class_exists($id)) {
            throw new RuntimeException(sprintf('Container cannot resolve "%s": class not found.', $id));
        }

        return $this->instances[$id] = $this->build($id);
    }

    /**
     * Build a brand new instance of the given class.
     */
    public function make(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException(sprintf('Container cannot build "%s": class not found.', $class));
        }

        return $this->build($class);
    }

    private function build(string $class): object
    {
        if (isset($this->resolving[$class])) {
            throw new RuntimeException(sprintf(
                'Circular dependency detected while resolving "%s" (chain: %s).',
                $class,
                implode(' -> ', array_keys($this->resolving)) . ' -> ' . $class
            ));
        }

        $this->resolving[$class] = true;

        try {
            $reflection = new ReflectionClass($class);

            if (!$reflection->isInstantiable()) {
                throw new RuntimeException(sprintf('Class "%s" is not instantiable.', $class));
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return new $class();
            }

            $arguments = [];

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $arguments[] = $this->get($type->getName());

                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();

                    continue;
                }

                if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                    $arguments[] = null;

                    continue;
                }

                throw new RuntimeException(sprintf(
                    'Unable to resolve parameter "$%s" of "%s::__construct()".',
                    $parameter->getName(),
                    $class
                ));
            }

            return $reflection->newInstanceArgs($arguments);
        } finally {
            unset($this->resolving[$class]);
        }
    }
}
