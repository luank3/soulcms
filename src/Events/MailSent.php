<?php

declare(strict_types=1);

namespace SoulCMS\Events;

/**
 * Raised after a private message has been stored.
 */
final class MailSent
{
    public function __construct(
        public readonly string $sender,
        public readonly string $receiver,
        public readonly string $content,
    ) {
    }
}
