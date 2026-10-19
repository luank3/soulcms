<?php

/**
 * @var array $post
 * @var array $errors
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('layers', '', 16) ?>
        <h1 class="panel__title">Thêm chương</h1>
        <span class="panel__meta truncate"><?= e($post['title']) ?></span>
    </div>

    <div class="panel__body">
        <?= view('partials/errors', ['errors' => $errors]) ?>

        <form class="form" method="post">
            <?= csrf_field() ?>

            <div class="field">
                <label for="title">Tiêu đề chương</label>
                <input id="title" name="title" required autofocus placeholder="Chương 1 — Bắt đầu">
            </div>

            <div class="field">
                <label for="content">Nội dung</label>
                <textarea id="content" name="content" rows="12" required></textarea>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('check', '', 15) ?> Lưu chương</button>
                <a class="btn btn--ghost" href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">Huỷ</a>
            </div>
        </form>
    </div>
</section>
