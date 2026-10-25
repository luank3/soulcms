<?php

/**
 * @var list<array{slug:string,title:string,content:string,description:string,keyword:string}> $pages
 * @var array{slug:string,title:string,content:string,description:string,keyword:string}|null $page
 */

$editing = $page !== null;
$values = $page ?? ['slug' => '', 'title' => '', 'content' => '', 'description' => '', 'keyword' => ''];
?>
<section class="panel">
    <div class="panel__head">
        <?= icon('file', '', 16) ?>
        <h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('manager.static.title')) ?></h2>
    </div>
    <div class="panel__body">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(\SoulCMS\Support\Lang::get('manager.static.page_title')) ?></th><th><?= e(\SoulCMS\Support\Lang::get('manager.static.path')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pages as $item): ?>
                    <tr>
                        <td><?= e($item['title']) ?></td>
                        <td><a href="<?= e(url('/' . $item['slug'] . '.html')) ?>">/<?= e($item['slug']) ?>.html</a></td>
                        <td class="row">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/manager/static_pages?edit=' . rawurlencode($item['slug']))) ?>"><?= e(\SoulCMS\Support\Lang::get('manager.static.edit')) ?></a>
                            <form method="post" action="<?= e(url('/manager/static_pages/delete')) ?>" data-confirm="<?= e(\SoulCMS\Support\Lang::get('manager.static.delete_confirm')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                <button class="btn btn--danger btn--sm" type="submit"><?= e(\SoulCMS\Support\Lang::get('manager.static.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pages === []): ?><tr><td colspan="3" class="muted"><?= e(\SoulCMS\Support\Lang::get('manager.static.empty')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <form class="form mt-5" method="post" action="<?= e(url('/manager/static_pages')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="current_slug" value="<?= e($editing ? $values['slug'] : '') ?>">
            <h3><?= e(\SoulCMS\Support\Lang::get($editing ? 'manager.static.edit_heading' : 'manager.static.add_heading')) ?></h3>
            <div class="field">
                <label for="page_title"><?= e(\SoulCMS\Support\Lang::get('manager.static.page_title')) ?></label>
                <input id="page_title" name="title" required maxlength="160" value="<?= e($values['title']) ?>">
            </div>
            <div class="field">
                <label for="page_slug"><?= e(\SoulCMS\Support\Lang::get('manager.static.slug')) ?></label>
                <input id="page_slug" name="slug" required maxlength="80" pattern="[a-z0-9][a-z0-9-]*" value="<?= e($values['slug']) ?>">
                <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('manager.static.slug_hint')) ?></span>
            </div>
            <div class="field">
                <label for="page_content"><?= e(\SoulCMS\Support\Lang::get('articles.publish.content')) ?></label>
                <textarea id="page_content" name="content" rows="12"><?= e($values['content']) ?></textarea>
                <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('manager.static.content_hint')) ?></span>
            </div>
            <div class="field">
                <label for="page_description"><?= e(\SoulCMS\Support\Lang::get('manager.static.seo_description')) ?></label>
                <textarea id="page_description" name="description" rows="2" maxlength="300"><?= e($values['description']) ?></textarea>
            </div>
            <div class="field">
                <label for="page_keyword"><?= e(\SoulCMS\Support\Lang::get('manager.static.seo_keywords')) ?></label>
                <input id="page_keyword" name="keyword" maxlength="300" value="<?= e($values['keyword']) ?>">
            </div>
            <div class="form__actions"><button class="btn" type="submit"><?= icon('check', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('manager.static.save')) ?></button></div>
        </form>
    </div>
</section>
