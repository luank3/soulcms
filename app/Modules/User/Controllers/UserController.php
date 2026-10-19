<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\User\Controllers;

use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Foundation\Container;
use SoulCMS\Support\Validator;
use SoulCMS\Http\Controller;
use SoulCMS\Http\Exception\ValidationException;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Support\RateLimiter;

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
                'page_title' => 'Dang ky',
                'errors' => [],
                'old' => [],
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
                $errors['nick'] = 'This nickname is already taken.';
            } elseif ($this->users->emailExists($email)) {
                $errors['email'] = 'This e-mail address is already registered.';
            }
        }

        if ($errors !== []) {
            if ($this->wantsJson()) {
                return $this->json(['status' => 'error', 'errors' => $errors], 422);
            }

            return $this->html('user/register', [
                'page_title' => 'Dang ky',
                'errors' => $errors,
                'old' => ['nick' => $nick, 'email' => $email],
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
                'page_title' => 'Dang nhap',
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
            return $this->loginFailure('Too many login attempts. Please try again later.', $identifier);
        }

        $user = $this->auth->attempt($identifier, $password);

        if ($user === null) {
            $row = $this->users->findByIdentifier($identifier);

            if ($row !== null) {
                $this->users->registerFailedLogin((int) $row['id']);
            }

            return $this->loginFailure('Invalid credentials.', $identifier);
        }

        $this->users->resetFailedLogins($user->id());
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
            'page_title' => 'Dang nhap',
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
                'page_title' => 'Quen mat khau',
                'sent' => false,
            ], 'layouts/auth');
        }

        $email = trim((string) $this->input('email', ''));
        $user = $this->users->findByEmail($email);

        if ($user !== null) {
            $token = bin2hex(random_bytes(32));

            $this->users->updateUser((int) $user['id'], [
                'reset_token' => $token,
                'reset_expires' => time() + 3600,
            ]);

            $this->session->put('reset_token', $token);
            $this->session->put('reset_user_id', (int) $user['id']);
        }

        // The response never reveals whether the address exists.
        return $this->html('user/forgot_password', [
            'page_title' => 'Quen mat khau',
            'sent' => true,
        ], 'layouts/auth');
    }

    public function resetPassword(Request $request): Response
    {
        $token = (string) ($this->input('token') ?? $this->session->get('reset_token', ''));

        if (!$this->isPost()) {
            return $this->html('user/reset_password', [
                'page_title' => 'Dat lai mat khau',
                'token' => $token,
                'errors' => [],
            ], 'layouts/auth');
        }

        $password = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirm', '');

        $errors = [];

        if (mb_strlen($password) < 6) {
            $errors['password'] = 'The password must be at least 6 characters long.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        $userId = (int) $this->session->get('reset_user_id', 0);

        if ($userId <= 0 || $token === '' || !$this->session->get('reset_token')) {
            $errors['token'] = 'The reset link is invalid or has expired.';
        }

        if ($errors !== []) {
            return $this->html('user/reset_password', [
                'page_title' => 'Dat lai mat khau',
                'token' => $token,
                'errors' => $errors,
            ], 'layouts/app');
        }

        $this->users->updatePassword($userId, $this->hasher->hash($password));
        $this->users->updateUser($userId, ['reset_token' => null, 'reset_expires' => null]);
        $this->session->forget('reset_token');
        $this->session->forget('reset_user_id');

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
            'page_title' => 'Danh sach thanh vien',
            'users' => $this->users->listUsers($per, ($page - 1) * $per, $orderBy, $sort),
            'total' => $this->users->countUsers(),
            'pagination' => paging('', $page, (int) ceil(max(1, $this->users->countUsers()) / $per)),
            'current_page' => $page,
        ]);
    }

    public function userDetail(Request $request): Response
    {
        $account = (string) $this->param('account', '');

        $user = $this->users->findByNick($account);

        if ($user === null) {
            return Response::html('Not found', 404);
        }

        $nick = (string) $user['nick'];

        return $this->html('user/detail', [
            'page_title' => (string) $user['name'],
            'profile' => $user,
            'waifu' => $this->users->waifu((int) $user['id']),
            'posts' => $this->articles->postListByAuthor($nick, 20),
        ]);
    }
}
