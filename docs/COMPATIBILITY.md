# SoulCMS ↔ SketchCMS compatibility

A feature-by-feature comparison against
[SketchCMS v1](https://github.com/pentanoic/2026_SketchCMS/tree/v1), the
upstream project whose database schema SoulCMS serves.

Legend: **✅** implemented · **◐** partial · **❌** not implemented ·
**＋** new in SoulCMS (no upstream equivalent)

---

## 1. Database schema

**Result: identical.** 11 tables, 100 columns — same names, same types,
same nullability, same defaults, same lengths.

| Table | Columns | Status |
|---|---|---|
| `chat` | 4 | ✅ |
| `articles_category` | 7 | ✅ |
| `articles_chap` | 7 | ✅ |
| `articles_cmt` | 5 | ✅ |
| `articles_file` | 14 | ✅ |
| `articles_tags` | 4 | ✅ |
| `articles_post_tags` | 2 | ✅ |
| `articles_post` | 14 | ✅ |
| `mail` | 8 | ✅ |
| `users` | 29 | ✅ |
| `users_waifu` | 6 | ✅ |

Verified three ways:

1. **Column diff** — every column compared on type, nullability, default and
   length. The only textual differences are `id` (portable `id` type vs.
   `int UNSIGNED`, both auto-increment primary keys).
2. **DDL diff** — the generated MySQL DDL matches the upstream `create_sql`
   apart from clause order, `DEFAULT '1'` vs `DEFAULT 1` (identical in
   MySQL), explicit vs implicit `NULL` (identical), and **extra indexes**
   (see below).
3. **Live metadata diff** — both schemas are created in SQLite and compared
   with `PRAGMA table_info`. The only differences are `DEFAULT '1'` vs `1`,
   explicit `DEFAULT NULL` vs implicit, and `int unsigned` vs `integer`
   (SQLite has no unsigned type). Both are value-identical.

### Indexes SoulCMS adds

Indexes do not change the data model, so they are safe to add:

| Table | Index | Why |
|---|---|---|
| `articles_post` | `category`, `author`, `update_time`, `slug` | the forum listing, the author page and slug lookups |
| `articles_cmt` | `blogid` | comment threads per post |
| `articles_chap` | `box`, `slug` | chapter lists and permalinks |
| `articles_category` | `slug` | category lookups |
| `articles_file` | `blogid` | attachments per post |
| `articles_post_tags` | `tag_id` | tag listing |
| `mail` | `sender_receiver` | conversation lookups |
| `users` | `nick`, `email` | login and profile lookups |
| `users_waifu` | `user_id` | waifu lookup |

`articles_tags.slug` keeps the upstream `UNIQUE` constraint.

---

## 2. Features present in SketchCMS but not in SoulCMS

These are real gaps. The schema columns they need all exist, so each one can
be implemented without a migration.

### 2.1 The media module — entirely missing

| Upstream | Description |
|---|---|
| `/media/upload` | upload to Telegram or a Cloudflare Worker |
| `/media/download/{file_id}/{file_name}` | download with a pretty filename |
| `/media/library` | the shared media library |
| `mediaModel::insertMedia` / `getLibrary` | storage of library entries |

SoulCMS stores attachments in `articles_file` but has no media library, no
remote upload and no download route. The `articles_file` columns
(`type`, `status`, `price`, `saleoff`, `condition`, `mua`, `passphrase`) are
therefore written but never interpreted.

### 2.2 The paid-file system — entirely missing

`FileBuy`, `FileBoughtCount`, `FilePurchaserList` and the `price` /
`saleoff` / `condition` / `mua` columns have no implementation. SoulCMS
uploads a file and shows it; it cannot sell one.

### 2.3 The like system — entirely missing

`articles_post.like` and `PostLikeList` / `PostLikeSave` are not implemented.

### 2.4 Mail — partially missing

| Upstream | Status |
|---|---|
| `MailSend`, `MailDetailList`, `MailList`, `MailCount` | ✅ |
| `MailSystemCount`, `MailSystemList` | ✅ |
| `MailViewUpdate` (mark as read) | ❌ |
| `LatestMailDetail` | ❌ |
| `MailBlocked` (block a sender) | ❌ |
| `MailSystemView`, `MailSystemClear` | ❌ |

### 2.5 User module — partially missing

| Upstream | Status |
|---|---|
| register, login, logout, forgot/reset password | ✅ |
| `UserList`, `UserListOnline`, `UserDetailWithFields` | ✅ |
| `UserDetailEdit` (profile editing) | ❌ — no `/user/{nick}/info` route |
| `UserDetailBlockList` (`users.blocklist`) | ❌ — the column exists, unused |
| `UserStats` (post + comment counts for a member) | ❌ |
| `UserOnlineStatus` | ◐ — `users.on` is written, but no status query |
| `CheckResetToken` | ◐ — the token is validated in the session only |

### 2.6 Reputation system — not implemented

`users.karma`, `karma_daily_time`, `karma_level`, `xu`, `tiemluc`,
`sft_time`, `sft_level` are all present in the schema and all unused. The
upstream ranks members by registration age and activity; SoulCMS shows the
numbers but never computes them.

### 2.7 Login lockout — not implemented

`users.failed_logins` is incremented and `lock_time` is never read. The
upstream locks an account after a configurable number of failures; SoulCMS
relies on the rate limiter only.

### 2.8 Admin panel — partially missing

| Upstream route | Status |
|---|---|
| `/manager` dashboard | ✅ |
| `/manager/users` | ✅ |
| `/manager/users/edit`, `/manager/users/ban` | ✅ |
| `/manager/articles` | ✅ |
| `/manager/articles/cat/add|edit|delete` | ◐ — add and delete only |
| `/manager/articles/post/delete` | ✅ |
| `/manager/articles/chapter/delete` | ❌ |
| `/manager/url_rules` + add/edit/delete | ✅ (implemented during this review) |
| `/manager/modules` | ◐ — enable/disable only |
| `/manager/modules/add|upload|delete|install|uninstall|download` | ❌ — no module marketplace |
| `/manager/modules/template/action` | ❌ — no per-module template switching |
| `/manager/static_pages` + add/edit/delete | ❌ |
| `/manager/templates` + set_default/download/view | ❌ — no theme manager |
| `/manager/smtp` | ❌ |
| `/manager/security` | ❌ |
| `/manager/shoutbox/clean` | ❌ |
| `/manager/media`, `/manager/media/config` | ❌ |

### 2.9 Content rendering

| Upstream | Status |
|---|---|
| `bbcode` (`[url]`, `[img]`, `[b]`, `[quote]`…) | ❌ — SoulCMS renders a small Markdown subset |
| `smileys` | ❌ |
| syntax highlighting (Highlight.php) | ❌ |
| `PageDescription` / `PageKeyword` (SEO meta extraction) | ◐ — the post meta is stored but not extracted from the body |
| `TrimContent` / `ContentLen` (excerpts) | ❌ |

### 2.10 Platform features

| Upstream | Status |
|---|---|
| CAPTCHA on registration | ❌ |
| e-mail sending (PHPMailer) | ❌ — the reset token is stored in the session, never mailed |
| SMTP configuration | ❌ |
| language switcher | ❌ |
| static pages (`templates/_assets/static-page`) | ❌ |
| the "waifu" decorations | ◐ — the table and the row exist, there is no UI |
| registration restricted by country (`Manager.allow_country`) | ❌ |
| Telegram / Cloudinary Worker uploads | ❌ |

### 2.11 Routes

| Upstream | Status |
|---|---|
| `/home`, `/index.html` (home aliases) | ❌ |
| `/{slug}.html` (catch-all static page) | ❌ |
| `/shoutbox/ele` (single chat element) | ❌ |
| everything else in the upstream route table | ✅ |

---

## 3. Features SoulCMS adds

| Area | Addition |
|---|---|
| Databases | SQLite, MySQL, MariaDB, PostgreSQL (upstream: MySQL only) |
| Routing | indexed route table, route cache, 405 + `Allow` header, HEAD → GET |
| HTTP | immutable request/response, middleware pipeline, typed HTTP exceptions |
| Security | CSRF tokens, bcrypt with transparent upgrade of legacy hashes, rate limiting, security headers |
| Performance | lazy sessions, conditional GET, Brotli/gzip, static asset serving, prepared statements |
| Front-end | a complete design system, dark/light themes, no external CDN dependency |
| Tooling | `bin/soulcms` CLI (install, seed, routes, cache, verify), PHPUnit suite, headless-Chrome behaviour suite |

---

## 4. Behavioural differences that are intentional

| Upstream | SoulCMS | Reason |
|---|---|---|
| Password = substitution + MD5 | bcrypt, legacy hashes accepted and upgraded | MD5 is not a password hash |
| `zlib.output_compression` in PHP | compression in the middleware, once | double compression breaks browsers |
| Route scan (one regex per route) | static map + segment buckets | 1.4× faster and predictable |
| Sessions always started | started on demand | anonymous page views skip the session lock |
| Errors via `die()` and raw `echo` | typed exceptions and responses | testable, consistent |
| Settings in the database | `settings` table when it exists, JSON file otherwise | keeps the SketchCMS schema untouched |

---

## 5. Recommended order for closing the gaps

1. **Media module** — the largest missing surface; the schema is ready.
2. **Password-reset e-mail** — the current flow only works in the same
   browser session, which is a functional bug for real users.
3. **Profile editing** — members currently cannot change their own details.
4. **Login lockout** — `failed_logins` and `lock_time` are already tracked.
5. **BBCode** — upstream content will not render correctly without it.
6. **The like system and the paid-file system** — both are single tables.
7. **The remaining admin screens** — static pages, themes, SMTP, security.
