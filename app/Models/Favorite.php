<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Per-user favorites ("likes").
 *
 * Each row links one user to one media item. Favorites are strictly personal:
 * every query is scoped by user_id, so a user only ever sees their own
 * collection. Works for any media type (video / image / pdf / ppt) because it
 * references the media row, not a type-specific table.
 */
final class Favorite
{
    public static function isFavorite(int $userId, int $mediaId): bool
    {
        return (bool) Database::scalar(
            "SELECT 1 FROM favorites WHERE user_id = ? AND media_id = ? LIMIT 1",
            [$userId, $mediaId]
        );
    }

    /**
     * Toggle a favorite on/off.
     *
     * @return bool the NEW state - true when the item is now favorited,
     *              false when it was just removed.
     */
    public static function toggle(int $userId, int $mediaId): bool
    {
        if (self::isFavorite($userId, $mediaId)) {
            Database::execute(
                "DELETE FROM favorites WHERE user_id = ? AND media_id = ?",
                [$userId, $mediaId]
            );
            return false;
        }
        // INSERT IGNORE guards against a race / double-submit on the unique key.
        Database::execute(
            "INSERT IGNORE INTO favorites (user_id, media_id) VALUES (?, ?)",
            [$userId, $mediaId]
        );
        return true;
    }

    /**
     * The media ids the user has favorited. Used to pre-mark the heart on
     * cards/detail pages.
     *
     * @return array<int,int>
     */
    public static function idsForUser(int $userId): array
    {
        $rows = Database::all("SELECT media_id FROM favorites WHERE user_id = ?", [$userId]);
        return array_map(static fn ($r) => (int) $r['media_id'], $rows);
    }

    public static function countForUser(int $userId): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM favorites WHERE user_id = ?", [$userId]);
    }
}
