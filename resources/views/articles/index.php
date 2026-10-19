<?php

/**
 * @var array  $posts
 * @var array  $categories
 */

?>
<section class="panel">
    <div class="panel__head">
        <h1 class="panel__title">Tất cả bài viết</h1>
        <span class="panel__meta"><?= e((string) count($posts)) ?> bài</span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($posts === []): ?>
            <?= view('partials/empty', [
                'title' => 'Chưa có bài viết nào',
                'message' => 'Khi có bài viết mới, chúng sẽ xuất hiện ở đây.',
                'icon' => 'file',
            ]) ?>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($posts as $post): ?>
                    <?= view('partials/feed-row', ['post' => $post]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
