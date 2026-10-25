<?php

/**
 * @var array $receiver
 * @var array $errors
 */

use SoulCMS\Support\Lang;

?>
<section class="panel" style="max-width:640px">
    <div class="panel__head">
        <span class="avatar"><?= e(mb_substr((string) $receiver['nick'], 0, 2)) ?></span>
        <h1 class="panel__title"><?= e(Lang::get('mail.compose.title', ['nick' => (string) $receiver['nick']])) ?></h1>
    </div>

    <div class="panel__body">
        <?= view('partials/errors', ['errors' => $errors]) ?>

        <form class="form" method="post" action="<?= e(url('/mail/send/' . $receiver['nick'])) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="content"><?= e(Lang::get('mail.compose.message')) ?></label>
                <textarea id="content" name="content" rows="6" required autofocus placeholder="<?= e(Lang::get('mail.compose.placeholder')) ?>"></textarea>
            </div>

            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('mail', '', 15) ?> <?= e(Lang::get('mail.compose.send')) ?></button>
                <a class="btn btn--ghost" href="<?= e(url('/mail')) ?>"><?= e(Lang::get('mail.compose.cancel')) ?></a>
            </div>
        </form>
    </div>
</section>
