-- =====================================================================
-- Migration: per-user Favorites ("likes").
--
-- Additive only - creates the `favorites` table if it does not yet exist.
-- Safe to run on an existing database. Fresh installs already get this table
-- from schema.sql.
-- =====================================================================
USE `greyshades_media`;

CREATE TABLE IF NOT EXISTS `favorites` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `media_id`   BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fav_user_media` (`user_id`,`media_id`),
    KEY `idx_fav_user` (`user_id`),
    KEY `idx_fav_media` (`media_id`),
    CONSTRAINT `fk_fav_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_fav_media` FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
