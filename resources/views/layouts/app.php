<?php

/**
 * The public application shell.
 *
 * @var string       $content
 * @var string|null  $page_title
 * @var string|null  $page_description
 * @var string|null  $page_keyword
 */

use SoulCMS\Auth\AuthManager;
use SoulCMS\Support\Lang;

$auth = auth();
$viewer = $auth->user();
$siteName = (string) config('app.name', 'SoulCMS');
$currentTheme = theme();
$newMail = 0;
$systemNotices = 0;

$role = $viewer->isAuthenticated ? member_role($viewer->toArray()) : null;

if ($viewer->isAuthenticated) {
    $newMail = $auth->newMailCount($viewer->id());
    $systemNotices = $viewer->get('new_mail') !== null
        ? count(array_filter(explode('.', (string) $viewer->get('new_mail')), static fn (string $item): bool => trim($item) !== ''))
        : 0;
}

?><!doctype html>
<html lang="<?= e(Lang::locale()) ?>" data-theme="<?= e($currentTheme) ?>"
      data-ui-theme-light="<?= e(Lang::get('ui.theme_light')) ?>" data-ui-theme-dark="<?= e(Lang::get('ui.theme_dark')) ?>"
      data-ui-switch-light="<?= e(Lang::get('ui.switch_light')) ?>" data-ui-switch-dark="<?= e(Lang::get('ui.switch_dark')) ?>"
      data-ui-drawer-open="<?= e(Lang::get('ui.drawer_open')) ?>" data-ui-drawer-close="<?= e(Lang::get('ui.drawer_close')) ?>"
      data-ui-copy-success="<?= e(Lang::get('ui.copy_success')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($page_title ?? $siteName) ?> · <?= e($siteName) ?></title>
    <meta name="description" content="<?= e($page_description ?? config('app.description', '')) ?>">
    <meta name="keywords" content="<?= e($page_keyword ?? config('app.keyword', '')) ?>">
    <meta name="color-scheme" content="dark light">
    <meta property="og:title" content="<?= e($page_title ?? $siteName) ?>">
    <meta property="og:description" content="<?= e($page_description ?? config('app.description', '')) ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="<?= e(asset('assets/css/soulcms.css')) ?>">
    <link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>">
</head>
<body>
<a class="skip-link" href="#main"><?= e(Lang::get('shell.skip_navigation')) ?></a>

<div class="shell">

    <header class="topbar">
        <button class="iconbtn drawer-toggle" type="button" data-drawer-toggle aria-controls="primary-navigation" aria-expanded="false" aria-label="<?= e(Lang::get('shell.menu_open')) ?>">
            <?= icon('menu') ?>
        </button>

        <a class="topbar__brand" href="<?= e(url('/')) ?>">
            <span class="topbar__mark" aria-hidden="true"><?= e(mb_substr($siteName, 0, 2)) ?></span>
            <span><?= e($siteName) ?></span>
        </a>

        <form class="topbar__search topbar__search--collapsible" id="global-search" action="<?= e(url('/search')) ?>" method="get" role="search">
            <?= icon('search') ?>
            <label class="sr-only" for="q"><?= e(Lang::get('shell.search_articles')) ?></label>
            <input id="q" type="search" name="q" placeholder="<?= e(Lang::get('shell.search_articles')) ?>…" value="<?= e((string) ($_GET['q'] ?? '')) ?>" autocomplete="off">
        </form>

        <div class="topbar__actions">
            <button class="iconbtn search-toggle" type="button" data-search-toggle aria-controls="global-search" aria-expanded="false" aria-label="<?= e(Lang::get('shell.search')) ?>">
                <?= icon('search') ?>
            </button>

            <?php if ($viewer->isAuthenticated): ?>
                <a class="iconbtn" href="<?= e(url('/mail')) ?>" aria-label="<?= e(Lang::get('shell.messages')) ?>">
                    <?= icon('mail') ?>
                    <?php if ($newMail > 0): ?>
                        <span class="iconbtn__count"><?= e((string) min($newMail, 99)) ?></span>
                    <?php endif; ?>
                </a>

                <a class="iconbtn" href="<?= e(url('/mail/system')) ?>" aria-label="<?= e(Lang::get('shell.notifications')) ?>">
                    <?= icon('bell') ?>
                    <?php if ($systemNotices > 0): ?>
                        <span class="iconbtn__dot"></span>
                    <?php endif; ?>
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
                        <div class="mt-4"><?= member_chip($viewer->toArray()) ?></div>
                    </div>
                    <a class="menu__item" href="<?= e(url('/user/' . $viewer->nick())) ?>"><?= icon('user') ?> <?= e(Lang::get('shell.profile')) ?></a>
                    <a class="menu__item" href="<?= e(url('/users')) ?>"><?= icon('users') ?> <?= e(Lang::get('shell.members')) ?></a>
                    <?php if ($viewer->isStaff()): ?>
                        <a class="menu__item" href="<?= e(url('/manager')) ?>"><?= icon('settings') ?> <?= e(Lang::get('shell.admin')) ?></a>
                    <?php endif; ?>
                    <div class="menu__sep"></div>
                    <a class="menu__item menu__item--danger" href="<?= e(url('/logout')) ?>"><?= icon('logout') ?> <?= e(Lang::get('shell.logout')) ?></a>
                </div>
            <?php else: ?>
                <button class="iconbtn" type="button" data-theme-toggle aria-label="<?= e(Lang::get('shell.theme_toggle')) ?>">
                    <?= icon($currentTheme === 'dark' ? 'sun' : 'moon') ?>
                </button>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/login')) ?>"><?= e(Lang::get('shell.login')) ?></a>
                <a class="btn btn--sm" href="<?= e(url('/register')) ?>"><?= e(Lang::get('shell.register')) ?></a>
            <?php endif; ?>
        </div>
    </header>

    <aside class="rail" id="primary-navigation" aria-label="<?= e(Lang::get('shell.navigation')) ?>" aria-hidden="true" inert>
        <?php if ($viewer->isAuthenticated): ?>
            <div class="membercard">
                <span class="avatar"><img src="<?= e(avatar_svg($viewer->toArray())) ?>" alt="" width="34" height="34"></span>
                <div class="membercard__body">
                    <div class="membercard__name"><?= e($viewer->name() !== '' ? $viewer->name() : $viewer->nick()) ?></div>
                    <div class="membercard__role role-<?= e($role['key']) ?>">
                        <?= e($role['label']) ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row" style="margin-bottom:var(--s-4)">
                <a class="btn btn--sm grow" href="<?= e(url('/register')) ?>"><?= e(Lang::get('shell.register')) ?></a>
                <a class="btn btn--ghost btn--sm grow" href="<?= e(url('/login')) ?>"><?= e(Lang::get('shell.login')) ?></a>
            </div>
        <?php endif; ?>

        <nav class="rail__section">
            <p class="rail__heading"><?= e(Lang::get('shell.forum')) ?></p>
            <a class="rail__link" href="<?= e(url('/')) ?>" <?= is_active('/') ? 'aria-current="page"' : '' ?>>
                <span class="rail__icon"><?= icon('home') ?></span> <?= e(Lang::get('shell.forum')) ?>
            </a>
            <a class="rail__link" href="<?= e(url('/articles')) ?>" <?= is_active('/articles') ? 'aria-current="page"' : '' ?>>
                <span class="rail__icon"><?= icon('file') ?></span> <?= e(Lang::get('shell.articles')) ?>
            </a>
            <a class="rail__link" href="<?= e(url('/shoutbox')) ?>" <?= is_active('/shoutbox') ? 'aria-current="page"' : '' ?>>
                <span class="rail__icon"><?= icon('chat') ?></span> <?= e(Lang::get('shell.chat')) ?>
            </a>
            <a class="rail__link" href="<?= e(url('/search')) ?>" <?= is_active('/search') ? 'aria-current="page"' : '' ?>>
                <span class="rail__icon"><?= icon('search') ?></span> <?= e(Lang::get('shell.search')) ?>
            </a>
            <a class="rail__link" href="<?= e(url('/users')) ?>" <?= is_active('/users') ? 'aria-current="page"' : '' ?>>
                <span class="rail__icon"><?= icon('users') ?></span> <?= e(Lang::get('shell.members')) ?>
            </a>
            <?php if ($viewer->isAuthenticated): ?>
                <a class="rail__link" href="<?= e(url('/media/library')) ?>" <?= is_active('/media/library') ? 'aria-current="page"' : '' ?>>
                    <span class="rail__icon"><?= icon('file') ?></span> <?= e(Lang::get('shell.media_library')) ?>
                </a>
            <?php endif; ?>
        </nav>

        <?php if ($viewer->isAuthenticated): ?>
            <nav class="rail__section">
                <p class="rail__heading"><?= e(Lang::get('shell.personal')) ?></p>
                <a class="rail__link" href="<?= e(url('/user/' . $viewer->nick())) ?>" <?= is_active('/user/' . $viewer->nick()) ? 'aria-current="page"' : '' ?>>
                    <span class="rail__icon"><?= icon('user') ?></span> <?= e(Lang::get('shell.profile')) ?>
                </a>
                <a class="rail__link" href="<?= e(url('/mail')) ?>" <?= is_active('/mail', 'mail') && !is_active('/mail/system') ? 'aria-current="page"' : '' ?>>
                    <span class="rail__icon"><?= icon('mail') ?></span> <?= e(Lang::get('shell.messages')) ?>
                    <?php if ($newMail > 0): ?>
                        <span class="rail__count"><?= e((string) min($newMail, 99)) ?></span>
                    <?php endif; ?>
                </a>
                <a class="rail__link" href="<?= e(url('/mail/system')) ?>" <?= is_active('/mail/system') ? 'aria-current="page"' : '' ?>>
                    <span class="rail__icon"><?= icon('bell') ?></span> <?= e(Lang::get('shell.notifications')) ?>
                </a>
                <?php if ($viewer->isAdmin()): ?>
                    <a class="rail__link" href="<?= e(url('/manager')) ?>" <?= is_active('/manager') ? 'aria-current="page"' : '' ?>>
                        <span class="rail__icon"><?= icon('settings') ?></span> <?= e(Lang::get('shell.admin')) ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <nav class="rail__section">
            <p class="rail__heading"><?= e(Lang::get('shell.other')) ?></p>
            <a class="rail__link" href="<?= e(url('/faq/terms')) ?>" <?= is_active('/faq') ? 'aria-current="page"' : '' ?>>
                <span class="rail__icon"><?= icon('file') ?></span> <?= e(Lang::get('shell.rules')) ?>
            </a>
            <a class="rail__link" href="<?= e(url('/sitemap.xml')) ?>">
                <span class="rail__icon"><?= icon('layers') ?></span> <?= e(Lang::get('shell.sitemap')) ?>
            </a>
        </nav>

        <form class="rail__section locale-switch" method="get" action="<?= e(url('/language')) ?>">
            <?php $currentUri = request()->getUri(); ?>
            <input type="hidden" name="return" value="<?= e($currentUri->getPath() . ($currentUri->getQuery() !== '' ? '?' . $currentUri->getQuery() : '')) ?>">
            <label class="rail__heading" for="locale-choice"><?= e(Lang::get('language.label')) ?></label>
            <div class="row">
                <select id="locale-choice" name="locale" aria-label="<?= e(Lang::get('language.label')) ?>">
                    <option value="vi" <?= Lang::locale() === 'vi' ? 'selected' : '' ?>>Tiếng Việt</option>
                    <option value="en" <?= Lang::locale() === 'en' ? 'selected' : '' ?>>English</option>
                </select>
                <button class="btn btn--ghost btn--sm" type="submit"><?= e(Lang::get('language.submit')) ?></button>
            </div>
        </form>

        <button class="btn btn--ghost btn--block drawer-close mt-5" type="button" data-drawer-close>
            <?= icon('chevron-left') ?> <?= e(Lang::get('shell.menu_close')) ?>
        </button>
    </aside>

    <div class="shell__main">
        <main class="shell__body" id="main" tabindex="-1">
            <?= $content ?>
        </main>

        <footer class="footer">
            <div>&copy; <?= date('Y') ?> <strong><?= e($siteName) ?></strong> · <?= e(Lang::get('shell.built_by')) ?></div>
            <div class="row">
                <a href="<?= e(url('/faq/terms')) ?>"><?= e(Lang::get('shell.rules')) ?></a>
                <a href="<?= e(url('/faq/about')) ?>"><?= e(Lang::get('shell.about')) ?></a>
                <a href="<?= e(url('/faq/help')) ?>"><?= e(Lang::get('shell.help')) ?></a>
            </div>
        </footer>
    </div>
</div>

<button class="drawer-scrim" type="button" data-drawer-scrim hidden aria-label="<?= e(Lang::get('shell.menu_close')) ?>"></button>

<nav class="bottomnav" aria-label="<?= e(Lang::get('shell.quick_navigation')) ?>">
    <a href="<?= e(url('/')) ?>" <?= is_active('/') ? 'aria-current="page"' : '' ?>>
        <?= icon('home', '', 20) ?><span><?= e(Lang::get('shell.home')) ?></span>
    </a>
    <a href="<?= e(url('/search')) ?>" <?= is_active('/search') ? 'aria-current="page"' : '' ?>>
        <?= icon('search', '', 20) ?><span><?= e(Lang::get('shell.search')) ?></span>
    </a>
    <a class="bottomnav__center" href="<?= e(url('/shoutbox')) ?>" <?= is_active('/shoutbox') ? 'aria-current="page"' : '' ?>>
        <?= icon('chat', '', 24) ?><span><?= e(Lang::get('shell.chat')) ?></span>
    </a>
    <?php if ($viewer->isAuthenticated): ?>
        <a href="<?= e(url('/mail')) ?>" <?= is_active('/mail', 'mail') ? 'aria-current="page"' : '' ?>>
            <?= icon('mail', '', 20) ?><span><?= e(Lang::get('shell.messages')) ?></span>
        </a>
        <a href="<?= e(url('/user/' . $viewer->nick())) ?>">
            <?= icon('user', '', 20) ?><span><?= e(Lang::get('shell.personal')) ?></span>
        </a>
    <?php else: ?>
        <a href="<?= e(url('/login')) ?>">
            <?= icon('login', '', 20) ?><span><?= e(Lang::get('shell.login')) ?></span>
        </a>
    <?php endif; ?>
</nav>

<div class="toasts" data-toasts aria-live="polite"></div>

<script src="<?= e(asset('assets/js/soulcms.js')) ?>" nonce="<?= e(csp_nonce()) ?>" defer></script>
</body>
</html>
