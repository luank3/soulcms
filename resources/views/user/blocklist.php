<?php

/**
 * @var array<string,mixed> $profile
 * @var list<array{nick:string,name:string}> $blocked_users
 * @var array<string,string> $errors
 */

use SoulCMS\Support\Lang;

?>
<section class="panel" style="max-width:44rem;margin-inline:auto">
    <div class="panel__head">
        <?= icon('users', '', 16) ?>
        <h1 class="panel__title"><?= e(Lang::get('user.blocklist.title')) ?></h1>
    </div>
    <div class="panel__body">
        <?php if ($errors !== []): ?>
            <div class="alert alert--error" role="alert">
                <div>
                    <p class="alert__title"><?= e(Lang::get('user.blocklist.error_title')) ?></p>
                    <ul style="margin:0;padding-left:var(--s-5)">
                        <?php foreach ($errors as $message): ?>
                            <li><?= e($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        <form class="form" method="post" action="<?= e(url('/user/' . $profile['nick'] . '/blocklist')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="block">
            <div class="field">
                <label for="nick"><?= e(Lang::get('user.blocklist.member_name')) ?></label>
                <input id="nick" name="nick" type="text" minlength="3" maxlength="32" pattern="[A-Za-z0-9_-]+" required autocomplete="off">
            </div>
            <button class="btn" type="submit"><?= icon('user', '', 16) ?> <?= e(Lang::get('user.blocklist.block')) ?></button>
        </form>
    </div>
    <div class="panel__body panel__body--flush">
        <?php if ($blocked_users === []): ?>
            <p class="muted" style="padding:var(--s-4);margin:0"><?= e(Lang::get('user.blocklist.empty')) ?></p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($blocked_users as $blocked): ?>
                    <li class="list__item">
                        <div class="row" style="justify-content:space-between;gap:var(--s-3)">
                            <a href="<?= e(url('/user/' . $blocked['nick'])) ?>">
                                <?= e($blocked['name']) ?> <span class="muted">@<?= e($blocked['nick']) ?></span>
                            </a>
                            <form method="post" action="<?= e(url('/user/' . $profile['nick'] . '/blocklist')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unblock">
                                <input type="hidden" name="nick" value="<?= e($blocked['nick']) ?>">
                                <button class="btn btn--sm btn--ghost" type="submit"><?= e(Lang::get('user.blocklist.unblock')) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="panel__body">
        <a class="btn btn--ghost" href="<?= e(url('/user/' . $profile['nick'])) ?>"><?= e(Lang::get('user.blocklist.back_profile')) ?></a>
    </div>
</section>
