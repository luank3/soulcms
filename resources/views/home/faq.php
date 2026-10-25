<?php

declare(strict_types=1);

/**
 * @var string $faq_page
 */

$pageKey = in_array($faq_page, ['terms', 'about', 'help'], true) ? $faq_page : 'terms';
$items = match ($pageKey) {
    'about' => ['one_database', 'lightweight', 'open'],
    'help' => ['write_post', 'tagging', 'reply', 'password'],
    default => ['respect', 'category', 'useful', 'privacy'],
};

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('file', '', 16) ?>
        <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('faq.' . $pageKey . '.title')) ?></h1>
    </div>

    <div class="panel__body">
        <p class="muted" style="max-width:64ch"><?= e(\SoulCMS\Support\Lang::get('faq.' . $pageKey . '.intro')) ?></p>

        <div class="stack mt-5">
            <?php foreach ($items as $item): ?>
                <div>
                    <h3 style="font-size:var(--step-1);margin-bottom:var(--s-1)"><?= e(\SoulCMS\Support\Lang::get('faq.' . $pageKey . '.' . $item . '.title')) ?></h3>
                    <p class="muted" style="max-width:64ch;margin:0"><?= e(\SoulCMS\Support\Lang::get('faq.' . $pageKey . '.' . $item . '.body')) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
