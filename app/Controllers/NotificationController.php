<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\CategoryFollow;
use App\Models\Notification;

/**
 * In-app notification center (the bell).
 *
 *   GET  /notifications            -> full page list + followed categories
 *   GET  /notifications/feed       -> JSON for the bell dropdown (count + recent)
 *   POST /notifications/read-all   -> mark everything read
 *   POST /notifications/{id}/read  -> mark one read (then open its link)
 */
final class NotificationController
{
    public function index(): void
    {
        $userId = (int) Auth::id();
        Notification::purgeExpired($userId);
        echo view('notifications/index', [
            'uploads'   => Notification::ofType($userId, 'upload', 100),
            'others'    => Notification::excludingType($userId, 'upload', 100),
            'follows'   => CategoryFollow::followedList($userId),
            'unread'    => Notification::unreadCount($userId),
        ]);
    }

    /** JSON feed for the bell dropdown. */
    public function feed(): void
    {
        $userId = (int) Auth::id();
        Notification::purgeExpired($userId);
        $rows = Notification::recent($userId, 12);
        $items = array_map(static function (array $n): array {
            return [
                'id'      => (int) $n['id'],
                'type'    => (string) $n['type'],
                'title'   => (string) $n['title'],
                'body'    => (string) ($n['body'] ?? ''),
                'url'     => (string) ($n['url'] ?? ''),
                'is_read' => (int) $n['is_read'] === 1,
                'ago'     => self::ago((string) $n['created_at']),
            ];
        }, $rows);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => true,
            'count' => Notification::unreadCount($userId),
            'items' => $items,
        ]);
    }

    public function readAll(): void
    {
        Csrf::verifyOrFail();
        Notification::markAllRead((int) Auth::id());
        if ($this->wantsJson()) { $this->json(['ok' => true]); return; }
        redirect('/notifications');
    }

    public function read(int $id): void
    {
        Csrf::verifyOrFail();
        $userId = (int) Auth::id();
        $n = Notification::find($id, $userId);
        Notification::markRead($id, $userId);

        if ($this->wantsJson()) { $this->json(['ok' => true]); return; }
        redirect($n && !empty($n['url']) ? $this->relativePath((string) $n['url']) : '/notifications');
    }

    /** Convert a stored absolute URL back to a path for redirect(). */
    private function relativePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/notifications';
    }

    private static function ago(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) return '';
        $diff = max(0, time() - $ts);
        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   return intdiv($diff, 60) . 'm ago';
        if ($diff < 86400)  return intdiv($diff, 3600) . 'h ago';
        if ($diff < 604800) return intdiv($diff, 86400) . 'd ago';
        return date('d M Y', $ts);
    }

    private function wantsJson(): bool
    {
        $xhr    = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return $xhr || str_contains($accept, 'application/json');
    }

    private function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
