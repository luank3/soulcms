<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\App\Modules\Articles\Controllers\ArticleController;
use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Auth\AuthManager;
use SoulCMS\Auth\PasswordHasher;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Foundation\Clock;
use SoulCMS\Foundation\Config;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Request;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\Uri;
use SoulCMS\Support\Lang;
use SoulCMS\View\Engine;
use SoulCMS\View\ViewFactory;

final class HomeFeedViewTest extends TestCase
{
    private ?Container $previousContainer = null;
    private ViewFactory $views;

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        Container::swap($container);

        $config = new Config([
            'app' => [
                'url' => ['path' => ''],
                'content' => [
                    'post_list_order_by' => ['id', 'time', 'update_time', 'view'],
                    'post_list_sort' => ['asc', 'desc'],
                ],
            ],
        ]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $auth = new AuthManager(
            $connection,
            new Request('GET', Uri::fromString('/')),
            new PasswordHasher(),
            new Clock(),
            new SessionManager(),
            $config,
        );
        $engine = new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-test-views', true);
        $this->views = new ViewFactory($engine);

        $container->instance('config', $config);
        $container->instance('auth', $auth);
        $container->instance('view', $this->views);
    }

    protected function tearDown(): void
    {
        Container::swap($this->previousContainer);
    }

    public function testEnglishLocaleTranslatesPublicNavigationLabels(): void
    {
        $container = Container::getInstance();
        $request = new Request('GET', Uri::fromString('/'));
        $container->instance('request', $request);
        $container->instance('csp', new \SoulCMS\Http\Csp());
        Lang::setLocale('en');

        try {
            $html = $this->views->render('layouts/app', [
                'content' => '<p>Content</p>',
                'page_title' => 'Test',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('> Articles', $html);
        self::assertStringContainsString('aria-label="Quick navigation"', $html);
        self::assertStringContainsString('data-ui-switch-light="Switch to light mode"', $html);
    }

    public function testFeedRowShowsAnEscapedPlainTextExcerpt(): void
    {
        $html = $this->views->render('partials/feed-row', [
            'post' => [
                'id' => 1,
                'slug' => 'sample',
                'title' => 'Sample',
                'author' => 'member',
                'content' => '[b]Đậm[/b] <script>alert(1)</script>',
                'update_time' => time(),
                'view' => 0,
            ],
        ]);

        self::assertStringContainsString('Đậm &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('[b]', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testArticleDetailRendersPostSeoMetadataInTheDocumentHead(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        Container::swap($container);
        $config = new Config(['app' => ['name' => 'SoulCMS', 'url' => ['path' => '']]]);
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, nick TEXT, name TEXT, email TEXT, level INTEGER, reg INTEGER, karma INTEGER, xu INTEGER, avatar TEXT, cover TEXT, sex TEXT)',
            'CREATE TABLE articles_post (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, meta_data TEXT, time INTEGER, blocked INTEGER, sticked INTEGER, update_time INTEGER, category INTEGER, view INTEGER, author TEXT, buyfile INTEGER, `like` TEXT)',
            'CREATE TABLE articles_category (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)',
            'CREATE TABLE articles_chap (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, time INTEGER, box INTEGER, author TEXT)',
            'CREATE TABLE articles_cmt (id INTEGER PRIMARY KEY, blogid TEXT, author TEXT, comment TEXT, time INTEGER)',
            'CREATE TABLE articles_file (id INTEGER PRIMARY KEY, time INTEGER, filename TEXT, filecate TEXT, filesize INTEGER, passphrase TEXT, type TEXT, author TEXT, status TEXT, price INTEGER, blogid INTEGER)',
            'CREATE TABLE articles_tags (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)',
            'CREATE TABLE articles_post_tags (post_id INTEGER, tag_id INTEGER)',
        ] as $sql) {
            $connection->query($sql);
        }
        $connection->query("INSERT INTO users (id, nick, name, email, level, reg, karma, xu) VALUES (1, 'alice', 'Alice', '', 0, 1, 0, 0)");
        $connection->query("INSERT INTO articles_post (id, title, slug, content, meta_data, time, blocked, sticked, update_time, category, view, author, buyfile) VALUES (1, 'Original title', 'original-title', 'Body [b]content[/b]', '{\"meta_title\":\"Custom SEO\",\"meta_desc\":\"Custom description\",\"meta_keywords\":\"alpha, beta\"}', 1, 0, 0, 1, 1, 0, 'alice', 0)");
        $request = new Request('GET', Uri::fromString('/articles/1-original-title.html'));
        $auth = new AuthManager($connection, $request, new PasswordHasher(), new Clock(), new SessionManager(), $config);
        $views = new ViewFactory(new Engine(dirname(__DIR__, 2) . '/resources/views', sys_get_temp_dir() . '/soulcms-seo-views', true));
        $container->instance('config', $config);
        $container->instance(Config::class, $config);
        $container->instance('auth', $auth);
        $container->instance(AuthManager::class, $auth);
        $container->instance('request', $request);
        $container->instance(Request::class, $request);
        $container->instance('view', $views);
        $container->instance(ViewFactory::class, $views);
        $container->instance('csp', new \SoulCMS\Http\Csp());
        $controller = new ArticleController(
            $container,
            new ArticleRepository($connection),
            new UserRepository($connection),
            $connection,
            new \SoulCMS\Foundation\Events\Dispatcher(),
            new \SoulCMS\Http\Session\SessionManager(),
        );
        $controller->setActionRequest($request->withAttribute('routeParams', ['postSlug' => '1-original-title']));

        try {
            $response = $controller->postDetail($request);
        } finally {
            Container::swap($previous);
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<title>Custom SEO · SoulCMS</title>', $response->getBody());
        self::assertStringContainsString('<meta name="description" content="Custom description">', $response->getBody());
        self::assertStringContainsString('<meta name="keywords" content="alpha, beta">', $response->getBody());
    }

    public function testEnglishLocaleTranslatesHomeFeedControlsAndEmptyStates(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('home/index', [
                'posts' => [],
                'categories' => [],
                'stats' => ['count_post' => 0, 'count_user' => 0],
                'sticked' => [],
                'online_users' => [],
                'pagination' => '',
                'current_page' => 1,
                'category' => null,
                'order_by' => 'update_time',
                'sort' => 'desc',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Latest posts', $html);
        self::assertStringContainsString('List sorting options', $html);
        self::assertStringContainsString('Sort by', $html);
        self::assertStringContainsString('Recent activity', $html);
        self::assertStringContainsString('Apply', $html);
        self::assertStringContainsString('No posts yet', $html);
        self::assertStringContainsString('Categories', $html);
        self::assertStringContainsString('No categories yet', $html);
        self::assertStringContainsString('Online now', $html);
        self::assertStringContainsString('No one is online right now.', $html);
    }

    public function testEnglishLocaleTranslatesArticleIndexAndEmptyState(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('articles/index', [
                'posts' => [],
                'categories' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('All articles', $html);
        self::assertStringContainsString('No articles yet', $html);
        self::assertStringContainsString('New articles will appear here.', $html);
    }

    public function testEnglishLocaleTranslatesArticleDetailInterface(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('articles/post_detail', [
                'post' => [
                    'id' => 1,
                    'slug' => 'sample',
                    'title' => 'Sample',
                    'author' => 'alice',
                    'time' => 1,
                    'update_time' => 1,
                    'view' => 2,
                    'content' => 'Body',
                ],
                'chapters' => [['id' => 2, 'slug' => 'one', 'title' => 'Chapter one', 'author' => 'alice', 'time' => 1]],
                'comments' => [],
                'tags' => [],
                'files' => [['id' => 3, 'filename' => 'sample.zip', 'filecate' => 'zip', 'filesize' => 42, 'author' => 'alice']],
                'comment_count' => 0,
                'pagination' => '',
                'like_summary' => ['count' => 0, 'liked' => false],
                'viewer_authenticated' => false,
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Updated', $html);
        self::assertStringContainsString('views', $html);
        self::assertStringContainsString('likes', $html);
        self::assertStringContainsString('Chapters', $html);
        self::assertStringContainsString('Attachments', $html);
        self::assertStringContainsString('Comments', $html);
        self::assertStringContainsString('No comments yet', $html);
        self::assertStringContainsString('Log in', $html);
        self::assertStringNotContainsString('Bình luận', $html);
    }

    public function testEnglishLocaleTranslatesFileDetailPurchaseAndDownloadLabels(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('articles/file_detail', [
                'file' => [
                    'id' => 1,
                    'filename' => 'report.pdf',
                    'filecate' => 'pdf',
                    'filesize' => 1024,
                    'author' => 'alice',
                    'time' => 1,
                    'price' => 10,
                    'passphrase' => 'secret',
                ],
                'post' => ['id' => 2, 'slug' => 'post'],
                'download_url' => '/download',
                'can_purchase' => true,
                'has_purchased' => false,
                'purchasers' => [['nick' => 'buyer', 'name' => 'Buyer']],
                'viewer_balance' => 20,
                'purchase_error' => null,
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Paid file: 10 xu', $html);
        self::assertStringContainsString('Your balance: 20 xu', $html);
        self::assertStringContainsString('Buy file', $html);
        self::assertStringContainsString('Members who purchased this file', $html);
        self::assertStringContainsString('Download', $html);
        self::assertStringContainsString('Password-protected file', $html);
        self::assertStringContainsString('Back to article', $html);
        self::assertStringNotContainsString('Mua tệp', $html);
    }

    public function testEnglishLocaleTranslatesSearchFormAndEmptyResults(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('articles/search', [
                'query' => 'missing',
                'results' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Search', $html);
        self::assertStringContainsString('results', $html);
        self::assertStringContainsString('Keyword', $html);
        self::assertStringContainsString('For example: guide, php, sqlite…', $html);
        self::assertStringContainsString('Search', $html);
        self::assertStringContainsString('No results found', $html);
        self::assertStringContainsString('No articles matched this search.', $html);
        self::assertStringNotContainsString('Từ khoá', $html);
    }

    public function testEnglishLocaleTranslatesChapterDetailLabels(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('articles/chapter_detail', [
                'chapter' => [
                    'id' => 1,
                    'slug' => 'chapter-one',
                    'title' => 'Chapter one',
                    'author' => 'alice',
                    'time' => 1,
                    'content' => 'Body',
                ],
                'post' => null,
                'chapters' => [[
                    'id' => 2,
                    'slug' => 'chapter-two',
                    'title' => 'Chapter two',
                ]],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Chapter', $html);
        self::assertStringContainsString('Other chapters', $html);
        self::assertStringNotContainsString('Các chương khác', $html);
    }

    public function testEnglishLocaleTranslatesTagDetailAndEmptyState(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('articles/tag_detail', [
                'tag' => ['slug' => 'sample', 'name' => 'Sample', 'count' => 2],
                'posts' => [],
                'pagination' => '',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('2 posts', $html);
        self::assertStringContainsString('No posts use this tag yet', $html);
        self::assertStringContainsString('Posts will appear here when they use this tag.', $html);
        self::assertStringNotContainsString('bài', $html);
    }

    public function testEnglishLocaleTranslatesArticlePublishingForm(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('articles/post_publish', [
                'categories' => [['id' => 1, 'name' => 'News']],
                'errors' => [],
                'old' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Write a new post', $html);
        self::assertStringContainsString('Title', $html);
        self::assertStringContainsString('Category', $html);
        self::assertStringContainsString('URL slug (optional)', $html);
        self::assertStringContainsString('Tags (comma-separated)', $html);
        self::assertStringContainsString('Content', $html);
        self::assertStringContainsString('Publish post', $html);
        self::assertStringContainsString('Cancel', $html);
        self::assertStringNotContainsString('Viết bài mới', $html);
    }

    public function testEnglishLocaleTranslatesChapterAndFileUploadForms(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $chapter = $this->views->render('articles/chapter_publish', [
                'post' => ['id' => 1, 'slug' => 'post', 'title' => 'Sample'],
                'errors' => [],
            ]);
            $file = $this->views->render('articles/file_upload', [
                'post' => ['id' => 1, 'slug' => 'post', 'title' => 'Sample'],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Add chapter', $chapter);
        self::assertStringContainsString('Chapter title', $chapter);
        self::assertStringContainsString('Save chapter', $chapter);
        self::assertStringContainsString('Upload a file', $file);
        self::assertStringContainsString('Choose file', $file);
        self::assertStringContainsString('Upload', $file);
        self::assertStringNotContainsString('Tải tệp lên', $file);
    }

    public function testEnglishLocaleTranslatesArticleEditForm(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('articles/edit', [
                'action' => 'post',
                'row' => ['id' => 1, 'slug' => 'sample', 'title' => 'Sample', 'content' => 'Body', 'category' => 1],
                'categories' => [['id' => 1, 'name' => 'News']],
                'errors' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Edit post', $html);
        self::assertStringContainsString('Title', $html);
        self::assertStringContainsString('Category', $html);
        self::assertStringContainsString('Content', $html);
        self::assertStringContainsString('Save changes', $html);
        self::assertStringContainsString('Cancel', $html);
        self::assertStringNotContainsString('Chỉnh sửa', $html);
    }

    public function testEnglishLocaleTranslatesFaqContent(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('home/faq', ['faq_page' => 'terms']);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Rules &amp; Terms of Use', $html);
        self::assertStringContainsString('Respect others', $html);
        self::assertStringContainsString('Protect personal information', $html);
        self::assertStringNotContainsString('Nội quy', $html);
    }

    public function testEnglishLocaleTranslatesShoutboxAndEmptyState(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('shoutbox/index', ['messages' => []]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Chat', $html);
        self::assertStringContainsString('recent messages', $html);
        self::assertStringContainsString('Chat room is empty', $html);
        self::assertStringContainsString('Be the first to say hello.', $html);
        self::assertStringContainsString('Log in', $html);
        self::assertStringNotContainsString('Trò chuyện', $html);
    }

    public function testEnglishLocaleTranslatesMediaLibrary(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        Container::getInstance()->instance('csp', new \SoulCMS\Http\Csp());
        try {
            $html = $this->views->render('media/library', ['files' => []]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Media library', $html);
        self::assertStringContainsString('Upload a file', $html);
        self::assertStringContainsString('Your files', $html);
        self::assertStringContainsString('No files in your library yet.', $html);
        self::assertStringNotContainsString('Thư viện media', $html);
    }

    public function testEnglishLocaleTranslatesManagerCategoryEditForm(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('manager/category_edit', [
                'category' => ['id' => 1, 'name' => 'News', 'slug' => 'news', 'content' => '', 'keyword' => ''],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Edit category', $html);
        self::assertStringContainsString('Category name', $html);
        self::assertStringContainsString('Description', $html);
        self::assertStringContainsString('Keywords', $html);
        self::assertStringContainsString('Save category', $html);
    }

    public function testEnglishLocaleTranslatesStaticPageManager(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('manager/static_pages', [
                'pages' => [],
                'page' => null,
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Static pages', $html);
        self::assertStringContainsString('No static pages yet.', $html);
        self::assertStringContainsString('Add a page', $html);
        self::assertStringContainsString('URL slug', $html);
        self::assertStringContainsString('Markdown and BBCode are supported.', $html);
        self::assertStringContainsString('Save page', $html);
    }

    public function testEnglishLocaleTranslatesManagerSettingsForm(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('manager/settings', [
                'settings' => ['site_name' => 'SoulCMS', 'site_description' => 'Forum', 'site_keyword' => 'community'],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Site settings', $html);
        self::assertStringContainsString('Site name', $html);
        self::assertStringContainsString('Displayed in the meta tags and when sharing posts.', $html);
        self::assertStringContainsString('Save settings', $html);
    }

    public function testEnglishLocaleTranslatesCategoryListingAndCreationForm(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('articles/category_publish', [
                'categories' => [],
                'errors' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Categories', $html);
        self::assertStringContainsString('Add category', $html);
        self::assertStringContainsString('Create category', $html);
        self::assertStringContainsString('Name', $html);
        self::assertStringContainsString('Posts', $html);
    }

    public function testVietnameseLocaleTranslatesArticleFormExamples(): void
    {
        Lang::setLocale('vi');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $category = $this->views->render('articles/category_publish', [
                'categories' => [],
                'errors' => [],
            ]);
            $chapter = $this->views->render('articles/chapter_publish', [
                'post' => ['id' => 1, 'slug' => 'post', 'title' => 'Sample'],
                'errors' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('placeholder="Thảo luận chung"', $category);
        self::assertStringContainsString('placeholder="thảo luận, cộng đồng"', $category);
        self::assertStringContainsString('placeholder="Chương 1 — Bắt đầu"', $chapter);
    }

    public function testEnglishLocaleTranslatesArticleManagerLists(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('manager/articles', [
                'posts' => [],
                'categories' => [],
                'pagination' => '',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Posts', $html);
        self::assertStringContainsString('No posts yet', $html);
        self::assertStringContainsString('Categories', $html);
        self::assertStringContainsString('Add category', $html);
        self::assertStringContainsString('Category name', $html);
        self::assertStringContainsString('No categories yet.', $html);
    }

    public function testEnglishLocaleTranslatesManagerUserTable(): void
    {
        Lang::setLocale('en');
        try {
            $html = $this->views->render('manager/users', [
                'users' => [],
                'total' => 0,
                'pagination' => '',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Members', $html);
        self::assertStringContainsString('Role', $html);
        self::assertStringContainsString('Email', $html);
        self::assertStringContainsString('Joined', $html);
        self::assertStringContainsString('Level', $html);
    }

    public function testVietnameseLocaleTranslatesManagerUserEmailHeading(): void
    {
        Lang::setLocale('vi');
        try {
            $html = $this->views->render('manager/users', [
                'users' => [],
                'total' => 0,
                'pagination' => '',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('>E-mail<', $html);
    }

    public function testEnglishLocaleTranslatesSharedErrorAndFeedLabels(): void
    {
        Lang::setLocale('en');
        try {
            $notFound = $this->views->render('errors/404', ['status' => 404]);
            $serverError = $this->views->render('errors/500', ['status' => 500]);
            $errors = $this->views->render('partials/errors', ['errors' => ['Invalid value']]);
            $feedRow = $this->views->render('partials/feed-row', [
                'post' => [
                    'id' => 1,
                    'slug' => 'sample',
                    'title' => 'Sample',
                    'author' => 'alice',
                    'content' => '',
                    'update_time' => time(),
                    'comment_count' => 2,
                    'chapter_count' => 3,
                    'view' => 4,
                ],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Page not found', $notFound);
        self::assertStringContainsString('Back to the forum', $notFound);
        self::assertStringContainsString('Something went wrong', $serverError);
        self::assertStringContainsString('2 comments', $feedRow);
        self::assertStringContainsString('3 chapters', $feedRow);
        self::assertStringContainsString('4 views', $feedRow);
        self::assertStringContainsString('1 error needs attention', $errors);
    }

    public function testEnglishLocaleTranslatesManagerDashboardAndModules(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $dashboard = $this->views->render('manager/dashboard', [
                'stats' => [],
                'database' => 'sqlite',
                'php_version' => '8.3',
                'modules' => [],
                'enabled_modules' => [],
            ]);
            $modules = $this->views->render('manager/modules', [
                'modules' => [],
                'enabled' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Posts', $dashboard);
        self::assertStringContainsString('Environment', $dashboard);
        self::assertStringContainsString('Database:', $dashboard);
        self::assertStringContainsString('Modules', $modules);
        self::assertStringContainsString('Version', $modules);
        self::assertStringContainsString('Save configuration', $modules);
    }

    public function testEnglishLocaleTranslatesUrlRuleManager(): void
    {
        Lang::setLocale('en');
        Container::getInstance()->instance('session', new SessionManager());
        try {
            $html = $this->views->render('manager/url_rules', [
                'rewrite' => [],
                'disabled' => [],
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('Redirects', $html);
        self::assertStringContainsString('No redirect rules yet', $html);
        self::assertStringContainsString('Add a rule', $html);
        self::assertStringContainsString('Public path', $html);
        self::assertStringContainsString('Target path', $html);
        self::assertStringContainsString('Save rule', $html);
    }

    public function testEnglishLocaleTranslatesAdministrationShell(): void
    {
        Lang::setLocale('en');
        $container = Container::getInstance();
        $request = new Request('GET', Uri::fromString('/manager'));
        $container->instance('request', $request);
        $container->instance('csp', new \SoulCMS\Http\Csp());
        try {
            $html = $this->views->render('layouts/admin', [
                'content' => '<p>Admin content</p>',
                'page_title' => 'Dashboard',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('<html lang="en"', $html);
        self::assertStringContainsString('Administration', $html);
        self::assertStringContainsString('Skip navigation', $html);
        self::assertStringContainsString('Administration navigation', $html);
        self::assertStringContainsString('Static pages', $html);
        self::assertStringContainsString('Close menu', $html);
    }

    public function testEnglishLocaleTranslatesAuthTitleFallbackAndEmptyAction(): void
    {
        Lang::setLocale('en');
        $container = Container::getInstance();
        $container->instance('request', new Request('GET', Uri::fromString('/login')));
        $container->instance('csp', new \SoulCMS\Http\Csp());
        try {
            $auth = $this->views->render('layouts/auth', ['content' => '<p>Sign-in form</p>']);
            $empty = $this->views->render('partials/empty', [
                'title' => 'Empty',
                'actionUrl' => '/',
                'icon' => 'home',
            ]);
        } finally {
            Lang::setLocale('vi');
        }

        self::assertStringContainsString('<title>Sign in · SoulCMS</title>', $auth);
        self::assertStringContainsString('>Continue</a>', $empty);
    }

    public function testHomeFeedExposesSafeSortControlsAndRetainsCategory(): void
    {
        $html = $this->views->render('home/index', [
            'posts' => [],
            'categories' => [],
            'stats' => [],
            'sticked' => [],
            'online_users' => [],
            'pagination' => '',
            'current_page' => 1,
            'category' => ['id' => 5, 'name' => 'Tin tức', 'content' => ''],
            'order_by' => 'view',
            'sort' => 'asc',
        ]);

        self::assertStringContainsString('name="order_by"', $html);
        self::assertStringContainsString('name="sort"', $html);
        self::assertStringContainsString('value="view" selected', $html);
        self::assertStringContainsString('value="asc" selected', $html);
        self::assertStringContainsString('name="category" value="5"', $html);
    }
}
