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
        <h1 class="panel__title">Viết bài mới</h1>
    </div>

    <div class="panel__body">
        <?= view('partials/errors', ['errors' => $errors]) ?>

        <form class="form" method="post" action="<?= e(url('/articles/post')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="title">Tiêu đề</label>
                <input id="title" name="title" value="<?= e((string) ($old['title'] ?? '')) ?>" required autofocus
                       placeholder="Một tiêu đề rõ ràng, dễ tìm">
            </div>

            <div class="field">
                <label for="category">Chuyên mục</label>
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
                <label for="slug">Đường dẫn (tùy chọn)</label>
                <input id="slug" name="slug" value="<?= e((string) ($old['slug'] ?? '')) ?>"
                       placeholder="tu-dien-bai-viet">
                <span class="field__hint">Bỏ trống để hệ thống tự sinh từ tiêu đề.</span>
            </div>

            <div class="field">
                <label for="tags">Thẻ (phân tách bằng dấu phẩy)</label>
                <input id="tags" name="tags" value="<?= e((string) ($old['tags'] ?? '')) ?>" placeholder="php, hiệu năng">
            </div>

            <div class="field">
                <label for="content">Nội dung</label>
                <textarea id="content" name="content" rows="12" required
                          placeholder="Viết nội dung của bạn…"><?= e((string) ($old['content'] ?? '')) ?></textarea>
                <span class="field__hint">Hỗ trợ **đậm**, *nghiêng*, `mã` và danh sách bằng dấu gạch ngang.</span>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('check', '', 15) ?> Đăng bài</button>
                <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Huỷ</a>
            </div>
        </form>
    </div>
</section>
