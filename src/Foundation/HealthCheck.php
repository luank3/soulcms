<?php

declare(strict_types=1);

namespace SoulCMS\Foundation;

use SoulCMS\Database\Connection;
use SoulCMS\Foundation\Paths;

/**
 * Reports whether the application is able to serve traffic.
 *
 * The endpoint is intentionally terse: it names the failing subsystem and
 * nothing else. It never leaks a path, a version, a credential or a stack
 * trace, so it is safe to expose to a load balancer.
 */
final class HealthCheck
{
    /**
     * @return array{status:string,checks:array<string,array{status:string,message?:string}>}
     */
    public function run(): array
    {
        $checks = [];

        $checks['database'] = $this->checkDatabase();
        $checks['storage'] = $this->checkStorage();
        $checks['cache'] = $this->checkCache();

        $healthy = true;

        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $healthy = false;
            }
        }

        return [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ];
    }

    /**
     * @return array{status:string,message?:string}
     */
    private function checkDatabase(): array
    {
        try {
            $connection = Container::getInstance()->get('db');

            if (!$connection instanceof Connection) {
                return ['status' => 'error', 'message' => 'not configured'];
            }

            $connection->scalar('SELECT 1');

            return ['status' => 'ok'];
        } catch (\Throwable $exception) {
            return ['status' => 'error', 'message' => 'unreachable'];
        }
    }

    /**
     * @return array{status:string,message?:string}
     */
    private function checkStorage(): array
    {
        $directory = Paths::storage();

        if (!is_dir($directory)) {
            return ['status' => 'error', 'message' => 'missing'];
        }

        if (!is_writable($directory)) {
            return ['status' => 'error', 'message' => 'not writable'];
        }

        return ['status' => 'ok'];
    }

    /**
     * @return array{status:string,message?:string}
     */
    private function checkCache(): array
    {
        $directory = Paths::cache();

        if (!is_dir($directory)) {
            return ['status' => 'error', 'message' => 'missing'];
        }

        if (!is_writable($directory)) {
            return ['status' => 'error', 'message' => 'not writable'];
        }

        return ['status' => 'ok'];
    }
}
