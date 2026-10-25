<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Manager\Controllers;

use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\Shoutbox\Repositories\ChatRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Module\ModuleManager;
use SoulCMS\Http\Controller;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Support\Lang;

/**
 * The administration panel.
 */
final class ManagerController extends Controller
{
    public function __construct(
        Container $container,
        private ArticleRepository $articles,
        private UserRepository $users,
        private ModuleManager $modules,
        private Connection $connection,
        private PasswordHasher $hasher,
        private \SoulCMS\Support\SettingsStore $settings,
    ) {
        parent::__construct($container);
    }

    public function dashboard(Request $request): Response
    {
        return $this->html('manager/dashboard', [
            'page_title' => Lang::get('admin.nav.dashboard'),
            'stats' => $this->articles->stats(),
            'database' => $this->connection->driver()->value,
            'php_version' => PHP_VERSION,
            'modules' => $this->modules->all(),
            'enabled_modules' => $this->modules->enabled(),
        ], 'layouts/admin');
    }

    public function users(Request $request): Response
    {
        $page = max(1, (int) $this->query('page', 1));
        $per = 25;

        if ($this->isPost()) {
            $action = (string) $this->input('action', '');

            if ($action === 'ban') {
                $id = (int) $this->input('id', 0);
                $level = (int) $this->input('level', -1);
                $this->users->updateUser($id, ['level' => $level]);
            }

            if ($action === 'reset_password') {
                $id = (int) $this->input('id', 0);
                $password = (string) $this->input('password', '');

                if ($password !== '') {
                    $this->users->updatePassword($id, $this->hasher->hash($password));
                }
            }

            if ($action === 'level') {
                $id = (int) $this->input('id', 0);
                $this->users->updateUser($id, ['level' => (int) $this->input('level', 0)]);
            }

            return Response::redirect(url('/manager/users'));
        }

        return $this->html('manager/users', [
            'page_title' => Lang::get('manager.users.title'),
            'users' => $this->users->listUsers($per, ($page - 1) * $per),
            'total' => $this->users->countUsers(),
            'pagination' => paging(url('/manager/users'), $page, (int) ceil(max(1, $this->users->countUsers()) / $per)),
            'current_page' => $page,
        ], 'layouts/admin');
    }

    public function userEdit(Request $request): Response
    {
        $id = (int) $this->input('id', 0);
        $nick = trim((string) $this->input('nick', ''));
        $email = trim((string) $this->input('email', ''));

        $data = [];

        if ($nick !== '') {
            $data['nick'] = $nick;
            $data['name'] = $nick;
        }

        if ($email !== '') {
            $data['email'] = $email;
        }

        if ($data !== []) {
            $this->users->updateUser($id, $data);
        }

        return Response::redirect(url('/manager/users'));
    }

    public function articles(Request $request): Response
    {
        $page = max(1, (int) $this->query('page', 1));
        $per = 25;

        return $this->html('manager/articles', [
            'page_title' => Lang::get('manager.articles.posts'),
            'posts' => $this->articles->postList(0, $per, 'update_time', 'desc', ($page - 1) * $per),
            'categories' => $this->articles->categoryList(100, false),
            'pagination' => paging(url('/manager/articles'), $page, (int) ceil(max(1, $this->articles->stats()['count_post'] ?? 0) / $per)),
            'current_page' => $page,
        ], 'layouts/admin');
    }

    public function categoryAdd(Request $request): Response
    {
        if (!$this->isPost()) {
            return Response::html(Lang::get('error.method_not_allowed'), 405);
        }

        $name = trim((string) $this->input('name', ''));
        $slug = trim((string) $this->input('slug', ''));
        if ($name === '' || $slug === '') {
            return Response::html(Lang::get('manager.category.required'), 422);
        }

        $this->articles->createCategory([
            'name' => $name,
            'slug' => $slug,
            'content' => (string) $this->input('content', ''),
            'keyword' => (string) $this->input('keyword', ''),
        ]);

        return Response::redirect(url('/manager/articles'));
    }

    public function categoryEdit(Request $request): Response
    {
        $id = (int) $this->input('id', $this->query('id', 0));
        $category = $this->articles->categoryDetail($id);

        if ($category === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        if (!$this->isPost()) {
            return $this->html('manager/category_edit', [
                'page_title' => Lang::get('manager.category.edit'),
                'category' => $category,
            ], 'layouts/admin');
        }

        $name = trim((string) $this->input('name', ''));
        $slug = trim((string) $this->input('slug', ''));
        if ($name === '' || $slug === '') {
            return Response::html(Lang::get('manager.category.required'), 422);
        }

        $this->articles->updateCategory($id, [
            'name' => $name,
            'slug' => $slug,
            'content' => (string) $this->input('content', ''),
            'keyword' => (string) $this->input('keyword', ''),
        ]);

        return Response::redirect(url('/manager/articles'));
    }

    public function categoryDelete(Request $request): Response
    {
        $id = (int) $this->input('id', 0);
        $this->connection->delete('articles_category', ['id' => $id]);

        return Response::redirect(url('/manager/articles'));
    }

    public function postDelete(Request $request): Response
    {
        $id = (int) $this->input('id', 0);
        $this->articles->deletePost($id);

        return Response::redirect(url('/manager/articles'));
    }

    public function chapterDelete(Request $request): Response
    {
        if (!$this->isPost()) {
            return Response::html(Lang::get('error.method_not_allowed'), 405);
        }

        $this->articles->deleteChapter((int) $this->input('id', 0));

        return Response::redirect(url('/manager/articles'));
    }

    public function shoutboxClean(Request $request): Response
    {
        if (!$this->isPost()) {
            return Response::html(Lang::get('error.method_not_allowed'), 405);
        }

        (new ChatRepository($this->connection))->deleteAll();

        return Response::redirect(url('/manager/shoutbox/clean'));
    }

    public function staticPages(Request $request): Response
    {
        $pages = $this->container->get(\SoulCMS\Support\StaticPageStore::class);
        $currentSlug = (string) $this->query('edit', '');
        $page = $currentSlug !== '' ? $pages->find($currentSlug) : null;

        if ($this->isPost()) {
            $current = trim((string) $this->input('current_slug', ''));
            $data = [
                'slug' => trim((string) $this->input('slug', '')),
                'title' => trim((string) $this->input('title', '')),
                'content' => (string) $this->input('content', ''),
                'description' => trim((string) $this->input('description', '')),
                'keyword' => trim((string) $this->input('keyword', '')),
            ];

            if (!$pages->save($current !== '' ? $current : null, $data)) {
                return Response::html(Lang::get('manager.static.invalid'), 422);
            }

            return Response::redirect(url('/manager/static_pages'));
        }

        return $this->html('manager/static_pages', [
            'page_title' => Lang::get('manager.static.title'),
            'pages' => $pages->all(),
            'page' => $page,
        ], 'layouts/admin');
    }

    public function staticPageDelete(Request $request): Response
    {
        if (!$this->isPost()) {
            return Response::html(Lang::get('error.method_not_allowed'), 405);
        }

        $this->container->get(\SoulCMS\Support\StaticPageStore::class)->delete((string) $this->input('slug', ''));

        return Response::redirect(url('/manager/static_pages'));
    }

    public function modules(Request $request): Response
    {
        if ($this->isPost()) {
            $state = [];

            foreach ($this->modules->all() as $name => $module) {
                $state[$name] = (bool) $this->input('module_' . $name, false);
            }

            $this->modules->saveState($state);
            $this->settings->set('modules_enabled', (string) json_encode($state));

            return Response::redirect(url('/manager/modules'));
        }

        return $this->html('manager/modules', [
            'page_title' => Lang::get('manager.modules.title'),
            'modules' => $this->modules->all(),
            'enabled' => $this->modules->enabled(),
        ], 'layouts/admin');
    }

    public function settings(Request $request): Response
    {
        if ($this->isPost()) {
            $this->settings->setMany([
                'site_name' => (string) $this->input('site_name', ''),
                'site_description' => (string) $this->input('site_description', ''),
                'site_keyword' => (string) $this->input('site_keyword', ''),
            ]);

            return Response::redirect(url('/manager/settings'));
        }

        return $this->html('manager/settings', [
            'page_title' => Lang::get('manager.settings.title'),
            'settings' => $this->settings->all(),
        ], 'layouts/admin');
    }

    public function urlRules(Request $request): Response
    {
        if ($this->isPost()) {
            $rules = $this->readUrlRules();

            $from = '/' . trim((string) $this->input('from', ''), '/');
            $to = trim((string) $this->input('to', ''), '/');
            $method = strtoupper((string) $this->input('method', 'GET|POST'));
            $mode = (string) $this->input('mode', 'rewrite');

            if ($mode === 'disable') {
                if ($from !== '/' && !in_array($from, $rules['disable'], true)) {
                    $rules['disable'][] = $from;
                }
            } elseif ($from !== '/' && $to !== '') {
                $rules['rewrite'][$from] = ['target' => '/' . $to, 'method' => $method];
            }

            $this->saveUrlRules($rules);

            return Response::redirect(url('/manager/url-rules'));
        }

        $rules = $this->readUrlRules();

        return $this->html('manager/url_rules', [
            'page_title' => Lang::get('manager.url_rules.title'),
            'rewrite' => $rules['rewrite'],
            'disabled' => $rules['disable'],
        ], 'layouts/admin');
    }

    public function urlRuleEdit(Request $request): Response
    {
        if (!$this->isPost()) {
            return Response::redirect(url('/manager/url-rules'));
        }

        $rules = $this->readUrlRules();
        $from = '/' . trim((string) $this->input('from', ''), '/');
        $to = trim((string) $this->input('to', ''), '/');
        $method = strtoupper((string) $this->input('method', 'GET|POST'));
        $original = (string) $this->input('original', '');

        if ($original !== '' && isset($rules['rewrite'][$original])) {
            unset($rules['rewrite'][$original]);
        }

        if ($from !== '/' && $to !== '') {
            $rules['rewrite'][$from] = ['target' => '/' . $to, 'method' => $method];
        }

        $this->saveUrlRules($rules);

        return Response::redirect(url('/manager/url-rules'));
    }

    public function urlRuleDelete(Request $request): Response
    {
        if (!$this->isPost()) {
            return Response::redirect(url('/manager/url-rules'));
        }

        $rules = $this->readUrlRules();
        $from = '/' . trim((string) $this->input('from', ''), '/');

        unset($rules['rewrite'][$from]);
        $rules['disable'] = array_values(array_filter($rules['disable'], static fn (string $path): bool => $path !== $from));

        $this->saveUrlRules($rules);

        return Response::redirect(url('/manager/url-rules'));
    }

    /**
     * @return array{rewrite:array<string,array<string,mixed>>,disable:list<string>}
     */
    private function readUrlRules(): array
    {
        $raw = (string) $this->settings->get('url_rules', '{}');
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return ['rewrite' => [], 'disable' => []];
        }

        $rewrite = [];

        foreach ((array) ($decoded['rewrite'] ?? []) as $path => $rule) {
            $rewrite[(string) $path] = is_array($rule) ? $rule : ['target' => (string) $rule, 'method' => 'GET|POST'];
        }

        return [
            'rewrite' => $rewrite,
            'disable' => array_values(array_filter((array) ($decoded['disable'] ?? []), 'is_string')),
        ];
    }

    /**
     * @param array{rewrite:array<string,array<string,mixed>>,disable:list<string>} $rules
     */
    private function saveUrlRules(array $rules): void
    {
        $this->settings->set('url_rules', (string) json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

}
