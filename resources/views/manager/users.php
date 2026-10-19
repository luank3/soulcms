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
        <h2 class="panel__title">Thành viên</h2>
        <span class="panel__meta"><?= e((string) $total) ?> người</span>
    </div>

    <div class="panel__body panel__body--flush">
        <div class="tablewrap">
            <table class="table">
                <thead>
                <tr><th>Thành viên</th><th>Vai trò</th><th>E-mail</th><th>Ngày tham gia</th><th class="text-right">Cấp độ</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $member): ?>
                    <tr>
                        <td>
                            <div class="row" style="gap:var(--s-2)">
                                <span class="avatar"><img src="<?= e(avatar_svg($member)) ?>" alt="" width="30" height="30"></span>
                                <a href="<?= e(url('/user/' . $member['nick'])) ?>"><?= e($member['nick']) ?></a>
                            </div>
                        </td>
                        <td><?= member_chip($member) ?></td>
                        <td class="muted small"><?= e((string) ($member['email'] ?? '')) ?></td>
                        <td class="mono muted"><?= e(date('d.m.Y', (int) $member['reg'])) ?></td>
                        <td class="text-right">
                            <form class="row" method="post" style="justify-content:flex-end;flex-wrap:nowrap"
                                  data-confirm="Đổi cấp độ của thành viên này?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="level">
                                <input type="hidden" name="id" value="<?= e((string) (int) $member['id']) ?>">
                                <input type="number" name="level" value="<?= e((string) (int) $member['level']) ?>"
                                       style="width:76px" aria-label="Cấp độ">
                                <button class="btn btn--quiet btn--sm" type="submit">Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pagination !== ''): ?>
        <div class="panel__body"><?= $pagination ?></div>
    <?php endif; ?>
</section>
