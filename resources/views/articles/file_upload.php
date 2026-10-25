<?php

/**
 * @var array $post
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('download', '', 16) ?>
        <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('articles.file.upload_title')) ?></h1>
        <span class="panel__meta truncate"><?= e($post['title']) ?></span>
    </div>

    <div class="panel__body">
        <form class="form" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="field">
                <label for="file"><?= e(\SoulCMS\Support\Lang::get('articles.file.choose')) ?></label>
                <input id="file" name="file" type="file" required>
                <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('articles.file.upload_hint')) ?></span>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('upload', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('articles.file.upload')) ?></button>
                <a class="btn btn--ghost" href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>"><?= e(\SoulCMS\Support\Lang::get('articles.publish.cancel')) ?></a>
            </div>
        </form>
    </div>
</section>
