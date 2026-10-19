<?php

/**
 * @var array $modules
 * @var array $enabled
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('layers', '', 16) ?>
        <h2 class="panel__title">Module</h2>
        <span class="panel__meta"><?= e((string) count($modules)) ?> module</span>
    </div>

    <div class="panel__body panel__body--flush">
        <form method="post">
            <?= csrf_field() ?>

            <div class="tablewrap">
                <table class="table">
                    <thead><tr><th>Module</th><th>Phiên bản</th><th>Mô tả</th><th class="text-right">Bật</th></tr></thead>
                    <tbody>
                    <?php foreach ($modules as $name => $module): ?>
                        <tr>
                            <td><?= e($module->title()) ?> <span class="mono muted"><?= e($name) ?></span></td>
                            <td class="mono muted"><?= e($module->version()) ?></td>
                            <td class="muted small"><?= e($module->description()) ?></td>
                            <td class="text-right">
                                <input type="checkbox" name="module_<?= e($name) ?>" value="1"
                                       <?= in_array($name, $enabled, true) ? 'checked' : '' ?>
                                       aria-label="Bật module <?= e($name) ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel__body">
                <button class="btn" type="submit"><?= icon('check', '', 15) ?> Lưu cấu hình</button>
            </div>
        </form>
    </div>
</section>
