<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Media;
use App\Models\Notification;
use App\Models\ShareLink;

/**
 * Generate (and revoke) expiring share links for a single media item.
 * Public consumption of a link is handled by SharePublicController.
 */
final class ShareController
{
    /** Allowed expiry presets -> minutes. */
    private const DURATIONS = [
        '1h'  => 60,
        '6h'  => 360,
        '24h' => 1440,
        '7d'  => 10080,
        '30d' => 43200,
    ];

    public function create(string $uuid): void
    {
        Csrf::verifyOrFail();

        $m = Media::findByUuid($uuid);
        if (!$m) { $this->fail(404, 'Media not found.'); return; }

        $codes = Media::sectionCodesFor((int) $m['id'], (int) $m['section_id']);
        if (!Auth::canAccessSections($codes)) { $this->fail(403, 'You cannot share this media.'); return; }

        $durKey  = (string) ($_POST['duration'] ?? '24h');
        $minutes = self::DURATIONS[$durKey] ?? self::DURATIONS['24h'];

        $link = ShareLink::create((int) $m['id'], (int) Auth::id(), $minutes);
        $shareUrl = url('/s/' . $link['token']);
        $expiresHuman = date('d M Y, H:i', strtotime($link['expires_at']));

        ActivityLog::record('media.share', 'media', (int) $m['id'], ['expires_at' => $link['expires_at']]);
        Notification::create(
            (int) Auth::id(),
            'share',
            'Share link created',
            'A link for "' . $m['title'] . '" was created. It expires ' . $expiresHuman . '.',
            url('/media/' . $m['uuid'])
        );

        if ($this->wantsJson()) {
            $this->json([
                'ok'            => true,
                'url'           => $shareUrl,
                'expires_at'    => $link['expires_at'],
                'expires_human' => $expiresHuman,
            ]);
            return;
        }

        flash('success', 'Share link created (expires ' . $expiresHuman . '): ' . $shareUrl);
        redirect('/media/' . $m['uuid']);
    }

    public function revoke(int $id): void
    {
        Csrf::verifyOrFail();
        ShareLink::revoke($id, (int) Auth::id());
        if ($this->wantsJson()) { $this->json(['ok' => true]); return; }
        flash('success', 'Share link revoked.');
        redirect('/notifications');
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

    private function fail(int $code, string $message): void
    {
        if ($this->wantsJson()) { $this->json(['ok' => false, 'error' => $message], $code); return; }
        http_response_code($code);
        flash('error', $message);
        redirect('/dashboard');
    }
}
