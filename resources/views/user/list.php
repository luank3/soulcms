<?php

/**
 * @var array  $users
 * @var int    $total
 * @var string $pagination
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('users', '', 16) ?>
        <h1 class="panel__title">Thành viên</h1>
        <span class="panel__meta"><?= e((string) $total) ?> người</span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($users === []): ?>
            <?= view('partials/empty', [
                'title' => 'Chưa có thành viên nào',
                'message' => 'Danh sách sẽ hiện ở đây khi có người đăng ký.',
                'icon' => 'users',
            ]) ?>
        <?php else: ?>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                    <tr><th>Thành viên</th><th>Vai trò</th><th>Karma</th><th>Ngày tham gia</th></tr>
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
