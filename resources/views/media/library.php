<?php

/** @var list<array<string,mixed>> $files */

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('file', '', 16) ?>
        <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('media.library.title')) ?></h1>
    </div>
    <div class="panel__body">
        <form class="form" method="post" action="<?= e(url('/media/upload')) ?>" enctype="multipart/form-data" data-media-upload data-upload-success="<?= e(\SoulCMS\Support\Lang::get('media.library.upload_success')) ?>" data-upload-error="<?= e(\SoulCMS\Support\Lang::get('media.library.upload_error')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="document"><?= e(\SoulCMS\Support\Lang::get('media.library.upload_file')) ?></label>
                <input id="document" name="document" type="file" required>
                <span class="field__hint"><?= e(\SoulCMS\Support\Lang::get('media.library.upload_hint')) ?></span>
            </div>
            <button class="btn" type="submit"><?= icon('upload', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('media.library.upload')) ?></button>
            <output data-media-upload-result aria-live="polite"></output>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel__head"><h2 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('media.library.your_files')) ?></h2></div>
    <div class="panel__body">
        <?php if ($files === []): ?>
            <p class="muted"><?= e(\SoulCMS\Support\Lang::get('media.library.empty')) ?></p>
        <?php else: ?>
            <div class="media-grid">
                <?php foreach ($files as $file): ?>
                    <article class="media-item">
                        <?php if (($file['is_image'] ?? false) && is_string($file['preview_url'] ?? null)): ?>
                            <a href="<?= e($file['url']) ?>"><img src="<?= e($file['preview_url']) ?>" alt="<?= e($file['filename']) ?>" loading="lazy"></a>
                        <?php endif; ?>
                        <h3><?= e($file['filename']) ?></h3>
                        <p class="muted"><?= e(format_bytes((int) $file['filesize'])) ?> · <?= e(date('d/m/Y H:i', (int) $file['time'])) ?></p>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/media/download/' . (int) $file['id'] . '/' . rawurlencode((string) $file['filename']))) ?>"><?= e(\SoulCMS\Support\Lang::get('media.library.open_file')) ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<script nonce="<?= e(csp_nonce()) ?>">
document.querySelector('[data-media-upload]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const output = form.querySelector('[data-media-upload-result]');
    const data = new FormData(form);
    const response = await fetch(form.action, {method: 'POST', body: data, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
    const result = await response.json();
    output.textContent = result.status === 'success' ? form.dataset.uploadSuccess : (result.message || form.dataset.uploadError);
    if (result.status === 'success') window.location.reload();
});
</script>
<style>
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:var(--s-4)}
.media-item{min-width:0;border:1px solid var(--line);border-radius:var(--r-2);padding:var(--s-3)}
.media-item img{width:100%;height:140px;object-fit:cover;border-radius:var(--r-1)}
.media-item h3{overflow-wrap:anywhere;font-size:var(--step-0)}
</style>
