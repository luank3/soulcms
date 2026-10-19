<?php

/**
 * @var array  $stats
 * @var string $database
 * @var string $php_version
 * @var array  $modules
 * @var array  $enabled_modules
 */

?>
<div class="stack-lg">
    <div class="stats">
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_post'] ?? 0)) ?></div>
            <div class="stats__label">Bài viết</div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_chapter'] ?? 0)) ?></div>
            <div class="stats__label">Chương</div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_comment'] ?? 0)) ?></div>
            <div class="stats__label">Bình luận</div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_user'] ?? 0)) ?></div>
            <div class="stats__label">Thành viên</div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_category'] ?? 0)) ?></div>
            <div class="stats__label">Chuyên mục</div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_file'] ?? 0)) ?></div>
            <div class="stats__label">Tệp</div>
        </div>
    </div>

    <div class="grid grid--2">
        <section class="panel">
            <div class="panel__head">
                <?= icon('layers', '', 16) ?>
                <h2 class="panel__title">Module</h2>
            </div>
            <div class="panel__body panel__body--flush">
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th>Tên</th><th>Phiên bản</th><th>Trạng thái</th></tr></thead>
                        <tbody>
                        <?php foreach ($modules as $name => $module): ?>
                            <tr>
                                <td><?= e($module->title()) ?> <span class="mono muted"><?= e($name) ?></span></td>
                                <td class="mono muted"><?= e($module->version()) ?></td>
                                <td>
                                    <?php if (in_array($name, $enabled_modules, true)): ?>
                                        <span class="chip" style="--role:var(--live)">Đang bật</span>
                                    <?php else: ?>
                                        <span class="chip" style="--role:var(--ink-3)">Đang tắt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <?= icon('settings', '', 16) ?>
                <h2 class="panel__title">Môi trường</h2>
            </div>
            <div class="panel__body">
                <p class="feed__meta" style="gap:var(--s-5)">
                    <span>Cơ sở dữ liệu: <strong><?= e($database) ?></strong></span>
                    <span>Phiên bản PHP: <strong><?= e($php_version) ?></strong></span>
                    <span>Bộ nhớ đệm: <strong><?= e(config('app.debug', false) ? 'Tắt (debug)' : 'Bật') ?></strong></span>
                </p>
                <p class="muted small mt-4" style="margin-bottom:0">
                    Chạy <code>php bin/soulcms db:verify</code> để so sánh lược đồ hiện tại với lược đồ chuẩn.
                </p>
            </div>
        </section>
    </div>
</div>
