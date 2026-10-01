# Changelog

Notable changes to the ISP Platform.

Format loosely follows [Keep a Changelog](https://keepachangelog.com/).
Versions follow [Semantic Versioning](https://semver.org/).

---

## [0.9.0] — 2026-10-01

First tagged release. The repository had no tags before this; `0.9.0`
rather than `1.0.0` is deliberate — see `RELEASE_READINESS.md` for
exactly what is still missing, including the fact that **none of this
has been verified running against a real database**.

### Security — injection and output

- Replaced string-interpolated SQL with prepared statements across the
  application; added two CI scanners that fail the build on a
  reintroduction.
- Introduced an escaping policy (`includes/html.php`: `e()`, `e_attr()`,
  `e_js()`, `e_attr_js()`, `e_url()`, `e_href()`) and applied it across
  88 files.
- Fixed `e(false)` returning `'0'`.

### Security — access control

- `admin_edit.php` had **no role check at all**: any authenticated user
  could request `?id=1` and grant themselves superadmin.
- Added `includes/rbac.php` with `require_role()` and query-level branch
  scoping (`branch_scope()`, `branch_owns()`, `require_branch_access()`,
  `require_customer_access()`), applied to 33 files.
- Removed the duplicate login pages, leaving `index.php` as the only
  file that verifies an admin password.

### Security — CSRF

- Added `includes/csrf.php` and tokens to every state-changing form,
  with a global fetch/XHR shim for AJAX and `api_csrf_check()` for API
  callers.
- Fixed three guards that ran **after** the write they were meant to
  protect, and two POST handlers (`admin.php`, `nas.php`) that had no
  guard at all while appearing protected to a whole-file grep. The one
  in `admin.php` created administrator accounts.
- `scripts/check_csrf.php` now fails when a database write precedes the
  first token check, not merely when a token is absent.

### Security — sessions

- The hardened cookie flags in `config.php.example` never applied:
  25 files called `session_start()` before it, so the admin session
  cookie had no `HttpOnly` and no `SameSite`. All session startup now
  goes through `session_boot()` in `includes/session.php`, enforced by
  CI.
- `session_kill()` on logout and timeout; `session_destroy()` alone had
  left the cookie in the browser.
- Fixed an absolute timeout that did not apply to sessions lacking a
  `login_time`, and an idle timeout that only started on the second
  page load.

### Security — headers and transport

- Added a Content-Security-Policy. The enforced policy keeps
  `'unsafe-inline'` — the codebase has 188 inline handlers — so it is
  **not** an XSS defence; it does enforce `base-uri`, `form-action`,
  `object-src`, `frame-ancestors`, a `script-src` allowlist and no
  `unsafe-eval`. The strict policy ships as report-only.
- Added `nosniff`, `Referrer-Policy`, `Permissions-Policy`,
  `X-Frame-Options`, and HSTS on HTTPS.
- Replaced wildcard CORS on the payment endpoints with an allowlist
  (`includes/cors.php`) and added caller authentication.
- Removed a duplicate CSRF shim that attached the token to
  **cross-origin** requests, handing it to third parties.

### Security — secrets

- Moved credentials to `.env`; added `.env.example` and
  `config.php.example`.
- Stopped rendering stored secrets back into forms: the RADIUS shared
  secret (`nas_edit.php`) and the customer's cleartext PPPoE password
  (`user_edit.php`).
- `disconnect_user.php` no longer passes the RADIUS secret as a
  command-line argument, where `ps` could read it.
- Generated WiFi passwords were `substr(md5($username . time()), 0, 10)`
  — reconstructible from a known username and a guessable timestamp.
  Now `generate_wifi_password()` using `random_int()`.
- `getClientIP()` believed `X-Forwarded-For` from anyone, making every
  IP in the audit log forgeable. It is now honoured only from a
  configured `TRUSTED_PROXIES` address.

### Fixed

- `includes/rbac.php` used `const` inside an `if` block — a parse error
  that made all 114 authenticated pages return 500.
- A literal `?>` inside a `//` comment in `includes/html.php` ended PHP
  mode early.
- `change_password.php` used an undefined `$admin_id`, so the UPDATE
  matched zero rows while the page reported success. Admin password
  changes had never worked.
- `network_topology.php` had a syntax error (optional chaining on the
  left of an assignment) that silently disabled all 465 lines of the
  page's JavaScript.
- `assets/js/disconnect.js` contained raw `<script>` tags.
- `disconnect_user.php` sent the Disconnect-Request to an arbitrary NAS
  rather than the one the customer was connected through, so it did
  nothing on multi-router deployments.
- `ticket_replies` was written with two incompatible column sets.
- Rebuilt a truncated `hotspot/admin/users.php`; fixed seven
  `json_encode($message)` assignments rendered as HTML.
- `work_diary_api.php` wrote uploads to a working-directory-relative
  path and returned `$conn->error` to the client.

### Changed

- All 433 includes made `__DIR__`-relative; removed all 20 `chdir()`
  calls. `$base_path` is now a URL prefix only.
- Extracted CSS and JS out of `network_topology.php` (1754 → 498 lines)
  and `mobile_tech.php` (990 → 167) into `assets/`, which is what made
  the two dead scripts above detectable.

### Added

- `tests/` — a dependency-free runner with **172 assertions**.
- `.github/workflows/ci.yml` — syntax, tests, schema (MySQL 8), CSRF
  coverage and ordering, session startup, include paths, JS syntax,
  secret scanning.
- `AUDIT_REPORT.md` (§1–§21) and `RELEASE_READINESS.md`.

### Known issues

- **Two parallel invoicing systems.** eSewa and Khalti mark
  `billing_invoices` paid, but the invoice the customer receives lives
  in `invoices` and is never marked paid; neither gateway extends the
  customer's expiry. Online payments do not reliably complete the
  transaction. Needs a product decision.
- **Credentials in git history** still require rotation at the source.
- No database migrations, no verified restore, no error monitoring.
- 188 inline event handlers prevent enforcing a strict CSP.
