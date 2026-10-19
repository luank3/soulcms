# SoulCMS

A fast, modern, **multi-database** content management system for PHP 8.2+.

SoulCMS is a ground-up rewrite of [SketchCMS](https://github.com/pentanoic/2026_SketchCMS)
that keeps the **exact same database schema** — so an existing SketchCMS
database is served by SoulCMS without any migration — while replacing the
framework internals with a modern, high-performance architecture.

```
┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌────────────────┐
│  public/     │→  │  Http Kernel │→  │   Router    │→  │  Controllers   │
│  index.php   │   │  + Pipeline  │   │  (indexed)  │   │  + Repositories│
└──────────────┘   └──────────────┘   └──────────────┘   └────────┬───────┘
                                                                  │
                          ┌───────────────────────────────────────▼────────┐
                          │  Database abstraction (PDO)                     │
                          │  SQLite · MySQL · MariaDB · PostgreSQL          │
                          └────────────────────────────────────────────────┘
```

---

## Highlights

| Area | SoulCMS | SketchCMS |
|---|---|---|
| Databases | SQLite, MySQL, MariaDB, PostgreSQL | MySQL only |
| Routing | Indexed route table, static map + segment buckets, route cache | Linear scan over one regex per route |
| HTTP layer | Immutable request/response, middleware pipeline | Globals, `die()`-based error handling |
| Structure | PSR-4, `SoulCMS\` namespace, strict types, DI container | Global classes, custom autoloader |
| Schema | Portable definition rendered per driver | MySQL-only DDL strings |
| Security | CSP with nonces, Argon2id, lockout, hashed identifiers | MD5 passwords, no CSP |
| Performance | Lazy sessions, conditional GET, compression, prepared statements | Session always started, no caching |
| Dependencies | Zero runtime dependencies (Composer is optional) | Plates, PHPMailer, Highlight.php |

---

## Quick start

```bash
composer install          # optional – the framework itself has no runtime deps
cp .env.example .env      # then edit the database credentials
php bin/soulcms install   # create the schema + the administrator account
php bin/soulcms db:seed   # optional: demo members, posts and a chat room
php bin/soulcms serve     # http://127.0.0.1:8000
```

**Requirements:** PHP 8.2+ with `pdo`, `json`, `mbstring`, `openssl`, and one of
`pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`.

---

## Documentation

| Document | Contents |
|---|---|
| [Architecture](docs/ARCHITECTURE.md) | the kernel, the router, the database layer, modules, events, the view engine, the container |
| [Design](docs/DESIGN.md) | the design direction, the tokens, the layout, the components, accessibility |
| [Hardening](docs/HARDENING.md) | passwords, sessions, headers, injection, availability, the deployment checklist |
| [Development](docs/DEVELOPMENT.md) | installation, configuration, the CLI, testing, performance, deployment |
| [Compatibility](docs/COMPATIBILITY.md) | the feature-by-feature comparison with SketchCMS, including what is *not* implemented yet |
| [AGENTS.md](AGENTS.md) | the guide for coding agents working on this repository |

---

## Project structure

```
soulcms/
├── bin/               # CLI and benchmark
├── config/            # application and database configuration
├── database/          # portable schema definitions and the demo seeder
├── docs/              # the documentation above
├── lang/              # message catalogues (vi, en)
├── public/            # the document root and the assets
├── resources/views/   # compiled PHP templates
├── routes/web.php     # the application routes
├── src/               # SoulCMS\ — the framework
├── app/               # modules and event listeners
└── tests/             # the PHPUnit suites and the browser suite
```

---

## Testing

```bash
vendor/bin/phpunit
```

```
OK (75 tests, 197 assertions)
```

---

## License

MIT.
