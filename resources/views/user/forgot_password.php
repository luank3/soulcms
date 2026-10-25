<?php

/**
 * @var bool $sent
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1><?= e(\SoulCMS\Support\Lang::get('auth.forgot.title')) ?></h1>
        <p><?= e(\SoulCMS\Support\Lang::get('auth.forgot.intro')) ?></p>
    </div>

    <?php if (!empty($sent)): ?>
        <div class="alert alert--success">
            <?= icon('check', '', 18) ?>
            <div>
                <p class="alert__title"><?= e(\SoulCMS\Support\Lang::get('auth.forgot.sent_title')) ?></p>
                <p><?= e(\SoulCMS\Support\Lang::get('auth.forgot.sent_message')) ?></p>
            </div>
        </div>
        <p class="auth__foot"><a href="<?= e(url('/login')) ?>"><?= e(\SoulCMS\Support\Lang::get('auth.forgot.back_login')) ?></a></p>
    <?php else: ?>
        <form class="form" method="post" action="<?= e(url('/login/password/forgot')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="email"><?= e(\SoulCMS\Support\Lang::get('auth.forgot.email')) ?></label>
                <input id="email" name="email" type="email" required autofocus autocomplete="email">
            </div>

            <button class="btn btn--block" type="submit"><?= icon('mail', '', 16) ?> <?= e(\SoulCMS\Support\Lang::get('auth.forgot.submit')) ?></button>
        </form>

        <p class="auth__foot"><a href="<?= e(url('/login')) ?>"><?= e(\SoulCMS\Support\Lang::get('auth.forgot.back_login')) ?></a></p>
    <?php endif; ?>
</div>
