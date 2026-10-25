<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Mail\Controllers;

use SoulCMS\App\Modules\Mail\Repositories\MailRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Events\MailSent;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Events\Dispatcher;
use SoulCMS\Http\Controller;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Support\Lang;

/**
 * The private message controller.
 */
final class MailController extends Controller
{
    public function __construct(
        Container $container,
        private MailRepository $mail,
        private UserRepository $users,
        private \SoulCMS\Database\Connection $connection,
        private Dispatcher $events,
    ) {
        parent::__construct($container);
    }

    public function index(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $nick = (string) $this->auth->user()->nick();
        $page = max(1, (int) $this->query('page', 1));
        $per = 20;

        $partner = (string) ($this->query('with', ''));
        $messages = [];

        if ($partner !== '') {
            $messages = $this->mail->conversation($nick, $partner, $per, ($page - 1) * $per);
            $this->mail->markConversationRead($nick, $partner);
        }

        $partners = $this->mail->parseList((string) $this->auth->user()->get('mail_list', ''));
        $latest = $this->mail->latestDetails($nick, $partners);

        return $this->html('mail/index', [
            'page_title' => Lang::get('mail.inbox.title'),
            'messages' => $messages,
            'partners' => $partners,
            'latest' => $latest,
            'partner' => $partner,
            'current_page' => $page,
        ]);
    }

    public function send(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $receiver = (string) $this->param('receiver', '');
        $target = $this->users->findByNick($receiver);

        if ($target === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        if ($this->users->isBlockedBy((int) $target['id'], $this->auth->user()->nick())) {
            return Response::html(Lang::get('mail.blocked_recipient'), 403);
        }

        if (!$this->isPost()) {
            return $this->html('mail/send', [
                'page_title' => Lang::get('mail.compose.page_title'),
                'receiver' => $target,
                'errors' => [],
            ]);
        }

        $content = trim((string) $this->input('content', ''));

        if ($content === '') {
            return $this->html('mail/send', [
                'page_title' => Lang::get('mail.compose.page_title'),
                'receiver' => $target,
                'errors' => ['content' => Lang::get('mail.validation.message_empty')],
            ], 'layouts/app');
        }

        $sender = (string) $this->auth->user()->nick();
        $time = time();
        $receiver = (string) $target['nick'];

        // Both copies of the message and the two inbox updates are one unit
        // of work: a half-written conversation is never visible.
        $this->connection->transaction(function () use ($sender, $receiver, $content, $time): void {
            $this->mail->store([
                'sender_receiver' => MailRepository::conversationKey($sender, $receiver),
                'nick' => $sender,
                'content' => $content,
                'time' => $time,
                'update_time' => $time,
                'type' => 'private',
                'view' => 'yes',
            ]);

            $this->mail->store([
                'sender_receiver' => MailRepository::conversationKey($receiver, $sender),
                'nick' => $sender,
                'content' => $content,
                'time' => $time,
                'update_time' => $time,
                'type' => 'private',
                'view' => 'no',
            ]);

            $this->mail->pushToUserList('users', 'mail_list', 'nick', $receiver, $sender);
            $this->mail->pushToUserList('users', 'new_mail', 'nick', $sender, $receiver);
        });

        $this->events->dispatch(new MailSent($sender, $receiver, $content));

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(url('/mail'));
    }

    public function system(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $nick = (string) $this->auth->user()->nick();
        $bot = (string) $this->config->get('app.content.system_bot', 'SystemBOT');

        $messages = $this->mail->conversation($bot, $nick, 50, 0);
        $this->mail->markSystemRead($nick, $bot);

        return $this->html('mail/system', [
            'page_title' => Lang::get('mail.system.title'),
            'messages' => $messages,
        ]);
    }

    public function clearSystem(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        if (!$this->isPost()) {
            return Response::html(Lang::get('error.method_not_allowed'), 405);
        }

        $nick = (string) $this->auth->user()->nick();
        $bot = (string) $this->config->get('app.content.system_bot', 'SystemBOT');
        $this->mail->clearSystem($nick, $bot);

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(url('/mail/system'));
    }
}
