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

### 2.1 Attachment storage — S3 design adaptation

The SketchCMS attachment upload uses Telegram/Cloudinary Workers. SoulCMS
intentionally uses a private S3-compatible bucket instead: `S3Storage` signs
server-side uploads and temporary downloads with AWS Signature Version 4 and
adds no runtime SDK dependency. Set `STORAGE_DRIVER=s3`, `S3_ENDPOINT`,
`S3_BUCKET`, `S3_REGION`, `S3_ACCESS_KEY`, and `S3_SECRET_KEY`; the endpoint
must use HTTPS. `S3_PATH_STYLE=true` is the default for compatible providers.
Uploads are content-validated. The existing `articles_file` schema is
unchanged; `type='s3'` identifies these objects, whose keys derive from
`articles/{post_id}/{file_id}.{extension}`. Downloads are presigned for five
minutes and shown only for public/free files or to the post author/admins.
`STORAGE_DRIVER=local` is the default. The adapter intentionally implements
server-side PUT plus presigned GETs (not browser-direct uploads), and should use
bucket credentials limited to object access in the configured bucket.

The upstream shared media library is now implemented as a member-scoped library
at `/media/library`, backed by the existing `articles_file` table. It accepts
validated uploads to the configured private S3 bucket, returns temporary
presigned URLs, and lists only the current member's library objects. Library
objects use `blogid=0` to stay separate from post attachments; no schema changes
are required. The upstream Telegram/Cloudflare Worker backends are intentionally
replaced by S3; their absence is a design choice, not an unfinished feature.
The `/manager/media/config` screen for site-wide storage settings is still
unimplemented; S3 is configured through environment variables.

### 2.2 The paid-file system — implemented for S3 attachments

`FileBuy` is supported for private, priced S3 attachments: a signed-in buyer's
`users.xu` balance is charged once, and the buyer is recorded in the existing
dot-separated `articles_file.mua` column. The download becomes available only
after purchase; the file detail page also shows existing purchasers as profile links. Free/public files and the post author/admin keep their existing
access rules. Upstream `saleoff` and `condition` pricing semantics are not yet
implemented; purchases charge the stored `price`. Legacy local attachments are
not offered for purchase.

### 2.3 The like system — implemented

Members can like a post once; post authors cannot like their own post. Likes
use the existing dot-separated `articles_post.like` field, and a successful
like sends the author a system notification.

### 2.4 Mail — partially missing

| Upstream | Status |
|---|---|
| `MailSend`, `MailDetailList`, `MailList`, `MailCount` | ✅ |
| `MailSystemCount`, `MailSystemList` | ✅ |
| `MailViewUpdate` (mark as read) | ✅ — opening a conversation marks its messages read |
| `LatestMailDetail` | ✅ — inbox preview and unread state loaded for all conversations in one query |
| `MailBlocked` (block a sender) | ✅ — a recipient's existing `users.blocklist` prevents the blocked member from opening or sending private messages |
| `MailSystemView`, `MailSystemClear` | ✅ — opening system notifications marks them read; clear uses a POST-only action |

### 2.5 User module — partially missing

| Upstream | Status |
|---|---|
| register, login, logout, forgot/reset password | ✅ |
| `UserList`, `UserListOnline`, `UserDetailWithFields` | ✅ |
| `UserDetailEdit` (profile editing) | ✅ — owner-only name, e-mail and gender updates at `/user/{nick}/info` |
| `UserDetailBlockList` (`users.blocklist`) | ✅ — owner-managed, stored in the existing dot-separated column; blocked users cannot send private messages |
| `UserStats` (post + comment counts for a member) | ✅ — profile counts include all authored posts and their comments |
| `UserOnlineStatus` | ✅ — member profile shows status based on activity within the last five minutes |
| `CheckResetToken` | ✅ — reset links are checked against `users.reset_token` and `reset_expires`, and consumed with the password update; e-mail delivery is still unavailable |

### 2.6 Reputation system — display-only rank implemented

SoulCMS derives a profile reputation rank from registration age and post/comment
activity in the last 30 days. The score and label are display-only; no karma,
level, currency, or other account columns are modified. The upstream daily karma
and resource/energy progression remains unsupported.

### 2.7 Login lockout — implemented

`failed_logins` and `lock_time` enforce the configured failed-login threshold and
recovery window. A failed password attempt is counted once by `AuthManager`;
the login controller only returns the generic failure response.

### 2.8 Admin panel — partially missing

| Upstream route | Status |
|---|---|
| `/manager` dashboard | ✅ |
| `/manager/users` | ✅ |
| `/manager/users/edit`, `/manager/users/ban` | ✅ |
| `/manager/articles` | ✅ |
| `/manager/articles/cat/add|edit|delete` | ◐ — add, edit and delete; full upstream bulk tools remain absent |
| `/manager/articles/post/delete` | ✅ |
| `/manager/articles/chapter/delete` | ✅ — POST-only, admin-guarded chapter deletion |
| `/manager/url_rules` + add/edit/delete | ✅ (implemented during this review) |
| `/manager/modules` | ◐ — enable/disable only |
| `/manager/modules/add|upload|delete|install|uninstall|download` | ❌ — no module marketplace |
| `/manager/modules/template/action` | ❌ — no per-module template switching |
| `/manager/static_pages` + add/edit/delete | ✅ — settings-backed page definitions; unchanged SketchCMS schema |
| `/manager/templates` + set_default/download/view | ❌ — no theme manager |
| `/manager/smtp` | ❌ — SMTP is configured through environment variables; manager UI remains missing |
| `/manager/security` | ❌ |
| `/manager/shoutbox/clean` | ✅ — POST-only, admin-guarded action clears the chat table |
| `/manager/media`, `/manager/media/config` | ◐ — member S3 library at `/media/library`; manager-wide storage configuration is via environment variables, with no manager screen |

### 2.9 Content rendering

| Upstream | Status |
|---|---|
| `bbcode` (`[url]`, `[img]`, `[b]`, `[quote]`…) | ◐ — formatting and aliases, links including safe Markdown `[label](URL)` and formatted labels on safe `[url=URL]` links, automatic plain HTTP(S) URLs and safe `[d]` download links, images, quotes (including inline formatting and `[quote=name]` attribution), semantic `[sub]`/`[sup]` tags, collapsed escaped `[spoiler]` disclosures, hex and allow-listed named colors (including bold `[bcolor]`), bounded `[size]` spans (8–72 px), text alignment and basic Markdown ordered/unordered lists and inline formatting within BBCode list items and upstream numeric list-selector behavior, Markdown blockquotes, horizontal rules, validated YouTube embeds, Markdown headings, tables with inline-formatted cells, fenced code and strikethrough render safely alongside BBCode and common emoticons; advanced upstream tags remain unsupported |
| `smileys` | ◐ — common text emoticons (including confusion, surprise, neutral and crying faces) convert to Unicode emoji; the full upstream smiley set/assets are not replicated |
| syntax highlighting (Highlight.php) | ◐ — lightweight server-side highlighting for allow-listed PHP, JavaScript and SQL code blocks; upstream Highlight.php's full language/theme support is not replicated |
| `PageDescription` / `PageKeyword` (SEO meta extraction) | ✅ — article pages extract `meta_title`, `meta_desc`, and `meta_keywords` from `meta_data`, falling back to title-derived keywords and a 150-character plain-text body description |
| `TrimContent` / `ContentLen` (excerpts) | ✅ — post feeds show a 160-character plain-text excerpt with BBCode/Markdown formatting removed |

### 2.10 Platform features

| Upstream | Status |
|---|---|
| CAPTCHA on registration | ✅ — session-bound one-use arithmetic challenge; no third-party service |
| e-mail sending (password reset) | ◐ — sends one-hour database-backed reset links over configured SMTP; no links are sent if mail is not configured |
| SMTP configuration and password-reset email | ◐ — `MAIL_*` environment settings and a dependency-free SMTP sender deliver one-hour reset links; no manager SMTP screen |
| language switcher | ◐ — visitors can persist Vietnamese/English selection in a same-site cookie; the document language, common public-shell navigation/control labels, login/registration forms, password-reset screens, member-profile labels, profile-edit and blocklist forms, member directory, private-mail inbox, system notifications, message-compose form, home-feed controls, article listing/detail, paid-file, search, chapter, tag and publishing interfaces, FAQ, shoutbox, media library, category management, static-page management, site settings, article and member administration, dashboard, shared errors, feed statistics, URL-rule manager and administration shell switch; the language names remain in their native form |
| static pages (`templates/_assets/static-page`) | ✅ — admin-managed Markdown/BBCode pages render at `/{slug}.html`; settings-backed to preserve the schema |
| the "waifu" decorations | ◐ — member profiles render the saved header/side image paths only when they resolve to local files under `public/images`; owners can hide/show decorations via the existing `close` column |
| registration restricted by country (`Manager.allow_country`) | ❌ |
| media upload/download routes | ✅ — authenticated S3 uploads, member-scoped library, and signed download redirects; S3 intentionally replaces the Telegram/Worker workaround |

Role badges on member profiles and directory entries, relative timestamps, and
pagination labels also use the selected Vietnamese or English catalogue,
including administrator and moderation roles. Client-side theme and drawer
labels and copy confirmations are localized through the same server-rendered
catalogue. Controller-generated page titles, generic HTTP error responses, and
shoutbox API errors use that catalogue as well. Registration CAPTCHA feedback,
duplicate-account errors, login throttling, article, mail, and manager
validation, and blocklist feedback are translated before reaching the member.
Module titles and descriptions in the administration panel, generated system
notifications for comments and likes, and media-upload and paid-file purchase
API errors follow the selected language.

### 2.11 Routes

| Upstream | Status |
|---|---|
| `/home`, `/index.html` (home aliases) | ✅ — both resolve to the home feed |
| `/{slug}.html` (catch-all static page) | ✅ — slug-constrained, settings-backed static pages |
| `/shoutbox/ele` (single chat element) | ✅ — returns the requested chat row as a safely escaped HTML fragment |
| `/media/upload`, `/media/download/{file_id}/{file_name}`, `/media/library` | ◐ — upload and member library provided via S3; download and manager settings are adapted rather than upstream-compatible |

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

1. **Remaining admin screens and rendering details** — themes and SMTP/security admin UI, full smiley assets, complete BBCode tags, full syntax-highlighter language/theme parity, and paid-file `saleoff` / `condition` semantics.
