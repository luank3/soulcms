<?php

/**
 * @var bool $sent
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1>Quên mật khẩu</h1>
        <p>Nhập e-mail để nhận liên kết đặt lại mật khẩu.</p>
    </div>

    <?php if (!empty($sent)): ?>
        <div class="alert alert--success">
            <?= icon('check', '', 18) ?>
            <div>
                <p class="alert__title">Đã gửi yêu cầu</p>
                <p>Nếu e-mail tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu.</p>
            </div>
        </div>
        <p class="auth__foot"><a href="<?= e(url('/login')) ?>">Quay lại đăng nhập</a></p>
    <?php else: ?>
        <form class="form" method="post" action="<?= e(url('/login/password/forgot')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="email">
            </div>

            <button class="btn btn--block" type="submit"><?= icon('mail', '', 16) ?> Gửi liên kết</button>
        </form>

        <p class="auth__foot"><a href="<?= e(url('/login')) ?>">Quay lại đăng nhập</a></p>
    <?php endif; ?>
</div>
