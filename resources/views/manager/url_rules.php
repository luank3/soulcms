<?php

/**
 * URL rules: rewrite a public path onto another path, or block it entirely.
 *
 * @var array<string,array<string,mixed>> $rewrite
 * @var list<string>                       $disabled
 */

$rules = $rewrite ?? [];
$blocked = $disabled ?? [];

?>
<div class="stack-lg">

    <section class="panel">
        <div class="panel__head">
            <?= icon('link', '', 16) ?>
            <h2 class="panel__title">Chuyển hướng</h2>
            <span class="panel__meta"><?= e((string) count($rules)) ?> quy tắc</span>
        </div>

        <div class="panel__body panel__body--flush">
            <?php if ($rules === []): ?>
                <?= view('partials/empty', [
                    'title' => 'Chưa có quy tắc chuyển hướng',
                    'message' => 'Thêm một quy tắc để gắn một đường dẫn ngắn với một trang của diễn đàn.',
                    'icon' => 'link',
                ]) ?>
            <?php else: ?>
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th>Đường dẫn</th><th>Đến</th><th>Phương thức</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rules as $from => $rule): ?>
                            <tr>
                                <td class="mono"><?= e($from) ?></td>
                                <td class="mono muted"><?= e((string) ($rule['target'] ?? '')) ?></td>
                                <td class="mono muted"><?= e((string) ($rule['method'] ?? 'GET|POST')) ?></td>
                                <td class="table__actions">
                                    <form class="inline-form" method="post" action="<?= e(url('/manager/url-rules/edit')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="original" value="<?= e($from) ?>">
                                        <input type="hidden" name="from" value="<?= e($from) ?>">
                                        <input type="hidden" name="to" value="<?= e(ltrim((string) ($rule['target'] ?? ''), '/')) ?>">
                                        <input type="hidden" name="method" value="<?= e((string) ($rule['method'] ?? 'GET|POST')) ?>">
                                        <button class="btn btn--quiet btn--sm" type="submit">Sửa</button>
                                    </form>
                                    <form class="inline-form" method="post" action="<?= e(url('/manager/url-rules/delete')) ?>"
                                          data-confirm="Xoá quy tắc này?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="from" value="<?= e($from) ?>">
                                        <button class="btn btn--danger btn--sm" type="submit">
                                            <?= icon('trash', '', 14) ?> Xoá
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <?= icon('plus', '', 16) ?>
            <h2 class="panel__title">Thêm quy tắc</h2>
        </div>

        <div class="panel__body">
            <form class="form" method="post" action="<?= e(url('/manager/url-rules')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="mode">Loại quy tắc</label>
                    <select id="mode" name="mode">
                        <option value="rewrite">Chuyển hướng sang trang khác</option>
                        <option value="disable">Chặn đường dẫn</option>
                    </select>
                </div>

                <div class="field">
                    <label for="from">Đường dẫn công khai</label>
                    <input id="from" name="from" placeholder="/tin-tuc" required>
                    <span class="field__hint">Đường dẫn người dùng gõ vào, không có dấu "/" ở đầu và cuối.</span>
                </div>

                <div class="field">
                    <label for="to">Đường dẫn đích</label>
                    <input id="to" name="to" placeholder="/articles?category=2">
                    <span class="field__hint">Bỏ trống nếu bạn chỉ muốn chặn đường dẫn.</span>
                </div>

                <div class="field">
                    <label for="method">Phương thức</label>
                    <select id="method" name="method">
                        <option value="GET|POST">GET và POST</option>
                        <option value="GET">Chỉ GET</option>
                        <option value="POST">Chỉ POST</option>
                        <option value="*">Mọi phương thức</option>
                    </select>
                </div>

                <div class="form__actions">
                    <button class="btn" type="submit"><?= icon('check', '', 15) ?> Lưu quy tắc</button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($blocked !== []): ?>
        <section class="panel">
            <div class="panel__head">
                <?= icon('lock', '', 16) ?>
                <h2 class="panel__title">Đường dẫn bị chặn</h2>
                <span class="panel__meta"><?= e((string) count($blocked)) ?></span>
            </div>
            <div class="panel__body panel__body--flush">
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th>Đường dẫn</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($blocked as $path): ?>
                            <tr>
                                <td class="mono"><?= e($path) ?></td>
                                <td class="table__actions">
                                    <form class="inline-form" method="post" action="<?= e(url('/manager/url-rules/delete')) ?>"
                                          data-confirm="Bỏ chặn đường dẫn này?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="from" value="<?= e($path) ?>">
                                        <button class="btn btn--ghost btn--sm" type="submit">Bỏ chặn</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
