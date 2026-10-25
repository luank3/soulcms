<?php

/**
 * @var array $categories
 * @var array $errors
 */

?>
<div class="stack-lg">
    <section class="panel">
        <div class="panel__head">
            <?= icon('folder', '', 16) ?>
            <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('articles.categories.title')) ?></h1>
            <span class="panel__meta"><?= e((string) count($categories)) ?></span>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="tablewrap">
                <table class="table">
                    <thead>
                    <tr><th>ID</th><th><?= e(\SoulCMS\Support\Lang::get('articles.categories.name')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('articles.categories.slug')) ?></th><th class="num"><?= e(\SoulCMS\Support\Lang::get('articles.categories.posts')) ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td class="mono muted"><?= e((string) (int) $category['id']) ?></td>
                            <td>
                                <a href="<?= e(url('/?category=' . (int) $category['id'])) ?>"><?= e($category['name']) ?></a>
                            </td>
                            <td class="mono muted"><?= e($category['slug']) ?></td>
                            <td class="num"><?= e((string) (int) ($category['count_post'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <?= icon('plus', '', 16) ?>
            <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('articles.categories.add')) ?></h1>
        </div>
        <div class="panel__body">
            <?= view('partials/errors', ['errors' => $errors]) ?>

            <form class="form" method="post" action="<?= e(url('/articles/category')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="name"><?= e(\SoulCMS\Support\Lang::get('articles.categories.name')) ?></label>
                    <input id="name" name="name" required placeholder="<?= e(\SoulCMS\Support\Lang::get('articles.categories.name_placeholder')) ?>">
                </div>

                <div class="field">
                    <label for="slug"><?= e(\SoulCMS\Support\Lang::get('articles.categories.slug')) ?></label>
                    <input id="slug" name="slug" placeholder="thao-luan-chung">
                </div>

                <div class="field">
                    <label for="content"><?= e(\SoulCMS\Support\Lang::get('manager.category.description')) ?></label>
                    <textarea id="content" name="content" rows="3"></textarea>
                </div>

                <div class="field">
                    <label for="keyword"><?= e(\SoulCMS\Support\Lang::get('manager.category.keywords')) ?></label>
                    <input id="keyword" name="keyword" placeholder="<?= e(\SoulCMS\Support\Lang::get('articles.categories.keywords_placeholder')) ?>">
                </div>

                <div class="form__actions">
                    <button class="btn" type="submit"><?= icon('plus', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('articles.categories.create')) ?></button>
                </div>
            </form>
        </div>
    </section>
</div>
