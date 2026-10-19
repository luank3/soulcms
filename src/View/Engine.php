<?php

declare(strict_types=1);

namespace SoulCMS\View;

use RuntimeException;
use SoulCMS\Foundation\Paths;

/**
 * A dependency free template engine.
 *
 * Templates are plain PHP files. They are compiled once into
 * `storage/cache/views` and executed with an extracted data scope, which
 * keeps rendering allocation-free at request time and avoids the overhead of
 * a full template language.
 *
 * Layouts are supported through `$this->section()` / `$this->yield()`.
 */
final class Engine
{
    /** @var array<string,mixed> */
    private array $globals = [];

    /**
     * The compiled file of every template rendered in this request.
     *
     * @var array<string,array{0:string,1:int|false}>
     */
    private array $compiled = [];

    public function __construct(
        private string $viewPath,
        private string $cachePath,
        private bool $debug = false,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->globals = array_merge($this->globals, $key);

            return;
        }

        $this->globals[$key] = $value;
    }

    /**
     * @return array<string,mixed>
     */
    public function getGlobals(): array
    {
        return $this->globals;
    }

    public function exists(string $name): bool
    {
        return is_file($this->resolvePath($name));
    }

    /**
     * @param array<string,mixed> $data
     */
    public function render(string $name, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($this->resolvePath($name), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($this->resolvePath($layout), array_merge($data, ['content' => $content]));
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderFile(string $path, array $data): string
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('View "%s" was not found.', $path));
        }

        $compiled = $this->compile($path);

        $scope = array_merge($this->globals, $data);

        ob_start();

        (static function (string $__file, array $__scope): void {
            extract($__scope, EXTR_SKIP);
            require $__file;
        })($compiled, $scope);

        return (string) ob_get_clean();
    }

    /**
     * Return the compiled file for a template, recompiling when the source
     * is newer (or when the cache is disabled in debug mode).
     */
    private function compile(string $path): string
    {
        $sourceMtime = @filemtime($path);

        // The same template is usually rendered many times per request (one
        // row per post, for instance). Remembering the resolved file keeps
        // the file system out of the hot path.
        if (isset($this->compiled[$path]) && $this->compiled[$path][1] === $sourceMtime) {
            return $this->compiled[$path][0];
        }

        $hash = substr(hash('xxh128', $path), 0, 16);
        $compiled = $this->cachePath . '/' . $hash . '.php';

        if ($this->debug || !is_file($compiled) || filemtime($compiled) < $sourceMtime) {
            $this->ensureCacheDirectory();

            $temporary = $compiled . '.' . getmypid() . '.tmp';

            file_put_contents($temporary, "<?php\n// Compiled from: " . str_replace('*/', '', $path) . "\n?>" . file_get_contents($path));
            rename($temporary, $compiled);
            opcache_invalidate($compiled, true);
        }

        $this->compiled[$path] = [$compiled, $sourceMtime];

        return $compiled;
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0775, true);
        }
    }

    public function resolvePath(string $name): string
    {
        $name = trim(str_replace(['..', '\\'], '', $name), '/');

        if ($name === '') {
            $name = 'index';
        }

        if (!str_ends_with($name, '.php')) {
            $name .= '.php';
        }

        // Absolute names are used by registered view namespaces.
        if (str_starts_with($name, '/')) {
            return $this->viewPath . $name;
        }

        return $this->viewPath . '/' . $name;
    }

    public function getViewPath(): string
    {
        return $this->viewPath;
    }

    public function clearCache(): int
    {
        if (!is_dir($this->cachePath)) {
            return 0;
        }

        $removed = 0;
        $files = glob($this->cachePath . '/*.php') ?: [];

        foreach ($files as $file) {
            if (unlink($file)) {
                ++$removed;
            }
        }

        return $removed;
    }
}
