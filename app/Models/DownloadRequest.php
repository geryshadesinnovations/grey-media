<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Download request & approval workflow.
 *
 * When a media item's direct download is disabled, a user submits a request.
 * An admin approves it, which mints a single-use token bound to that exact
 * user. The token works once: after a successful download `used_at` is set and
 * the grant is dead. No other user can use the token (the download route also
 * checks the logged-in user matches the request owner).
 */
final class DownloadRequest
{
    /** The most recent request a user has for a given media item, or null. */
    public static function latestFor(int $userId, int $mediaId): ?array
    {
        return Database::first(
            "SELECT * FROM download_requests
             WHERE user_id = ? AND media_id = ?
             ORDER BY id DESC LIMIT 1",
            [$userId, $mediaId]
        );
    }

    public static function hasPending(int $userId, int $mediaId): bool
    {
        return (bool) Database::scalar(
            "SELECT 1 FROM download_requests
             WHERE user_id = ? AND media_id = ? AND status = 'pending' LIMIT 1",
            [$userId, $mediaId]
        );
    }

    public static function create(int $userId, int $mediaId, string $reason = ''): int
    {
        Database::execute(
            "INSERT INTO download_requests (media_id, user_id, reason) VALUES (?,?,?)",
            [$mediaId, $userId, ($reason !== '' ? mb_substr($reason, 0, 500) : null)]
        );
        return Database::lastId();
    }

    public static function find(int $id): ?array
    {
        return Database::first("SELECT * FROM download_requests WHERE id = ?", [$id]);
    }

    /**
     * Approve a request: set status, mint a single-use token, set an expiry
     * window for that token. Returns the generated token.
     */
    public static function approve(int $id, int $adminId, int $ttlDays = 7): string
    {
        $token   = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable("+{$ttlDays} days"))->format('Y-m-d H:i:s');
        Database::execute(
            "UPDATE download_requests
             SET status = 'approved', token = ?, expires_at = ?, used_at = NULL,
                 reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?",
            [$token, $expires, $adminId, $id]
        );
        return $token;
    }

    public static function reject(int $id, int $adminId): void
    {
        Database::execute(
            "UPDATE download_requests
             SET status = 'rejected', token = NULL, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?",
            [$adminId, $id]
        );
    }

    /**
     * Resolve a single-use download token to its request row, but only if it is
     * approved, unused, and unexpired. Returns null otherwise.
     */
    public static function resolveUsableToken(string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) return null;
        return Database::first(
            "SELECT * FROM download_requests
             WHERE token = ? AND status = 'approved' AND used_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1",
            [$token]
        );
    }

    /**
     * Consume the token (single use). Uses a conditional UPDATE so two
     * simultaneous requests can never both succeed - only the one that flips
     * used_at from NULL wins.
     *
     * @return bool true if this call consumed the token.
     */
    public static function consume(int $id): bool
    {
        return Database::execute(
            "UPDATE download_requests SET used_at = NOW() WHERE id = ? AND used_at IS NULL",
            [$id]
        ) > 0;
    }

    /** An approved, unused, unexpired request for this user+media (for the UI). */
    public static function usableFor(int $userId, int $mediaId): ?array
    {
        return Database::first(
            "SELECT * FROM download_requests
             WHERE user_id = ? AND media_id = ? AND status = 'approved'
               AND used_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id DESC LIMIT 1",
            [$userId, $mediaId]
        );
    }

    public static function pendingCount(): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM download_requests WHERE status = 'pending'");
    }

    /** Admin list: pending first, then recently reviewed. */
    public static function listForAdmin(int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        return Database::all(
            "SELECT dr.*, m.uuid AS media_uuid, m.title AS media_title, m.media_type,
                    u.name AS user_name, u.username AS user_username,
                    rv.name AS reviewer_name
             FROM download_requests dr
             JOIN media m ON m.id = dr.media_id
             JOIN users u ON u.id = dr.user_id
             LEFT JOIN users rv ON rv.id = dr.reviewed_by
             ORDER BY (dr.status = 'pending') DESC, dr.created_at DESC
             LIMIT $limit"
        );
    }
}
