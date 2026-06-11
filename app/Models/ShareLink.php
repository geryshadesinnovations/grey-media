<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Secure, time-limited public share links for a single media item.
 *
 * A random 64-char token maps to exactly one media item and grants view-only
 * access (no login, no password) until it expires or is revoked. Expiry is
 * enforced on every access, so links self-revoke once they lapse.
 */
final class ShareLink
{
    /**
     * Create a share link valid for $ttlMinutes minutes.
     *
     * @return array{token:string,expires_at:string}
     */
    public static function create(int $mediaId, int $userId, int $ttlMinutes): array
    {
        $ttlMinutes = max(5, min(60 * 24 * 90, $ttlMinutes)); // 5 min .. 90 days
        $token   = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable("+{$ttlMinutes} minutes"))->format('Y-m-d H:i:s');

        Database::execute(
            "INSERT INTO share_links (token, media_id, created_by, expires_at) VALUES (?,?,?,?)",
            [$token, $mediaId, $userId, $expires]
        );
        return ['token' => $token, 'expires_at' => $expires];
    }

    /**
     * Resolve a token to its media row, but only if the link is still valid
     * (not expired, not revoked). Returns null otherwise.
     */
    public static function resolveValid(string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) return null;
        return Database::first(
            "SELECT m.*, sl.id AS share_id, sl.expires_at AS share_expires_at
             FROM share_links sl
             JOIN media m ON m.id = sl.media_id
             WHERE sl.token = ? AND sl.revoked = 0 AND sl.expires_at > NOW()
             LIMIT 1",
            [$token]
        );
    }

    /** Record an access (for the creator's audit view). */
    public static function touch(string $token): void
    {
        Database::execute(
            "UPDATE share_links SET access_count = access_count + 1, last_accessed_at = NOW() WHERE token = ?",
            [$token]
        );
    }

    /** Active (non-expired, non-revoked) links a user created. */
    public static function activeForUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        return Database::all(
            "SELECT sl.*, m.uuid AS media_uuid, m.title AS media_title
             FROM share_links sl JOIN media m ON m.id = sl.media_id
             WHERE sl.created_by = ? AND sl.revoked = 0 AND sl.expires_at > NOW()
             ORDER BY sl.created_at DESC LIMIT $limit",
            [$userId]
        );
    }

    public static function revoke(int $id, int $userId): bool
    {
        return Database::execute(
            "UPDATE share_links SET revoked = 1 WHERE id = ? AND created_by = ?",
            [$id, $userId]
        ) > 0;
    }

    public static function purgeExpired(): void
    {
        Database::execute("DELETE FROM share_links WHERE expires_at < (NOW() - INTERVAL 7 DAY)");
    }
}
