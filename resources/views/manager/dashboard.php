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
            <div class="stats__label"><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.posts')) ?></div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_chapter'] ?? 0)) ?></div>
            <div class="stats__label"><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.chapters')) ?></div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_comment'] ?? 0)) ?></div>
            <div class="stats__label"><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.comments')) ?></div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_user'] ?? 0)) ?></div>
            <div class="stats__label"><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.members')) ?></div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_category'] ?? 0)) ?></div>
            <div class="stats__label"><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.categories')) ?></div>
        </div>
        <div class="stats__item">
            <div class="stats__value"><?= e((string) ($stats['count_file'] ?? 0)) ?></div>
            <div class="stats__label"><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.files')) ?></div>
        </div>
    </div>

    <div class="grid grid--2">
        <section class="panel">
            <div class="panel__head">
                <?= icon('layers', '', 16) ?>
                <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.modules.title')) ?></h2>
            </div>
            <div class="panel__body panel__body--flush">
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.name')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.version')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.status')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($modules as $name => $module): ?>
                            <tr>
                                <td><?= e($module->title()) ?> <span class="mono muted"><?= e($name) ?></span></td>
                                <td class="mono muted"><?= e($module->version()) ?></td>
                                <td>
                                    <?php if (in_array($name, $enabled_modules, true)): ?>
                                        <span class="chip" style="--role:var(--live)"><?= e(\SoulCMS\Support\Lang::get('manager.modules.enabled')) ?></span>
                                    <?php else: ?>
                                        <span class="chip" style="--role:var(--ink-3)"><?= e(\SoulCMS\Support\Lang::get('manager.modules.disabled')) ?></span>
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
                <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.environment')) ?></h2>
            </div>
            <div class="panel__body">
                <p class="feed__meta" style="gap:var(--s-5)">
                    <span><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.database')) ?>: <strong><?= e($database) ?></strong></span>
                    <span><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.php')) ?>: <strong><?= e($php_version) ?></strong></span>
                    <span><?= e(\SoulCMS\Support\Lang::get('manager.dashboard.cache')) ?>: <strong><?= e(\SoulCMS\Support\Lang::get(config('app.debug', false) ? 'manager.dashboard.cache_debug' : 'manager.dashboard.cache_enabled')) ?></strong></span>
                </p>
                <p class="muted small mt-4" style="margin-bottom:0">
                    <?= e(\SoulCMS\Support\Lang::get('manager.dashboard.verify_hint')) ?> <code>php bin/soulcms db:verify</code>
                </p>
            </div>
        </section>
    </div>
</div>
