<?php

/**
 * The administration shell: a workshop, not a dashboard of cards.
 *
 * @var string      $content
 * @var string|null $page_title
 */

use SoulCMS\Auth\AuthManager;

$auth = auth();
$viewer = $auth->user();
$siteName = (string) config('app.name', 'SoulCMS');
$currentTheme = theme();

$groups = [
    'Nội dung' => [
        ['/manager', 'chart', 'Tổng quan'],
        ['/manager/articles', 'file', 'Bài viết'],
    ],
    'Cộng đồng' => [
        ['/manager/users', 'users', 'Thành viên'],
    ],
    'Hệ thống' => [
        ['/manager/modules', 'layers', 'Module'],
        ['/manager/settings', 'settings', 'Cài đặt'],
        ['/manager/url-rules', 'link', 'Đường dẫn'],
    ],
];

?><!doctype html>
<html lang="<?= e(config('app.locale', 'vi')) ?>" data-theme="<?= e($currentTheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($page_title ?? 'Quản trị') ?> · <?= e($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="dark light">
    <link rel="stylesheet" href="<?= e(asset('assets/css/soulcms.css')) ?>">
    <link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Bỏ qua điều hướng</a>

<div class="shell">

    <header class="topbar">
        <button class="iconbtn drawer-toggle" type="button" data-drawer-toggle aria-controls="admin-navigation" aria-expanded="false" aria-label="Mở menu">
            <?= icon('menu') ?>
        </button>

        <a class="topbar__brand" href="<?= e(url('/manager')) ?>">
            <span class="topbar__mark" aria-hidden="true"><?= icon('settings', '', 15) ?></span>
            <span>Quản trị</span>
        </a>

        <div class="topbar__actions">
            <a class="iconbtn" href="<?= e(url('/')) ?>" aria-label="Về diễn đàn">
                <?= icon('home') ?>
            </a>
            <button class="iconbtn" type="button" data-theme-toggle aria-label="Đổi chế độ sáng tối">
                <?= icon($currentTheme === 'dark' ? 'sun' : 'moon') ?>
            </button>
            <button class="iconbtn" type="button" data-menu-toggle aria-controls="account-menu" aria-expanded="false" aria-label="Tài khoản">
                <span class="avatar"><img src="<?= e(avatar_svg($viewer->toArray())) ?>" alt="" width="34" height="34"></span>
            </button>

            <div class="menu" id="account-menu" data-menu hidden>
                <div class="menu__head">
                    <div class="strong"><?= e($viewer->name() !== '' ? $viewer->name() : $viewer->nick()) ?></div>
                    <div class="mono muted">@<?= e($viewer->nick()) ?></div>
                </div>
                <a class="menu__item" href="<?= e(url('/user/' . $viewer->nick())) ?>"><?= icon('user') ?> Hồ sơ</a>
                <a class="menu__item" href="<?= e(url('/')) ?>"><?= icon('forum') ?> Diễn đàn</a>
                <div class="menu__sep"></div>
                <a class="menu__item menu__item--danger" href="<?= e(url('/logout')) ?>"><?= icon('logout') ?> Đăng xuất</a>
            </div>
        </div>
    </header>

    <aside class="rail" id="admin-navigation" aria-label="Điều hướng quản trị">
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
                <p class="admin__group"><?= e($group) ?></p>
                <?php foreach ($items as [$path, $icon, $label]): ?>
                    <a href="<?= e(url($path)) ?>" <?= is_active($path) ? 'aria-current="page"' : '' ?>>
                        <?= icon($icon) ?> <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <button class="btn btn--ghost btn--block drawer-close mt-5" type="button" data-drawer-close>
            <?= icon('chevron-left') ?> Đóng menu
        </button>
    </aside>

    <div class="shell__main">
        <main class="shell__body" id="main" tabindex="-1">
            <div class="row row--between" style="margin-bottom:var(--s-5)">
                <h1 style="margin:0"><?= e($page_title ?? 'Tổng quan') ?></h1>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/')) ?>"><?= icon('home', '', 15) ?> Về diễn đàn</a>
            </div>

            <div class="stack"><?= $content ?></div>
        </main>

        <footer class="footer">
            <div>&copy; <?= date('Y') ?> <strong><?= e($siteName) ?></strong> · Bảng quản trị</div>
            <div class="mono">PHP <?= e(PHP_VERSION) ?></div>
        </footer>
    </div>
</div>

<button class="drawer-scrim" type="button" data-drawer-scrim hidden aria-label="Đóng menu"></button>

<div class="toasts" data-toasts aria-live="polite"></div>

<script src="<?= e(asset('assets/js/soulcms.js')) ?>" nonce="<?= e(csp_nonce()) ?>" defer></script>
</body>
</html>
