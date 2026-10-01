# Migrations

```
php scripts/migrate.php status      # what has run, what has not
php scripts/migrate.php up --dry    # print the SQL, change nothing
php scripts/migrate.php up          # apply
```

## Rules

1. **Never edit a migration that has been applied anywhere.** Add a new
   one. The runner records filenames, so an edited file will not re-run,
   and the two databases will silently diverge.
2. **Name them `NNN_description.sql`**, zero-padded. They are applied in
   filename order.
3. **Write them to be re-runnable where practical** — `IF NOT EXISTS`,
   `IF EXISTS`. MySQL commits DDL implicitly, so a migration that fails
   halfway cannot be rolled back by the transaction around it.
4. **Always `--dry` against production first**, and take a backup you
   have actually restored at least once.
5. **No down-migrations.** Rolling a schema change backwards on a live
   database is usually more dangerous than rolling forward with a new
   migration, and an untested down-migration is a trap, not a safety
   net.

## Destructive changes

Anything that drops a column or a table goes in its own migration,
separate from everything else, with a comment explaining what was
checked first. Prefer the two-step: stop writing to the column in one
release, drop it in the next.
