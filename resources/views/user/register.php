<?php

/**
 * @var array<string,string> $errors
 * @var array<string,mixed>  $old
 * @var string               $captcha_question
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1><?= e(\SoulCMS\Support\Lang::get('auth.register.title')) ?></h1>
        <p><?= e(\SoulCMS\Support\Lang::get('auth.register.intro')) ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert">
            <?= icon('alert', '', 18) ?>
            <div>
                <p class="alert__title"><?= e(\SoulCMS\Support\Lang::get('auth.register.error_title', ['count' => count($errors)])) ?></p>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= e($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('/register')) ?>" novalidate>
        <?= csrf_field() ?>

        <?= view('partials/field', [
            'name' => 'nick',
            'label' => \SoulCMS\Support\Lang::get('auth.register.identifier'),
            'type' => 'text',
            'value' => $old['nick'] ?? '',
            'errors' => $errors,
            'required' => true,
            'autofocus' => true,
            'hint' => \SoulCMS\Support\Lang::get('auth.register.identifier_hint'),
        ]) ?>

        <?= view('partials/field', [
            'name' => 'email',
            'label' => \SoulCMS\Support\Lang::get('auth.register.email'),
            'type' => 'email',
            'value' => $old['email'] ?? '',
            'errors' => $errors,
            'required' => true,
        ]) ?>

        <?= view('partials/field', [
            'name' => 'password',
            'label' => \SoulCMS\Support\Lang::get('auth.register.password'),
            'type' => 'password',
            'errors' => $errors,
            'required' => true,
            'hint' => \SoulCMS\Support\Lang::get('auth.register.password_hint'),
        ]) ?>

        <?= view('partials/field', [
            'name' => 'password_confirm',
            'label' => \SoulCMS\Support\Lang::get('auth.register.confirm_password'),
            'type' => 'password',
            'errors' => $errors,
            'required' => true,
        ]) ?>

        <div class="field">
            <label for="sex"><?= e(\SoulCMS\Support\Lang::get('auth.register.sex')) ?></label>
            <select id="sex" name="sex">
                <option value="boy"><?= e(\SoulCMS\Support\Lang::get('auth.register.male')) ?></option>
                <option value="girl"><?= e(\SoulCMS\Support\Lang::get('auth.register.female')) ?></option>
                <option value="other"><?= e(\SoulCMS\Support\Lang::get('auth.register.other')) ?></option>
            </select>
        </div>

        <div class="field">
            <label for="captcha_answer"><?= e(\SoulCMS\Support\Lang::get('auth.register.captcha')) ?>: <?= e($captcha_question) ?></label>
            <input id="captcha_answer" name="captcha_answer" type="number" inputmode="numeric" required autocomplete="off">
        </div>

        <button class="btn btn--block" type="submit"><?= icon('user', '', 16) ?> <?= e(\SoulCMS\Support\Lang::get('auth.register.submit')) ?></button>
    </form>

    <p class="auth__foot">
        <?= e(\SoulCMS\Support\Lang::get('auth.register.have_account')) ?> <a href="<?= e(url('/login')) ?>"><?= e(\SoulCMS\Support\Lang::get('auth.login.title')) ?></a>
    </p>
</div>
