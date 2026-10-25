<?php

declare(strict_types=1);

namespace SoulCMS\Support;

/** Generates page metadata for content that has no explicit SEO overrides. */
final class PageMetadata
{
    /**
     * @return array{title:string,description:string,keyword:string}
     */
    public static function forPost(string $title, string $content, ?string $stored): array
    {
        $plainContent = strip_tags(Bbcode::render(
            $content,
            static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        ));
        $description = html_entity_decode($plainContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/\s+/u', ' ', $description) ?? $description;
        $keywords = preg_split('/\s+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $metadata = json_decode((string) $stored, true);

        return [
            'title' => self::override($metadata, 'meta_title', $title),
            'description' => self::override($metadata, 'meta_desc', mb_substr(trim($description), 0, 150)),
            'keyword' => self::override($metadata, 'meta_keywords', implode(', ', $keywords)),
        ];
    }

    /** @param mixed $metadata */
    private static function override(mixed $metadata, string $key, string $fallback): string
    {
        if (!is_array($metadata) || !isset($metadata[$key]) || !is_scalar($metadata[$key])) {
            return $fallback;
        }

        $value = trim((string) $metadata[$key]);

        return $value !== '' ? $value : $fallback;
    }
}
