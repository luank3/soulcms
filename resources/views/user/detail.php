<?php

/**
 * @var array      $profile
 * @var array|null $waifu
 * @var array      $posts
 */

$role = member_role($profile);

?>
<div class="stack-lg">
    <section class="panel">
        <div class="panel__body">
            <div class="row" style="gap:var(--s-4);align-items:flex-start">
                <span class="avatar avatar--xl">
                    <img src="<?= e(avatar_svg($profile)) ?>" alt="" width="88" height="88">
                </span>
                <div class="grow">
                    <h1 style="margin-bottom:var(--s-2)"><?= e((string) ($profile['name'] ?: $profile['nick'])) ?></h1>
                    <p class="feed__meta">
                        <span>@<?= e($profile['nick']) ?></span>
                        <?= member_chip($profile) ?>
                        <span>Tham gia <?= e(date('d.m.Y', (int) $profile['reg'])) ?></span>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid grid--2">
        <section class="panel">
            <div class="panel__head">
                <?= icon('chart', '', 16) ?>
                <h2 class="panel__title">Thông tin</h2>
            </div>
            <div class="panel__body">
                <p class="feed__meta" style="gap:var(--s-5)">
                    <span>Vai trò: <strong class="role-<?= e($role['key']) ?>"><?= e($role['label']) ?></strong></span>
                    <span>Cấp: <strong><?= e((string) $role['level']) ?></strong></span>
                    <span>Karma: <strong><?= e((string) (int) ($profile['karma'] ?? 0)) ?></strong></span>
                    <span>Điểm: <strong><?= e((string) (int) ($profile['xu'] ?? 0)) ?></strong></span>
                </p>
                <?php if (!empty($profile['email'])): ?>
                    <p class="feed__meta" style="margin-top:var(--s-3)">
                        <span>E-mail: <?= e((string) $profile['email']) ?></span>
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <?= icon('file', '', 16) ?>
                <h2 class="panel__title">Bài viết</h2>
                <span class="panel__meta"><?= e((string) count($posts)) ?></span>
            </div>
            <div class="panel__body panel__body--flush">
                <?php if ($posts === []): ?>
                    <p class="muted" style="padding:var(--s-4);margin:0">Thành viên này chưa đăng bài nào.</p>
                <?php else: ?>
                    <div class="feed">
                        <?php foreach ($posts as $post): ?>
                            <div class="feed__row" style="grid-template-columns:3px minmax(0,1fr) auto">
                                <span class="feed__gutter" <?= marker_style((string) ($post['slug'] ?? '')) ?>></span>
                                <div>
                                    <h3 class="feed__title" style="font-size:var(--step-0)">
                                        <a href="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">
                                            <?= e($post['title']) ?>
                                        </a>
                                    </h3>
                                    <p class="feed__meta"><span><?= e(time_ago((int) $post['update_time'])) ?></span></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
