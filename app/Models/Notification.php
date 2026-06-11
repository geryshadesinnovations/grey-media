<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Per-user in-app notifications (the bell).
 *
 * Notifications are created by the system when relevant events happen:
 *   - new uploads in a category the user follows
 *   - share link events
 *   - download request approvals / rejections (and new requests, for admins)
 */
final class Notification
{
    /** Create a single notification. Failures are swallowed (never break a request). */
    public static function create(int $userId, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        try {
            Database::execute(
                "INSERT INTO notifications (user_id, type, title, body, url) VALUES (?,?,?,?,?)",
                [$userId, mb_substr($type, 0, 48), mb_substr($title, 0, 190), $body !== null ? mb_substr($body, 0, 500) : null, $url]
            );
        } catch (\Throwable $e) {
            error_log('[Notification] ' . $e->getMessage());
        }
    }

    /** Fan out the same notification to many users (e.g. category followers). */
    public static function createMany(array $userIds, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) self::create($uid, $type, $title, $body, $url);
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    public static function recent(int $userId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        return Database::all(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT $limit",
            [$userId]
        );
    }

    public static function all(int $userId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        return Database::all(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT $limit",
            [$userId]
        );
    }

    /** Notifications of a single type (e.g. 'upload'). */
    public static function ofType(int $userId, string $type, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        return Database::all(
            "SELECT * FROM notifications WHERE user_id = ? AND type = ? ORDER BY id DESC LIMIT $limit",
            [$userId, $type]
        );
    }

    /** Notifications NOT of the given type (everything except uploads). */
    public static function excludingType(int $userId, string $type, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        return Database::all(
            "SELECT * FROM notifications WHERE user_id = ? AND type <> ? ORDER BY id DESC LIMIT $limit",
            [$userId, $type]
        );
    }

    public static function markRead(int $id, int $userId): void
    {
        // is_read flips immediately; viewed_at is stamped only on the FIRST
        // view and starts this user's 24h auto-removal timer.
        Database::execute(
            "UPDATE notifications
             SET is_read = 1, viewed_at = COALESCE(viewed_at, NOW())
             WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }

    public static function find(int $id, int $userId): ?array
    {
        return Database::first(
            "SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1",
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::execute(
            "UPDATE notifications
             SET is_read = 1, viewed_at = COALESCE(viewed_at, NOW())
             WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Lazy garbage-collect: permanently remove this user's notifications that
     * were first viewed more than 24 hours ago. Each user has an independent
     * timer because viewed_at is per-row (and rows are per-user). Called on
     * read paths (feed/index) so no cron is required.
     */
    public static function purgeExpired(int $userId): void
    {
        try {
            Database::execute(
                "DELETE FROM notifications
                 WHERE user_id = ? AND viewed_at IS NOT NULL
                   AND viewed_at < (NOW() - INTERVAL 24 HOUR)",
                [$userId]
            );
        } catch (\Throwable $e) {
            error_log('[Notification::purgeExpired] ' . $e->getMessage());
        }
    }
}
