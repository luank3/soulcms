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
            <h1 class="panel__title">Chuyên mục</h1>
            <span class="panel__meta"><?= e((string) count($categories)) ?></span>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="tablewrap">
                <table class="table">
                    <thead>
                    <tr><th>Mã</th><th>Tên</th><th>Đường dẫn</th><th class="num">Bài viết</th></tr>
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
            <h1 class="panel__title">Thêm chuyên mục</h1>
        </div>
        <div class="panel__body">
            <?= view('partials/errors', ['errors' => $errors]) ?>

            <form class="form" method="post" action="<?= e(url('/articles/category')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="name">Tên chuyên mục</label>
                    <input id="name" name="name" required placeholder="Thảo luận chung">
                </div>

                <div class="field">
                    <label for="slug">Đường dẫn</label>
                    <input id="slug" name="slug" placeholder="thao-luan-chung">
                </div>

                <div class="field">
                    <label for="content">Mô tả</label>
                    <textarea id="content" name="content" rows="3"></textarea>
                </div>

                <div class="field">
                    <label for="keyword">Từ khoá</label>
                    <input id="keyword" name="keyword" placeholder="thảo luận, cộng đồng">
                </div>

                <div class="form__actions">
                    <button class="btn" type="submit"><?= icon('plus', '', 15) ?> Tạo chuyên mục</button>
                </div>
            </form>
        </div>
    </section>
</div>
