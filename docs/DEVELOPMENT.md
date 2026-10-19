# Development

How to install, configure, run and test SoulCMS.

---

## 1. Requirements

* PHP **8.2+** with `pdo`, `json`, `mbstring`, `openssl`
* One of: `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`
* Optional: `zlib` (gzip), `brotli` (Brotli), `gd` (images), `apcu` (rate
  limiting), `sodium` (Argon2id)

Composer is optional: the framework has **no runtime dependencies**. It is
only needed for PHPUnit.

---

## 2. Installation

```bash
composer install          # optional – the framework itself has no runtime deps
cp .env.example .env      # then edit the database credentials
php bin/soulcms install   # create the schema + the administrator account
php bin/soulcms db:seed   # optional: demo members, posts and a chat room
php bin/soulcms serve     # http://127.0.0.1:8000
```

`install` is idempotent and creates the SketchCMS schema in the configured
driver. Pass `--force` to drop the existing tables first, and
`--admin=…`, `--password=…`, `--email=…` to customise the administrator.

`db:seed` inserts a small community (7 members with different roles, 10 posts
with chapters, comments and tags, and a live chat) so the interface can be
reviewed with real content in it. The seeded accounts all use the password
`secret123`.

### Compatibility demo with SketchCMS in Docker

This optional stack runs the original SketchCMS v1 installer and MariaDB, then
runs SoulCMS against that same database. It does not overwrite `.env` or expose
the database port on the host.

```bash
cp .env.sketchcms-demo.example .env.sketchcms-demo
# Replace the root/app passwords and APP_KEY with random local values.
docker compose --env-file .env.sketchcms-demo -f compose.compatibility-demo.yaml \\
    up -d --build
```

Open `http://127.0.0.1:8081/install/` and install SketchCMS with database host
`db`, database `sketchcms`, user `soulcms`, and `SKETCHCMS_DB_PASSWORD` from the
environment file. Create the admin as `admin`; use the local value of
`SKETCHCMS_ADMIN_PASSWORD` (the example is for local demos only).

Then seed and verify the database through SoulCMS:

```bash
docker compose --env-file .env.sketchcms-demo -f compose.compatibility-demo.yaml \\
    exec soulcms php bin/soulcms db:verify
docker compose --env-file .env.sketchcms-demo -f compose.compatibility-demo.yaml \\
    exec soulcms php bin/soulcms db:seed --force
```

`--force` replaces the installer's welcome content with SoulCMS's demo posts,
comments, members and chat; use it only on this fresh demo database. SoulCMS is
served at `http://127.0.0.1:8000` and the original SketchCMS at
`http://127.0.0.1:8081`. `docker compose ... down` stops the stack but keeps its
named database volume.

---

## 3. Configuration

Configuration lives in `config/` and is layered:

1. `config/app.php`, `config/database.php` — defaults
2. `config/local.php` — optional local overrides (git-ignored)
3. Environment variables (`.env` or the process environment) — always win

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=soulcms
DB_USERNAME=soulcms
DB_PASSWORD=secret
```

`DB_CONNECTION` names the **driver** of the default connection. A second
connection can be declared under `database.connections` and selected with
`DB_CONNECTION_NAME`.

Switching drivers requires **no code change**: the schema definition is
portable and the DDL is rendered by the matching grammar.

### The variables that matter

| Variable | Purpose |
|---|---|
| `APP_ENV` | `production` enables the strict configuration checks. |
| `APP_DEBUG` | Verbose errors. Keep it off in production. |
| `APP_KEY` | Application secret; at least 32 characters in production. |
| `APP_URL_HOST`, `APP_URL_PATH` | The canonical host and the path the application is served from. |
| `APP_LOCALE` | The interface language (`vi` or `en`). |
| `DB_CONNECTION` | `sqlite`, `mysql`, `mariadb` or `pgsql`. |
| `DB_DATABASE` | The database name, or the file path for SQLite. |
| `APP_PASSWORD_PEPPER` | Optional secret mixed into every password hash. |
| `SESSION_IDLE_TIMEOUT` | Minutes of inactivity before a session dies. |

---

## 4. Project structure

```
soulcms/
├── AGENTS.md               # the guide for coding agents
├── bin/
│   ├── soulcms            # CLI: install, migrate, routes, cache, serve
│   └── benchmark          # micro benchmark of the hot paths
├── config/                # app.php, database.php (+ optional local.php)
├── database/
│   ├── schema/            # portable schema definitions
│   └── seed.php           # demo content
├── docs/                  # architecture, design, hardening, compatibility
├── lang/                  # message catalogues (vi, en)
├── public/                # document root (index.php, assets/)
├── resources/views/       # compiled PHP templates
├── routes/web.php         # application routes
├── src/                   # SoulCMS\ — the framework
│   ├── Auth/              # authentication, password hashing
│   ├── Database/          # connection, query builder, schema grammars
│   ├── Events/            # domain events
│   ├── Foundation/        # application, container, config, modules, events
│   ├── Http/              # request, response, kernel, middleware, session
│   ├── Routing/           # router, route collection, compiler, cache
│   ├── Support/           # helpers, logger, rate limiter, settings, validator
│   └── View/              # template engine and factory
├── app/
│   ├── Listeners/         # event listeners
│   └── Modules/           # the CMS features
│       ├── Articles/      # posts, chapters, comments, tags, files
│       ├── Home/          # landing page, FAQ, robots.txt, health
│       ├── Mail/          # private messages
│       ├── Manager/       # administration panel
│       ├── Shoutbox/      # chat room
│       └── User/          # registration, login, profiles
└── tests/                 # PHPUnit suites + the headless browser suite
```

---

## 5. The CLI

```
php bin/soulcms install              Create the schema and the administrator
php bin/soulcms migrate              Apply the schema (idempotent)
php bin/soulcms db:verify            Compare the database with the schema
php bin/soulcms db:seed              Insert demo content (posts, members, chat)
php bin/soulcms routes:list          Print the routing table
php bin/soulcms routes:cache         Compile the routing table
php bin/soulcms cache:clear          Remove the compiled views and routes
php bin/soulcms serve                Start the development server
```

---

## 6. Testing

```bash
vendor/bin/phpunit                                   # everything
vendor/bin/phpunit --testsuite=Unit                  # one suite
vendor/bin/phpunit tests/Unit/RouterTest.php         # one file
vendor/bin/phpunit --filter testInjectsRawSql        # one test
```

The suites:

| Location | Covers |
|---|---|
| `tests/Unit/` | the router, the schema grammars, the query builder, the validator, the password hasher, the compression middleware, the URL rules, the configuration validator, the event dispatcher |
| `tests/Integration/` | the installer and the SketchCMS column layout, and a compatibility test that creates the **original SketchCMS DDL** and reads it back |
| `tests/js/behaviour.html` | the front-end behaviour, in headless Chrome |

The front-end suite:

```bash
google-chrome --headless --disable-gpu --no-sandbox --dump-dom \
    file://$PWD/tests/js/behaviour.html | grep -E "PASS|FAIL"
```

It covers the theme switch, the drawer, the account menu, the toasts and the
icon-sprite handling.

---

## 7. Performance

```bash
php bin/benchmark 20000
```

```
Route matching (13 paths, 20000 iterations)
Naive: one regex per route                   0.0173 s       0.86 ms/1k      1156069 req/s
SoulCMS: indexed collection                  0.0122 s       0.61 ms/1k      1639344 req/s
Speed-up                                       1.42x faster

Full request handling (route + controller + view + database)
Request lifecycle                           10.0307 s     501.53 ms/1k         1994 req/s
Average latency                                0.50 ms/request
```

The full request figure is dominated by rendering the interface: the shell
alone (top bar, navigation rail, mobile bar) costs about a quarter of a
millisecond. The framework's own work — routing, the middleware stack, the
session handling and the database work — is a small fraction of that.

### Deployment notes

* Prefer pointing the document root at `public/`. If shared hosting does not let
  you change it, the project root can be the document root: the root `.htaccess`
  routes requests through `public/` and denies access to application internals.
  This fallback requires Apache `mod_rewrite` and `.htaccess` overrides.
* Set `APP_DEBUG=false` and provide a strong `APP_KEY`.
* Run `php bin/soulcms routes:cache` after every deployment that changes the
  routes.
* Use OPcache with `opcache.validate_timestamps=0` in production and warm it
  with `routes:cache` plus a warm-up request.
* `storage/` must be writable by the web server and must never be served.

See [`ARCHITECTURE.md`](ARCHITECTURE.md) for the techniques behind the
numbers and [`HARDENING.md`](HARDENING.md) for the deployment checklist.
