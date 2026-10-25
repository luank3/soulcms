<?php

/**
 * @var string $token
 * @var array  $errors
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1><?= e(\SoulCMS\Support\Lang::get('auth.reset.title')) ?></h1>
        <p><?= e(\SoulCMS\Support\Lang::get('auth.reset.intro')) ?></p>
    </div>

    <?= view('partials/errors', ['errors' => $errors]) ?>

    <form class="form" method="post" action="<?= e(url('/login/password/reset')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="field">
            <label for="password"><?= e(\SoulCMS\Support\Lang::get('auth.reset.password')) ?></label>
            <input id="password" name="password" type="password" required minlength="6" autofocus autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirm"><?= e(\SoulCMS\Support\Lang::get('auth.reset.confirm_password')) ?></label>
            <input id="password_confirm" name="password_confirm" type="password" required minlength="6" autocomplete="new-password">
        </div>

        <button class="btn btn--block" type="submit"><?= icon('check', '', 16) ?> <?= e(\SoulCMS\Support\Lang::get('auth.reset.submit')) ?></button>
    </form>

    <p class="auth__foot"><a href="<?= e(url('/login')) ?>"><?= e(\SoulCMS\Support\Lang::get('auth.forgot.back_login')) ?></a></p>
</div>
