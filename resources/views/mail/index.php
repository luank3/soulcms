<?php

/**
 * @var array  $messages
 * @var array  $partners
 * @var string $partner
 */

?>
<div class="stack-lg">
    <section class="panel">
        <div class="panel__head">
            <?= icon('mail', '', 16) ?>
            <h1 class="panel__title">Tin nhắn</h1>
            <?php if ($partners !== []): ?>
                <span class="panel__meta"><?= e((string) count($partners)) ?> cuộc trò chuyện</span>
            <?php endif; ?>
        </div>

        <div class="panel__body panel__body--flush">
            <?php if ($partners === []): ?>
                <?= view('partials/empty', [
                    'title' => 'Chưa có cuộc trò chuyện nào',
                    'message' => 'Bắt đầu bằng cách gửi tin nhắn cho một thành viên khác.',
                    'icon' => 'mail',
                ]) ?>
            <?php else: ?>
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th>Người nhận</th><th class="num">Tin nhắn</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($partners as $item): ?>
                            <tr>
                                <td>
                                    <div class="row" style="gap:var(--s-2)">
                                        <span class="avatar"><?= e(mb_substr((string) $item, 0, 2)) ?></span>
                                        <a href="<?= e(url('/user/' . $item)) ?>">@<?= e($item) ?></a>
                                    </div>
                                </td>
                                <td class="num">—</td>
                                <td class="table__actions">
                                    <a class="btn btn--quiet btn--sm" href="<?= e(url('/mail?with=' . $item)) ?>">Mở</a>
                                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/mail/send/' . $item)) ?>">Trả lời</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($partner !== ''): ?>
        <section class="panel">
            <div class="panel__head">
                <span class="avatar"><?= e(mb_substr($partner, 0, 2)) ?></span>
                <h2 class="panel__title">@<?= e($partner) ?></h2>
                <a class="btn btn--ghost btn--sm" style="margin-left:auto" href="<?= e(url('/mail/send/' . $partner)) ?>">
                    <?= icon('reply', '', 14) ?> Trả lời
                </a>
            </div>

            <div class="panel__body panel__body--flush">
                <?php if ($messages === []): ?>
                    <p class="muted" style="padding:var(--s-4);margin:0">Chưa có tin nhắn nào trong cuộc trò chuyện này.</p>
                <?php else: ?>
                    <div class="thread" style="padding:0 var(--s-4)">
                        <?php foreach ($messages as $message): ?>
                            <article class="comment">
                                <span class="avatar"><?= e(mb_substr((string) $message['nick'], 0, 2)) ?></span>
                                <div>
                                    <div class="comment__head">
                                        <a class="comment__author" href="<?= e(url('/user/' . $message['nick'])) ?>">
                                            @<?= e($message['nick']) ?>
                                        </a>
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
    <?php endif; ?>
</div>
