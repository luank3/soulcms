<?php

/**
 * @var string $action
 * @var array  $row
 * @var array  $categories
 * @var array  $errors
 */

$isPost = $action === 'post';

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('pencil', '', 16) ?>
        <h1 class="panel__title">Chỉnh sửa <?= e($isPost ? 'bài viết' : 'chương') ?></h1>
    </div>

    <div class="panel__body">
        <?= view('partials/errors', ['errors' => $errors]) ?>

        <form class="form" method="post">
            <?= csrf_field() ?>

            <div class="field">
                <label for="title">Tiêu đề</label>
                <input id="title" name="title" value="<?= e($row['title']) ?>" required autofocus>
            </div>

            <?php if ($isPost): ?>
                <div class="field">
                    <label for="category">Chuyên mục</label>
                    <select id="category" name="category">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) (int) $category['id']) ?>"
                                <?= (int) $category['id'] === (int) $row['category'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="field">
                <label for="content">Nội dung</label>
                <textarea id="content" name="content" rows="12" required><?= e($row['content']) ?></textarea>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('check', '', 15) ?> Lưu thay đổi</button>
                <a class="btn btn--ghost" href="<?= e(url('/articles/' . (int) ($row['box'] ?? 0) . '-' . $row['slug'] . '.html')) ?>">Huỷ</a>
            </div>
        </form>
    </div>
</section>
