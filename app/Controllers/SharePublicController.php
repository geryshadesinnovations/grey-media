<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\FileResponse;
use App\Models\ShareLink;

/**
 * PUBLIC consumption of share links - NO login required.
 *
 * Every route resolves the token to its media item and verifies the link is
 * still valid (not expired, not revoked). A token grants view-only access to
 * exactly one item; nothing else on the platform is reachable through it.
 *
 *   GET /s/{token}          -> standalone viewer page
 *   GET /s/{token}/stream   -> original file (range-enabled)
 *   GET /s/{token}/thumb    -> poster / thumbnail
 *   GET /s/{token}/preview  -> pdf/image preview (for pdf & converted ppt)
 */
final class SharePublicController
{
    public function show(string $token): void
    {
        $m = ShareLink::resolveValid($token);
        if (!$m) { $this->gone(); return; }

        ShareLink::touch($token);
        ActivityLog::record('media.share.view', 'media', (int) $m['id'], ['token' => substr($token, 0, 8)]);

        echo view('share/show', [
            'media' => $m,
            'token' => $token,
            'type'  => $m['media_type'],
        ]);
    }

    public function stream(string $token): void
    {
        $m = ShareLink::resolveValid($token);
        if (!$m) { $this->gone(); return; }
        $abs = storage_path((string) $m['file_path']);
        if (!is_file($abs)) { http_response_code(404); return; }
        FileResponse::inline($abs, (string) $m['mime_type'], true);
    }

    public function thumb(string $token): void
    {
        $m = ShareLink::resolveValid($token);
        if (!$m) { $this->gone(); return; }
        $rel = $m['thumbnail_path'] ?: $m['preview_path'];
        if (!$rel) {
            header('Content-Type: image/svg+xml');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="100%" height="100%" fill="#1f2937"/><text x="50%" y="50%" fill="#9ca3af" font-family="sans-serif" text-anchor="middle" dy=".3em">No preview</text></svg>';
            return;
        }
        $abs = storage_path((string) $rel);
        if (!is_file($abs)) { http_response_code(404); return; }
        FileResponse::inline($abs, mime_content_type($abs) ?: 'image/jpeg', false);
    }

    public function preview(string $token): void
    {
        $m = ShareLink::resolveValid($token);
        if (!$m) { $this->gone(); return; }
        $rel = $m['preview_path'] ?: $m['thumbnail_path'];
        if (!$rel) { http_response_code(404); return; }
        $abs = storage_path((string) $rel);
        if (!is_file($abs)) { http_response_code(404); return; }
        $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => mime_content_type($abs) ?: 'application/octet-stream',
        };
        FileResponse::inline($abs, $mime, $mime === 'application/pdf');
    }

    /** 410 Gone for an expired/invalid/revoked link. */
    private function gone(): void
    {
        http_response_code(410);
        echo view('share/expired', []);
    }
}
