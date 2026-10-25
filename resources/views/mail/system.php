<?php

/**
 * @var array $messages
 */

use SoulCMS\Support\Lang;

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('bell', '', 16) ?>
        <h1 class="panel__title"><?= e(Lang::get('mail.system.title')) ?></h1>
        <span class="panel__meta"><?= e((string) count($messages)) ?></span>
        <?php if ($messages !== []): ?>
            <form method="post" action="<?= e(url('/mail/system/clear')) ?>" style="margin-left:auto">
                <?= csrf_field() ?>
                <button class="btn btn--sm btn--ghost" type="submit"><?= e(Lang::get('mail.system.clear')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($messages === []): ?>
            <?= view('partials/empty', [
                'title' => Lang::get('mail.system.empty_title'),
                'message' => Lang::get('mail.system.empty_message'),
                'icon' => 'bell',
            ]) ?>
        <?php else: ?>
            <div class="thread" style="padding:0 var(--s-4)">
                <?php foreach ($messages as $message): ?>
                    <article class="comment">
                        <span class="avatar"><?= icon('bell', '', 16) ?></span>
                        <div>
                            <div class="comment__head">
                                <span class="comment__author"><?= e(Lang::get('mail.system.sender')) ?></span>
                                <span class="comment__time"><?= e(date('d.m.Y H:i', (int) $message['time'])) ?></span>
                            </div>
                            <div class="comment__body"><?= nl2br(e($message['content'])) ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
