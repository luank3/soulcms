<?php

/** @var array<string,mixed> $category */

?>
<section class="panel">
    <div class="panel__head">
        <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.category.edit')) ?></h1>
    </div>
    <div class="panel__body">
        <form class="form" method="post" action="<?= e(url('/manager/articles/category/edit')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) (int) $category['id']) ?>">
            <div class="field">
                <label for="name"><?= e(\SoulCMS\Support\Lang::get('manager.category.name')) ?></label>
                <input id="name" name="name" value="<?= e((string) $category['name']) ?>" required maxlength="255">
            </div>
            <div class="field">
                <label for="slug">Slug</label>
                <input id="slug" name="slug" value="<?= e((string) $category['slug']) ?>" required maxlength="255">
            </div>
            <div class="field">
                <label for="content"><?= e(\SoulCMS\Support\Lang::get('manager.category.description')) ?></label>
                <textarea id="content" name="content" rows="4"><?= e((string) $category['content']) ?></textarea>
            </div>
            <div class="field">
                <label for="keyword"><?= e(\SoulCMS\Support\Lang::get('manager.category.keywords')) ?></label>
                <input id="keyword" name="keyword" value="<?= e((string) $category['keyword']) ?>" maxlength="255">
            </div>
            <button class="btn" type="submit"><?= e(\SoulCMS\Support\Lang::get('manager.category.save')) ?></button>
        </form>
    </div>
</section>
