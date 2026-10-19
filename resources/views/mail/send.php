<?php

/**
 * @var array $receiver
 * @var array $errors
 */

?>
<section class="panel" style="max-width:640px">
    <div class="panel__head">
        <span class="avatar"><?= e(mb_substr((string) $receiver['nick'], 0, 2)) ?></span>
        <h1 class="panel__title">Gửi tin nhắn cho @<?= e($receiver['nick']) ?></h1>
    </div>

    <div class="panel__body">
        <?= view('partials/errors', ['errors' => $errors]) ?>

        <form class="form" method="post" action="<?= e(url('/mail/send/' . $receiver['nick'])) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="content">Nội dung</label>
                <textarea id="content" name="content" rows="6" required autofocus placeholder="Viết lời nhắn…"></textarea>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('mail', '', 15) ?> Gửi</button>
                <a class="btn btn--ghost" href="<?= e(url('/mail')) ?>">Huỷ</a>
            </div>
        </form>
    </div>
</section>
