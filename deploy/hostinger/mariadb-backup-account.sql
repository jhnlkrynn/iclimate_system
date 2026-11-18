-- Run as an administrator on the target MariaDB server after replacing the
-- placeholders. Never commit the resulting password or run this against the
-- local staging database without explicit authorization.
CREATE USER 'REPLACE_WITH_BACKUP_ACCOUNT'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_SECRET';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT
  ON `REPLACE_WITH_PRODUCTION_DATABASE`.*
  TO 'REPLACE_WITH_BACKUP_ACCOUNT'@'127.0.0.1';
FLUSH PRIVILEGES;

-- For any non-transactional tables that must be dumped consistently, the DBA
-- must separately approve LOCK TABLES or convert those tables to InnoDB first.
-- Do not grant SUPER, FILE, PROCESS, GRANT OPTION, or global privileges for
-- the normal InnoDB --single-transaction backup path.

