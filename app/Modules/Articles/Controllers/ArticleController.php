<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Articles\Controllers;

use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Events\CommentPosted;
use SoulCMS\Events\PostPublished;
use SoulCMS\Foundation\Container;
use SoulCMS\Foundation\Events\Dispatcher;
use SoulCMS\Foundation\Str;
use SoulCMS\Http\Controller;
use SoulCMS\Http\UploadedFile;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;

/**
 * The forum controller.
 */
final class ArticleController extends Controller
{
    public function __construct(
        Container $container,
        private ArticleRepository $articles,
        private UserRepository $users,
        private \SoulCMS\Database\Connection $connection,
    ) {
        parent::__construct($container);
    }

    public function index(Request $request): Response
    {
        $per = (int) $this->config->get('app.content.per_page', 10);
        $page = max(1, (int) $this->query('page', 1));

        $posts = $this->articles->postList(0, $per, 'update_time', 'desc', ($page - 1) * $per);

        return $this->html('articles/index', [
            'page_title' => 'Bai viet',
            'posts' => $this->hydrate($posts),
            'categories' => $this->articles->categoryList(20),
        ]);
    }

    public function postDetail(Request $request): Response
    {
        $slug = (string) $this->param('postSlug', '');
        $post = $this->resolvePost($slug);

        if ($post === null) {
            return Response::html('Not found', 404);
        }

        $postId = (int) $post['id'];

        if ($this->isPost()) {
            $response = $this->handleComment($post);

            if ($response !== null) {
                return $response;
            }
        }

        $views = $this->articles->incrementView($postId, (int) ($post['view'] ?? 0));
        $post['view'] = $views;
        $post['url'] = url('/articles/' . $postId . '-' . $post['slug'] . '.html');
        $post['author_detail'] = $this->users->findByNick((string) $post['author']);

        $category = $this->articles->categoryDetail((int) $post['category']);

        if ($category !== null) {
            $post['category_name'] = $category['name'];
            $post['category_slug'] = $category['slug'];
        }

        $page = max(1, (int) $this->query('page', 1));
        $per = 10;
        $commentCount = $this->articles->countCommentsInPost($postId);

        return $this->html('articles/post_detail', [
            'page_title' => (string) $post['title'],
            'post' => $post,
            'chapters' => $this->articles->chapterList($postId),
            'comments' => $this->articles->commentList($postId, $per, ($page - 1) * $per),
            'comment_count' => $commentCount,
            'pagination' => paging($post['url'], $page, (int) ceil(max(1, $commentCount) / $per)),
            'current_page' => $page,
            'tags' => $this->articles->tagsForPost($postId),
            'files' => $this->articles->fileListForPost($postId),
        ]);
    }

    /**
     * Store a comment on a post.
     *
     * @param array<string,mixed> $post
     */
    private function handleComment(array $post): ?Response
    {
        $comment = trim((string) $this->input('comment', ''));

        if ($comment === '') {
            return null;
        }

        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $author = (string) $this->auth->user()->nick();
        $time = time();

        // The comment and the notification it triggers are one unit of work:
        // either both are stored, or neither is.
        $this->connection->transaction(function () use ($post, $author, $comment, $time): void {
            $this->articles->addComment((int) $post['id'], $author, mb_substr($comment, 0, 5000), $time);

            $this->events->dispatch(new CommentPosted(
                (int) $post['id'],
                $author,
                mb_substr($comment, 0, 5000),
                $post
            ));
        });

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html'));
    }


    public function chapterDetail(Request $request): Response
    {
        $slug = (string) $this->param('chapterSlug', '');

        if (preg_match('/^(?P<id>\d+)-(?P<slug>[a-zA-Z0-9\-_]+)$/', $slug, $matches)) {
            $chapter = $this->articles->chapterDetail((int) $matches['id']);
        } else {
            $chapter = $this->connection->selectOne(
                'SELECT * FROM `articles_chap` WHERE `slug` = ? ORDER BY `id` DESC LIMIT 1',
                [$slug]
            );
        }

        if ($chapter === null) {
            return Response::html('Not found', 404);
        }

        $post = $this->articles->postDetail((int) $chapter['box']);

        return $this->html('articles/chapter_detail', [
            'page_title' => (string) $chapter['title'],
            'chapter' => $chapter,
            'post' => $post,
            'chapters' => $this->articles->chapterList((int) $chapter['box']),
            'author_detail' => $this->users->findByNick((string) $chapter['author']),
        ]);
    }

    public function search(Request $request): Response
    {
        $query = trim((string) $this->query('q', ''));

        $results = $query === '' ? [] : $this->articles->search($query);

        return $this->html('articles/search', [
            'page_title' => 'Tim kiem',
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function tagDetail(Request $request): Response
    {
        $slug = (string) $this->param('tagSlug', '');
        $tag = $this->articles->tagBySlug($slug);

        if ($tag === null) {
            return Response::html('Not found', 404);
        }

        $per = (int) $this->config->get('app.content.per_page', 10);
        $page = max(1, (int) $this->query('page', 1));

        return $this->html('articles/tag_detail', [
            'page_title' => 'Tag: ' . (string) $tag['name'],
            'tag' => $tag,
            'posts' => $this->hydrate($this->articles->postListByTag((int) $tag['id'], $per, ($page - 1) * $per)),
            'pagination' => paging(url('/tag/' . $slug), $page, (int) ceil(max(1, (int) $tag['count']) / $per)),
            'current_page' => $page,
        ]);
    }

    public function postPublish(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $categories = $this->articles->categoryList(50, false);

        if (!$this->isPost()) {
            return $this->html('articles/post_publish', [
                'page_title' => 'Viet bai',
                'categories' => $categories,
                'errors' => [],
                'old' => [],
            ]);
        }

        $title = trim((string) $this->input('title', ''));
        $slug = Str::slug((string) ($this->input('slug') ?: $title));
        $content = (string) $this->input('content', '');
        $categoryId = (int) $this->input('category', 0);

        $errors = [];

        if ($title === '') {
            $errors['title'] = 'The title is required.';
        }

        if ($categoryId <= 0 || !$this->articles->categoryExists($categoryId)) {
            $errors['category'] = 'Please choose a valid category.';
        }

        if ($errors !== []) {
            return $this->html('articles/post_publish', [
                'page_title' => 'Viet bai',
                'categories' => $categories,
                'errors' => $errors,
                'old' => ['title' => $title, 'content' => $content, 'category' => $categoryId],
            ], 'layouts/app');
        }

        $now = time();

        $postId = $this->connection->transaction(function () use ($title, $slug, $content, $categoryId, $now, $author, $tags): int {
            $postId = $this->articles->publishPost([
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'meta_data' => null,
                'time' => $now,
                'update_time' => $now,
                'category' => $categoryId,
                'author' => $author,
                'blocked' => 0,
                'sticked' => 0,
                'view' => 0,
                'buyfile' => 0,
            ]);

            $this->articles->syncTags($postId, $tags);

            return $postId;
        });

        $this->events->dispatch(new PostPublished($postId, $author, $title));

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success', 'id' => $postId, 'url' => url('/articles/' . $postId . '-' . $slug . '.html')]);
        }

        return Response::redirect(url('/articles/' . $postId . '-' . $slug . '.html'));
    }

    public function categoryPublish(Request $request): Response
    {
        if (!$this->auth->check() || $this->auth->level() < 120) {
            return Response::redirect(url('/login'));
        }

        if (!$this->isPost()) {
            return $this->html('articles/category_publish', [
                'page_title' => 'Quan ly chuyen muc',
                'categories' => $this->articles->categoryList(100, false),
                'errors' => [],
            ]);
        }

        $name = trim((string) $this->input('name', ''));
        $slug = Str::slug((string) ($this->input('slug') ?: $name));

        if ($name === '' || $slug === '') {
            return $this->html('articles/category_publish', [
                'page_title' => 'Quan ly chuyen muc',
                'categories' => $this->articles->categoryList(100, false),
                'errors' => ['name' => 'The category name is required.'],
            ], 'layouts/app');
        }

        $this->articles->createRow(ArticleRepository::TABLE_CATEGORY, [
            'community' => (int) $this->input('community', 1),
            'name' => $name,
            'slug' => $slug,
            'content' => (string) $this->input('content', ''),
            'keyword' => (string) $this->input('keyword', ''),
            'meta_data' => null,
        ]);

        return Response::redirect(url('/articles/category'));
    }

    public function chapterPublish(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $postId = (int) $this->param('id', 0);
        $post = $this->articles->postDetail($postId);

        if ($post === null) {
            return Response::html('Not found', 404);
        }

        if (!$this->isPost()) {
            return $this->html('articles/chapter_publish', [
                'page_title' => 'Them chuong',
                'post' => $post,
                'errors' => [],
            ]);
        }

        $title = trim((string) $this->input('title', ''));
        $content = (string) $this->input('content', '');

        if ($title === '') {
            return $this->html('articles/chapter_publish', [
                'page_title' => 'Them chuong',
                'post' => $post,
                'errors' => ['title' => 'The chapter title is required.'],
            ], 'layouts/app');
        }

        $chapterId = $this->articles->publishChapter([
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => $content,
            'time' => time(),
            'box' => $postId,
            'author' => (string) $this->auth->user()->nick(),
        ]);

        return Response::redirect(url('/articles/' . $postId . '-' . $post['slug'] . '.html'));
    }

    public function edit(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $action = (string) $this->param('action', 'post');
        $id = (int) $this->param('id', 0);

        $isOwner = false;
        $row = $action === 'chapter'
            ? $this->articles->chapterDetail($id)
            : $this->articles->postDetail($id);

        if ($row === null) {
            return Response::html('Not found', 404);
        }

        $isOwner = (string) $row['author'] === (string) $this->auth->user()->nick();
        $isStaff = $this->auth->level() >= 120;

        if (!$isOwner && !$isStaff) {
            return Response::html('Forbidden', 403);
        }

        if (!$this->isPost()) {
            return $this->html('articles/edit', [
                'page_title' => 'Chinh sua',
                'action' => $action,
                'row' => $row,
                'categories' => $this->articles->categoryList(50, false),
                'errors' => [],
            ]);
        }

        $data = [];

        if ($action === 'chapter') {
            $data['title'] = trim((string) $this->input('title', $row['title']));
            $data['content'] = (string) $this->input('content', $row['content']);
            $this->articles->editChapter($id, $data);
        } else {
            $data['title'] = trim((string) $this->input('title', $row['title']));
            $data['content'] = (string) $this->input('content', $row['content']);
            $data['update_time'] = time();

            $categoryId = (int) $this->input('category', (int) $row['category']);

            if ($categoryId > 0) {
                $data['category'] = $categoryId;
            }

            $this->articles->editPost($id, $data);
        }

        return Response::redirect(url('/articles/' . (int) ($row['box'] ?? 0) . '-' . $row['slug'] . '.html'));
    }

    public function delete(Request $request): Response
    {
        if (!$this->auth->check() || $this->auth->level() < 120) {
            return Response::html('Forbidden', 403);
        }

        $action = (string) $this->param('action', 'post');
        $id = (int) $this->param('id', 0);

        if ($action === 'chapter') {
            $this->articles->deleteChapter($id);
        } else {
            $this->articles->deletePost($id);
        }

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success']);
        }

        return Response::redirect(url('/'));
    }

    public function fileUpload(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect(url('/login'));
        }

        $postId = (int) $this->param('id', 0);
        $post = $this->articles->postDetail($postId);

        if ($post === null) {
            return Response::html('Not found', 404);
        }

        if (!$this->isPost()) {
            return $this->html('articles/file_upload', [
                'page_title' => 'Tai len tep',
                'post' => $post,
            ]);
        }

        $files = $request->getUploadedFiles();
        $uploaded = 0;
        $rejected = 0;

        $maxBytes = (int) $this->config->get('app.uploads.max_bytes', 10485760);
        $allowedExtensions = (array) $this->config->get('app.uploads.allowed_extensions', []);
        $allowedMimeTypes = (array) $this->config->get('app.uploads.allowed_mime_types', []);

        foreach ($files as $field => $file) {
            if (!is_array($file)) {
                continue;
            }

            $upload = UploadedFile::fromArray($file, $allowedExtensions, $allowedMimeTypes, $maxBytes);

            if ($upload === null) {
                ++$rejected;

                continue;
            }

            // The file itself is stored outside the document root under a
            // generated name; only the metadata is kept in the database.
            $stored = $upload->store(\SoulCMS\Foundation\Paths::storage('uploads/' . $postId));

            $this->articles->createRow(ArticleRepository::TABLE_FILE, [
                'time' => time(),
                'filename' => $upload->originalName,
                'filecate' => $upload->extension,
                'filesize' => $upload->size,
                'passphrase' => '0',
                'type' => $stored ? 'normal' : 'rejected',
                'author' => (string) $this->auth->user()->nick(),
                'status' => 'private',
                'price' => 0,
                'blogid' => $postId,
            ]);

            ++$uploaded;
        }

        if ($this->wantsJson()) {
            return $this->json([
                'status' => 'success',
                'uploaded' => $uploaded,
                'rejected' => $rejected,
            ], $rejected > 0 && $uploaded === 0 ? 422 : 200);
        }

        return Response::redirect(url('/articles/' . $postId . '-' . $post['slug'] . '.html'));
    }

    public function fileDetail(Request $request): Response
    {
        $id = (int) $this->param('id', 0);
        $file = $this->articles->fileDetail($id);

        if ($file === null) {
            return Response::html('Not found', 404);
        }

        return $this->html('articles/file_detail', [
            'page_title' => (string) $file['filename'],
            'file' => $file,
            'post' => $this->articles->postDetail((int) ($file['blogid'] ?? 0)),
        ]);
    }

    public function sitemap(Request $request): Response
    {
        $base = url('/', true);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        $xml .= "  <url><loc>" . e($base) . "</loc></url>\n";

        foreach ($this->articles->allPostsForSitemap() as $post) {
            $xml .= "  <url><loc>" . e($base . 'articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')
                . "</loc><lastmod>" . date('Y-m-d', (int) $post['update_time']) . "</lastmod></url>\n";
        }

        $xml .= "</urlset>";

        return Response::text($xml)->withHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Attach the author of every post with a single extra query.
     *
     * @param list<array<string,mixed>> $posts
     * @return list<array<string,mixed>>
     */
    private function hydrate(array $posts): array
    {
        $nicks = array_map(static fn (array $post): string => (string) ($post['author'] ?? ''), $posts);

        return $this->articles->hydrateAuthors($posts, $this->users->byNick($nicks));
    }

    /**
     * Resolve a post from its "id-slug" identifier.
     *
     * @return array<string,mixed>|null
     */
    private function resolvePost(string $slug): ?array
    {
        if (preg_match('/^(?P<id>\d+)-(?P<slug>[a-zA-Z0-9\-_]+)$/', $slug, $matches)) {
            return $this->articles->postDetail((int) $matches['id']);
        }

        return $this->articles->postBySlug($slug);
    }
}
