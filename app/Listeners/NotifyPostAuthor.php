<?php

declare(strict_types=1);

namespace SoulCMS\App\Listeners;

use SoulCMS\Events\CommentPosted;
use SoulCMS\Support\Lang;

/**
 * Notifies the author of a post that somebody commented on it.
 *
 * The listener owns the notification; the comment controller only knows that
 * a comment was posted.
 */
final class NotifyPostAuthor
{
    public function __construct(
        private \SoulCMS\Database\Connection $connection,
        private \SoulCMS\Foundation\Config $config,
    ) {
    }

    public function __invoke(CommentPosted $event): void
    {
        $postAuthor = (string) ($event->post['author'] ?? '');

        if ($postAuthor === '' || $postAuthor === $event->author) {
            return;
        }

        $bot = (string) $this->config->get('app.content.system_bot', 'SystemBOT');
        $now = time();

        $this->connection->insert('mail', [
            'sender_receiver' => mb_strtolower($postAuthor) . '_' . mb_strtolower($bot),
            'nick' => $bot,
            'content' => Lang::get('notification.post_commented', [
                'author' => $event->author,
                'title' => (string) ($event->post['title'] ?? ''),
            ]),
            'time' => $now,
            'update_time' => $now,
            'type' => 'system',
            'view' => 'no',
        ]);
    }
}
