<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Articles;

use SoulCMS\Routing\Router;

/**
 * The forum: posts, chapters, comments, tags and attachments.
 */
final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string
    {
        return 'articles';
    }

    public function title(): string
    {
        return 'Bài viết';
    }

    public function description(): string
    {
        return 'Bài viết, chương, bình luận, thẻ và tệp đính kèm.';
    }

    public function routes(Router $router): void
    {
        $controller = \SoulCMS\App\Modules\Articles\Controllers\ArticleController::class;

        $router->add('/search', [$controller, 'search'], 'GET', 'articles.search');
        $router->add('/articles', [$controller, 'index'], 'GET', 'articles.index');
        $router->add('/articles/category', [$controller, 'categoryPublish'], 'GET|POST', 'articles.category.publish');
        $router->add('/articles/post', [$controller, 'postPublish'], 'GET|POST', 'articles.post.publish');
        $router->add('/articles/{categorySlug:[a-zA-Z0-9\-_]+}/post', [$controller, 'postPublish'], 'GET|POST', 'articles.post.publish.in-category');
        $router->add('/articles/post-{id:[0-9]+}/upload', [$controller, 'fileUpload'], 'GET|POST', 'articles.post.upload');
        $router->add('/articles/post-{id:[0-9]+}/add-chap', [$controller, 'chapterPublish'], 'GET|POST', 'articles.post.chapter');
        $router->add('/articles/{action:post|chapter}-{id:[0-9]+}/edit', [$controller, 'edit'], 'GET|POST', 'articles.edit');
        $router->add('/articles/{action:post|chapter}-{id:[0-9]+}/delete', [$controller, 'delete'], 'GET|POST', 'articles.delete');
        $router->add('/articles/{postSlug:[a-zA-Z0-9\-_]+}.html', [$controller, 'postDetail'], 'GET|POST', 'articles.show');
        $router->add('/articles/{postSlug:[a-zA-Z0-9\-_]+}', [$controller, 'postDetail'], 'GET|POST', 'articles.show.slug');
        $router->add('/tag/{tagSlug:[a-zA-Z0-9\-_]+}', [$controller, 'tagDetail'], 'GET', 'articles.tag');
        $router->add('/view-chap/{chapterSlug:[a-zA-Z0-9\-_]+}.html', [$controller, 'chapterDetail'], 'GET', 'articles.chapter.show');
        $router->add('/view-chap/{chapterSlug:[a-zA-Z0-9\-_]+}', [$controller, 'chapterDetail'], 'GET', 'articles.chapter');
        $router->add('/view-file/{id:[0-9]+}', [$controller, 'fileDetail'], 'GET|POST', 'articles.file');
    }
}
