<?php

/**
 * @var array      $file
 * @var array|null  $post
 * @var string|null $download_url
 * @var bool        $can_purchase
 * @var bool        $has_purchased
 * @var list<array<string,mixed>> $purchasers
 * @var int         $viewer_balance
 * @var string|null $purchase_error
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

        <?php if (!empty($purchase_error)): ?>
            <div class="alert alert--error mt-5" role="alert"><?= e($purchase_error) ?></div>
        <?php endif; ?>

        <?php if ($can_purchase): ?>
            <div class="alert alert--info mt-5">
                <div>
                    <p class="alert__title"><?= e(\SoulCMS\Support\Lang::get('articles.file.paid', ['price' => (string) (int) $file['price']])) ?></p>
                    <p><?= e(\SoulCMS\Support\Lang::get('articles.file.balance', ['balance' => (string) $viewer_balance])) ?></p>
                    <form method="post" action="<?= e(url('/view-file/' . (int) $file['id'])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="buy">
                        <button class="btn btn--sm" type="submit" <?= $viewer_balance < (int) $file['price'] ? 'disabled' : '' ?>><?= e(\SoulCMS\Support\Lang::get('articles.file.buy')) ?></button>
                    </form>
                </div>
            </div>
        <?php elseif ($has_purchased): ?>
            <p class="feed__meta mt-5"><?= e(\SoulCMS\Support\Lang::get('articles.file.purchased')) ?></p>
        <?php endif; ?>

        <?php if ($purchasers !== []): ?>
            <ul class="file-purchasers mt-5" aria-label="<?= e(\SoulCMS\Support\Lang::get('articles.file.purchasers')) ?>">
                <?php foreach ($purchasers as $purchaser): ?>
                    <?php $purchaserNick = (string) ($purchaser['nick'] ?? ''); ?>
                    <li><a href="<?= e(url('/user/' . $purchaserNick)) ?>"><?= e((string) (($purchaser['name'] ?? '') !== '' ? $purchaser['name'] : $purchaserNick)) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($download_url !== null): ?>
            <p class="mt-5">
                <a class="btn" href="<?= e($download_url) ?>" rel="nofollow"><?= e(\SoulCMS\Support\Lang::get('articles.file.download')) ?></a>
            </p>
        <?php endif; ?>

        <?php if (!empty($file['passphrase']) && $file['passphrase'] !== '0'): ?>
            <div class="alert alert--warn mt-5">
                <?= icon('lock', '', 18) ?>
                <div>
                    <p class="alert__title"><?= e(\SoulCMS\Support\Lang::get('articles.file.password_protected')) ?></p>
                    <p><?= e(\SoulCMS\Support\Lang::get('articles.file.password')) ?>: <code><?= e((string) $file['passphrase']) ?></code></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($post !== null): ?>
            <p class="mt-5" style="margin-bottom:0">
                <a href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">
                    <?= icon('chevron-left', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('articles.file.back_to_article')) ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
