<?php

/**
 * The authentication shell — one card, no navigation.
 *
 * @var string      $content
 * @var string|null $page_title
 */

use SoulCMS\Support\Lang;

$siteName = (string) config('app.name', 'SoulCMS');
$currentTheme = theme();

?><!doctype html>
<html lang="<?= e(Lang::locale()) ?>" data-theme="<?= e($currentTheme) ?>"
      data-ui-theme-light="<?= e(Lang::get('ui.theme_light')) ?>" data-ui-theme-dark="<?= e(Lang::get('ui.theme_dark')) ?>"
      data-ui-switch-light="<?= e(Lang::get('ui.switch_light')) ?>" data-ui-switch-dark="<?= e(Lang::get('ui.switch_dark')) ?>"
      data-ui-drawer-open="<?= e(Lang::get('ui.drawer_open')) ?>" data-ui-drawer-close="<?= e(Lang::get('ui.drawer_close')) ?>"
      data-ui-copy-success="<?= e(Lang::get('ui.copy_success')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($page_title ?? Lang::get('auth.login.title')) ?> · <?= e($siteName) ?></title>
    <meta name="color-scheme" content="dark light">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= e(asset('assets/css/soulcms.css')) ?>">
    <link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>">
</head>
<body class="auth">

<header class="topbar">
    <a class="topbar__brand" href="<?= e(url('/')) ?>">
        <span class="topbar__mark" aria-hidden="true"><?= e(mb_substr($siteName, 0, 2)) ?></span>
        <span><?= e($siteName) ?></span>
    </a>

    <div class="topbar__actions">
        <button class="iconbtn" type="button" data-theme-toggle aria-label="<?= e(Lang::get('auth.shell.theme_toggle')) ?>">
            <?= icon($currentTheme === 'dark' ? 'sun' : 'moon') ?>
        </button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/')) ?>"><?= e(Lang::get('auth.shell.return_forum')) ?></a>
    </div>
</header>

<main class="auth__body" id="main" tabindex="-1">
    <?= $content ?>
</main>

<footer class="footer">
    <div>&copy; <?= date('Y') ?> <strong><?= e($siteName) ?></strong></div>
    <div><?= icon('lock', '', 14) ?> <?= e(Lang::get('auth.shell.security')) ?></div>
</footer>

<div class="toasts" data-toasts aria-live="polite"></div>

<script src="<?= e(asset('assets/js/soulcms.js')) ?>" nonce="<?= e(csp_nonce()) ?>" defer></script>
</body>
</html>
