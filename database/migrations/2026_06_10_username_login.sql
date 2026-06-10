-- =====================================================================
-- Migration: switch login identity from email to a custom username.
--
-- Adds a unique `username` to `users` (letters + numbers), backfills it for
-- any existing rows, makes `email` optional, and switches the failed-login
-- throttle log to track the attempted username.
--
-- Safe to run once on an existing database. Run schema.sql/seed.sql instead
-- for a fresh install.
-- =====================================================================
USE `greyshades_media`;

-- 1) Add the username column (nullable first so we can backfill).
ALTER TABLE `users`
    ADD COLUMN `username` VARCHAR(64) NULL COMMENT 'Login identifier - letters & numbers only' AFTER `name`;

-- 2) Backfill: derive a letters+numbers username from the email local-part,
--    stripping anything that is not a letter or digit. Fall back to the row id
--    so the value is always present and unique.
UPDATE `users`
SET `username` = CONCAT(
        COALESCE(NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(`email`, '@', 1), '[^A-Za-z0-9]', ''), ''), 'user'),
        `id`
    )
WHERE `username` IS NULL OR `username` = '';

-- Give the original super admin the clean "admin" username if it is still free.
-- (The derived-table subquery avoids MySQL error 1093 on self-referencing UPDATE.)
UPDATE `users`
SET `username` = 'admin'
WHERE `email` = 'admin@greyshades.local'
  AND 0 = (SELECT c FROM (SELECT COUNT(*) c FROM `users` WHERE `username` = 'admin') AS sub);

-- 3) Enforce NOT NULL + uniqueness on username.
ALTER TABLE `users`
    MODIFY COLUMN `username` VARCHAR(64) NOT NULL COMMENT 'Login identifier - letters & numbers only';

-- 4) Drop the old unique email index and make email optional.
ALTER TABLE `users` DROP INDEX `uq_users_email`;
ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(190) NULL;
ALTER TABLE `users` ADD UNIQUE KEY `uq_users_username` (`username`);

-- 5) Failed-login throttle now records the attempted username.
ALTER TABLE `failed_logins`
    ADD COLUMN `username` VARCHAR(190) NULL AFTER `id`;
UPDATE `failed_logins` SET `username` = `email` WHERE `username` IS NULL;
ALTER TABLE `failed_logins` ADD KEY `idx_fl_username` (`username`);
-- Keep the legacy `email` column nullable for historical rows; new inserts use username.
ALTER TABLE `failed_logins` MODIFY COLUMN `email` VARCHAR(190) NULL;
