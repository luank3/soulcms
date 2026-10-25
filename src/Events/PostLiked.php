<?php

declare(strict_types=1);

namespace SoulCMS\Events;

/** Raised after a member likes a post. */
final class PostLiked
{
    public function __construct(
        public readonly int $postId,
        public readonly string $author,
        public readonly string $postAuthor,
        public readonly string $title,
    ) {
    }
}
