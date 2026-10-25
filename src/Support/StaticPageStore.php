<?php

declare(strict_types=1);

namespace SoulCMS\Support;

/** Stores administrator-managed static pages without extending the SketchCMS schema. */
final class StaticPageStore
{
    private const SETTING = 'static_pages';

    public function __construct(private SettingsStore $settings)
    {
    }

    /** @return list<array{slug:string,title:string,content:string,description:string,keyword:string}> */
    public function all(): array
    {
        $decoded = json_decode((string) $this->settings->get(self::SETTING, '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }

        $pages = [];
        foreach ($decoded as $page) {
            if (!is_array($page)) {
                continue;
            }

            $slug = (string) ($page['slug'] ?? '');
            $title = trim((string) ($page['title'] ?? ''));
            if (!$this->validSlug($slug) || $title === '') {
                continue;
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'content' => (string) ($page['content'] ?? ''),
                'description' => (string) ($page['description'] ?? ''),
                'keyword' => (string) ($page['keyword'] ?? ''),
            ];
        }

        usort($pages, static fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        return $pages;
    }

    /** @return array{slug:string,title:string,content:string,description:string,keyword:string}|null */
    public function find(string $slug): ?array
    {
        foreach ($this->all() as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }

        return null;
    }

    /** @param array{slug:string,title:string,content:string,description:string,keyword:string} $data */
    public function save(?string $currentSlug, array $data): bool
    {
        $slug = strtolower(trim($data['slug']));
        $title = trim($data['title']);
        if (!$this->validSlug($slug) || $title === '' || mb_strlen($title) > 160) {
            return false;
        }

        $pages = $this->all();
        foreach ($pages as $page) {
            if ($page['slug'] === $slug && $page['slug'] !== $currentSlug) {
                return false;
            }
        }

        if ($currentSlug !== null) {
            $found = false;
            foreach ($pages as $index => $page) {
                if ($page['slug'] === $currentSlug) {
                    $pages[$index] = $this->page($slug, $title, $data);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        } else {
            $pages[] = $this->page($slug, $title, $data);
        }

        $this->settings->set(self::SETTING, (string) json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return true;
    }

    public function delete(string $slug): bool
    {
        $pages = $this->all();
        $remaining = array_values(array_filter($pages, static fn (array $page): bool => $page['slug'] !== $slug));
        if (count($remaining) === count($pages)) {
            return false;
        }

        $this->settings->set(self::SETTING, (string) json_encode($remaining, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return true;
    }

    private function validSlug(string $slug): bool
    {
        return strlen($slug) <= 80 && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1;
    }

    /** @param array{slug:string,title:string,content:string,description:string,keyword:string} $data
     *  @return array{slug:string,title:string,content:string,description:string,keyword:string}
     */
    private function page(string $slug, string $title, array $data): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'content' => $data['content'],
            'description' => mb_substr(trim($data['description']), 0, 300),
            'keyword' => mb_substr(trim($data['keyword']), 0, 300),
        ];
    }
}
