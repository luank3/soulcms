<?php

/**
 * @var array $modules
 * @var array $enabled
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('layers', '', 16) ?>
        <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.modules.title')) ?></h2>
        <span class="panel__meta"><?= e(\SoulCMS\Support\Lang::get('manager.modules.count', ['count' => (string) count($modules)])) ?></span>
    </div>

    <div class="panel__body panel__body--flush">
        <form method="post">
            <?= csrf_field() ?>

            <div class="tablewrap">
                <table class="table">
                    <thead><tr><th>Module</th><th><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.version')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.modules.description')) ?></th><th class="text-right"><?= e(\SoulCMS\Support\Lang::get('manager.modules.enable')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($modules as $name => $module): ?>
                        <tr>
                            <td><?= e($module->title()) ?> <span class="mono muted"><?= e($name) ?></span></td>
                            <td class="mono muted"><?= e($module->version()) ?></td>
                            <td class="muted small"><?= e($module->description()) ?></td>
                            <td class="text-right">
                                <input type="checkbox" name="module_<?= e($name) ?>" value="1"
                                       <?= in_array($name, $enabled, true) ? 'checked' : '' ?>
                                       aria-label="<?= e(\SoulCMS\Support\Lang::get('manager.modules.enable_module', ['name' => $name])) ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel__body">
                <button class="btn" type="submit"><?= icon('check', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('manager.modules.save')) ?></button>
            </div>
        </form>
    </div>
</section>
