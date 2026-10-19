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
        <h2 class="panel__title">Bài viết</h2>
        <span class="panel__meta"><?= e((string) count($posts)) ?> bài</span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($posts === []): ?>
            <?= view('partials/empty', [
                'title' => 'Chưa có bài viết nào',
                'message' => 'Bài viết sẽ xuất hiện ở đây ngay khi có người đăng.',
                'icon' => 'file',
            ]) ?>
        <?php else: ?>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                    <tr><th>Bài viết</th><th>Tác giả</th><th>Chuyên mục</th><th>Cập nhật</th><th></th></tr>
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
                                      data-confirm="Xoá vĩnh viễn bài viết này?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) (int) $post['id']) ?>">
                                    <button class="btn btn--danger btn--sm" type="submit">
                                        <?= icon('trash', '', 14) ?> Xoá
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
