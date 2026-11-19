-- Temporary schema-migration account (Phase 18: 2026_11_16_000001 .. 2026_11_17_000002).
-- Run as a MariaDB administrator after replacing the placeholders. Never commit the resulting password.
-- Start the client with history disabled so the password is not written to ~/.mysql_history:
--     MYSQL_HISTFILE=/dev/null mysql -h127.0.0.1 -u<admin> -p
-- The application account (DB_USERNAME in .env) keeps only SELECT, INSERT, UPDATE, DELETE.

CREATE USER 'REPLACE_WITH_MIGRATION_ACCOUNT'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_SECRET';

-- Why each privilege is needed (database-scoped only; no global privileges, no GRANT OPTION):
--   SELECT, INSERT          migrations table bookkeeping; Schema::hasTable/hasColumn lookups
--   CREATE                  8 new tables, and AppServiceProvider's CREATE DATABASE IF NOT EXISTS before `migrate`
--   ALTER                   new columns on users / notifications / farmer_organizations; role and farm_type MODIFY
--   INDEX                   unique/secondary indexes on the new columns and tables
--   REFERENCES              24 new foreign keys (enforced by MySQL 8; harmless on MariaDB 10.4)
--   DELETE, DROP            only for `migrate:rollback` (drops the new tables/columns, deletes migration rows)
--   UPDATE, LOCK TABLES     only for restoring a mysqldump backup into this database (the dump uses LOCK TABLES)
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP, LOCK TABLES
  ON `REPLACE_WITH_PRODUCTION_DATABASE`.*
  TO 'REPLACE_WITH_MIGRATION_ACCOUNT'@'127.0.0.1';
FLUSH PRIVILEGES;

-- After the deployment is verified, disable the account (keep it locked rather than granting the app more):
--     ALTER USER 'REPLACE_WITH_MIGRATION_ACCOUNT'@'127.0.0.1' ACCOUNT LOCK;
-- or remove it entirely:
--     DROP USER 'REPLACE_WITH_MIGRATION_ACCOUNT'@'127.0.0.1';
