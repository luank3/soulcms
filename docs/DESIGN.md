# Design

The interface of SoulCMS and the rules that govern it.

---

## 1. Direction — "Truyện bản" (dispatch desk)

The product is a community forum: members publish long-form posts, comment,
tag, chat in a shoutbox and carry a level/karma identity. The interface is
built around that, not around a generic dashboard.

* **The feed is the product.** Discussions are rendered as a dense, hairline
  ruled *dispatch feed* — a category gutter, a serif title, a monospace
  metadata strip and a column of counters — instead of a grid of identical
  cards. Feed rows include a short plain-text excerpt; profiles also show a
  display-only reputation rank derived from registration age and recent activity.
* **Identity is data.** A member's role (Newbie → Legend, Contributor →
  Developer) is rendered with the role's own colour, so a reader recognises
  a trusted poster at a glance. The scale comes straight from the CMS level
  semantics, not from a decorative palette.
* **Type carries the voice.** Serif for titles (an editorial, long-read
  register), the system sans for controls, monospace for data (counts,
  timestamps, ids). No web fonts, so nothing blocks the first paint.
* **One accent.** Vermilion on cool slate, in the spirit of SketchCMS's own
  red-gradient heritage — and deliberately not a cream/terracotta or a
  near-black/acid-green default.
* **Two themes.** Dark by default, with a light paper theme; the choice is
  rendered on the server from a cookie, so the page never flashes.

---

## 2. Files

```
public/assets/css/soulcms.css     the design system (tokens → components → responsive)
public/assets/js/soulcms.js       progressive enhancement, no dependencies
public/assets/img/icons.svg       35 icons, one SVG sprite
resources/views/layouts/app.php   public shell: topbar, rail, bottom navigation
resources/views/layouts/admin.php administration shell
resources/views/layouts/auth.php  authentication shell
resources/views/partials/         feed row, field, empty state, error summary
```

---

## 3. Tokens

Everything is driven by custom properties in `public/assets/css/soulcms.css`.

### Colour

| Token | Dark | Light | Use |
|---|---|---|---|
| `--bg` | `#14161d` | `#eceef2` | page background |
| `--bg-deep` | `#101219` | `#e2e5ec` | top bar, navigation rail |
| `--surface` | `#1a1d26` | `#ffffff` | panels |
| `--surface-2` | `#21242f` | `#f4f6f9` | inputs, raised surfaces |
| `--line` | `#272b36` | `#d7dbe4` | borders |
| `--ink` / `--ink-2` / `--ink-3` | `#e8eaf0` / `#a8afc0` / `#737b8d` | `#191c24` / `#515869` / `#7b8395` | text hierarchy |
| `--accent` | `#e2492c` | `#c33a1e` | the single accent |
| `--live` | `#3fbf9a` | `#17916f` | online, success |

Member roles have their own scale, exposed as `--role-*` and applied by the
`.role-*` classes: `newbie`, `member`, `junior`, `senior`, `veteran`,
`legend`, `contributor`, `moderator`, `super-moderator`, `administrator`,
`developer`, `banned`.

### Type

| Role | Stack |
|---|---|
| Display (titles) | `"Iowan Old Style", Charter, "Bitstream Charter", "Palatino Linotype", Palatino, Georgia, serif` |
| UI (controls, body) | `system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif` |
| Data (counts, timestamps, ids) | `ui-monospace, SFMono-Regular, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace` |

The scale runs from `--step--1` (0.75rem) to `--step-4` (2.125rem).

### Space, radius, motion

* **Space** — `--s-1` … `--s-8` on a 4px base.
* **Radius** — `--r-1` (4px), `--r-2` (7px), `--r-3` (11px), `--r-pill`.
* **Motion** — `--ease: cubic-bezier(.22, .61, .36, 1)`; every transition is
  short (140–240ms) and `prefers-reduced-motion` is honoured globally.

---

## 4. Layout

```
┌──────────────────────────────────────────────────────────┐
│ TOPBAR  [☰] [mark] SoulCMS  [search…]      [actions]    │  56px, sticky
├───────────┬──────────────────────────────────────────────┤
│ RAIL      │  MAIN (max 1080px, reading column 68ch)       │
│ (244px)   │                                              │
│           │  ┌ dispatch row ───────────────────────────┐  │
│ ▎[CAT]   │  │ Title in serif            12  3  340     │  │
│ @author  │  │ author · role · 2h ago   replies  views  │  │
│ ▸ nav    │  └─────────────────────────────────────────┘  │
│           │                                              │
├───────────┴──────────────────────────────────────────────┤
│ FOOTER                                                    │
└──────────────────────────────────────────────────────────┘
```

* The **top bar** carries the brand, the search field, the notifications, the
  theme switch and the account menu.
* The **rail** is a standing column: the member card, the forum navigation, the
  personal navigation and the secondary links.
* The **feed** is the primary content surface. A 3px gutter painted with the
  category colour makes a section recognisable at a glance.
* **Admin shell** replaces the rail with a grouped admin navigation and
  renders figures as one strip rather than a grid of cards. Staff can create,
  edit and delete article categories without changing the SketchCMS schema.
* The **public rail** offers a Vietnamese/English preference that persists
  without a session; common shell navigation, controls, authentication forms,
  member-profile labels, profile-edit and blocklist forms, member directory,
  private-mail inbox, system notifications, message-compose form, home-feed
  controls and article listing follow the selection. Static pages are edited in
  the admin area and render through the public shell with SEO fields.

### Responsive behaviour

The layout changes structurally rather than shrinking:

| Breakpoint | Change |
|---|---|
| ≤ 1080px | the rail becomes a dismissible drawer with a scrim; a five-slot bottom navigation appears |
| ≤ 720px | the search field collapses behind an icon; the feed counters move under the title; the admin navigation becomes a horizontal scroller |
| ≤ 460px | the brand text is dropped, keeping only the mark; feed controls stack into a thumb-friendly layout |

---

## 5. Components

`panel`, `feed`, `feed-tools`, `btn`, `iconbtn`, `chip` (role identity), `chip--tag`,
`tagmark`, `stats`, `table`, `pager`, `alert`, `menu`, `toast`, `avatar`,
`field`, `empty`, `skeleton`, `comment`, `chatline`, `membercard`, `skip-link`.

Every state the product can actually reach is implemented: empty lists, empty
search results, validation errors (inline, with `aria-invalid` and
`aria-describedby`), disabled actions, loading skeletons and
destructive-action confirmation.

### Accessibility

* A skip link ("Bỏ qua điều hướng") is the first focusable element.
* `<main id="main" tabindex="-1">` is the landmark target.
* Form errors are announced through `aria-describedby`; the summary is a
  `role="alert"` region.
* Toasts live in an `aria-live="polite"` container.
* `:focus-visible` is styled for every interactive element.
* The mobile navigation drawer is hidden from assistive technology while closed; opening it moves and contains keyboard focus, and Escape or the scrim returns focus to its trigger.
* `prefers-reduced-motion` disables all transitions and animations.

---

## 6. Behaviour

The JavaScript is entirely optional — every page works without it. When it is
available it adds:

* theme switching (persisted in a cookie, applied server-side on the next
  request);
* the navigation drawer on small screens and a collapsible search field;
* feed sorting by configured date/activity/views, with category and sort settings retained across pagination;
* the account menu;
* toasts;
* confirmation before destructive actions (`data-confirm`);
* incremental chat polling (`/shoutbox/list?after=<id>`), which only
  transfers new messages and pauses while the tab is hidden;
* safe post rendering supports Markdown, a BBCode subset (including attributed
  quotes and safe download links, formatting aliases, sub/superscript, collapsed safe spoiler disclosures, hex and allow-listed
  named colors (including bold color), 8–72 px font sizes, text alignment,
  basic Markdown ordered/unordered lists, Markdown blockquotes, horizontal rules, validated YouTube embeds,
  Markdown headings/tables/strikethrough, fenced code, safe Markdown `[label](URL)` and automatic HTTP(S) links,
  common emoticons (including confusion, surprise, neutral and crying faces) and allow-listed PHP/JavaScript/SQL syntax highlighting;
  code blocks remain escaped;
* profile pages render saved local waifu decorations; their owners can hide or
  restore them without changing any decoration paths.
* copy-to-clipboard on code blocks.

The script is a single file with no dependencies and no build step.

---

## 7. Icons

`public/assets/img/icons.svg` is one sprite of 35 symbols on a 24×24 grid with
a 1.75 stroke, round caps and joins. They are referenced as:

```php
<?= icon('search') ?>
```

which renders `<svg><use href="/assets/img/icons.svg#i-search"></use></svg>`.

The sprite is served as a static asset with an immutable cache header, so it
costs one request, once.

---

## 8. Writing a new view

1. Put it in `resources/views/<module>/`.
2. Use the existing components before inventing a new one.
3. Escape everything that is not markup you wrote: `e()`.
4. Give every form a CSRF token: `csrf_field()`.
5. Use `view('partials/field', …)` for inputs so the error states are
   consistent.
6. Provide the empty state (`view('partials/empty', …)`) for any list.
7. The interface language is Vietnamese; use the catalogue in `lang/vi.php`
   for anything user facing.
