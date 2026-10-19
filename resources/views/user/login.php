<?php

/**
 * @var array<string,string> $errors
 * @var array<string,mixed>  $old
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1>Đăng nhập</h1>
        <p>Chào mừng trở lại với diễn đàn.</p>
    </div>

    <?php if (isset($errors['credentials'])): ?>
        <div class="alert alert--error" role="alert">
            <?= icon('alert', '', 18) ?>
            <div><p class="alert__title">Không thể đăng nhập</p><p><?= e($errors['credentials']) ?></p></div>
        </div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>

        <?= view('partials/field', [
            'name' => 'identifier',
            'label' => 'Tên đăng nhập hoặc e-mail',
            'type' => 'text',
            'value' => $old['identifier'] ?? '',
            'errors' => $errors,
            'required' => true,
            'autofocus' => true,
        ]) ?>

        <?= view('partials/field', [
            'name' => 'password',
            'label' => 'Mật khẩu',
            'type' => 'password',
            'errors' => $errors,
            'required' => true,
        ]) ?>

        <div class="form__row">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember" style="font-weight:400;color:var(--ink-2)">Ghi nhớ đăng nhập</label>
        </div>

        <button class="btn btn--block" type="submit"><?= icon('login', '', 16) ?> Đăng nhập</button>
    </form>

    <p class="auth__foot">
        Chưa có tài khoản? <a href="<?= e(url('/register')) ?>">Đăng ký ngay</a><br>
        <a href="<?= e(url('/login/password/forgot')) ?>">Quên mật khẩu?</a>
    </p>
</div>
