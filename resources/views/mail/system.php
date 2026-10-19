<?php

/**
 * @var array $messages
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('bell', '', 16) ?>
        <h1 class="panel__title">Thông báo hệ thống</h1>
        <span class="panel__meta"><?= e((string) count($messages)) ?></span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($messages === []): ?>
            <?= view('partials/empty', [
                'title' => 'Không có thông báo',
                'message' => 'Bạn sẽ nhận thông báo khi có người bình luận bài viết của mình.',
                'icon' => 'bell',
            ]) ?>
        <?php else: ?>
            <div class="thread" style="padding:0 var(--s-4)">
                <?php foreach ($messages as $message): ?>
                    <article class="comment">
                        <span class="avatar"><?= icon('bell', '', 16) ?></span>
                        <div>
                            <div class="comment__head">
                                <span class="comment__author">Hệ thống</span>
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
