<?php

/**
 * @var int    $status
 * @var string $message
 */

?>
<section class="panel">
    <div class="panel__body" style="text-align:center;padding:var(--s-8) var(--s-4)">
        <p class="mono muted" style="font-size:var(--step-4);line-height:1;margin:0"><?= e((string) ($status ?? 404)) ?></p>
        <h1 style="margin:var(--s-3) 0 var(--s-2)"><?= e(\SoulCMS\Support\Lang::get('error.404.title')) ?></h1>
        <p class="muted" style="max-width:44ch;margin:0 auto var(--s-5)">
            <?= e($message ?? \SoulCMS\Support\Lang::get('error.404.body')) ?>
        </p>
        <div class="row" style="justify-content:center">
            <a class="btn" href="<?= e(url('/')) ?>"><?= icon('home', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('errors.back_forum')) ?></a>
            <a class="btn btn--ghost" href="<?= e(url('/search')) ?>"><?= icon('search', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('errors.search')) ?></a>
        </div>
    </div>
</section>
