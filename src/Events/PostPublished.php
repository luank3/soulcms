<?php

declare(strict_types=1);

namespace SoulCMS\Events;

/**
 * Raised after a post has been published.
 */
final class PostPublished
{
    /**
     * @param array<string,mixed> $post
     */
    public function __construct(
        public readonly int $postId,
        public readonly string $author,
        public readonly string $title,
        public readonly array $post = [],
    ) {
    }
}
