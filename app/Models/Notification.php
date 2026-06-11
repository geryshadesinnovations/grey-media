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

    public static function markRead(int $id, int $userId): void
    {
        Database::execute(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
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
        Database::execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$userId]);
    }
}
