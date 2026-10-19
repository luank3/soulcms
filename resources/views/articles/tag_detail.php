<?php

/**
 * @var array  $tag
 * @var array  $posts
 * @var string $pagination
 */

?>
<section class="panel">
    <div class="panel__head">
        <span class="tagmark" <?= marker_style((string) $tag['slug']) ?>>#</span>
        <h1 class="panel__title"><?= e($tag['name']) ?></h1>
        <span class="panel__meta"><?= e((string) (int) $tag['count']) ?> bài</span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($posts === []): ?>
            <?= view('partials/empty', [
                'title' => 'Chưa có bài nào dùng thẻ này',
                'message' => 'Thẻ sẽ xuất hiện ở đây khi có bài viết gắn nó.',
                'icon' => 'tag',
            ]) ?>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($posts as $post): ?>
                    <?= view('partials/feed-row', ['post' => $post]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pagination !== ''): ?>
        <div class="panel__body"><?= $pagination ?></div>
    <?php endif; ?>
</section>
