<?php

declare(strict_types=1);

namespace SoulCMS\App\Listeners;

use SoulCMS\Events\PostLiked;
use SoulCMS\Database\Connection;
use SoulCMS\Foundation\Config;
use SoulCMS\Support\Lang;

/** Sends the post author a system notice when a member likes their post. */
final class NotifyPostAuthorOfLike
{
    public function __construct(
        private Connection $connection,
        private Config $config,
    ) {
    }

    public function __invoke(PostLiked $event): void
    {
        $recipient = mb_strtolower($event->postAuthor);
        $author = mb_strtolower($event->author);
        if ($recipient === '' || $recipient === $author) {
            return;
        }

        $bot = (string) $this->config->get('app.content.system_bot', 'SystemBOT');
        $now = time();
        $this->connection->insert('mail', [
            'sender_receiver' => $recipient . '_' . mb_strtolower($bot),
            'nick' => $bot,
            'content' => Lang::get('notification.post_liked', [
                'author' => $event->author,
                'title' => $event->title,
            ]),
            'time' => $now,
            'update_time' => $now,
            'type' => 'system',
            'view' => 'no',
        ]);
    }
}
