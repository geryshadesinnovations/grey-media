<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Media access layer.
 *
 *   - One row in `media` per physical file (UUID + sha256 hash) - never duplicated.
 *   - Categorisation, occasions and tags are pure metadata via junction tables,
 *     so the same file can show up in many places without being copied.
 */
final class Media
{
    public static function find(int $id): ?array
    {
        return Database::first("SELECT * FROM media WHERE id = ?", [$id]);
    }

    public static function findByUuid(string $uuid): ?array
    {
        return Database::first("SELECT * FROM media WHERE uuid = ?", [$uuid]);
    }

    public static function findByHash(string $hash): ?array
    {
        return Database::first("SELECT * FROM media WHERE file_hash = ?", [$hash]);
    }

    /**
     * Every section code a media item belongs to: its primary section plus
     * any section reached through an attached category. Used to authorise
     * viewing/streaming for files that span more than one section (e.g. a
     * file filed under both Graphics and Events).
     *
     * @return array<int,string>
     */
    public static function sectionCodesFor(int $mediaId, int $primarySectionId): array
    {
        $rows = Database::all(
            "SELECT s.code FROM sections s WHERE s.id = ?
             UNION
             SELECT s.code FROM media_categories mc
             JOIN categories c ON c.id = mc.category_id
             JOIN sections s   ON s.id = c.section_id
             WHERE mc.media_id = ?",
            [$primarySectionId, $mediaId]
        );
        return array_values(array_unique(array_map(static fn ($r) => (string) $r['code'], $rows)));
    }

    public static function create(array $data): int
    {
        Database::execute(
            "INSERT INTO media
             (uuid, section_id, company_id, title, description, keywords, media_type, mime_type,
              file_path, file_size, file_hash, thumbnail_path, preview_path,
              hls_master, duration_sec, width, height,
              is_downloadable, uploaded_by, processing_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['uuid'], (int) $data['section_id'],
                $data['company_id'] ?? null,
                $data['title'],
                $data['description'] ?? null, $data['keywords'] ?? null,
                $data['media_type'], $data['mime_type'],
                $data['file_path'], (int) ($data['file_size'] ?? 0),
                $data['file_hash'] ?? null,
                $data['thumbnail_path'] ?? null,
                $data['preview_path'] ?? null,
                $data['hls_master'] ?? null,
                $data['duration_sec'] ?? null,
                $data['width'] ?? null, $data['height'] ?? null,
                (int) ($data['is_downloadable'] ?? 0),
                (int) $data['uploaded_by'],
                $data['processing_status'] ?? 'ready',
            ]
        );
        return Database::lastId();
    }

    public static function attachCategories(int $mediaId, array $categoryIds): void
    {
        Database::execute("DELETE FROM media_categories WHERE media_id = ?", [$mediaId]);
        foreach (array_unique(array_map('intval', $categoryIds)) as $cid) {
            if ($cid <= 0) continue;
            Database::execute(
                "INSERT IGNORE INTO media_categories (media_id, category_id) VALUES (?,?)",
                [$mediaId, $cid]
            );
        }
    }

    public static function attachOccasions(int $mediaId, array $occasionIds): void
    {
        Database::execute("DELETE FROM media_occasions WHERE media_id = ?", [$mediaId]);
        foreach (array_unique(array_map('intval', $occasionIds)) as $oid) {
            if ($oid <= 0) continue;
            Database::execute(
                "INSERT IGNORE INTO media_occasions (media_id, occasion_id) VALUES (?,?)",
                [$mediaId, $oid]
            );
        }
    }

    public static function categoriesFor(int $mediaId): array
    {
        return Database::all(
            "SELECT c.* FROM media_categories mc
             JOIN categories c ON c.id = mc.category_id
             WHERE mc.media_id = ? ORDER BY c.name",
            [$mediaId]
        );
    }

    public static function occasionsFor(int $mediaId): array
    {
        return Database::all(
            "SELECT o.*, g.name AS group_name FROM media_occasions mo
             JOIN occasions o ON o.id = mo.occasion_id
             JOIN occasion_groups g ON g.id = o.group_id
             WHERE mo.media_id = ? ORDER BY o.name",
            [$mediaId]
        );
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['title','description','keywords','is_downloadable','is_featured','is_pinned','download_expiry','company_id'];
        $sets = []; $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $params[] = is_bool($data[$k]) ? (int) $data[$k] : $data[$k];
            }
        }
        if (!$sets) return;
        $params[] = $id;
        Database::execute("UPDATE media SET " . implode(',', $sets) . " WHERE id = ?", $params);
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM media WHERE id = ?", [$id]);
    }

    public static function bumpView(int $id): void
    {
        Database::execute("UPDATE media SET view_count = view_count + 1 WHERE id = ?", [$id]);
    }

    public static function bumpDownload(int $id): void
    {
        Database::execute("UPDATE media SET download_count = download_count + 1 WHERE id = ?", [$id]);
    }

    /**
     * Powerful search/list:
     *   filters:
     *     section_code   string (graphics|events)
     *     category_id    int    (matches that category OR any of its descendants)
     *     occasion_id    int
     *     tag_id         int
     *     media_type     string
     *     q              string (FULLTEXT or LIKE fallback)
     *     uploader_id    int
     *     featured       bool
     *   pagination:
     *     page, per_page (default 24)
     *   sort:
     *     newest|oldest|popular|az
     *   permissions:
     *     allowedSections: ['graphics','events']  (empty -> see nothing)
     */
    public static function search(array $filters, array $allowedSections, string $sort = 'newest', int $page = 1, int $perPage = 24): array
    {
        if (!$allowedSections) return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];

        $where = [];
        $params = [];

        // Section visibility.
        // A media item is visible when EITHER its primary section is allowed,
        // OR it has at least one category that belongs to an allowed section.
        // This means a file filed under both Graphics and Events (or only
        // Events) correctly shows up for an Events user, even though each
        // media row only stores a single "primary" section_id.
        $secMarks = implode(',', array_fill(0, count($allowedSections), '?'));
        $where[] = "(s.code IN ($secMarks) OR EXISTS (
            SELECT 1 FROM media_categories mcv
            JOIN categories cv ON cv.id = mcv.category_id
            JOIN sections sv   ON sv.id = cv.section_id
            WHERE mcv.media_id = m.id AND sv.code IN ($secMarks)
        ))";
        foreach ($allowedSections as $sc) $params[] = $sc; // primary section IN (...)
        foreach ($allowedSections as $sc) $params[] = $sc; // category-section EXISTS IN (...)

        if (!empty($filters['section_code']) && empty($filters['category_id'])) {
            if (in_array($filters['section_code'], $allowedSections, true)) {
                // Match the section either as the primary section OR via an
                // attached category's section, mirroring the visibility rule.
                $where[] = "(s.code = ? OR EXISTS (
                    SELECT 1 FROM media_categories mcs
                    JOIN categories cs ON cs.id = mcs.category_id
                    JOIN sections ss   ON ss.id = cs.section_id
                    WHERE mcs.media_id = m.id AND ss.code = ?
                ))";
                $params[] = $filters['section_code'];
                $params[] = $filters['section_code'];
            }
        }

        $joinCat = '';
        if (!empty($filters['category_id'])) {
            $catId = (int) $filters['category_id'];
            $ids = Category::descendantIds($catId);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            // Match media that has ANY of these category IDs (the selected
            // category + all its descendants) in its media_categories rows.
            // We use EXISTS instead of INNER JOIN to avoid duplicates without
            // needing DISTINCT which can hurt performance.
            $where[] = "EXISTS (
                SELECT 1 FROM media_categories mc
                WHERE mc.media_id = m.id AND mc.category_id IN ($marks)
            )";
            foreach ($ids as $cid) $params[] = $cid;
        }

        $joinOcc = '';
        if (!empty($filters['occasion_id'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM media_occasions mo
                WHERE mo.media_id = m.id AND mo.occasion_id = ?
            )";
            $params[] = (int) $filters['occasion_id'];
        }

        if (!empty($filters['media_type'])) {
            $where[] = "m.media_type = ?";
            $params[] = $filters['media_type'];
        }
        if (!empty($filters['company_id'])) {
            $where[] = "m.company_id = ?";
            $params[] = (int) $filters['company_id'];
        }
        if (!empty($filters['uploader_id'])) {
            $where[] = "m.uploaded_by = ?";
            $params[] = (int) $filters['uploader_id'];
        }
        if (!empty($filters['favorite_user_id'])) {
            // Restrict to media the given user has favorited. Combined with the
            // section-visibility rule above, a user only ever sees their own
            // favorites that they are still allowed to access.
            $where[] = "EXISTS (
                SELECT 1 FROM favorites fv
                WHERE fv.media_id = m.id AND fv.user_id = ?
            )";
            $params[] = (int) $filters['favorite_user_id'];
        }
        if (!empty($filters['featured'])) {
            $where[] = "m.is_featured = 1";
        }
        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $like = '%' . $q . '%';
            $where[] = "(
                m.title LIKE ?
                OR m.description LIKE ?
                OR m.keywords LIKE ?
                OR EXISTS (
                    SELECT 1 FROM media_categories mc2
                    JOIN categories c2 ON c2.id = mc2.category_id
                    WHERE mc2.media_id = m.id
                      AND c2.name LIKE ?
                )
                OR EXISTS (
                    SELECT 1 FROM media_occasions mo2
                    JOIN occasions o2 ON o2.id = mo2.occasion_id
                    WHERE mo2.media_id = m.id
                      AND o2.name LIKE ?
                )
            )";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $params[] = $like; $params[] = $like;
        }

        $orderBy = match ($sort) {
            'oldest'  => 'm.created_at ASC',
            'popular' => 'm.view_count DESC, m.created_at DESC',
            'az'      => 'm.title ASC',
            default   => 'm.is_pinned DESC, m.is_featured DESC, m.created_at DESC',
        };

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $base = "FROM media m
                 INNER JOIN sections s ON s.id = m.section_id
                 WHERE " . implode(' AND ', $where);

        $total = (int) Database::scalar("SELECT COUNT(*) $base", $params);

        $rows = Database::all(
            "SELECT m.*, s.code AS section_code, s.name AS section_name
             $base
             ORDER BY $orderBy
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Related media for the detail page.
     *
     * Primary signal: other items that share one or more categories with the
     * target (ranked by how many they share). If that doesn't fill the quota,
     * we top up with other items of the same media type. Section visibility is
     * always enforced so a user never sees something they can't access.
     *
     * @param array<int,string> $allowedSections
     */
    public static function related(int $mediaId, array $allowedSections, int $limit = 6): array
    {
        if (!$allowedSections) return [];
        $limit = max(1, min(24, $limit));

        $secMarks = implode(',', array_fill(0, count($allowedSections), '?'));
        $visClause = "(s.code IN ($secMarks) OR EXISTS (
            SELECT 1 FROM media_categories mcv
            JOIN categories cv ON cv.id = mcv.category_id
            JOIN sections sv   ON sv.id = cv.section_id
            WHERE mcv.media_id = m.id AND sv.code IN ($secMarks)
        ))";

        // 1) Items that share at least one category with the target.
        $params = [$mediaId];
        foreach ($allowedSections as $sc) $params[] = $sc;
        foreach ($allowedSections as $sc) $params[] = $sc;
        $params[] = $mediaId;

        $rows = Database::all(
            "SELECT m.*, s.code AS section_code, s.name AS section_name,
                    (SELECT COUNT(*) FROM media_categories mc1
                     WHERE mc1.media_id = m.id
                       AND mc1.category_id IN (
                           SELECT category_id FROM media_categories WHERE media_id = ?
                       )
                    ) AS shared
             FROM media m JOIN sections s ON s.id = m.section_id
             WHERE $visClause AND m.id <> ?
             HAVING shared > 0
             ORDER BY shared DESC, m.is_featured DESC, m.created_at DESC
             LIMIT $limit",
            $params
        );

        // 2) Top up with same-type items if we have spare slots.
        if (count($rows) < $limit) {
            $exclude = array_merge([$mediaId], array_map(static fn ($r) => (int) $r['id'], $rows));
            $exMarks = implode(',', array_fill(0, count($exclude), '?'));
            $need = $limit - count($rows);
            $type = (string) Database::scalar("SELECT media_type FROM media WHERE id = ?", [$mediaId]);

            $params2 = [];
            foreach ($allowedSections as $sc) $params2[] = $sc;
            foreach ($allowedSections as $sc) $params2[] = $sc;
            $params2[] = $type;
            foreach ($exclude as $id) $params2[] = $id;

            $more = Database::all(
                "SELECT m.*, s.code AS section_code, s.name AS section_name
                 FROM media m JOIN sections s ON s.id = m.section_id
                 WHERE $visClause AND m.media_type = ? AND m.id NOT IN ($exMarks)
                 ORDER BY m.is_featured DESC, m.view_count DESC, m.created_at DESC
                 LIMIT $need",
                $params2
            );
            $rows = array_merge($rows, $more);
        }

        return $rows;
    }

    /** Stats for admin dashboard */
    public static function statsOverview(): array
    {
        return [
            'media_total'    => (int) Database::scalar("SELECT COUNT(*) FROM media"),
            'media_video'    => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='video'"),
            'media_image'    => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='image'"),
            'media_pdf'      => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='pdf'"),
            'media_ppt'      => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='ppt'"),
            'storage_bytes'  => (int) Database::scalar("SELECT COALESCE(SUM(file_size),0) FROM media"),
            'users_total'    => (int) Database::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1"),
            'sessions_live'  => (int) Database::scalar("SELECT COUNT(*) FROM user_sessions WHERE is_active = 1 AND last_activity_at > (NOW() - INTERVAL 15 MINUTE)"),
            'downloads_30d'  => (int) Database::scalar("SELECT COUNT(*) FROM download_logs WHERE created_at > (NOW() - INTERVAL 30 DAY)"),
            'views_30d'      => (int) Database::scalar("SELECT COUNT(*) FROM view_logs WHERE created_at > (NOW() - INTERVAL 30 DAY)"),
        ];
    }

    public static function topViewed(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        return Database::all(
            "SELECT id, title, media_type, view_count FROM media ORDER BY view_count DESC LIMIT $limit"
        );
    }

    public static function topDownloaded(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        return Database::all(
            "SELECT id, title, media_type, download_count FROM media ORDER BY download_count DESC LIMIT $limit"
        );
    }

    /**
     * Lightweight, search-as-you-type suggestions for the topbar search box.
     *
     * Returns up to $limit hits across three buckets, in priority order:
     *   1. Individual media titles (clickable -> opens that file).
     *      Each row carries count=1 so users see a real number.
     *   2. Categories that match the query, with media_count of how many
     *      files live under that category subtree.
     *   3. Companies that match the query, with media_count.
     *
     * Permission filtering is honoured for media titles via $allowedSections.
     * Categories/companies are global metadata and visible to every signed-in
     * user; clicking a category will then re-filter through allowed sections
     * on the dashboard, so no leak occurs.
     */
    public static function suggest(string $q, array $allowedSections, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) return [];

        $like  = '%' . $q . '%';
        $start = $q . '%';
        $perBucket = max(2, min(8, $limit));

        $out = [];

        // -- 1. Media titles (filtered by section visibility). Each match
        // -- represents one file, so count is 1 (the user sees a concrete
        // -- match they can open). The dashboard search will show the full
        // -- result set when they hit Enter without picking a suggestion.
        if ($allowedSections) {
            $marks = implode(',', array_fill(0, count($allowedSections), '?'));
            $params = array_merge(
                $allowedSections,                 // primary section IN (...)
                $allowedSections,                 // category-section EXISTS IN (...)
                [$start, $like, $like, $start]
            );
            $rows = Database::all(
                "SELECT m.uuid, m.title, m.media_type
                 FROM media m JOIN sections s ON s.id = m.section_id
                 WHERE (s.code IN ($marks) OR EXISTS (
                            SELECT 1 FROM media_categories mcv
                            JOIN categories cv ON cv.id = mcv.category_id
                            JOIN sections sv   ON sv.id = cv.section_id
                            WHERE mcv.media_id = m.id AND sv.code IN ($marks)
                        ))
                   AND (m.title LIKE ? OR m.description LIKE ? OR m.keywords LIKE ?)
                 ORDER BY (m.title LIKE ?) DESC, m.is_featured DESC, m.created_at DESC
                 LIMIT $perBucket",
                $params
            );
            foreach ($rows as $r) {
                $out[] = [
                    'type'  => 'media',
                    'label' => $r['title'],
                    'meta'  => strtoupper((string) $r['media_type']),
                    'count' => 1,
                    'href'  => url('/media/' . $r['uuid']),
                ];
            }
        }

        // -- 2. Categories — show media count under each match (zero-aware).
        if ($allowedSections) {
            $marks = implode(',', array_fill(0, count($allowedSections), '?'));
            $rows = Database::all(
                "SELECT c.id, c.name,
                        (SELECT COUNT(DISTINCT mc.media_id)
                         FROM media_categories mc
                         WHERE mc.category_id = c.id) AS media_count
                 FROM categories c JOIN sections s ON s.id = c.section_id
                 WHERE s.code IN ($marks) AND c.name LIKE ?
                 ORDER BY (c.name LIKE ?) DESC, c.name
                 LIMIT $perBucket",
                array_merge($allowedSections, [$like, $start])
            );
            foreach ($rows as $r) {
                $out[] = [
                    'type'  => 'category',
                    'label' => $r['name'],
                    'meta'  => 'CATEGORY',
                    'count' => (int) $r['media_count'],
                    'href'  => url('/dashboard?category=' . (int) $r['id']),
                ];
            }
        }

        // -- 3. Companies — same idea, tells the user how many files exist
        // -- before they click in.
        $rows = Database::all(
            "SELECT co.id, co.name,
                    (SELECT COUNT(*) FROM media m WHERE m.company_id = co.id) AS media_count
             FROM companies co
             WHERE co.is_active = 1 AND co.name LIKE ?
             ORDER BY (co.name LIKE ?) DESC, co.name
             LIMIT $perBucket",
            [$like, $start]
        );
        foreach ($rows as $r) {
            $out[] = [
                'type'  => 'company',
                'label' => $r['name'],
                'meta'  => 'COMPANY',
                'count' => (int) $r['media_count'],
                'href'  => url('/dashboard?company=' . (int) $r['id']),
            ];
        }

        return array_slice($out, 0, $limit);
    }
}
