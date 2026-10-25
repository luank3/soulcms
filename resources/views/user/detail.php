<?php

/**
 * @var array      $profile
 * @var array|null $waifu
 * @var array      $posts
 * @var array{posts:int,comments:int} $stats
 * @var array{level:int,key:string,label:string,score:int} $reputation
 * @var bool $is_owner
 * @var bool $is_online
 * @var array{header:?string,rleft:?string,rright:?string,close:bool} $waifu_decoration
 */

use SoulCMS\Support\Lang;

$role = member_role($profile);

?>
<div class="stack-lg">
    <section class="panel profile-hero">
        <?php if (($waifu_decoration['header'] ?? null) !== null): ?>
            <img class="profile-hero__header" src="<?= e(asset((string) $waifu_decoration['header'])) ?>" alt="" aria-hidden="true">
        <?php endif; ?>
        <?php if (($waifu_decoration['rleft'] ?? null) !== null): ?>
            <img class="profile-hero__side profile-hero__side--left" src="<?= e(asset((string) $waifu_decoration['rleft'])) ?>" alt="" aria-hidden="true">
        <?php endif; ?>
        <?php if (($waifu_decoration['rright'] ?? null) !== null): ?>
            <img class="profile-hero__side profile-hero__side--right" src="<?= e(asset((string) $waifu_decoration['rright'])) ?>" alt="" aria-hidden="true">
        <?php endif; ?>
        <div class="panel__body profile-hero__content">
            <div class="row" style="gap:var(--s-4);align-items:flex-start">
                <span class="avatar avatar--xl">
                    <img src="<?= e(avatar_svg($profile)) ?>" alt="" width="88" height="88">
                </span>
                <div class="grow">
                    <h1 style="margin-bottom:var(--s-2)"><?= e((string) ($profile['name'] ?: $profile['nick'])) ?></h1>
                    <p class="feed__meta">
                        <span>@<?= e($profile['nick']) ?></span>
                        <span class="feed__stat" style="color:var(--<?= $is_online ? 'live' : 'ink-3' ?>)">
                            <?= $is_online ? '● ' . e(Lang::get('user.profile.online')) : '○ ' . e(Lang::get('user.profile.offline')) ?>
                        </span>
                        <?= member_chip($profile) ?>
                        <span><?= e(Lang::get('user.profile.joined')) ?> <?= e(date('d.m.Y', (int) $profile['reg'])) ?></span>
                        <?php if ($is_owner): ?>
                            <a class="btn btn--sm btn--ghost" href="<?= e(url('/user/' . $profile['nick'] . '/info')) ?>"><?= e(Lang::get('user.profile.edit')) ?></a>
                            <a class="btn btn--sm btn--ghost" href="<?= e(url('/user/' . $profile['nick'] . '/blocklist')) ?>"><?= e(Lang::get('user.profile.blocklist')) ?></a>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (($waifu_decoration['close'] ?? false) && $is_owner): ?>
                    <form method="post" action="<?= e(url('/user/' . $profile['nick'] . '/waifu')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="open">
                        <button class="btn btn--sm btn--ghost" type="submit"><?= e(Lang::get('user.profile.show_decorations')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($is_owner && !($waifu_decoration['close'] ?? false)): ?>
        <form method="post" action="<?= e(url('/user/' . $profile['nick'] . '/waifu')) ?>" class="row">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="close">
            <button class="btn btn--ghost btn--sm" type="submit"><?= e(Lang::get('user.profile.hide_decorations')) ?></button>
        </form>
    <?php endif; ?>

    <div class="grid grid--2">
        <section class="panel">
            <div class="panel__head">
                <?= icon('chart', '', 16) ?>
                <h2 class="panel__title"><?= e(Lang::get('user.profile.information')) ?></h2>
            </div>
            <div class="panel__body">
                <p class="feed__meta" style="gap:var(--s-5)">
                    <span><?= e(Lang::get('user.profile.role')) ?>: <strong class="role-<?= e($role['key']) ?>"><?= e($role['label']) ?></strong></span>
                    <span><?= e(Lang::get('user.profile.level')) ?>: <strong><?= e((string) $role['level']) ?></strong></span>
                    <span><?= e(Lang::get('user.profile.karma')) ?>: <strong><?= e((string) (int) ($profile['karma'] ?? 0)) ?></strong></span>
                    <span><?= e(Lang::get('user.profile.points')) ?>: <strong><?= e((string) (int) ($profile['xu'] ?? 0)) ?></strong></span>
                </p>
                <p class="feed__meta" style="margin-top:var(--s-3);gap:var(--s-5)">
                    <span><?= e(Lang::get('user.profile.reputation')) ?>: <strong class="role-<?= e($reputation['key']) ?>"><?= e($reputation['label']) ?></strong></span>
                    <span><?= e(Lang::get('user.profile.reputation_score')) ?>: <strong><?= e((string) $reputation['score']) ?></strong></span>
                </p>
                <p class="feed__meta" style="margin-top:var(--s-3);gap:var(--s-5)">
                    <span><?= e(Lang::get('user.profile.posts')) ?>: <strong><?= e((string) $stats['posts']) ?></strong></span>
                    <span><?= e(Lang::get('user.profile.comments')) ?>: <strong><?= e((string) $stats['comments']) ?></strong></span>
                </p>
                <?php if (!empty($profile['email'])): ?>
                    <p class="feed__meta" style="margin-top:var(--s-3)">
                        <span><?= e(Lang::get('user.profile.email')) ?>: <?= e((string) $profile['email']) ?></span>
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <?= icon('file', '', 16) ?>
                <h2 class="panel__title"><?= e(Lang::get('user.profile.posts')) ?></h2>
                <span class="panel__meta"><?= e((string) count($posts)) ?></span>
            </div>
            <div class="panel__body panel__body--flush">
                <?php if ($posts === []): ?>
                    <p class="muted" style="padding:var(--s-4);margin:0"><?= e(Lang::get('user.profile.no_posts')) ?></p>
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
