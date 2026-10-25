<?php

/**
 * @var array<string,string> $errors
 * @var array<string,mixed>  $old
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1><?= e(\SoulCMS\Support\Lang::get('auth.login.title')) ?></h1>
        <p><?= e(\SoulCMS\Support\Lang::get('auth.login.welcome')) ?></p>
    </div>

    <?php if (isset($errors['credentials'])): ?>
        <div class="alert alert--error" role="alert">
            <?= icon('alert', '', 18) ?>
            <div><p class="alert__title"><?= e(\SoulCMS\Support\Lang::get('auth.login.error_title')) ?></p><p><?= e($errors['credentials']) ?></p></div>
        </div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>

        <?= view('partials/field', [
            'name' => 'identifier',
            'label' => \SoulCMS\Support\Lang::get('auth.login.identifier'),
            'type' => 'text',
            'value' => $old['identifier'] ?? '',
            'errors' => $errors,
            'required' => true,
            'autofocus' => true,
        ]) ?>

        <?= view('partials/field', [
            'name' => 'password',
            'label' => \SoulCMS\Support\Lang::get('auth.login.password'),
            'type' => 'password',
            'errors' => $errors,
            'required' => true,
        ]) ?>

        <div class="form__row">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember" style="font-weight:400;color:var(--ink-2)"><?= e(\SoulCMS\Support\Lang::get('auth.login.remember')) ?></label>
        </div>

        <button class="btn btn--block" type="submit"><?= icon('login', '', 16) ?> <?= e(\SoulCMS\Support\Lang::get('auth.login.submit')) ?></button>
    </form>

    <p class="auth__foot">
        <?= e(\SoulCMS\Support\Lang::get('auth.login.no_account')) ?> <a href="<?= e(url('/register')) ?>"><?= e(\SoulCMS\Support\Lang::get('auth.login.register')) ?></a><br>
        <a href="<?= e(url('/login/password/forgot')) ?>"><?= e(\SoulCMS\Support\Lang::get('auth.login.forgot_password')) ?></a>
    </p>
</div>
