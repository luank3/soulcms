<?php

/**
 * @var array<string,string> $errors
 * @var array<string,mixed>  $old
 */

?>
<div class="auth__card">
    <div class="auth__head">
        <h1>Tạo tài khoản</h1>
        <p>Tham gia diễn đàn chỉ mất vài giây.</p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert">
            <?= icon('alert', '', 18) ?>
            <div>
                <p class="alert__title">Cần sửa <?= e((string) count($errors)) ?> trường</p>
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
            'label' => 'Tên đăng nhập',
            'type' => 'text',
            'value' => $old['nick'] ?? '',
            'errors' => $errors,
            'required' => true,
            'autofocus' => true,
            'hint' => '3–32 ký tự: chữ, số, gạch ngang hoặc gạch dưới.',
        ]) ?>

        <?= view('partials/field', [
            'name' => 'email',
            'label' => 'E-mail',
            'type' => 'email',
            'value' => $old['email'] ?? '',
            'errors' => $errors,
            'required' => true,
        ]) ?>

        <?= view('partials/field', [
            'name' => 'password',
            'label' => 'Mật khẩu',
            'type' => 'password',
            'errors' => $errors,
            'required' => true,
            'hint' => 'Tối thiểu 6 ký tự.',
        ]) ?>

        <?= view('partials/field', [
            'name' => 'password_confirm',
            'label' => 'Xác nhận mật khẩu',
            'type' => 'password',
            'errors' => $errors,
            'required' => true,
        ]) ?>

        <div class="field">
            <label for="sex">Giới tính</label>
            <select id="sex" name="sex">
                <option value="boy">Nam</option>
                <option value="girl">Nữ</option>
                <option value="other">Khác</option>
            </select>
        </div>

        <button class="btn btn--block" type="submit"><?= icon('user', '', 16) ?> Tạo tài khoản</button>
    </form>

    <p class="auth__foot">
        Đã có tài khoản? <a href="<?= e(url('/login')) ?>">Đăng nhập</a>
    </p>
</div>
