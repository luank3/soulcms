# AGENTS.md

Guidance for coding agents working on **SoulCMS**, a multi-database CMS for
PHP 8.2+ that serves a SketchCMS-compatible database schema.

---

## 1. What this project is

SoulCMS is a content management system and community forum written from
scratch: a small framework (`src/`, namespace `SoulCMS\`) plus a set of
feature modules (`app/Modules/`). It has **no runtime framework
dependencies** — Composer is only needed for PHPUnit.

The single most important constraint:

> **The database schema is a contract.** `database/schema/sketchcms.php`
> describes the eleven SketchCMS tables with identical names, types,
> nullability, defaults and lengths. An existing SketchCMS database must
> work with SoulCMS without a migration.

Changing a column name, type, nullability or default is a breaking change
unless it is accompanied by a migration and an update to
`docs/COMPATIBILITY.md`. **Indexes may be added freely** — they do not change
the data model.

Read `docs/ARCHITECTURE.md` before changing the framework, and
`docs/DESIGN.md` before changing the interface.

---

## 2. Setup

```bash
composer install          # only needed for PHPUnit
cp .env.example .env      # then set the database credentials
php bin/soulcms install   # create the schema and the administrator account
php bin/soulcms db:seed   # optional: demo members, posts, chapters, chat
php bin/soulcms serve     # http://127.0.0.1:8000
```

The seeded accounts all use the password `secret123`.

**`APP_KEY` is required in production** (32+ characters). The application
refuses to boot without it — that is intentional, not a bug.

---

## 3. Development workflow

```bash
php bin/soulcms routes:list        # inspect the routing table
php bin/soulcms routes:cache       # compile it (run after changing routes)
php bin/soulcms cache:clear        # drop the compiled views and routes
php bin/soulcms db:verify          # compare the database with the schema
php bin/benchmark 20000            # measure the hot paths
```

There is no build step. The PHP files are the source; the compiled views and
the compiled route table live in `storage/cache/` and are disposable.

**After editing a view partial**, the compiled copy in
`storage/cache/views/` is refreshed automatically (the engine compares the
modification times). If a change does not appear, run
`php bin/soulcms cache:clear`.

---

## 4. Testing

```bash
vendor/bin/phpunit                                   # the whole suite
vendor/bin/phpunit --testsuite=Unit                  # one suite
vendor/bin/phpunit tests/Unit/RouterTest.php         # one file
vendor/bin/phpunit --filter testInjectsRawSql        # one test
```

The front-end behaviour has a separate suite, run in headless Chrome:

```bash
google-chrome --headless --disable-gpu --no-sandbox --dump-dom \
    file://$PWD/tests/js/behaviour.html | grep -E "PASS|FAIL"
```

**Add or update a test for the code you change, even if nobody asked.** The
suite is the only thing standing between a refactor and a silent regression.

Useful test patterns:

* **Database** — build an in-memory SQLite connection
  (`new Connection(new PDO('sqlite::memory:'), Driver::Sqlite)`), create the
  schema with `SchemaBuilder`, and assert on the data.
* **HTTP** — construct a `Request` by hand and call
  `Application::create(dirname(__DIR__, 2))->handle($request)`.
* **Schema** — compare the generated DDL with the upstream SketchCMS DDL;
  `tests/Integration/SketchCmsCompatibilityTest.php` creates the *original*
  SketchCMS DDL and reads it back through SoulCMS.

---

## 5. Code style

* **PHP 8.2+**, `declare(strict_types=1);` in every file.
* **PSR-4** — `SoulCMS\` → `src/`, `SoulCMS\App\` → `app/`. The class name
  must match the file name (`ArticlesRepository` → `ArticlesRepository.php`).
* **One class per file.** Final by default; `final` unless a class is
  designed for extension.
* **Type everything**: parameters, return types and properties. Use `mixed`
  deliberately, not by default.
* **Readonly constructor promotion** for dependencies:
  `public function __construct(private ArticleRepository $articles)`.
* **4 spaces**, one blank line between methods, `declare(strict_types=1);`
  immediately after `<?php`.
* **Comments explain *why*, not *what*.** A comment that restates the code is
  noise.
* **No new runtime dependencies.** The framework must stay installable
  without Composer.
* **No `die()`, `echo` or `var_dump` in `src/` or `app/`.** Return a
  `Response`, throw an exception.
* The interface language is **Vietnamese**. User-facing strings belong in
  `lang/vi.php` (with `lang/en.php` as the fallback catalogue).

---

## 6. Where code goes

| Change | Location |
|---|---|
| A new page or feature | a module in `app/Modules/<Name>/` (Module, Controllers, Repositories) |
| A query | a repository in `app/Modules/<Name>/Repositories/` — never in a controller |
| A framework primitive | `src/` in the matching namespace (`Http/`, `Database/`, `Routing/`, `Support/`, `View/`, `Auth/`, `Foundation/`) |
| A template | `resources/views/<module>/`; shared pieces in `resources/views/partials/` |
| A route | `routes/web.php` for the core, `Module::routes()` for a module |
| A style | `public/assets/css/soulcms.css`, using the existing tokens |
| A message | `lang/vi.php` and `lang/en.php` |

**Layer rules:**

* A controller orchestrates; it owns no SQL and no HTML.
* A repository owns the SQL for one table and returns arrays.
* A side effect that is not part of the requested work is an **event**
  (`src/Events/`) with a listener (`app/Listeners/`), not controller code.
* A write that touches more than one table runs inside
  `$this->connection->transaction(fn () => …)`.

---

## 7. Security invariants

These are tested. Do not weaken them.

1. **Escape on output.** `e()` for anything that is not markup you wrote.
2. **Bind every value.** The query builder quotes identifiers and refuses
   anything that is not a plain name; raw SQL requires an explicit
   `Raw::expr(…)` object.
3. **Never start the session for an anonymous request.** Use
   `SessionManager::peek()`, not `get()`/`has()`.
4. **`redirect()` only accepts same-origin targets** — use `safe_url()`.
5. **Uploads are validated by content**, stored outside the document root
   under a generated name.
6. **A CSRF token on every unsafe method** — `csrf_field()` in the form.
7. **Passwords** are hashed by `PasswordHasher`; never hash them by hand.
8. **The CSP nonce** must be on any `<script>` tag: `nonce="<?= e(csp_nonce()) ?>"`.
9. **No secrets in the repository.** `.env` and `config/local.php` are
   git-ignored.

---

## 8. Gotchas

Things that have already cost time:

* **The route cache is invalidated by a signature** of the route source files.
  It rebuilds itself when they change, so an edit to a module is picked up
  automatically — but if a route seems "missing", run
  `php bin/soulcms routes:cache` and check `php bin/soulcms routes:list`.
* **`DB_CONNECTION` is the driver** (`sqlite`, `mysql`, `mariadb`, `pgsql`);
  `DB_CONNECTION_NAME` selects the connection. `ConnectionConfig` resolves
  both, and the application and the configuration validator must use it —
  never resolve the connection in two places.
* **The SketchCMS schema has no `settings` table.** `SettingsStore` uses the
  table when it exists and falls back to `storage/settings.json` otherwise.
  Do not add a `settings` table to the portable schema.
* **The middleware order is load-bearing.** `CompressionMiddleware` must stay
  *outside* `StaticAssetMiddleware`, because the asset handler answers the
  request directly and anything above it still gets to post-process the
  response.
* **Compression must use `gzencode()`, not `gzcompress()`.** `gzcompress`
  produces a zlib stream; a browser expecting gzip rejects it with
  `ERR_CONTENT_DECODING_FAILED`.
* **`public/.htaccess` compresses only static asset types.** Listing
  `text/html` there would double-compress the PHP responses.
* **The session fingerprint** binds a session to the user agent and the
  accepted language; a mismatch drops the session. Do not remove it.
* **`e()` is not enough for a URL in a `Location` header** — use `safe_url()`.
* **The view cache is disposable.** `storage/cache/views/` can be deleted at
  any time.

---

## 9. Performance

The hot path is: route → middleware → controller → repository → view.

* Do not add a query per rendered item. Batch it — see
  `ArticleRepository::hydrateCounters()`, which replaced an N+1 with two
  queries.
* The session must stay lazy.
* The compiled route table and the compiled views are the reason the request
  path is fast; keep them warm in production.
* `php bin/benchmark 20000` measures the result. The full request is
  dominated by rendering the interface (~0.5 ms), not by the framework.

---

## 10. Before you finish

```bash
vendor/bin/phpunit                    # must be green
php -l <every file you touched>
php bin/soulcms db:verify             # if you touched the schema
php bin/soulcms routes:list           # if you touched the routes
```

Then, if you changed the interface:

```bash
google-chrome --headless --disable-gpu --no-sandbox --dump-dom \
    file://$PWD/tests/js/behaviour.html | grep -E "PASS|FAIL"
```

And update the documentation when the change is visible:

| Change | Document |
|---|---|
| the framework's structure | `docs/ARCHITECTURE.md` |
| the interface | `docs/DESIGN.md` |
| a security measure | `docs/HARDENING.md` |
| a command or a setting | `docs/DEVELOPMENT.md` |
| a SketchCMS feature | `docs/COMPATIBILITY.md` |

---

## 11. Pull request guidelines

* Title: `[area] Short description` — e.g. `[articles] Batch the comment counters`.
* Explain **why** the change is needed, not what the diff shows.
* List the checks you ran (the commands above).
* Note every behaviour change, and every change to the database schema.
* If a SketchCMS feature is implemented or removed, update
  `docs/COMPATIBILITY.md` in the same change.
