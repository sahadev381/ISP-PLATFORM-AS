# Release readiness

Honest assessment of what it would take to call this platform
production grade, and what a version number would mean if we put one
on it today.

Written 2026-10-01, after security phases 1–15 (`b39e4e2` … `4a4aa41`).

---

## 1. The most important caveat

**Not one page of this application has been loaded in a browser during
this work, and not one query has been run against a real MySQL
server.**

The sandbox used for the audit has no MySQL, no Docker and no system
PHP. Everything was verified with:

- a real PHP parser (PHP-WASM 8.2) over all 210 files — syntax only
- `node --check` over every JavaScript block
- 159 unit assertions, almost all against pure helper functions
  (`e()`, `csrf_*`, `branch_scope()`, `cors_*`, session/CSP config)
- static scanners for SQL interpolation, CSRF ordering, include paths,
  schema/column agreement

That is enough to prove *absence* of a whole class of defects. It is
**not** enough to prove the application works. A page can parse
perfectly, pass every static check, and still fail on the first
request because a column was renamed or an include is missing at
runtime.

So the honest statement is: *the code has been audited and hardened; it
has not been tested running.* Nobody should put a "production grade"
label on it until it has run on a staging server against a copy of
real data.

---

## 2. What has genuinely improved

| Area | Before | Now |
|---|---|---|
| SQL injection | string-interpolated queries | prepared statements; two CI scanners |
| XSS | raw echo of DB values | `e()`/`e_js()`/`e_url()` policy, 88 files fixed |
| Access control | `admin_edit.php` had no role check at all | `require_role()` + query-level branch scoping |
| CSRF | none | tokens everywhere, ordering enforced in CI |
| Sessions | cookie flags never applied | one hardened `session_boot()`, enforced in CI |
| Response headers | none | CSP, HSTS, nosniff, Referrer-Policy, frame-ancestors |
| Payment API | `Access-Control-Allow-Origin: *`, no auth | allowlist CORS + caller authentication |
| Secrets in UI | RADIUS secret and PPPoE password rendered | never sent to the browser |
| Config | credentials hardcoded | `.env`, with `.example` templates |
| CI | none | lint, tests, schema, CSRF, session, include-path, JS syntax |

---

## 3. Blockers that need a decision from the owner

These are not engineering problems. I cannot guess the answer.

### 3.1 Two parallel invoicing systems

`invoices` (keyed by `username`) and `billing_invoices` (keyed by
`customer_id`) both exist and are both written to. **eSewa and Khalti
mark `billing_invoices` paid; the invoice the customer actually
receives lives in `invoices` and is never marked paid.** Neither
gateway extends the customer's expiry.

This means online payments are, today, not reliably completing the
business transaction. It is the single most serious open issue and it
outranks everything else in this document.

Options: repoint the gateways at `invoices`, or migrate `invoices`
into `billing_invoices` and rewrite `scripts/auto_invoice.php`. Either
is a few hours' work once decided. Deciding requires knowing which
table your accounting actually trusts.

**Run `php scripts/compare_invoice_tables.php` to decide from data
rather than memory.** It is read-only. It prints volumes, date ranges
and money for both tables, which of them anything has written to in
the last 30 days, and - the useful part - how many completed rows in
`payment_transactions` point at an `invoices` row that is still not
marked paid. Each of those is a customer who paid and was probably
never renewed.

Write the answer into this section once you have it, so the next
person does not have to ask the question again.

### 3.2 Credential rotation

These are in the git history and must be considered compromised:
`radius`/`radiuspass`, `monitordb`/`password`, `admin123`, GenieACS
`ispapi`/`StrongPass123`, the Twilio SID and token, the Khalti keys.

Removing them from the code (done) does not remove them from history.
They need rotating at the source. Until that happens the platform is
not production grade regardless of code quality.

**`ROTATION.md` is now the runbook** - ordered so that nothing is
changed before the thing that reads it, with the FreeRADIUS and cron
cases called out because they do not read this project's `.env` and
will keep using the old value until they are restarted.

`scripts/check_secrets.php` runs in CI and fails if any of the leaked
values, or a credential of any recognisable shape, reappears in code.
It cannot un-leak anything; it stops the rotation being undone by the
next 2am debugging session.

---

## 4. Engineering gaps before a 1.0

Ordered by what I would do first.

1. **Staging deploy and a smoke test.** Every page loaded once against
   a copy of production data, by a human or a script. This is the gap
   that matters most, because it is the one the audit structurally
   could not close. Expect it to surface runtime errors; phases 6 and
   11 each found pages that had been broken for a long time without
   anyone noticing.
2. **Database migrations.** There is `database/schema.sql` and no way
   to move an existing database from one version to the next. Right
   now upgrading a live install means hand-written ALTERs. This blocks
   safe releases more than any remaining bug.
3. ~~**A verified backup and restore.**~~ Done. `db_backup.php` now
   takes a consistent snapshot and verifies the dump before pruning;
   `db_restore.php` restores it; `backup_drill.php` proves a restore
   by comparing every table and row count against the source. What
   remains is for the owner to *run* it, and put it in cron.
4. ~~**Error monitoring.**~~ Done in code. `includes/errors.php`
   records every warning, exception and fatal as one JSON line with a
   short reference that is also shown to the user and returned as
   `X-Request-Id`, so a phone call maps to a log record. Secrets are
   redacted on the way in. Set `ERROR_LOG_FILE` and point whatever you
   use for alerting at that file - the capture exists, the *alerting*
   is still a deployment decision nobody has made.
5. ~~**Tests against a database.**~~ Done. 24 assertions run against
   real MySQL 8 in CI. They earned their keep immediately: on their
   first real run they caught a settlement bug that had made the
   entire phase-16 invoice fix a no-op.
6. **Remove the 189 inline `on*` handlers.** Still open, and the
   description above was wrong in three ways worth recording.

   - It is **not sufficient**. At zero handlers the 44 inline
     `<script>` blocks still require `script-src 'unsafe-inline'`
     until each carries `nonce="<?= csp_nonce() ?>"`.
   - It is **not low risk, and there is no half-way state**. A nonce
     and `'unsafe-inline'` cannot coexist: the moment a nonce appears
     the browser ignores `'unsafe-inline'`, so every remaining `on*`
     handler stops working that same day. A partial conversion buys
     no security at all and risks breaking working screens - on pages
     that, per section 1, have still never been rendered in a
     browser. **Do this after staging exists, not before.**
   - **`style-src 'unsafe-inline'` is effectively permanent.** There
     are 1358 `style=""` attributes and a nonce applies to elements,
     not attributes. The honest target is `script-src` without
     `'unsafe-inline'`, `style-src` keeping it. Injected style is a
     real but far weaker vector than injected script.

   The report-only header did *not* "already produce the work list":
   it carried no `report-uri`, so the violations went to the console
   of whoever had devtools open and nowhere else. That is fixed, and
   `scripts/check_inline_handlers.php --report` prints the same work
   list grouped by how hard each one is to remove.

   A budget in `.inline-handler-budget` now fails CI if the count
   rises. Long cleanups lose to new code unless something stops the
   number going up.

---

## 5. Smaller things found while writing this

All four are now fixed; kept here as a record of what they were.

- ~~`user_add.php:63`~~ — the WiFi password was
  `substr(md5($username . time()), 0, 10)`. The username is known and
  `time()` is guessable within a narrow window, so every generated
  password was reconstructible by anyone who knew roughly when the
  account was created. Now `generate_wifi_password()`, which draws
  from `random_int()`.
- ~~`work_diary_api.php:25`~~ — uploaded to a working-directory
  relative path, which resolves differently under cron and FPM. Now
  `__DIR__`-anchored, with a random filename and a `getimagesize()`
  check so an uploaded `.php` cannot masquerade as an image.
- ~~`work_diary_api.php:38`~~ — returned `$conn->error` to the client,
  naming tables and columns. Now logged, not shipped.
- ~~`includes/security.php`~~ — `getClientIP()` trusted
  `HTTP_X_FORWARDED_FOR` unconditionally, so the IP written to
  `login_attempts` and `activity_log` could be set by the client. Now
  the header is only believed when the request actually arrived from
  a proxy listed in `TRUSTED_PROXIES`.

---

## 6. Versioning

**Correction.** An earlier draft of this document said the repository
had no tags. That was wrong — `git tag` was empty locally only because
tags had not been fetched. There is one tag on the remote:

```
v1.0.0 -> 0f0544e  "Initial commit - ISP System v1.0 (without secrets)"
```

It sits on the initial commit, on a different line of history from this
branch, and predates every fix in this document. So a `v1.0.0` already
exists and points at the version of the code that had the wildcard CORS,
the unprotected `admin_edit.php`, the interpolated SQL and the session
cookie with no flags.

That is worth saying plainly: **the thing currently labelled 1.0.0 is
the least safe version of this codebase**, and anyone pulling by tag
gets it. Re-pointing an existing tag is bad practice, so the sensible
move is to leave it and release forward.

Suggested scheme — semantic versioning, with the first number held
back until section 3 is resolved:

| Tag | Meaning | Gate |
|---|---|---|
| `v0.9.0` | audited and hardened, not yet verified running | **tagged 2026-10-01** |
| `v0.9.x` | runtime fixes found during staging | after the smoke test |
| `v1.1.0` | production grade | sections 3 and 4.1–4.4 all closed |

`0.9.0` is lower than the existing `1.0.0`, which is awkward but
honest: it says this code is not yet proven in the way a 1.0 claims.
The first release that has actually run against a real database should
be `1.1.0`, which both supersedes the old tag and does not pretend the
gap never existed.

Tagging this work `1.0.0` instead would have meant the number was a
wish rather than a statement, and the next person reading the repo
would have believed it.

---

## 7. So: when?

The code-side security work is largely done. What is left is mostly
not code:

- **Owner decisions (3.1, 3.2)** — hours of work, but blocked on you.
- **Staging + smoke test (4.1)** — needs a server and real data.
  Unknown number of runtime bugs; phases 6 and 11 suggest there will
  be some.
- **Migrations, backup drill, monitoring (4.2–4.4)** — a few days of
  straightforward work, and they can start now.

A realistic path to `v1.0.0` is: resolve the invoice question, rotate
the credentials, stand up staging, fix what the smoke test finds, add
migrations. None of it is hard. The invoice decision is the one thing
that cannot start without you.
