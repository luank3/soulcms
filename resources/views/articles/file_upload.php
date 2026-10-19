<?php

/**
 * @var array $post
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('download', '', 16) ?>
        <h1 class="panel__title">Tải tệp lên</h1>
        <span class="panel__meta truncate"><?= e($post['title']) ?></span>
    </div>

    <div class="panel__body">
        <form class="form" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="field">
                <label for="file">Chọn tệp</label>
                <input id="file" name="file" type="file" required>
                <span class="field__hint">Tệp đính kèm sẽ hiển thị ở cuối bài viết.</span>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('upload', '', 15) ?> Tải lên</button>
                <a class="btn btn--ghost" href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">Huỷ</a>
            </div>
        </form>
    </div>
</section>
