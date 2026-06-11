<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Users can follow categories to get notified when new media is uploaded
 * into them (or any of their descendant categories).
 */
final class CategoryFollow
{
    public static function isFollowing(int $userId, int $categoryId): bool
    {
        return (bool) Database::scalar(
            "SELECT 1 FROM category_follows WHERE user_id = ? AND category_id = ? LIMIT 1",
            [$userId, $categoryId]
        );
    }

    /** Toggle follow state. Returns the new state (true = now following). */
    public static function toggle(int $userId, int $categoryId): bool
    {
        if (self::isFollowing($userId, $categoryId)) {
            Database::execute(
                "DELETE FROM category_follows WHERE user_id = ? AND category_id = ?",
                [$userId, $categoryId]
            );
            return false;
        }
        Database::execute(
            "INSERT IGNORE INTO category_follows (user_id, category_id) VALUES (?,?)",
            [$userId, $categoryId]
        );
        return true;
    }

    /** @return array<int,int> category ids the user follows */
    public static function followedIds(int $userId): array
    {
        $rows = Database::all("SELECT category_id FROM category_follows WHERE user_id = ?", [$userId]);
        return array_map(static fn ($r) => (int) $r['category_id'], $rows);
    }

    public static function followedList(int $userId): array
    {
        return Database::all(
            "SELECT c.id, c.name, s.code AS section_code
             FROM category_follows cf
             JOIN categories c ON c.id = cf.category_id
             JOIN sections s   ON s.id = c.section_id
             WHERE cf.user_id = ? ORDER BY c.name",
            [$userId]
        );
    }

    /**
     * User ids that follow ANY of the given category ids (deduped).
     *
     * @param array<int,int> $categoryIds
     * @return array<int,int>
     */
    public static function followerUserIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if (!$categoryIds) return [];
        $marks = implode(',', array_fill(0, count($categoryIds), '?'));
        $rows = Database::all(
            "SELECT DISTINCT user_id FROM category_follows WHERE category_id IN ($marks)",
            $categoryIds
        );
        return array_map(static fn ($r) => (int) $r['user_id'], $rows);
    }
}
