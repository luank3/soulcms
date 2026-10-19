# Architecture

How a request travels through SoulCMS, and how the pieces fit together.

```
public/index.php
      │
      ▼
Application ── boots the container, the configuration, the modules
      │
      ▼
Kernel ── runs the global middleware pipeline
      │
      ▼
RoutingHandler ── applies the URL rules, matches a route
      │
      ▼
Controller ── orchestrates; it owns no SQL
      │
      ▼
Repository ── owns the SQL for one table
      │
      ▼
Connection (PDO) ── SQLite · MySQL · MariaDB · PostgreSQL
```

---

## 1. The HTTP kernel and the middleware pipeline

`SoulCMS\Http\Kernel` is a front controller. The request passes through the
global middleware stack before it reaches the router; a route may append its
own middleware.

```php
// config/app.php — outermost first
'middleware' => [
    SoulCMS\Http\Middleware\RequestIdMiddleware::class,
    SoulCMS\Http\Middleware\SecurityHeadersMiddleware::class,
    SoulCMS\Http\Middleware\CompressionMiddleware::class,
    SoulCMS\Http\Middleware\StaticAssetMiddleware::class,
    SoulCMS\Http\Middleware\CsrfMiddleware::class,
    SoulCMS\Http\Middleware\RateLimitMiddleware::class,
    SoulCMS\Http\Middleware\ConditionalGetMiddleware::class,
],
```

**Order matters.** `CompressionMiddleware` must sit *outside*
`StaticAssetMiddleware`, because the asset handler answers the request
directly and anything above it still gets to post-process the response.

Route level middleware is declared with the route:

```php
$router->add('/manager', [ManagerController::class, 'dashboard'], 'GET', 'manager.dashboard', [
    SoulCMS\Http\Middleware\RequireAdminMiddleware::class,
]);
```

`Request` and `Response` are **immutable** value objects. The router decorates
the request with the route parameters, and the controller reads them through
`$this->param('id')` (or the `$request` argument of the action).

Exceptions are mapped to HTTP responses centrally by
`SoulCMS\Http\ErrorHandler`:

* `HttpException` subclasses (`NotFoundHttpException`, `ForbiddenHttpException`,
  `MethodNotAllowedHttpException`, `ValidationException`, …) carry a status code
  and headers;
* anything else is logged and rendered as a 500.

The handler is called **inside** the pipeline (by `RoutingHandler`), so an error
response receives the same treatment as a successful one — compression, security
headers, conditional GET.

---

## 2. The router

The router has the biggest performance impact, so it avoids a linear scan.

* a **static map** — `method => path => Route`, an O(1) hash lookup;
* **dynamic buckets** — routes with parameters, grouped by the number of path
  segments and additionally protected by a *static prefix* check, so a mismatch
  is rejected with a string comparison instead of a regular expression.

```php
$router->get('/articles/{slug:[a-zA-Z0-9\-_]+}.html', [ArticleController::class, 'postDetail'], 'articles.show');
$router->add('/articles/post-{:id}/upload', [ArticleController::class, 'fileUpload'], 'GET|POST', 'articles.upload');
```

The SketchCMS placeholder syntax (`{:id}`, `{CategorySlug:[a-z]+}`) is
supported, so the original route table can be ported verbatim.

Additional properties:

* specificity ordering — `/articles/{slug}.html` wins over `/articles/{slug}`;
* `405 Method Not Allowed` with a correct `Allow` header;
* `HEAD` falls back to `GET`;
* named routes and `route('articles.show', ['slug' => 'x'])` URL generation;
* **route cache** — the compiled table is stored as a plain PHP array in
  `storage/cache/routes.php` and restored with a single `require`. The cache
  carries a signature of the source files, so it is invalidated automatically
  when a route file changes.

### URL rules

Administrators can rewrite a public path onto another one, or block a path
outright. The rules live in `SoulCMS\Http\UrlRules` and are applied by
`RoutingHandler` before the route table is consulted.

```json
{
  "rewrite": { "/tin-tuc": { "target": "/articles?category=2", "method": "GET|POST" } },
  "disable": ["/register"]
}
```

---

## 3. The database layer

`SoulCMS\Database\Connection` wraps PDO and normalises the differences
between the drivers. `SoulCMS\Database\Query\QueryBuilder` renders portable SQL
(`LIMIT 10 OFFSET 20` vs. MySQL's `LIMIT 20, 10`, quoting, aliases, joins,
`whereIn`, pagination).

**Identifiers are always quoted.** The builder refuses anything that is not a
plain identifier; raw SQL has to be wrapped in an explicit `Raw` object, so
unquoted fragments can never be reached from user input:

```php
$query->select(Raw::expr('COUNT(posts.id) AS count'));
$query->whereRaw(Raw::expr('LOWER(name) = LOWER(:name)'), ['name' => $name]);
```

The schema layer is split in two:

* `SoulCMS\Database\Schema\Column` / `TableDefinition` — a **driver neutral**
  description of the tables;
* `SoulCMS\Database\Schema\Grammar\*` — one grammar per driver that renders
  the DDL.

| Concept | SQLite | MySQL / MariaDB | PostgreSQL |
|---|---|---|---|
| Auto increment | `INTEGER PRIMARY KEY AUTOINCREMENT` | `INT UNSIGNED AUTO_INCREMENT` | `SERIAL` |
| Text | `TEXT` | `TEXT` | `TEXT` |
| JSON | `TEXT` | `JSON` | `JSONB` |
| Boolean | `INTEGER` | `TINYINT(1)` | `BOOLEAN` |
| Table options | – | `ENGINE=InnoDB CHARSET=utf8mb4` | – |
| Pagination | `LIMIT n OFFSET m` | `LIMIT m, n` | `LIMIT n OFFSET m` |
| Upsert | `ON CONFLICT` | `ON DUPLICATE KEY` | `ON CONFLICT` |
| `lastInsertId` | native | native | `RETURNING` |

Identifiers are always quoted, so reserved words such as `condition` (used by
`articles_file`) work on every driver.

`SoulCMS\Database\ConnectionConfig` is the single place that decides *which*
connection is in use; the application and the configuration validator both ask
it, so they can never disagree.

---

## 4. Schema compatibility with SketchCMS

`database/schema/sketchcms.php` describes the eleven SketchCMS tables —
`chat`, `articles_category`, `articles_chap`, `articles_cmt`, `articles_file`,
`articles_tags`, `articles_post_tags`, `articles_post`, `mail`, `users`,
`users_waifu` — with the **same table names, column names, types, nullability,
defaults and lengths** as the original.

```php
// database/schema/sketchcms.php
'articles_post' => (new TableDefinition('articles_post'))
    ->column(Column::id())
    ->column(Column::make('title', Column::TYPE_TEXT))
    ->column(Column::make('slug', Column::TYPE_STRING)->length(255))
    // …
```

Consequences:

* an existing SketchCMS MySQL database works with SoulCMS as-is;
* the same schema can be created on SQLite, MariaDB or PostgreSQL;
* `php bin/soulcms db:verify` compares the live database with the definition.

SketchCMS conventions that are preserved for compatibility:

* the `users.level` scale (`0` member, `120` contributor, `126` administrator,
  `127` developer, negative = banned);
* the `cuid` / `cups` "remember me" cookies and the `users.remember_token`
  column;
* the `mail.sender_receiver` convention (`sender_receiver`, lowercased and
  joined with `_`) and the dot separated `users.mail_list` / `users.new_mail`;
* the `articles_post_tags` composite primary key.

The only additions are **indexes**, which do not change the data model.

See [`COMPATIBILITY.md`](COMPATIBILITY.md) for the full feature comparison.

---

## 5. Modules

A module is a self-contained feature: routes, controllers, repositories and
views.

```php
namespace SoulCMS\App\Modules\Articles;

final class Module extends \SoulCMS\Foundation\Module\Module
{
    public function name(): string { return 'articles'; }
    public function title(): string { return 'Bài viết'; }
    public function description(): string { return 'Bài viết, chương, bình luận…'; }

    public function routes(Router $router): void
    {
        $router->add('/articles', [ArticleController::class, 'index'], 'GET', 'articles.index');
    }
}
```

Modules are discovered in `app/Modules/*/Module.php` and can be enabled or
disabled from the administration panel. Because the SketchCMS schema has no
`settings` table, the module state is stored in the `settings` table when it
exists and falls back to `storage/settings.json` otherwise.

Repositories own the SQL; controllers only orchestrate. The container
autowires them by type hint, so a controller is a plain class:

```php
final class ArticleController extends Controller
{
    public function __construct(
        Container $container,
        private ArticleRepository $articles,
        private UserRepository $users,
    ) {
        parent::__construct($container);
    }
}
```

---

## 6. Events and transactions

Side effects are **events**, not controller code. The comment controller stores
the comment and dispatches an event; a listener sends the notification.

```php
// app/Listeners/NotifyPostAuthor.php
$dispatcher->listen(CommentPosted::class, NotifyPostAuthor::class);
```

Multi-table writes run inside a **transaction**, so a half-written state is
never visible:

| Write | Unit of work |
|---|---|
| a comment | the comment + the notification it triggers |
| a private message | both copies + the two inbox updates |
| a post | the post + its tags |

Events live in `src/Events/`, listeners in `app/Listeners/`, and the
dispatcher in `src/Foundation/Events/Dispatcher.php`.

---

## 7. The view layer

`SoulCMS\View\Engine` compiles the templates of `resources/views` into plain
PHP files inside `storage/cache/views` and executes them. There is no template
language to learn — templates are PHP with a handful of helpers:

```php
<h1><?= e($page_title) ?></h1>
<?php foreach ($posts as $post): ?>
    <a href="<?= e(url('/articles/' . $post['id'] . '-' . $post['slug'] . '.html')) ?>">
        <?= e($post['title']) ?>
    </a>
<?php endforeach; ?>
```

The compiled path is remembered per request, so a template rendered many times
(a row per post, for instance) does not touch the file system.

Helpers: `e()`, `url()`, `asset()`, `route()`, `redirect()`, `safe_url()`,
`csrf_token()`, `csrf_field()`, `config()`, `request()`, `view()`, `auth()`,
`session()`, `old()`, `flash()`, `paging()`, `format_bytes()`, `time_ago()`,
`slugify()`, `icon()`, `csp_nonce()`, `member_role()`, `member_chip()`,
`avatar_svg()`, `marker_style()`.

---

## 8. Authentication

`SoulCMS\Auth\AuthManager` supports the session flow and the SketchCMS
remember-me cookies. Passwords are hashed with Argon2id (bcrypt as a fallback);
the legacy SketchCMS hash (a character substitution followed by MD5) is still
accepted and **transparently upgraded** on the first successful login.

The session is only started when it is actually needed — an anonymous visitor
never pays for the session file lock. `SessionManager::peek()` reads a value
without starting the session; that is what the authentication layer uses.

See [`HARDENING.md`](HARDENING.md) for the full list of measures.

---

## 9. The container

`SoulCMS\Foundation\Container` is a small PSR-11-style container with
constructor autowiring:

* `get()` returns a shared singleton, `make()` a fresh instance;
* services are registered in `Application::registerServices()`;
* the container resolves itself, so a class may type-hint `Container`.

Circular dependencies are detected and reported with the chain that caused
them.

---

## 10. Boot sequence

`Application::__construct()` performs, in order:

1. `Paths::setBase()` — the path registry.
2. `Env::load()` — the `.env` file.
3. the configuration is composed from `config/*.php` and `config/local.php`;
4. `ConfigValidator::validate()` — **fail fast** on an unusable configuration;
5. `Lang::setLocale()` — the interface language;
6. the services are registered (lazily instantiated).

`Application::handle()` boots the modules and runs the kernel.
