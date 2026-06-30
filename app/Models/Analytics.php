<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Read-only analytics queries for the admin dashboard, built entirely from
 * the existing log tables (view_logs, download_logs, activity_logs, media).
 */
final class Analytics
{
    /** Storage + counts grouped by media type. */
    public static function storageByType(): array
    {
        return Database::all(
            "SELECT media_type, COUNT(*) AS items, COALESCE(SUM(file_size),0) AS bytes
             FROM media GROUP BY media_type ORDER BY bytes DESC"
        );
    }

    /**
     * A zero-filled daily count series for the last $days days from a log
     * table. Returns rows [['date'=>'YYYY-MM-DD','label'=>'DD Mon','count'=>n], ...].
     */
    public static function dailySeries(string $table, int $days = 30): array
    {
        $allowed = ['view_logs', 'download_logs', 'activity_logs', 'media'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid table for analytics series');
        }
        $days = max(7, min(90, $days));

        $rows = Database::all(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
             FROM `$table`
             WHERE created_at >= (CURDATE() - INTERVAL " . ($days - 1) . " DAY)
             GROUP BY DATE(created_at)",
            []
        );
        $byDate = [];
        foreach ($rows as $r) $byDate[(string) $r['d']] = (int) $r['c'];

        $series = [];
        $cursor = new \DateTimeImmutable('-' . ($days - 1) . ' days');
        for ($i = 0; $i < $days; $i++) {
            $day = $cursor->modify("+{$i} days");
            $key = $day->format('Y-m-d');
            $series[] = [
                'date'  => $key,
                'label' => $day->format('d M'),
                'count' => $byDate[$key] ?? 0,
            ];
        }
        return $series;
    }

    /** Most active users (by number of views) in the last $days days. */
    public static function activeUsers(int $days = 30, int $limit = 10): array
    {
        $days  = max(1, min(365, $days));
        $limit = max(1, min(50, $limit));
        return Database::all(
            "SELECT u.id, u.name, u.username,
                    COUNT(DISTINCT vl.id) AS views,
                    (SELECT COUNT(*) FROM download_logs dl
                     WHERE dl.user_id = u.id AND dl.created_at >= (NOW() - INTERVAL $days DAY)) AS downloads
             FROM view_logs vl
             JOIN users u ON u.id = vl.user_id
             WHERE vl.created_at >= (NOW() - INTERVAL $days DAY)
             GROUP BY u.id
             ORDER BY views DESC
             LIMIT $limit"
        );
    }

    public static function actionBreakdown(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        return Database::all(
            "SELECT action, COUNT(*) AS c
             FROM activity_logs
             WHERE created_at >= (NOW() - INTERVAL $days DAY)
             GROUP BY action ORDER BY c DESC LIMIT 15"
        );
    }

    /**
     * Rows for CSV export of the activity log.
     * @return array<int,array<string,mixed>>
     */
    public static function activityForExport(int $limit = 5000): array
    {
        $limit = max(1, min(50000, $limit));
        return Database::all(
            "SELECT a.created_at, a.action, a.entity_type, a.entity_id,
                    u.username AS user, a.ip_address
             FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT $limit"
        );
    }

    /** Rows for CSV export of downloads. */
    public static function downloadsForExport(int $limit = 5000): array
    {
        $limit = max(1, min(50000, $limit));
        return Database::all(
            "SELECT dl.created_at, m.title AS media_title, m.media_type,
                    u.username AS user, dl.ip_address, dl.bytes_sent
             FROM download_logs dl
             JOIN media m ON m.id = dl.media_id
             LEFT JOIN users u ON u.id = dl.user_id
             ORDER BY dl.id DESC LIMIT $limit"
        );
    }
}
