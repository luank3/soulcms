<?php

/**
 * @var array $categories
 * @var array $errors
 * @var array $old
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('pencil', '', 16) ?>
        <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('articles.publish.title')) ?></h1>
    </div>

    <div class="panel__body">
        <?= view('partials/errors', ['errors' => $errors]) ?>

        <form class="form" method="post" action="<?= e(url('/articles/post')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="title"><?= e(\SoulCMS\Support\Lang::get('articles.publish.title_label')) ?></label>
                <input id="title" name="title" value="<?= e((string) ($old['title'] ?? '')) ?>" required autofocus
                       placeholder="<?= e(\SoulCMS\Support\Lang::get('articles.publish.title_placeholder')) ?>">
            </div>

            <div class="field">
                <label for="category"><?= e(\SoulCMS\Support\Lang::get('articles.publish.category')) ?></label>
                <select id="category" name="category" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) (int) $category['id']) ?>"
                            <?= (int) ($old['category'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="slug"><?= e(\SoulCMS\Support\Lang::get('articles.publish.slug')) ?></label>
                <input id="slug" name="slug" value="<?= e((string) ($old['slug'] ?? '')) ?>"
                       placeholder="example-post-slug">
                <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('articles.publish.slug_hint')) ?></span>
            </div>

            <div class="field">
                <label for="tags"><?= e(\SoulCMS\Support\Lang::get('articles.publish.tags')) ?></label>
                <input id="tags" name="tags" value="<?= e((string) ($old['tags'] ?? '')) ?>" placeholder="php, performance">
            </div>

            <div class="field">
                <label for="content"><?= e(\SoulCMS\Support\Lang::get('articles.publish.content')) ?></label>
                <textarea id="content" name="content" rows="12" required
                          placeholder="<?= e(\SoulCMS\Support\Lang::get('articles.publish.content_placeholder')) ?>"><?= e((string) ($old['content'] ?? '')) ?></textarea>
                <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('articles.publish.content_hint')) ?></span>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('check', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('articles.publish.submit')) ?></button>
                <a class="btn btn--ghost" href="<?= e(url('/')) ?>"><?= e(\SoulCMS\Support\Lang::get('articles.publish.cancel')) ?></a>
            </div>
        </form>
    </div>
</section>
