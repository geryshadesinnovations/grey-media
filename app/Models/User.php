<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function all(): array
    {
        return Database::all(
            "SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            "SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?",
            [$id]
        );
    }

    public static function roles(): array
    {
        return Database::all("SELECT * FROM roles ORDER BY id");
    }

    /**
     * Active users who can review download requests: super admins, plus anyone
     * with the can_manage_users flag.
     *
     * @return array<int,int>
     */
    public static function adminIds(): array
    {
        $rows = Database::all(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1 AND (r.code = 'super_admin' OR u.can_manage_users = 1)"
        );
        return array_map(static fn ($r) => (int) $r['id'], $rows);
    }

    /**
     * Usernames are the login identifier: letters and numbers only, 3-64 chars.
     */
    public static function isValidUsername(string $username): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{3,64}$/', $username);
    }

    /**
     * True when the username is already taken (case-insensitive), optionally
     * ignoring a given user id (used when editing an existing user).
     */
    public static function usernameExists(string $username, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            return (bool) Database::scalar(
                "SELECT 1 FROM users WHERE username = ? AND id <> ? LIMIT 1",
                [$username, $exceptId]
            );
        }
        return (bool) Database::scalar(
            "SELECT 1 FROM users WHERE username = ? LIMIT 1",
            [$username]
        );
    }

    public static function create(array $data): int
    {
        Database::execute(
            "INSERT INTO users
             (name, username, password_hash, role_id,
              can_graphics, can_events, can_upload, can_edit, can_delete, can_download, can_manage_users, is_active)
             VALUES (?,?,?,?, ?,?,?,?,?,?,?, ?)",
            [
                $data['name'], $data['username'],
                password_hash((string) $data['password'], PASSWORD_BCRYPT),
                (int) $data['role_id'],
                (int) ($data['can_graphics']     ?? 0),
                (int) ($data['can_events']       ?? 0),
                (int) ($data['can_upload']       ?? 0),
                (int) ($data['can_edit']         ?? 0),
                (int) ($data['can_delete']       ?? 0),
                (int) ($data['can_download']     ?? 0),
                (int) ($data['can_manage_users'] ?? 0),
                (int) ($data['is_active']        ?? 1),
            ]
        );
        return Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        $sets = []; $params = [];
        foreach ([
            'name','username','role_id','can_graphics','can_events',
            'can_upload','can_edit','can_delete','can_download','can_manage_users','is_active'
        ] as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $params[] = is_bool($data[$k]) ? (int) $data[$k] : $data[$k];
            }
        }
        if (!empty($data['password'])) {
            $sets[] = 'password_hash = ?';
            $params[] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }
        if (!$sets) return;
        $params[] = $id;
        Database::execute("UPDATE users SET " . implode(',', $sets) . " WHERE id = ?", $params);
    }

    public static function delete(int $id): void
    {
        Database::execute("UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
    }
}
