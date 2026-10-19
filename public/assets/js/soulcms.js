/**
 * SoulCMS front-end.
 *
 * Progressive enhancement only: every feature below has a working server
 * rendered fallback. No dependencies, no build step.
 */

(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;

    /* ------------------------------------------------------------------
       Theme — the server renders the current theme, this only flips it
       ------------------------------------------------------------------ */

    var THEME_COOKIE = 'soulcms_theme';

    function readCookie(name) {
        var parts = document.cookie ? document.cookie.split('; ') : [];

        for (var i = 0; i < parts.length; i++) {
            var pair = parts[i].split('=');
            if (decodeURIComponent(pair[0]) === name) {
                return decodeURIComponent(pair.slice(1).join('='));
            }
        }

        return null;
    }

    function setCookie(name, value, days) {
        var expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; samesite=lax';
    }

    function syncThemeButtons(theme) {
        var buttons = document.querySelectorAll('[data-theme-toggle]');

        for (var i = 0; i < buttons.length; i++) {
            var button = buttons[i];
            var use = button.querySelector('use');
            var label = button.querySelector('[data-theme-label]');

            if (use) {
                // The sprite is an external file, so only the fragment may
                // change: "/assets/img/icons.svg?v=1#i-sun" keeps its path.
                var href = use.getAttribute('href') || '';
                var base = href.split('#')[0];

                if (base) {
                    use.setAttribute('href', base + '#' + (theme === 'dark' ? 'i-sun' : 'i-moon'));
                }
            }

            if (label) {
                label.textContent = theme === 'dark' ? 'Chế độ sáng' : 'Chế độ tối';
            }

            button.setAttribute('aria-label', theme === 'dark' ? 'Chuyển sang chế độ sáng' : 'Chuyển sang chế độ tối');
        }
    }

    function initTheme() {
        syncThemeButtons(root.getAttribute('data-theme') || 'dark');

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-theme-toggle]');

            if (!button) {
                return;
            }

            event.preventDefault();

            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

            root.setAttribute('data-theme', next);
            setCookie(THEME_COOKIE, next, 365);
            syncThemeButtons(next);
        });
    }

    /* ------------------------------------------------------------------
       Drawer — the rail becomes an accessible sheet on small screens
       ------------------------------------------------------------------ */

    function initDrawer() {
        var toggle = document.querySelector('[data-drawer-toggle]');
        var rail = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : document.querySelector('.rail');
        var close = rail ? rail.querySelector('[data-drawer-close]') : null;
        var scrim = document.querySelector('[data-drawer-scrim]');
        var compact = window.matchMedia
            ? window.matchMedia('(max-width: 1080px)')
            : { matches: window.innerWidth <= 1080 };
        var returnFocus = null;

        function focusableItems() {
            if (!rail) {
                return [];
            }

            var items = rail.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');

            return Array.prototype.filter.call(items, function (item) {
                return item.getClientRects().length > 0;
            });
        }

        function setOpen(open, restoreFocus) {
            var isOpen = Boolean(open && compact.matches);
            body.setAttribute('data-drawer', isOpen ? 'open' : 'closed');

            if (scrim) {
                scrim.hidden = !isOpen;
            }

            if (toggle) {
                toggle.setAttribute('aria-expanded', String(isOpen));
                toggle.setAttribute('aria-label', isOpen ? 'Đóng menu' : 'Mở menu');
            }

            if (rail) {
                rail.inert = compact.matches && !isOpen;
                rail.setAttribute('aria-hidden', String(compact.matches && !isOpen));
            }

            if (isOpen) {
                returnFocus = toggle || document.activeElement;
                var items = focusableItems();
                var first = items[0] || close;
                if (first) {
                    first.focus();
                }
            } else if (restoreFocus && returnFocus && typeof returnFocus.focus === 'function') {
                returnFocus.focus();
                returnFocus = null;
            }
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-drawer-toggle]');

            if (button) {
                event.preventDefault();
                setOpen(body.getAttribute('data-drawer') !== 'open', body.getAttribute('data-drawer') === 'open');

                return;
            }

            if (event.target.closest('[data-drawer-close]') || event.target.closest('[data-drawer-scrim]')) {
                setOpen(false, true);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && body.getAttribute('data-drawer') === 'open') {
                setOpen(false, true);

                return;
            }

            if (event.key !== 'Tab' || body.getAttribute('data-drawer') !== 'open') {
                return;
            }

            var items = focusableItems();
            if (items.length === 0) {
                event.preventDefault();

                return;
            }

            var first = items[0];
            var last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        function resetForViewport() {
            var focusWasInRail = rail && rail.contains(document.activeElement);
            setOpen(false, false);

            if (compact.matches && focusWasInRail && toggle) {
                toggle.focus();
            }
        }

        if (typeof compact.addEventListener === 'function') {
            compact.addEventListener('change', resetForViewport);
        } else if (typeof compact.addListener === 'function') {
            compact.addListener(resetForViewport);
        }

        setOpen(false, false);
    }

    /* ------------------------------------------------------------------
       Search — collapsible on small screens
       ------------------------------------------------------------------ */

    function initSearch() {
        var toggle = document.querySelector('[data-search-toggle]');
        var form = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : null;
        var input = form ? form.querySelector('input[type="search"]') : null;

        function setOpen(open, restoreFocus) {
            body.setAttribute('data-search', open ? 'open' : 'closed');

            if (toggle) {
                toggle.setAttribute('aria-expanded', String(open));
            }

            if (open && input) {
                input.focus();
            } else if (restoreFocus && toggle) {
                toggle.focus();
            }
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-search-toggle]');

            if (button) {
                event.preventDefault();
                setOpen(body.getAttribute('data-search') !== 'open', body.getAttribute('data-search') === 'open');

                return;
            }

            if (body.getAttribute('data-search') === 'open' && !event.target.closest('#' + (form ? form.id : 'global-search'))) {
                setOpen(false, false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && body.getAttribute('data-search') === 'open') {
                setOpen(false, true);
            }
        });

        setOpen(false, false);
    }

    /* ------------------------------------------------------------------
       Menus — the account dropdown
       ------------------------------------------------------------------ */

    function initMenus() {
        function closeAll(except) {
            var menus = document.querySelectorAll('[data-menu]');

            for (var i = 0; i < menus.length; i++) {
                if (menus[i] !== except) {
                    menus[i].hidden = true;

                    var owner = document.querySelector('[aria-controls="' + menus[i].id + '"]');
                    if (owner) {
                        owner.setAttribute('aria-expanded', 'false');
                    }
                }
            }
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-menu-toggle]');

            if (trigger) {
                event.preventDefault();

                var menu = document.getElementById(trigger.getAttribute('aria-controls'));

                if (!menu) {
                    return;
                }

                var open = !menu.hidden;

                closeAll(menu);
                menu.hidden = open;
                trigger.setAttribute('aria-expanded', String(!open));

                if (!open) {
                    var first = menu.querySelector('a, button');
                    if (first) {
                        first.focus();
                    }
                }

                return;
            }

            if (!event.target.closest('[data-menu]')) {
                closeAll(null);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAll(null);
            }
        });
    }

    /* ------------------------------------------------------------------
       Toasts
       ------------------------------------------------------------------ */

    var ICONS = {
        success: '#i-check',
        error: '#i-alert',
        info: '#i-bell'
    };

    function toast(message, type) {
        var host = document.querySelector('[data-toasts]');

        if (!host) {
            return;
        }

        var el = document.createElement('div');
        el.className = 'toast toast--' + (type || 'info');
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('width', '18');
        icon.setAttribute('height', '18');
        icon.setAttribute('aria-hidden', 'true');

        var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', ICONS[type] || ICONS.info);
        icon.appendChild(use);

        var text = document.createElement('span');
        text.textContent = message;

        el.appendChild(icon);
        el.appendChild(text);
        host.appendChild(el);

        window.setTimeout(function () {
            el.style.transition = 'opacity .2s, translate .2s';
            el.style.opacity = '0';
            el.style.translate = '0 6px';

            window.setTimeout(function () {
                el.remove();
            }, 220);
        }, 4200);
    }

    window.soulToast = toast;

    /* ------------------------------------------------------------------
       Destructive actions ask first
       ------------------------------------------------------------------ */

    function initConfirm() {
        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form.dataset || !form.dataset.confirm) {
                return;
            }

            if (form.dataset.confirmed === '1') {
                return;
            }

            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();

                return;
            }

            form.dataset.confirmed = '1';
        });
    }

    /* ------------------------------------------------------------------
       Flash messages become toasts
       ------------------------------------------------------------------ */

    function initFlash() {
        var flashes = document.querySelectorAll('[data-flash]');

        for (var i = 0; i < flashes.length; i++) {
            toast(flashes[i].getAttribute('data-flash'), flashes[i].getAttribute('data-flash-type') || 'info');
        }
    }

    /* ------------------------------------------------------------------
       Chat — poll the shoutbox while the tab is visible
       ------------------------------------------------------------------ */

    function initChat() {
        var wall = document.querySelector('[data-chat-wall]');

        if (!wall) {
            return;
        }

        var endpoint = wall.getAttribute('data-chat-endpoint');
        var interval = parseInt(wall.getAttribute('data-chat-interval') || '8000', 10);
        var lastId = parseInt(wall.getAttribute('data-chat-last') || '0', 10);
        var busy = false;

        function formatTime(timestamp) {
            var date = new Date(parseInt(timestamp, 10) * 1000);

            if (isNaN(date.getTime())) {
                return '';
            }

            return ('0' + date.getHours()).slice(-2) + ':' + ('0' + date.getMinutes()).slice(-2);
        }

        function render(messages) {
            // The endpoint returns the new messages oldest first; the wall
            // shows the newest at the top, so walk backwards.
            for (var i = messages.length - 1; i >= 0; i--) {
                var message = messages[i];
                var id = parseInt(message.id, 10);

                if (id <= lastId) {
                    continue;
                }

                lastId = Math.max(lastId, id);

                var row = document.createElement('div');
                row.className = 'chatline';

                var who = document.createElement('span');
                who.className = 'chatline__who';
                who.textContent = '@' + message.name;

                var body = document.createElement('span');
                body.textContent = message.comment;

                var time = document.createElement('span');
                time.className = 'chatline__time';
                time.textContent = formatTime(message.time);

                row.appendChild(who);
                row.appendChild(body);
                row.appendChild(time);
                wall.insertBefore(row, wall.firstChild);
            }
        }

        function poll() {
            if (busy || document.hidden) {
                return;
            }

            busy = true;

            fetch(endpoint + '?after=' + lastId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (payload) {
                    if (payload && payload.messages) {
                        render(payload.messages);
                    }
                })
                .catch(function () { /* offline: try again on the next tick */ })
                .then(function () {
                    busy = false;
                });
        }

        window.setInterval(poll, interval);
    }

    /* ------------------------------------------------------------------
       Copy code blocks
       ------------------------------------------------------------------ */

    function initClipboard() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-copy]');

            if (!button) {
                return;
            }

            event.preventDefault();

            var source = document.querySelector(button.getAttribute('data-copy'));

            if (!source) {
                return;
            }

            var text = source.innerText;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    toast('Đã sao chép vào bộ nhớ tạm', 'success');
                });
            }
        });
    }

    /* ------------------------------------------------------------------
       Boot
       ------------------------------------------------------------------ */

    function boot() {
        initTheme();
        initDrawer();
        initSearch();
        initMenus();
        initConfirm();
        initFlash();
        initChat();
        initClipboard();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
