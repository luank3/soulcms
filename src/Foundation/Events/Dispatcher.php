<?php

declare(strict_types=1);

namespace SoulCMS\Foundation\Events;

use RuntimeException;

/**
 * A tiny synchronous event dispatcher.
 *
 * Events decouple the write path from its side effects: the comment
 * controller stores the comment and dispatches an event; a listener sends the
 * notification. Neither side knows about the other, and adding a new
 * reaction to a comment does not touch the controller.
 */
final class Dispatcher
{
    /** @var array<string,list<callable>> */
    private array $listeners = [];

    /** @var list<callable> */
    private array $wildcards = [];

    /**
     * @param callable|string $handler
     */
    public function listen(string $event, callable|string $handler): void
    {
        if (is_string($handler) && !class_exists($handler)) {
            throw new RuntimeException(sprintf('The listener class "%s" does not exist.', $handler));
        }

        $this->listeners[$event][] = $handler;
    }

    /**
     * Listen to every event.
     */
    public function listenToAll(callable $handler): void
    {
        $this->wildcards[] = $handler;
    }

    public function dispatch(object $event): void
    {
        $name = $event::class;

        foreach ($this->listeners[$name] ?? [] as $listener) {
            $this->call($listener, $event);
        }

        foreach ($this->wildcards as $listener) {
            $this->call($listener, $event);
        }
    }

    /**
     * @return list<string>
     */
    public function listenedEvents(): array
    {
        return array_keys($this->listeners);
    }

    private function call(callable|string $listener, object $event): void
    {
        if (is_string($listener)) {
            $container = \SoulCMS\Foundation\Container::getInstance();
            $instance = $container->get($listener);

            if (!is_callable($instance)) {
                throw new RuntimeException(sprintf('The listener "%s" is not callable.', $listener));
            }

            $instance($event);

            return;
        }

        $listener($event);
    }
}
