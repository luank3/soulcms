<?php

/**
 * @var string $query
 * @var array  $results
 */

?>
<section class="panel">
    <div class="panel__head">
        <h1 class="panel__title"><?= e(\SoulCMS\Support\Lang::get('articles.search.title')) ?></h1>
        <?php if ($query !== ''): ?>
            <span class="panel__meta"><?= e(\SoulCMS\Support\Lang::get('articles.search.count', ['count' => (string) count($results)])) ?></span>
        <?php endif; ?>
    </div>

    <div class="panel__body">
        <form class="form form--tight" method="get" action="<?= e(url('/search')) ?>">
            <div class="field">
                <label for="q"><?= e(\SoulCMS\Support\Lang::get('articles.search.keyword')) ?></label>
                <input id="q" type="search" name="q" value="<?= e($query) ?>" placeholder="<?= e(\SoulCMS\Support\Lang::get('articles.search.placeholder')) ?>" autofocus>
            </div>
            <div class="form__actions">
                <button class="btn" type="submit"><?= icon('search', '', 15) ?> <?= e(\SoulCMS\Support\Lang::get('articles.search.submit')) ?></button>
            </div>
        </form>
    </div>

    <?php if ($query !== ''): ?>
        <div class="panel__body panel__body--flush">
            <?php if ($results === []): ?>
                <?= view('partials/empty', [
                    'title' => \SoulCMS\Support\Lang::get('articles.search.empty_title'),
                    'message' => \SoulCMS\Support\Lang::get('articles.search.empty_message'),
                    'icon' => 'search',
                ]) ?>
            <?php else: ?>
                <div class="feed">
                    <?php foreach ($results as $result): ?>
                        <div class="feed__row">
                            <span class="feed__gutter" <?= marker_style((string) ($result['source'] ?? '')) ?>></span>
                            <div>
                                <h3 class="feed__title">
                                    <a href="<?= e(url('/articles/' . (int) $result['id'] . '-' . ($result['slug'] ?? '') . '.html')) ?>">
                                        <?= e($result['title']) ?>
                                    </a>
                                </h3>
                                <p class="feed__meta">
                                    <span class="tagmark" <?= marker_style((string) ($result['source'] ?? '')) ?>>
                                        <?= e((string) ($result['source'] ?? '')) ?>
                                    </span>
                                    <span>@<?= e((string) $result['author']) ?></span>
                                    <span><?= e(date('d.m.Y', (int) $result['time'])) ?></span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
