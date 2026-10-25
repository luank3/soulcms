<?php

/**
 * @var array  $messages
 * @var array  $partners
 * @var array  $latest
 * @var string $partner
 */

use SoulCMS\Support\Lang;

?>
<div class="stack-lg">
    <section class="panel">
        <div class="panel__head">
            <?= icon('mail', '', 16) ?>
            <h1 class="panel__title"><?= e(Lang::get('mail.inbox.title')) ?></h1>
            <?php if ($partners !== []): ?>
                <span class="panel__meta"><?= e(Lang::get(count($partners) === 1 ? 'mail.inbox.count_one' : 'mail.inbox.count_many', ['count' => count($partners)])) ?></span>
            <?php endif; ?>
        </div>

        <div class="panel__body panel__body--flush">
            <?php if ($partners === []): ?>
                <?= view('partials/empty', [
                    'title' => Lang::get('mail.inbox.empty_title'),
                    'message' => Lang::get('mail.inbox.empty_message'),
                    'icon' => 'mail',
                ]) ?>
            <?php else: ?>
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th><?= e(Lang::get('mail.inbox.recipient')) ?></th><th><?= e(Lang::get('mail.inbox.latest_message')) ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($partners as $item): ?>
                            <tr>
                                <td>
                                    <div class="row" style="gap:var(--s-2)">
                                        <span class="avatar"><?= e(mb_substr((string) $item, 0, 2)) ?></span>
                                        <a href="<?= e(url('/user/' . $item)) ?>">@<?= e($item) ?></a>
                                    </div>
                                </td>
                                <td>
                                    <?php if (isset($latest[$item])): ?>
                                        <span class="feed__meta">
                                            <strong><?= e((string) $latest[$item]['nick']) ?></strong>
                                            <?= e(mb_strimwidth((string) $latest[$item]['content'], 0, 72, '…')) ?>
                                            <time class="muted" datetime="<?= e(date(DATE_ATOM, (int) $latest[$item]['time'])) ?>"><?= e(time_ago((int) $latest[$item]['time'])) ?></time>
                                        </span>
                                        <?php if (($latest[$item]['view'] ?? 'yes') === 'no'): ?>
                                            <span class="chip chip--tag"><?= e(Lang::get('mail.inbox.unread')) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="muted"><?= e(Lang::get('mail.inbox.no_message')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="table__actions">
                                    <a class="btn btn--quiet btn--sm" href="<?= e(url('/mail?with=' . $item)) ?>"><?= e(Lang::get('mail.inbox.open')) ?></a>
                                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/mail/send/' . $item)) ?>"><?= e(Lang::get('mail.inbox.reply')) ?></a>
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
                    <?= icon('reply', '', 14) ?> <?= e(Lang::get('mail.inbox.reply')) ?>
                </a>
            </div>

            <div class="panel__body panel__body--flush">
                <?php if ($messages === []): ?>
                    <p class="muted" style="padding:var(--s-4);margin:0"><?= e(Lang::get('mail.inbox.thread_empty')) ?></p>
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
