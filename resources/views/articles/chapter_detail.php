<?php

/**
 * @var array      $chapter
 * @var array|null $post
 * @var array      $chapters
 */

$author = $author_detail ?? null;

?>
<div class="stack-lg">
    <article class="panel">
        <div class="panel__body">
            <p class="feed__meta">
                <?php if ($post !== null): ?>
                    <a href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">
                        <?= e($post['title']) ?>
                    </a>
                    <span>/</span>
                <?php endif; ?>
                <span>Chương</span>
            </p>

            <h1 style="margin:var(--s-2) 0 var(--s-3)"><?= e($chapter['title']) ?></h1>

            <p class="feed__meta">
                <a href="<?= e(url('/user/' . $chapter['author'])) ?>">@<?= e($chapter['author']) ?></a>
                <?php if (is_array($author) && $author !== []): ?>
                    <?= member_chip($author) ?>
                <?php endif; ?>
                <span><?= e(date('d.m.Y · H:i', (int) $chapter['time'])) ?></span>
            </p>

            <div class="prose mt-5"><?= markdown_to_html((string) $chapter['content']) ?></div>
        </div>
    </article>

    <section class="panel">
        <div class="panel__head">
            <?= icon('layers', '', 16) ?>
            <h2 class="panel__title">Các chương khác</h2>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="feed">
                <?php foreach ($chapters as $item): ?>
                    <div class="feed__row" style="grid-template-columns:3px minmax(0,1fr) auto">
                        <span class="feed__gutter" <?= marker_style((string) $item['slug']) ?>></span>
                        <div>
                            <h3 class="feed__title" style="font-size:var(--step-0)">
                                <a href="<?= e(url('/view-chap/' . (int) $item['id'] . '-' . $item['slug'] . '.html')) ?>">
                                    <?= e($item['title']) ?>
                                </a>
                            </h3>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
