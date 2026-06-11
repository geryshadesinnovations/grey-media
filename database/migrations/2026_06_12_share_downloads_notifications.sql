-- =====================================================================
-- Migration: Share links, Download request/approval, Notifications,
--            Category follows.
--
-- Additive only - creates new tables if they do not yet exist. Safe to run
-- on an existing database. Fresh installs already get these from schema.sql.
-- =====================================================================
USE `greyshades_media`;

CREATE TABLE IF NOT EXISTS `share_links` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token`            CHAR(64) NOT NULL,
    `media_id`         BIGINT UNSIGNED NOT NULL,
    `created_by`       INT UNSIGNED NOT NULL,
    `expires_at`       DATETIME NOT NULL,
    `revoked`          TINYINT(1) NOT NULL DEFAULT 0,
    `access_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `last_accessed_at` DATETIME NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_share_token` (`token`),
    KEY `idx_share_media` (`media_id`),
    KEY `idx_share_creator` (`created_by`),
    KEY `idx_share_expires` (`expires_at`),
    CONSTRAINT `fk_share_media` FOREIGN KEY (`media_id`)   REFERENCES `media`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_share_user`  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `download_requests` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `media_id`    BIGINT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `reason`      VARCHAR(500) NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `token`       CHAR(64) NULL,
    `used_at`     DATETIME NULL,
    `expires_at`  DATETIME NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dr_token` (`token`),
    KEY `idx_dr_media` (`media_id`),
    KEY `idx_dr_user` (`user_id`),
    KEY `idx_dr_status` (`status`),
    CONSTRAINT `fk_dr_media`    FOREIGN KEY (`media_id`)    REFERENCES `media`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dr_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(48) NOT NULL,
    `title`      VARCHAR(190) NOT NULL,
    `body`       VARCHAR(500) NULL,
    `url`        VARCHAR(255) NULL,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user_read` (`user_id`,`is_read`),
    KEY `idx_notif_user_created` (`user_id`,`created_at`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `category_follows` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cf_user_category` (`user_id`,`category_id`),
    KEY `idx_cf_category` (`category_id`),
    CONSTRAINT `fk_cf_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_cf_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
