# Temporary migration account for iClimate (Phase 18)

**For:** the MariaDB administrator of the iClimate server (127.0.0.1:3306, MariaDB 10.4).
**Why:** six schema migrations must be applied to `iclimate_staging_20261103_fix3`. The application account
(`iclimate_app`) is deliberately limited to SELECT, INSERT, UPDATE, DELETE and must stay that way, so a separate,
temporary account is needed for about one hour. Nothing else on the server changes.

## 1. Create the account (about 5 minutes)

1. Pick the account name `iclimate_migrator` and generate a strong password in your password manager.
   Do not type it into a shell command, ticket, chat, email or file.
2. Open the MariaDB client with history turned off. `-p` makes the client prompt for your admin password:

   ```sh
   MYSQL_HISTFILE=/dev/null mysql -h127.0.0.1 -u<your_admin_account> -p
   ```

3. Paste these statements into that client, typing the password where shown (from `mariadb-migration-account.sql`):

   ```sql
   CREATE USER 'iclimate_migrator'@'127.0.0.1' IDENTIFIED BY '<password from the password manager>';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP, LOCK TABLES
     ON `iclimate_staging_20261103_fix3`.*
     TO 'iclimate_migrator'@'127.0.0.1';
   FLUSH PRIVILEGES;
   ```

   It is limited to this one database and to connections from 127.0.0.1. There are no global privileges and no GRANT OPTION.
   Each privilege is explained in `mariadb-migration-account.sql`.

## 2. Check it (in the same client)

```sql
SHOW GRANTS FOR 'iclimate_migrator'@'127.0.0.1';
SHOW GRANTS FOR 'iclimate_app'@'127.0.0.1';
```

Expected:

- `iclimate_migrator`: a `GRANT USAGE ON *.*` line and **exactly one** line granting the ten privileges above on
  `` `iclimate_staging_20261103_fix3`.* ``. Nothing else.
- `iclimate_app` (must be **unchanged**): `SELECT, INSERT, UPDATE, DELETE ON iclimate_staging_20261103_fix3.*`, plus
  its existing `ALL PRIVILEGES ON iclimate_rt_audit_20261111.*` line (a test copy of the database).

Then `exit`.

## 3. Hand over the credentials

Share the account name and password with the person running the deployment through the password manager, or in
person. Do not send them by chat, email, ticket or commit. The deployment enters them at a hidden prompt; they are
never written to `.env` or to disk.

## 4. After the deployment (same day)

When the operator confirms the migration is done, lock the account:

```sh
MYSQL_HISTFILE=/dev/null mysql -h127.0.0.1 -u<your_admin_account> -p
```
```sql
ALTER USER 'iclimate_migrator'@'127.0.0.1' ACCOUNT LOCK;
SHOW CREATE USER 'iclimate_migrator'@'127.0.0.1';   -- must end with ACCOUNT LOCK
```

Keep it locked for a few days in case a recovery from backup is needed: the restore uses this account. Then remove it:

```sql
DROP USER 'iclimate_migrator'@'127.0.0.1';
```

Also delete the password from the password manager once the account is dropped.
