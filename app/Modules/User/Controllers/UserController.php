<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\User\Controllers;

use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Foundation\Container;
use SoulCMS\Support\Lang;
use SoulCMS\Support\Validator;
use SoulCMS\Http\Controller;
use SoulCMS\Http\Exception\ValidationException;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Support\RateLimiter;
use SoulCMS\Support\RegistrationCaptcha;
use SoulCMS\Support\ReputationRank;
use SoulCMS\Support\WaifuDecoration;
use SoulCMS\Support\SmtpMailer;

/**
 * Registration, login and member profiles.
 */
final class UserController extends Controller
{
    public function __construct(
        Container $container,
        private UserRepository $users,
        private ArticleRepository $articles,
        private PasswordHasher $hasher,
        private SessionManager $session,
        private RateLimiter $limiter,
    ) {
        parent::__construct($container);
    }

    public function register(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/'));
        }

        if (!$this->isPost()) {
            return $this->html('user/register', [
                'page_title' => Lang::get('auth.register.title'),
                'errors' => [],
                'old' => [],
                'captcha_question' => (new RegistrationCaptcha($this->session))->issue(),
            ], 'layouts/auth');
        }

        $captcha = new RegistrationCaptcha($this->session);
        if (!$captcha->verify((string) $this->input('captcha_answer', ''))) {
            return $this->html('user/register', [
                'page_title' => Lang::get('auth.register.title'),
                'errors' => ['captcha' => Lang::get('auth.register.captcha_invalid')],
                'old' => [
                    'nick' => trim((string) $this->input('nick', '')),
                    'email' => trim((string) $this->input('email', '')),
                ],
                'captcha_question' => $captcha->issue(),
            ], 'layouts/auth');
        }

        $nick = trim((string) $this->input('nick', ''));
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');

        $result = (new Validator($this->request->getParsedBody()))->check([
            'nick' => 'required|string:3,32|regex:/^[A-Za-z0-9\-_]+$/',
            'email' => 'required|email|max:255',
            'password' => 'required|string:min,6|confirmed:password_confirm',
        ]);

        $errors = $this->flattenErrors($result['errors']);

        // Uniqueness cannot be expressed as a rule, so it is checked here.
        if ($errors === []) {
            if ($this->users->nickExists($nick)) {
                $errors['nick'] = Lang::get('auth.register.nickname_taken');
            } elseif ($this->users->emailExists($email)) {
                $errors['email'] = Lang::get('auth.register.email_registered');
            }
        }

        if ($errors !== []) {
            if ($this->wantsJson()) {
                return $this->json(['status' => 'error', 'errors' => $errors], 422);
            }

            return $this->html('user/register', [
                'page_title' => Lang::get('auth.register.title'),
                'errors' => $errors,
                'old' => ['nick' => $nick, 'email' => $email],
                'captcha_question' => $captcha->issue(),
            ], 'layouts/auth');
        }

        $userId = $this->users->register([
            'nick' => $nick,
            'name' => $nick,
            'email' => $email,
            'pass' => $this->hasher->hash($password),
            'sex' => (string) $this->input('sex', 'boy'),
            'reg' => time(),
            'level' => 0,
        ]);

        $this->users->ensureWaifu($userId);

        $this->session->put('uid', $userId);

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success', 'id' => $userId]);
        }

        return Response::redirect(url('/'));
    }

    public function login(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/'));
        }

        if (!$this->isPost()) {
            return $this->html('user/login', [
                'page_title' => Lang::get('auth.login.title'),
                'errors' => [],
                'old' => [],
            ], 'layouts/auth');
        }

        $identifier = trim((string) $this->input('identifier', ''));
        $password = (string) $this->input('password', '');
        $remember = (bool) $this->input('remember', false);

        $ip = $request->getClientIp();
        $key = 'login:' . $ip;

        if (!$this->limiter->hit($key, (int) $this->config->get('app.security.login_max_attempts', 5), (int) $this->config->get('app.security.login_decay_seconds', 300))) {
            return $this->loginFailure(Lang::get('auth.login.rate_limited'), $identifier);
        }

        $user = $this->auth->attempt($identifier, $password);

        if ($user === null) {
            return $this->loginFailure(Lang::get('auth.failed'), $identifier);
        }

        $this->session->regenerate();

        if ($remember) {
            $this->auth->issueRememberToken($user->id());
        }

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success', 'redirect' => url('/')]);
        }

        return Response::redirect(url('/'));
    }

    /**
     * Reduce a list of validation messages to one message per field.
     *
     * @param array<string,list<string>> $errors
     * @return array<string,string>
     */
    private function flattenErrors(array $errors): array
    {
        $flat = [];

        foreach ($errors as $field => $messages) {
            $flat[(string) $field] = (string) (is_array($messages) ? ($messages[0] ?? '') : $messages);
        }

        return $flat;
    }

    private function loginFailure(string $message, string $identifier): Response
    {
        if ($this->wantsJson()) {
            return $this->json(['status' => 'error', 'message' => $message], 422);
        }

        return $this->html('user/login', [
            'page_title' => Lang::get('auth.login.title'),
            'errors' => ['credentials' => $message],
            'old' => ['identifier' => $identifier],
        ], 'layouts/auth');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(url('/'));
    }

    public function forgotPassword(Request $request): Response
    {
        if (!$this->isPost()) {
            return $this->html('user/forgot_password', [
                'page_title' => Lang::get('auth.forgot.title'),
                'sent' => false,
            ], 'layouts/auth');
        }

        $email = trim((string) $this->input('email', ''));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $this->users->findByEmail($email);
        if ($user !== null) {
            $mailer = $this->container->get(SmtpMailer::class);
            if (!$mailer instanceof SmtpMailer || !$mailer->isConfigured()) {
                return $this->html('user/forgot_password', [
                    'page_title' => Lang::get('auth.forgot.title'),
                    'sent' => true,
                ], 'layouts/auth');
            }

            $token = bin2hex(random_bytes(32));
            $expires = time() + 3600;
            $this->users->updateUser((int) $user['id'], [
                'reset_token' => $token,
                'reset_expires' => $expires,
            ]);
            $resetUrl = url('/login/password/reset?token=' . rawurlencode($token), true);
            $body = Lang::get('auth.forgot.email_body', [
                'name' => (string) $user['nick'],
                'url' => $resetUrl,
            ]);

            if (!$mailer->send(
                (string) $user['email'],
                Lang::get('auth.forgot.email_subject', ['app' => (string) $this->config->get('app.name', 'SoulCMS')]),
                $body
            )) {
                $this->users->clearResetToken((int) $user['id'], $token);
                return $this->html('user/forgot_password', [
                    'page_title' => Lang::get('auth.forgot.title'),
                    'sent' => true,
                ], 'layouts/auth');
            }
        }

        return $this->html('user/forgot_password', [
            'page_title' => Lang::get('auth.forgot.title'),
            'sent' => true,
        ], 'layouts/auth');
    }

    public function resetPassword(Request $request): Response
    {
        $token = trim((string) ($this->input('token') ?? $this->query('token', '')));
        $user = $token === '' ? null : $this->users->findByResetToken($token, time());

        if (!$this->isPost()) {
            return $this->html('user/reset_password', [
                'page_title' => Lang::get('auth.reset.title'),
                'token' => $user === null ? '' : $token,
                'errors' => $user === null ? ['token' => Lang::get('auth.reset.invalid_token')] : [],
            ], 'layouts/auth');
        }

        $password = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirm', '');

        $errors = [];

        if (mb_strlen($password) < 6) {
            $errors['password'] = Lang::get('auth.reset.password_short');
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = Lang::get('auth.reset.password_mismatch');
        }

        if ($user === null) {
            $errors['token'] = Lang::get('auth.reset.invalid_token');
        }

        if ($errors !== []) {
            return $this->html('user/reset_password', [
                'page_title' => Lang::get('auth.reset.title'),
                'token' => $token,
                'errors' => $errors,
            ], 'layouts/auth');
        }

        $passwordHash = $this->hasher->hash($password);
        if (!$this->users->consumeResetToken((int) $user['id'], $token, time(), $passwordHash)) {
            return $this->html('user/reset_password', [
                'page_title' => Lang::get('auth.reset.title'),
                'token' => '',
                'errors' => ['token' => Lang::get('auth.reset.invalid_token')],
            ], 'layouts/auth');
        }

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(url('/login'));
    }

    public function userList(Request $request): Response
    {
        $per = 20;
        $page = max(1, (int) $this->query('page', 1));

        $orderBy = (string) $this->query('order_by', 'level');
        $sort = (string) $this->query('sort', 'desc');

        return $this->html('user/list', [
            'page_title' => Lang::get('user.list.title'),
            'users' => $this->users->listUsers($per, ($page - 1) * $per, $orderBy, $sort),
            'total' => $this->users->countUsers(),
            'pagination' => paging('', $page, (int) ceil(max(1, $this->users->countUsers()) / $per)),
            'current_page' => $page,
        ]);
    }

    public function editProfile(Request $request): Response
    {
        $account = (string) $this->param('account', '');
        $user = $this->users->findByNick($account);

        if ($user === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        if (!$this->auth->check() || $this->auth->id() !== (int) $user['id']) {
            return Response::html(Lang::get('error.403.title'), 403);
        }

        if (!$this->isPost()) {
            return $this->html('user/edit', [
                'page_title' => Lang::get('user.edit.title'),
                'profile' => $user,
                'errors' => [],
            ]);
        }

        $input = $request->getParsedBody();
        foreach (['name', 'email', 'sex'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        $result = (new Validator($input))->check([
            'name' => 'required|string:1,255',
            'email' => 'required|email|max:255',
            'sex' => 'required|in:boy,girl,other',
        ]);
        $errors = $this->flattenErrors($result['errors']);
        $email = trim((string) ($result['data']['email'] ?? ''));

        if ($result['valid'] && $this->users->emailExistsForOtherUser($email, (int) $user['id'])) {
            $errors['email'] = Lang::get('auth.register.email_registered');
        }

        if ($errors !== []) {
            if ($this->wantsJson()) {
                return $this->json(['status' => 'error', 'errors' => $errors], 422);
            }

            return $this->html('user/edit', [
                'page_title' => Lang::get('user.edit.title'),
                'profile' => array_merge($user, $input),
                'errors' => $errors,
            ]);
        }

        $this->users->updateUser((int) $user['id'], [
            'name' => trim((string) $result['data']['name']),
            'email' => $email,
            'sex' => (string) $result['data']['sex'],
        ]);

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(safe_url('/user/' . $account));
    }

    public function blocklist(Request $request): Response
    {
        $account = (string) $this->param('account', '');
        $user = $this->users->findByNick($account);

        if ($user === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        if (!$this->auth->check() || $this->auth->id() !== (int) $user['id']) {
            return Response::html(Lang::get('error.403.title'), 403);
        }

        $blockedUsers = $this->users->blockedMembers((int) $user['id']);
        if (!$this->isPost()) {
            return $this->html('user/blocklist', [
                'page_title' => Lang::get('user.blocklist.title'),
                'profile' => $user,
                'blocked_users' => $blockedUsers,
                'errors' => [],
            ]);
        }

        $action = (string) $this->input('action', 'block');
        $nick = trim((string) $this->input('nick', ''));
        $errors = [];

        if (!in_array($action, ['block', 'unblock'], true)) {
            $errors['action'] = Lang::get('user.blocklist.action_invalid');
        } elseif (preg_match('/^[A-Za-z0-9\\-_]{3,32}$/', $nick) !== 1) {
            $errors['nick'] = Lang::get('user.blocklist.nickname_invalid');
        } elseif (mb_strtolower($nick) === mb_strtolower((string) $user['nick'])) {
            $errors['nick'] = Lang::get('user.blocklist.cannot_block_self');
        }

        $target = $action === 'block' && $errors === [] ? $this->users->findByNick($nick) : null;
        if ($action === 'block' && $errors === [] && $target === null) {
            $errors['nick'] = Lang::get('user.blocklist.member_not_found');
        }

        if ($errors !== []) {
            if ($this->wantsJson()) {
                return $this->json(['status' => 'error', 'errors' => $errors], 422);
            }

            return $this->html('user/blocklist', [
                'page_title' => Lang::get('user.blocklist.title'),
                'profile' => $user,
                'blocked_users' => $blockedUsers,
                'errors' => $errors,
            ]);
        }

        if ($action === 'block') {
            $this->users->blockUser((int) $user['id'], (string) $target['nick']);
        } else {
            $this->users->unblockUser((int) $user['id'], $nick);
        }

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(safe_url('/user/' . $account . '/blocklist'));
    }

    public function waifu(Request $request): Response
    {
        $account = (string) $this->param('account', '');
        $user = $this->users->findByNick($account);
        if ($user === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }
        if (!$this->isPost() || !$this->auth->check() || $this->auth->id() !== (int) $user['id']) {
            return Response::html(Lang::get('error.403.title'), 403);
        }

        $action = (string) $this->input('action', '');
        if (!in_array($action, ['close', 'open'], true)) {
            return Response::html(Lang::get('error.invalid_request'), 422);
        }
        $this->users->setWaifuClosed((int) $user['id'], $action === 'close');

        return Response::redirect(safe_url('/user/' . rawurlencode($account)));
    }

    public function userDetail(Request $request): Response
    {
        $account = (string) $this->param('account', '');

        $user = $this->users->findByNick($account);

        if ($user === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        $nick = (string) $user['nick'];
        $isOwner = $this->auth->check() && $this->auth->id() === (int) $user['id'];
        $isOnline = $this->users->isOnline((int) $user['id']);
        $activity = $this->users->reputationActivity($nick, time() - 30 * 86400);
        $reputation = ReputationRank::forMember(
            (int) $user['reg'],
            $activity['posts'],
            $activity['comments']
        );

        return $this->html('user/detail', [
            'page_title' => (string) $user['name'],
            'profile' => $user,
            'waifu_decoration' => WaifuDecoration::fromRow($this->users->waifu((int) $user['id'])),
            'posts' => $this->articles->postListByAuthor($nick, 20),
            'stats' => $this->articles->statsByAuthor($nick),
            'reputation' => $reputation,
            'is_owner' => $isOwner,
            'is_online' => $isOnline,
        ]);
    }


}
