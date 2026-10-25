<?php

/**
 * @var array $settings
 */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('settings', '', 16) ?>
        <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.settings.title')) ?></h2>
    </div>

    <div class="panel__body">
        <form class="form" method="post">
            <?= csrf_field() ?>

            <div class="field">
                <label for="site_name"><?= e(\SoulCMS\Support\Lang::get('manager.settings.name')) ?></label>
                <input id="site_name" name="site_name" value="<?= e((string) ($settings['site_name'] ?? config('app.name'))) ?>">
            </div>

            <div class="field">
                <label for="site_description"><?= e(\SoulCMS\Support\Lang::get('manager.settings.description')) ?></label>
                <textarea id="site_description" name="site_description" rows="3"><?= e((string) ($settings['site_description'] ?? config('app.description'))) ?></textarea>
                <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('manager.settings.description_hint')) ?></span>
            </div>

            <div class="field">
                <label for="site_keyword"><?= e(\SoulCMS\Support\Lang::get('manager.settings.keywords')) ?></label>
                <input id="site_keyword" name="site_keyword" value="<?= e((string) ($settings['site_keyword'] ?? config('app.keyword'))) ?>">
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('check', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('manager.settings.save')) ?></button>
            </div>
        </form>
    </div>
</section>
