<?php

declare(strict_types=1);

namespace SoulCMS\Support;

/**
 * A tiny, dependency free file logger.
 */
final class Logger
{
    public function __construct(
        private string $file,
        private bool $debug = false,
        private string $channel = 'stack',
    ) {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->debug) {
            $this->log('DEBUG', $message, $context);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $directory = dirname($this->file);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        // Correlate every line with the request that produced it.
        $requestId = $this->requestId();

        if ($requestId !== null) {
            $context = ['request' => $requestId] + $context;
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * The correlation id of the request in flight, when there is one.
     */
    private function requestId(): ?string
    {
        try {
            $container = \SoulCMS\Foundation\Container::getInstance();

            if (!$container->has('request')) {
                return null;
            }

            $request = $container->get('request');

            return $request instanceof \SoulCMS\Http\Request ? $request->getId() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
