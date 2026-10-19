<?php

/**
 * @var array      $file
 * @var array|null $post
 */

?>
<section class="panel">
    <div class="panel__body">
        <h1 style="margin-bottom:var(--s-3)"><?= e($file['filename']) ?></h1>

        <p class="feed__meta">
            <span class="tagmark" <?= marker_style((string) $file['filecate']) ?>><?= e((string) $file['filecate']) ?></span>
            <span><?= e(format_bytes((int) $file['filesize'])) ?></span>
            <span>@<?= e((string) $file['author']) ?></span>
            <span><?= e(date('d.m.Y', (int) $file['time'])) ?></span>
        </p>

        <?php if (!empty($file['passphrase']) && $file['passphrase'] !== '0'): ?>
            <div class="alert alert--warn mt-5">
                <?= icon('lock', '', 18) ?>
                <div>
                    <p class="alert__title">Tệp có mật khẩu</p>
                    <p>Mật khẩu: <code><?= e((string) $file['passphrase']) ?></code></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($post !== null): ?>
            <p class="mt-5" style="margin-bottom:0">
                <a href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">
                    <?= icon('chevron-left', '', 15) ?> Về bài viết
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
