<?php

/**
 * The administration shell: a workshop, not a dashboard of cards.
 *
 * @var string      $content
 * @var string|null $page_title
 */

use SoulCMS\Auth\AuthManager;
use SoulCMS\Support\Lang;

$auth = auth();
$viewer = $auth->user();
$siteName = (string) config('app.name', 'SoulCMS');
$currentTheme = theme();

$groups = [
    'admin.nav.content' => [
        ['/manager', 'chart', 'admin.nav.dashboard'],
        ['/manager/articles', 'file', 'admin.nav.articles'],
        ['/manager/static_pages', 'file', 'admin.nav.static_pages'],
    ],
    'admin.nav.community' => [
        ['/manager/users', 'users', 'admin.nav.members'],
    ],
    'admin.nav.system' => [
        ['/manager/modules', 'layers', 'admin.nav.modules'],
        ['/manager/settings', 'settings', 'admin.nav.settings'],
        ['/manager/url-rules', 'link', 'admin.nav.url_rules'],
    ],
];

?><!doctype html>
<html lang="<?= e(Lang::locale()) ?>" data-theme="<?= e($currentTheme) ?>"
      data-ui-theme-light="<?= e(Lang::get('ui.theme_light')) ?>" data-ui-theme-dark="<?= e(Lang::get('ui.theme_dark')) ?>"
      data-ui-switch-light="<?= e(Lang::get('ui.switch_light')) ?>" data-ui-switch-dark="<?= e(Lang::get('ui.switch_dark')) ?>"
      data-ui-drawer-open="<?= e(Lang::get('ui.drawer_open')) ?>" data-ui-drawer-close="<?= e(Lang::get('ui.drawer_close')) ?>"
      data-ui-copy-success="<?= e(Lang::get('ui.copy_success')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($page_title ?? Lang::get('admin.title')) ?> · <?= e($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="dark light">
    <link rel="stylesheet" href="<?= e(asset('assets/css/soulcms.css')) ?>">
    <link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>">
</head>
<body>
<a class="skip-link" href="#main"><?= e(Lang::get('shell.skip_navigation')) ?></a>

<div class="shell">

    <header class="topbar">
        <button class="iconbtn drawer-toggle" type="button" data-drawer-toggle aria-controls="admin-navigation" aria-expanded="false" aria-label="<?= e(Lang::get('shell.menu_open')) ?>">
            <?= icon('menu') ?>
        </button>

        <a class="topbar__brand" href="<?= e(url('/manager')) ?>">
            <span class="topbar__mark" aria-hidden="true"><?= icon('settings', '', 15) ?></span>
            <span><?= e(Lang::get('admin.title')) ?></span>
        </a>

        <div class="topbar__actions">
            <a class="iconbtn" href="<?= e(url('/')) ?>" aria-label="<?= e(Lang::get('admin.back_forum')) ?>">
                <?= icon('home') ?>
            </a>
            <button class="iconbtn" type="button" data-theme-toggle aria-label="<?= e(Lang::get('shell.theme_toggle')) ?>">
                <?= icon($currentTheme === 'dark' ? 'sun' : 'moon') ?>
            </button>
            <button class="iconbtn" type="button" data-menu-toggle aria-controls="account-menu" aria-expanded="false" aria-label="<?= e(Lang::get('shell.account')) ?>">
                <span class="avatar"><img src="<?= e(avatar_svg($viewer->toArray())) ?>" alt="" width="34" height="34"></span>
            </button>

            <div class="menu" id="account-menu" data-menu hidden>
                <div class="menu__head">
                    <div class="strong"><?= e($viewer->name() !== '' ? $viewer->name() : $viewer->nick()) ?></div>
                    <div class="mono muted">@<?= e($viewer->nick()) ?></div>
                </div>
                <a class="menu__item" href="<?= e(url('/user/' . $viewer->nick())) ?>"><?= icon('user') ?> <?= e(Lang::get('shell.profile')) ?></a>
                <a class="menu__item" href="<?= e(url('/')) ?>"><?= icon('forum') ?> <?= e(Lang::get('shell.forum')) ?></a>
                <div class="menu__sep"></div>
                <a class="menu__item menu__item--danger" href="<?= e(url('/logout')) ?>"><?= icon('logout') ?> <?= e(Lang::get('shell.logout')) ?></a>
            </div>
        </div>
    </header>

    <aside class="rail" id="admin-navigation" aria-label="<?= e(Lang::get('admin.navigation')) ?>">
        <div class="membercard">
            <span class="avatar"><img src="<?= e(avatar_svg($viewer->toArray())) ?>" alt="" width="34" height="34"></span>
            <div class="membercard__body">
                <div class="membercard__name"><?= e($viewer->name() !== '' ? $viewer->name() : $viewer->nick()) ?></div>
                <div class="membercard__role role-<?= e(member_role($viewer->toArray())['key']) ?>">
                    <?= e(member_role($viewer->toArray())['label']) ?>
                </div>
            </div>
        </div>

        <nav class="admin__nav">
            <?php foreach ($groups as $group => $items): ?>
                <p class="admin__group"><?= e(Lang::get($group)) ?></p>
                <?php foreach ($items as [$path, $icon, $label]): ?>
                    <a href="<?= e(url($path)) ?>" <?= is_active($path) ? 'aria-current="page"' : '' ?>>
                        <?= icon($icon) ?> <?= e(Lang::get($label)) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <button class="btn btn--ghost btn--block drawer-close mt-5" type="button" data-drawer-close>
            <?= icon('chevron-left') ?> <?= e(Lang::get('shell.menu_close')) ?>
        </button>
    </aside>

    <div class="shell__main">
        <main class="shell__body" id="main" tabindex="-1">
            <div class="row row--between" style="margin-bottom:var(--s-5)">
                <h1 style="margin:0"><?= e($page_title ?? Lang::get('admin.nav.dashboard')) ?></h1>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/')) ?>"><?= icon('home', '', 15) ?> <?= e(Lang::get('admin.back_forum')) ?></a>
            </div>

            <div class="stack"><?= $content ?></div>
        </main>

        <footer class="footer">
            <div>&copy; <?= date('Y') ?> <strong><?= e($siteName) ?></strong> · <?= e(Lang::get('admin.footer')) ?></div>
            <div class="mono">PHP <?= e(PHP_VERSION) ?></div>
        </footer>
    </div>
</div>

<button class="drawer-scrim" type="button" data-drawer-scrim hidden aria-label="<?= e(Lang::get('shell.menu_close')) ?>"></button>

<div class="toasts" data-toasts aria-live="polite"></div>

<script src="<?= e(asset('assets/js/soulcms.js')) ?>" nonce="<?= e(csp_nonce()) ?>" defer></script>
</body>
</html>
