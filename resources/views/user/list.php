<?php

/**
 * @var array  $users
 * @var int    $total
 * @var string $pagination
 */

use SoulCMS\Support\Lang;

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('users', '', 16) ?>
        <h1 class="panel__title"><?= e(Lang::get('user.list.title')) ?></h1>
        <span class="panel__meta"><?= e(Lang::get($total === 1 ? 'user.list.count_one' : 'user.list.count_many', ['count' => $total])) ?></span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($users === []): ?>
            <?= view('partials/empty', [
                'title' => Lang::get('user.list.empty_title'),
                'message' => Lang::get('user.list.empty_message'),
                'icon' => 'users',
            ]) ?>
        <?php else: ?>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                    <tr><th><?= e(Lang::get('user.list.member')) ?></th><th><?= e(Lang::get('user.list.role')) ?></th><th><?= e(Lang::get('user.list.karma')) ?></th><th><?= e(Lang::get('user.list.joined')) ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $member): ?>
                        <tr>
                            <td>
                                <div class="row" style="gap:var(--s-2)">
                                    <span class="avatar">
                                        <img src="<?= e(avatar_svg($member)) ?>" alt="" width="30" height="30">
                                    </span>
                                    <a href="<?= e(url('/user/' . $member['nick'])) ?>"><?= e($member['nick']) ?></a>
                                </div>
                            </td>
                            <td><?= member_chip($member) ?></td>
                            <td class="num"><?= e((string) (int) $member['karma']) ?></td>
                            <td class="mono muted"><?= e(date('d.m.Y', (int) $member['reg'])) ?></td>
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
