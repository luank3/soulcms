<?php

/**
 * @var array<string,mixed> $profile
 * @var array<string,string> $errors
 */

use SoulCMS\Support\Lang;

?>
<section class="panel" style="max-width:44rem;margin-inline:auto">
    <div class="panel__head">
        <?= icon('user', '', 16) ?>
        <h1 class="panel__title"><?= e(Lang::get('user.edit.title')) ?></h1>
    </div>
    <div class="panel__body">
        <?php if ($errors !== []): ?>
            <div class="alert alert--error" role="alert">
                <div>
                    <p class="alert__title"><?= e(Lang::get('user.edit.error_title')) ?></p>
                    <ul style="margin:0;padding-left:var(--s-5)">
                        <?php foreach ($errors as $message): ?>
                            <li><?= e($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        <form class="form" method="post" action="<?= e(url('/user/' . $profile['nick'] . '/info')) ?>">
            <?= csrf_field() ?>
            <?= view('partials/field', [
                'name' => 'name',
                'label' => Lang::get('user.edit.display_name'),
                'value' => (string) ($profile['name'] ?? ''),
                'errors' => $errors,
                'required' => true,
                'attributes' => ['maxlength' => 255, 'autocomplete' => 'name'],
            ]) ?>
            <?= view('partials/field', [
                'name' => 'email',
                'label' => Lang::get('user.edit.email'),
                'type' => 'email',
                'value' => (string) ($profile['email'] ?? ''),
                'errors' => $errors,
                'required' => true,
                'attributes' => ['maxlength' => 255, 'autocomplete' => 'email'],
            ]) ?>
            <div class="field">
                <label for="sex"><?= e(Lang::get('user.edit.gender')) ?></label>
                <select id="sex" name="sex">
                    <option value="boy"<?= ($profile['sex'] ?? 'boy') === 'boy' ? ' selected' : '' ?>><?= e(Lang::get('user.edit.male')) ?></option>
                    <option value="girl"<?= ($profile['sex'] ?? '') === 'girl' ? ' selected' : '' ?>><?= e(Lang::get('user.edit.female')) ?></option>
                    <option value="other"<?= ($profile['sex'] ?? '') === 'other' ? ' selected' : '' ?>><?= e(Lang::get('user.edit.other')) ?></option>
                </select>
            </div>
            <div class="row" style="gap:var(--s-3)">
                <button class="btn" type="submit"><?= icon('check', '', 16) ?> <?= e(Lang::get('user.edit.save')) ?></button>
                <a class="btn btn--ghost" href="<?= e(url('/user/' . $profile['nick'])) ?>"><?= e(Lang::get('user.edit.cancel')) ?></a>
            </div>
        </form>
    </div>
</section>
