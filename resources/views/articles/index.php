<?php

/**
 * @var array  $posts
 * @var array  $categories
 */

use SoulCMS\Support\Lang;

?>
<section class="panel">
    <div class="panel__head">
        <h1 class="panel__title"><?= e(Lang::get('articles.index.title')) ?></h1>
        <span class="panel__meta"><?= e((string) count($posts)) ?> <?= e(Lang::get('home.post_count_short')) ?></span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($posts === []): ?>
            <?= view('partials/empty', [
                'title' => Lang::get('articles.index.empty_title'),
                'message' => Lang::get('articles.index.empty_message'),
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
