<?php

/**
 * @var string $token
 * @var array  $errors
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1>Đặt mật khẩu mới</h1>
        <p>Chọn một mật khẩu mới cho tài khoản của bạn.</p>
    </div>

    <?= view('partials/errors', ['errors' => $errors]) ?>

    <form class="form" method="post" action="<?= e(url('/login/password/reset')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="field">
            <label for="password">Mật khẩu mới</label>
            <input id="password" name="password" type="password" required minlength="6" autofocus autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirm">Xác nhận mật khẩu</label>
            <input id="password_confirm" name="password_confirm" type="password" required minlength="6" autocomplete="new-password">
        </div>

        <button class="btn btn--block" type="submit"><?= icon('check', '', 16) ?> Lưu mật khẩu</button>
    </form>

    <p class="auth__foot"><a href="<?= e(url('/login')) ?>">Quay lại đăng nhập</a></p>
</div>
