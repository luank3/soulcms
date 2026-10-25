<?php

/**
 * One row of the dispatch feed.
 *
 * @var array      $post
 * @var array|null $author
 */

$postId = (int) ($post['id'] ?? 0);
$slug = (string) ($post['slug'] ?? '');
$permalink = url('/articles/' . $postId . '-' . $slug . '.html');
$authorName = (string) ($post['author'] ?? '');
$author = $post['author_detail'] ?? null;
$categoryName = (string) ($post['category_name'] ?? '');
$categorySlug = (string) ($post['category_slug'] ?? '');
$marker = $categorySlug !== '' ? $categorySlug : $slug;

?>
<div class="feed__row">
    <span class="feed__gutter" <?= marker_style($marker) ?>></span>

    <div>
        <h3 class="feed__title"><a href="<?= e($permalink) ?>"><?= e($post['title']) ?></a></h3>

        <?php $excerpt = content_excerpt((string) ($post['content'] ?? '')); ?>
        <?php if ($excerpt !== ''): ?>
            <p class="muted small"><?= e($excerpt) ?></p>
        <?php endif; ?>

        <p class="feed__meta">
            <?php if ($categoryName !== ''): ?>
                <span class="tagmark" <?= marker_style($categorySlug) ?>><?= e($categoryName) ?></span>
            <?php endif; ?>

            <a href="<?= e(url('/user/' . $authorName)) ?>">@<?= e($authorName) ?></a>

            <?php if (is_array($author) && $author !== []): ?>
                <?= member_chip($author) ?>
            <?php endif; ?>

            <span><?= e(time_ago((int) ($post['update_time'] ?? 0))) ?></span>
        </p>
    </div>

    <div class="feed__stats">
        <span class="feed__stat"><?= e(\SoulCMS\Support\Lang::get('feed.comments', ['count' => (string) (int) ($post['comment_count'] ?? 0)])) ?></span>
        <span class="feed__stat"><?= e(\SoulCMS\Support\Lang::get('feed.chapters', ['count' => (string) (int) ($post['chapter_count'] ?? 0)])) ?></span>
        <span class="feed__stat"><?= e(\SoulCMS\Support\Lang::get('feed.views', ['count' => (string) (int) ($post['view'] ?? 0)])) ?></span>
    </div>
</div>
