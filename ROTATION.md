# Credential rotation

Seven credentials are in this repository's git history. Removing them
from the code — done in phases 1 and 2 — does not remove them from
history, and history is public to anyone who has ever cloned. They
must be changed at the source.

This is the last thing standing between the platform and a 1.0 that
can be defended. It is also the one piece of work no amount of code
can do for you.

> **The order matters.** Several of these are used by processes that
> do not read `.env` — the RADIUS server, cron, the monitoring
> collector. Change the credential first and the service breaks
> before the config catches up. Each section below changes the
> *secondary* copy first and the live one last, so there is never a
> moment where only the broken combination exists.

Work through this with the output of `php scripts/smoke_test.php`
open in another terminal. It is how you find out which of these the
application actually still uses.

---

## Before you start

```bash
# 1. A backup you have proved you can restore. Not optional - several
#    of these steps change database grants.
php scripts/backup_drill.php

# 2. Know what the application currently reads.
grep -o "env('[A-Z_]*'" -r includes/ scripts/ *.php | sort -u
```

If the drill does not pass, stop. Rotating credentials is exactly the
activity most likely to need a restore.

---

## 1. The RADIUS database user (`radius` / `radiuspass`)

The highest-value secret here: it reads and writes `radcheck`,
`radreply` and `radacct`, which is every customer's internet access.

FreeRADIUS reads its own credentials from
`/etc/freeradius/3.0/mods-available/sql`, **not** from this project's
`.env`. Both must change, and FreeRADIUS must be restarted.

```sql
-- Create the replacement alongside the old one.
CREATE USER 'radius_app'@'localhost' IDENTIFIED BY '<new, from a password manager>';
GRANT SELECT, INSERT, UPDATE, DELETE ON radius.* TO 'radius_app'@'localhost';
FLUSH PRIVILEGES;
```

```bash
# .env
DB_USER=radius_app
DB_PASS=<new>

# /etc/freeradius/3.0/mods-available/sql
#   login = "radius_app"
#   password = "<new>"
systemctl restart freeradius

# Prove both halves work before removing the old user.
php scripts/smoke_test.php
radtest <a test user> <their password> localhost 0 <radius secret>
```

Only then:

```sql
DROP USER 'radius'@'localhost';
```

Do not skip the `radtest`. The web panel and FreeRADIUS reach the same
database by different paths, and it is entirely possible for the panel
to work while authentication is broken — which you will discover from
customers rather than from a log.

## 2. The monitoring database user (`monitordb` / `password`)

Same shape, lower stakes — `monitoring/db.php` reads `MON_DB_*`.
Nothing outside this project uses it, so it is a single change.

```bash
# .env
MON_DB_USER=<new>
MON_DB_PASS=<new>
```

## 3. The `admin123` administrator password

Not a config value: a row in `admins`. Anyone who has read the history
knows it, and it is the kind of password that survives in production
for years because nobody remembers it is there.

```bash
php scripts/create_admin.php          # make a replacement first
```

Then log in as the new account, confirm it works, and only then
disable the old one. Deleting the last working admin account locks
everybody out of the panel, and there is no recovery flow.

```sql
SELECT id, username, role, last_login FROM admins ORDER BY last_login;
```

Review that list while you are here. An audit that only rotates the
password you know about leaves the accounts you forgot.

## 4. GenieACS (`ispapi` / `StrongPass123`)

Changed in the GenieACS UI under Admin → Users, then:

```bash
# .env
GENIEACS_USER=<new>
GENIEACS_PASS=<new>
```

GenieACS holds TR-069 control of customer routers. Someone with this
can change any customer's WiFi credentials or push firmware. Treat it
as equal in severity to the RADIUS database, not as an integration
detail.

## 5. Twilio (account SID and auth token)

Rotate the auth token in the Twilio console. The SID is an identifier
rather than a secret, but rotate it too if the console allows a new
subaccount — it appears in the same leaked lines and there is no
benefit to leaving half the pair in place.

```bash
# .env
TWILIO_SID=<new>
TWILIO_TOKEN=<new>
```

A leaked Twilio token is a billing problem before it is a security
problem: it is spent on someone else's SMS traffic, and the first
sign is the invoice.

## 6. Khalti (public and secret keys)

Regenerate in the Khalti merchant dashboard.

```bash
# .env
KHALTI_PUBLIC_KEY=<new>
KHALTI_SECRET_KEY=<new>
```

The secret key verifies payment callbacks. While the old one is
valid, a forged callback can mark an invoice paid that nobody paid
for. Check `payment_transactions` for anything you do not recognise
once the new key is in place.

## 7. eSewa merchant secret

Via eSewa merchant support; the same reasoning as Khalti.

```bash
# .env
ESEWA_MERCHANT_CODE=<new>
ESEWA_SECRET=<new>
```

---

## After

```bash
php scripts/check_secrets.php   # nothing crept back in
php scripts/smoke_test.php      # every page still loads
php scripts/backup_drill.php    # the new grants still permit a dump
```

Then record the date somewhere durable. The next person to audit this
needs to know whether "the credentials in the history" are still live
or merely historical — and that is a one-line note, not an
investigation.

---

## Why the history is not being rewritten

`git filter-repo` could strip these values from every commit. It is
deliberately not recommended here:

- It rewrites every commit hash, so every clone and fork must be
  re-cloned, and any existing fork keeps the old objects anyway.
- GitHub retains unreachable objects; the values stay reachable by
  direct SHA for a long time after the rewrite.
- It gives a strong feeling of having fixed the problem, which is
  dangerous if the credentials were not actually changed.

Rotation makes the history harmless. Rewriting history without
rotation makes it look harmless. Only one of those is a fix.
