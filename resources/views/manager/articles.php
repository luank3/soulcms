<?php

/**
 * @var array  $posts
 * @var array  $categories
 * @var string $pagination
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('file', '', 16) ?>
        <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.articles.posts')) ?></h2>
        <span class="panel__meta"><?= e(\SoulCMS\Support\Lang::get('manager.articles.post_count', ['count' => (string) count($posts)])) ?></span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($posts === []): ?>
            <?= view('partials/empty', [
                'title' => \SoulCMS\Support\Lang::get('manager.articles.empty_posts_title'),
                'message' => \SoulCMS\Support\Lang::get('manager.articles.empty_posts_message'),
                'icon' => 'file',
            ]) ?>
        <?php else: ?>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                    <tr><th><?= e(\SoulCMS\Support\Lang::get('manager.articles.posts')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.articles.author')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.articles.category')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.articles.updated')) ?></th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">
                                    <?= e($post['title']) ?>
                                </a>
                            </td>
                            <td class="muted">@<?= e($post['author']) ?></td>
                            <td>
                                <span class="tagmark" <?= marker_style((string) ($post['category_slug'] ?? '')) ?>>
                                    <?= e((string) ($post['category_name'] ?? '—')) ?>
                                </span>
                            </td>
                            <td class="mono muted"><?= e(date('d.m.Y', (int) $post['update_time'])) ?></td>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/manager/articles/post/delete')) ?>"
                                      data-confirm="<?= e(\SoulCMS\Support\Lang::get('manager.articles.delete_post_confirm')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) (int) $post['id']) ?>">
                                    <button class="btn btn--danger btn--sm" type="submit">
                                        <?= icon('trash', '', 14) ?> <?= e(\SoulCMS\Support\Lang::get('manager.static.delete')) ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pagination !== ''): ?>
        <div class="panel__body"><?= $pagination ?></div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('articles.categories.title')) ?></h2>
        <span class="panel__meta"><?= e((string) count($categories)) ?></span>
    </div>
    <div class="panel__body">
        <form class="form" method="post" action="<?= e(url('/manager/articles/category/add')) ?>">
            <?= csrf_field() ?>
            <div class="form__row">
                <label class="field"><span class="field__label"><?= e(\SoulCMS\Support\Lang::get('manager.category.name')) ?></span><input name="name" required maxlength="255"></label>
                <label class="field"><span class="field__label">Slug</span><input name="slug" required maxlength="255"></label>
                <button class="btn btn--primary" type="submit"><?= e(\SoulCMS\Support\Lang::get('manager.articles.add_category')) ?></button>
            </div>
        </form>
    </div>
    <?php if ($categories === []): ?>
        <div class="panel__body"><p class="muted" style="margin:0"><?= e(\SoulCMS\Support\Lang::get('manager.articles.empty_categories')) ?></p></div>
    <?php else: ?>
        <div class="panel__body panel__body--flush">
            <div class="tablewrap">
                <table class="table">
                    <thead><tr><th><?= e(\SoulCMS\Support\Lang::get('manager.category.name')) ?></th><th>Slug</th><th><?= e(\SoulCMS\Support\Lang::get('articles.categories.posts')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= e((string) $category['name']) ?></td>
                            <td class="mono muted"><?= e((string) $category['slug']) ?></td>
                            <td class="num"><?= e((string) (int) ($category['count_post'] ?? 0)) ?></td>
                            <td class="table__actions">
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/manager/articles/category/edit?id=' . (int) $category['id'])) ?>"><?= e(\SoulCMS\Support\Lang::get('manager.category.edit')) ?></a>
                                <form method="post" action="<?= e(url('/manager/articles/category/delete')) ?>" data-confirm="<?= e(\SoulCMS\Support\Lang::get('manager.articles.delete_category_confirm')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) (int) $category['id']) ?>">
                                    <button class="btn btn--danger btn--sm" type="submit"><?= e(\SoulCMS\Support\Lang::get('manager.static.delete')) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
