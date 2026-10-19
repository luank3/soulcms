<?php

declare(strict_types=1);

namespace SoulCMS\Events;

/**
 * Raised after a comment has been stored.
 *
 * Listeners use it to notify the author of the post, to update the counters
 * or to run a moderation rule, without the controller knowing about any of
 * them.
 */
final class CommentPosted
{
    /**
     * @param array<string,mixed> $post
     */
    public function __construct(
        public readonly int $postId,
        public readonly string $author,
        public readonly string $comment,
        public readonly array $post = [],
    ) {
    }
}
