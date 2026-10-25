<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Shoutbox\Controllers;

use SoulCMS\App\Modules\Shoutbox\Repositories\ChatRepository;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Controller;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Support\Lang;
use SoulCMS\Support\RateLimiter;

/**
 * The shoutbox controller.
 */
final class ShoutboxController extends Controller
{
    public function __construct(
        Container $container,
        private ChatRepository $chat,
        private RateLimiter $limiter,
    ) {
        parent::__construct($container);
    }

    public function index(Request $request): Response
    {
        return $this->html('shoutbox/index', [
            'page_title' => Lang::get('shoutbox.title'),
            'messages' => $this->chat->messages(50),
        ]);
    }

    public function list(Request $request): Response
    {
        // The chat poller asks for everything after the last id it knows.
        $after = (int) $this->query('after', 0);

        if ($after > 0) {
            return $this->json([
                'status' => 'success',
                'messages' => $this->chat->messagesAfter($after),
            ]);
        }

        $page = max(1, (int) $this->query('page', 1));

        return $this->json([
            'status' => 'success',
            'messages' => $this->chat->messages(50, ($page - 1) * 50),
        ]);
    }

    public function element(Request $request): Response
    {
        $id = max(1, (int) $this->query('chatID', 1));
        $message = $this->chat->message($id);

        if ($message === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        return $this->html('shoutbox/element', ['message' => $message], null)
            ->withHeader('Cache-Control', 'no-store');
    }

    public function count(Request $request): Response
    {
        return $this->json([
            'status' => 'success',
            'count' => $this->chat->countMessages(),
        ]);
    }

    public function send(Request $request): Response
    {
        if (!$this->isPost()) {
            return $this->json(['status' => 'error', 'message' => Lang::get('shoutbox.method_required')], 405);
        }

        if (!$this->auth->check()) {
            return $this->json(['status' => 'error', 'message' => Lang::get('shoutbox.authentication_required')], 401);
        }

        $key = 'shoutbox:' . $request->getClientIp();

        if (!$this->limiter->hit($key, 5, 10)) {
            return $this->json(['status' => 'error', 'message' => Lang::get('shoutbox.rate_limited')], 429);
        }

        $message = trim((string) $this->input('message', ''));

        if ($message === '') {
            return $this->json(['status' => 'error', 'message' => Lang::get('shoutbox.message_empty')], 422);
        }

        $this->chat->store([
            'name' => (string) $this->auth->user()->nick(),
            'time' => time(),
            'comment' => mb_substr($message, 0, 2000),
        ]);

        return $this->json(['status' => 'success']);
    }
}
