# Hardening

The framework is built to be exposed to the internet. The measures below are
part of the core, not optional add-ons.

---

## 1. Authentication and passwords

| Measure | Detail |
|---|---|
| Hashing | Argon2id (64 MiB, 4 rounds) when available, bcrypt otherwise |
| Pepper | Optional `APP_PASSWORD_PEPPER`, mixed into every hash; hashes made before it was enabled keep working, because verification tries the peppered form first and the plain form second |
| Legacy upgrade | The SketchCMS hash (substitution + MD5) is accepted and transparently replaced on the first successful login |
| Timing | "Unknown account", "locked account" and "wrong password" cost the same time and return the same message, so the endpoint cannot be used to enumerate members |
| Lockout | `users.failed_logins` / `users.lock_time` lock an account after a configurable number of failures; the lock expires after the decay window |
| Session fixation | The session id is rotated when the privilege level changes |
| Session lifetime | Absolute timeout (12 h) and idle timeout (30 min) |
| Session fingerprint | The session is bound to a hash of the user agent and the accepted language; a replay from another client drops the session |
| Session identifiers | 48 bytes of entropy, `session.use_strict_mode` on, `use_trans_sid` off |

The pepper is optional on purpose: enabling it later must not lock anybody
out, and a hash that was created without it still verifies.

---

## 2. Transport and headers

* **Content Security Policy** with a per-request nonce. `script-src` is
  `'self' 'nonce-…'`, `script-src-attr` is `'none'`, `object-src` is `'none'`;
  `frame-src` allows only the privacy-enhanced YouTube embed origin for validated
  `[vid]` tags. `base-uri` and `form-action` are `'self'`.
  `style-src` keeps `'unsafe-inline'` because the forum paints each category
  with a derived colour; inline *styles* cannot execute script in any current
  browser, whereas inline *scripts* can — those are locked down with the
  nonce.
* `Strict-Transport-Security` (over HTTPS only), `Permissions-Policy`,
  `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`,
  `X-Content-Type-Options`, `Referrer-Policy`, `Origin-Agent-Cluster`.
* `X-Powered-By` is removed.
* Extra sources can be added with `app.security.csp_extra`.

---

## 3. Injection and traversal

* Every query is a prepared statement with bound parameters.
* The query builder **quotes every identifier** and refuses anything that is
  not a plain identifier. Raw SQL has to be wrapped in an explicit `Raw`
  object, so unquoted fragments can never be reached from user input by
  accident.
* `redirect()` only accepts same-origin targets: an absolute URL pointing at
  another host, a protocol relative URL or a `javascript:` URL is replaced by
  the site root (`safe_url()`).
* Uploads are validated by **content**, not by the file name: the extension is
  checked against an allow list, the real MIME type is sniffed, the size is
  capped and the display name is sanitised. With `STORAGE_DRIVER=s3`, bytes are
  transferred over HTTPS using SigV4 and a deterministic key derived from the
  post/file ids; the bucket should remain private.
* S3 download URLs for attachments are presigned for five minutes and only
  exposed for public or free files, the post author, or an administrator.
  Shared-library objects are scoped to the owning member for listing and are
  publicly downloadable only when marked public and free. File names rendered in
  the interface always go through `e()`.

---

## 4. Cross-site request forgery

* A CSRF token is issued per session, compared with `hash_equals`, and required
  on every method except `GET`, `HEAD`, `OPTIONS` and `TRACE`.
* The token is accepted from the `_token` form field or the `X-CSRF-Token`
  header, so AJAX calls are protected too.
* A route can be exempted by name prefix (`app.security.csrf_exempt`).

---

## 5. Availability

* Rate limiting per client on every mutating request, plus a separate,
  stricter limit on the login endpoint.
* The limiter uses APCu when available and falls back to a lock-protected
  file store.
* The health endpoint (`/health`) reports the database, the storage and the
  cache without leaking a path, a version or a stack trace.

---

## 6. Correctness

* Multi-table writes run inside a **transaction**: a comment and its
  notification, a message and its two inbox updates, a post and its tags.
* Side effects are **events**, not controller code: the comment controller
  dispatches `CommentPosted`, a listener sends the notification.
* The configuration is **validated at boot**: a missing `APP_KEY`, an unknown
  driver or an empty database path stops the application with a readable
  message instead of failing halfway through a request.
* The compiled route table is invalidated automatically when a route file
  changes.
* Input is validated through one `Validator` with a message catalogue, so a
  field can never be checked in two places with two different rules.

---

## 7. Observability

* Every response carries an `X-Request-Id`, and every log line is correlated
  with it.
* Errors are logged with the request id, the exception class and the
  location.
* In production the error page shows a generic message; the detail stays in
  the log.

---

## 8. Secrets

| Variable | Purpose |
|---|---|
| `APP_KEY` | Application secret. Required in production, at least 32 characters. |
| `APP_PASSWORD_PEPPER` | Optional, mixed into every password hash. |
| `DB_PASSWORD` | The database credential. |
| `S3_ACCESS_KEY`, `S3_SECRET_KEY` | Credentials for the configured object-storage bucket. Grant only the required bucket/object permissions. |

`.env` is git-ignored. `config/local.php` is the alternative for secrets that
must not live in a file at all.

---

## 9. Checklist for a new deployment

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] a strong `APP_KEY` (32+ characters)
- [ ] `APP_PASSWORD_PEPPER` set, if the accounts can be re-hashed
- [ ] the document root points at `public/`
- [ ] HTTPS terminated in front of the application
- [ ] `php bin/soulcms routes:cache` after every deployment that changes the
      routes
- [ ] `php bin/soulcms db:verify` passes
- [ ] `storage/` is writable by the web server and not served
- [ ] OPcache enabled with `opcache.validate_timestamps=0`
