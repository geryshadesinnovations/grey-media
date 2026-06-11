-- =====================================================================
-- Migration: notification auto-cleanup.
--
-- Adds `viewed_at` to notifications. When a user first views a notification
-- we stamp viewed_at; a lazy garbage-collect then removes it 24h later for
-- that user only (everyone keeps their own independent timer).
--
-- Additive + idempotent-ish. Safe to run once on an existing database.
-- =====================================================================
USE `greyshades_media`;

ALTER TABLE `notifications`
    ADD COLUMN `viewed_at` DATETIME NULL
        COMMENT 'First time the user viewed it; auto-removed 24h later'
        AFTER `is_read`;

ALTER TABLE `notifications`
    ADD KEY `idx_notif_viewed` (`viewed_at`);
