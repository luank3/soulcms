<?php

/**
 * URL rules: rewrite a public path onto another path, or block it entirely.
 *
 * @var array<string,array<string,mixed>> $rewrite
 * @var list<string>                       $disabled
 */

$rules = $rewrite ?? [];
$blocked = $disabled ?? [];

?>
<div class="stack-lg">

    <section class="panel">
        <div class="panel__head">
            <?= icon('link', '', 16) ?>
            <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.title')) ?></h2>
            <span class="panel__meta"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.count', ['count' => (string) count($rules)])) ?></span>
        </div>

        <div class="panel__body panel__body--flush">
            <?php if ($rules === []): ?>
                <?= view('partials/empty', [
                    'title' => \SoulCMS\Support\Lang::get('manager.url_rules.empty_title'),
                    'message' => \SoulCMS\Support\Lang::get('manager.url_rules.empty_message'),
                    'icon' => 'link',
                ]) ?>
            <?php else: ?>
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.path')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.target')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.method')) ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rules as $from => $rule): ?>
                            <tr>
                                <td class="mono"><?= e($from) ?></td>
                                <td class="mono muted"><?= e((string) ($rule['target'] ?? '')) ?></td>
                                <td class="mono muted"><?= e((string) ($rule['method'] ?? 'GET|POST')) ?></td>
                                <td class="table__actions">
                                    <form class="inline-form" method="post" action="<?= e(url('/manager/url-rules/edit')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="original" value="<?= e($from) ?>">
                                        <input type="hidden" name="from" value="<?= e($from) ?>">
                                        <input type="hidden" name="to" value="<?= e(ltrim((string) ($rule['target'] ?? ''), '/')) ?>">
                                        <input type="hidden" name="method" value="<?= e((string) ($rule['method'] ?? 'GET|POST')) ?>">
                                        <button class="btn btn--quiet btn--sm" type="submit"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.edit')) ?></button>
                                    </form>
                                    <form class="inline-form" method="post" action="<?= e(url('/manager/url-rules/delete')) ?>"
                                          data-confirm="<?= e(\SoulCMS\Support\Lang::get('manager.url_rules.delete_confirm')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="from" value="<?= e($from) ?>">
                                        <button class="btn btn--danger btn--sm" type="submit">
                                            <?= icon('trash', '', 14) ?> <?= e(\SoulCMS\Support\Lang::get('manager.static.delete')) ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <?= icon('plus', '', 16) ?>
            <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.add')) ?></h2>
        </div>

        <div class="panel__body">
            <form class="form" method="post" action="<?= e(url('/manager/url-rules')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="mode"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.type')) ?></label>
                    <select id="mode" name="mode">
                        <option value="rewrite"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.rewrite')) ?></option>
                        <option value="disable"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.disable')) ?></option>
                    </select>
                </div>

                <div class="field">
                    <label for="from"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.public_path')) ?></label>
                    <input id="from" name="from" placeholder="/news" required>
                    <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.public_hint')) ?></span>
                </div>

                <div class="field">
                    <label for="to"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.target_path')) ?></label>
                    <input id="to" name="to" placeholder="/articles?category=2">
                    <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.target_hint')) ?></span>
                </div>

                <div class="field">
                    <label for="method"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.method')) ?></label>
                    <select id="method" name="method">
                        <option value="GET|POST"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.get_post')) ?></option>
                        <option value="GET"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.get')) ?></option>
                        <option value="POST"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.post')) ?></option>
                        <option value="*"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.any')) ?></option>
                    </select>
                </div>

                <div class="form__actions">
                    <button class="btn" type="submit"><?= icon('check', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('manager.url_rules.save')) ?></button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($blocked !== []): ?>
        <section class="panel">
            <div class="panel__head">
                <?= icon('lock', '', 16) ?>
                <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.blocked')) ?></h2>
                <span class="panel__meta"><?= e((string) count($blocked)) ?></span>
            </div>
            <div class="panel__body panel__body--flush">
                <div class="tablewrap">
                    <table class="table">
                        <thead><tr><th><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.path')) ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($blocked as $path): ?>
                            <tr>
                                <td class="mono"><?= e($path) ?></td>
                                <td class="table__actions">
                                    <form class="inline-form" method="post" action="<?= e(url('/manager/url-rules/delete')) ?>"
                                          data-confirm="<?= e(\SoulCMS\Support\Lang::get('manager.url_rules.unblock_confirm')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="from" value="<?= e($path) ?>">
                                        <button class="btn btn--ghost btn--sm" type="submit"><?= e(\SoulCMS\Support\Lang::get('manager.url_rules.unblock')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
