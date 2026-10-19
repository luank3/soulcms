<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Home\Controllers;

use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Http\Controller;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * The public home controller.
 */
final class HomeController extends Controller
{
    public function __construct(
        \SoulCMS\Foundation\Container $container,
        private ArticleRepository $articles,
        private UserRepository $users,
    ) {
        parent::__construct($container);
    }

    public function index(Request $request): Response
    {
        $per = (int) $this->config->get('app.content.per_page', 10);
        $page = max(1, (int) $this->query('page', 1));

        $categoryId = max(0, (int) $this->query('category', 0));
        $orderBy = (string) $this->query('order_by', 'update_time');
        $sort = (string) $this->query('sort', 'desc');

        $allowedOrder = (array) $this->config->get('app.content.post_list_order_by', ['update_time']);
        $allowedSort = (array) $this->config->get('app.content.post_list_sort', ['desc']);

        if (!in_array($orderBy, $allowedOrder, true)) {
            $orderBy = 'update_time';
        }

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'desc';
        }

        $category = null;
        $total = (int) ($this->articles->stats()['count_post'] ?? 0);
        $stickedCount = $this->articles->countSticked();

        if ($categoryId > 0) {
            $category = $this->articles->categoryDetail($categoryId);

            if ($category === null) {
                $categoryId = 0;
            } else {
                $total = $this->articles->countPostsInCategory($categoryId, true);
            }
        }

        $posts = $this->articles->postList(
            $categoryId,
            $per,
            $orderBy,
            $sort,
            ($page - 1) * $per,
            true
        );

        $posts = $this->articles->hydrateCounters(
            $this->articles->hydrateAuthors(
                $posts,
                $this->users->byNick(array_map(static fn (array $post): string => (string) $post['author'], $posts))
            )
        );

        $pageTitle = $category !== null
            ? 'Chuyen muc: ' . (string) $category['name']
            : (string) $this->config->get('app.name', 'SoulCMS');

        $paginationQuery = ['order_by' => $orderBy, 'sort' => $sort];
        if ($categoryId > 0) {
            $paginationQuery['category'] = $categoryId;
        }

        return $this->html('home/index', [
            'page_title' => $pageTitle,
            'page_description' => $category['content'] ?? $this->config->get('app.description'),
            'page_keyword' => $category['keyword'] ?? $this->config->get('app.keyword'),
            'posts' => $posts,
            'categories' => $this->articles->categoryList(20),
            'stats' => $this->articles->stats(),
            'sticked' => $this->withCounters($this->articles->postListSticked(5)),
            'online_users' => $this->users->onlineUsers(12),
            'pagination' => paging('?' . http_build_query($paginationQuery), $page, (int) ceil(max(1, $total - $stickedCount) / $per)),
            'current_page' => $page,
            'category' => $category,
            'order_by' => $orderBy,
            'sort' => $sort,
        ]);
    }

    /**
     * Add the comment and chapter counters to a list of posts.
     *
     * @param list<array<string,mixed>> $posts
     * @return list<array<string,mixed>>
     */
    private function withCounters(array $posts): array
    {
        return $this->articles->hydrateCounters($posts);
    }

    /**
     * The health endpoint used by the load balancer and the orchestrator.
     */
    public function health(Request $request): Response
    {
        $report = (new \SoulCMS\Foundation\HealthCheck())->run();

        return Response::json($report, $report['status'] === 'ok' ? 200 : 503)
            ->withHeader('Cache-Control', 'no-store');
    }

    public function robots(Request $request): Response
    {
        $base = url('/', true);

        $body = "User-agent: *\n";
        $body .= "Allow: /\n";
        $body .= "Disallow: /manager\n";
        $body .= "Disallow: /login\n";
        $body .= "Disallow: /register\n";
        $body .= "Sitemap: " . $base . "sitemap.xml\n";

        return Response::text($body)->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function faq(Request $request): Response
    {
        $page = (string) $this->param('page', 'terms');

        $titles = [
            'terms' => 'Noi quy & Dieu khoan su dung',
            'about' => 'Ve chung toi',
            'help' => 'Trung tam tro giup',
        ];

        return $this->html('home/faq', [
            'page_title' => $titles[$page] ?? $titles['terms'],
            'faq_page' => $page,
        ]);
    }

    public function plugin(Request $request): Response
    {
        $plugin = (string) $this->param('plugin', '');

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $plugin)) {
            return Response::html('Not found', 404);
        }

        $view = 'plugin/' . $plugin;

        if (!$this->view->exists($view)) {
            return Response::html('Not found', 404);
        }

        return $this->html($view);
    }
}
