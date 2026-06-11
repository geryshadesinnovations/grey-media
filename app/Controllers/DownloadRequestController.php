<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\FileResponse;
use App\Models\DownloadRequest;
use App\Models\Media;
use App\Models\Notification;
use App\Models\User;

/**
 * Download request & approval workflow (the requester side).
 *
 *   POST /media/{uuid}/download-request  -> submit a request (when direct
 *                                           download is disabled for the user)
 *   GET  /download/approved/{token}      -> single-use download via an
 *                                           approved token (bound to the user)
 *
 * Admin review (approve/reject) lives in AdminController.
 */
final class DownloadRequestController
{
    public function store(string $uuid): void
    {
        Csrf::verifyOrFail();

        $m = Media::findByUuid($uuid);
        if (!$m) { flash('error', 'Media not found.'); redirect('/dashboard'); }

        $codes = Media::sectionCodesFor((int) $m['id'], (int) $m['section_id']);
        if (!Auth::canAccessSections($codes)) {
            flash('error', 'You cannot access this media.');
            redirect('/dashboard');
        }

        // If the user can already download it directly, no request is needed.
        if ($this->canAlreadyDownload($m)) {
            redirect('/download/' . $m['uuid']);
        }

        $userId = (int) Auth::id();
        if (DownloadRequest::hasPending($userId, (int) $m['id'])) {
            flash('error', 'You already have a pending request for this item.');
            redirect('/media/' . $m['uuid']);
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        $id = DownloadRequest::create($userId, (int) $m['id'], $reason);
        ActivityLog::record('download.request', 'media', (int) $m['id'], ['request_id' => $id]);

        // Notify reviewers.
        $who = Auth::user()['name'] ?? 'A user';
        Notification::createMany(
            User::adminIds(),
            'download_request',
            'New download request',
            $who . ' requested to download "' . $m['title'] . '".',
            url('/admin/download-requests')
        );

        flash('success', 'Your download request has been submitted for approval.');
        redirect('/media/' . $m['uuid']);
    }

    /** Single-use download via an approved token, bound to the requesting user. */
    public function downloadApproved(string $token): void
    {
        if (!Auth::check()) { redirect('/login'); }

        $req = DownloadRequest::resolveUsableToken($token);
        if (!$req) { http_response_code(410); echo view('errors/link-expired', []); return; }

        // Token is bound to one user - nobody else can use it.
        if ((int) $req['user_id'] !== (int) Auth::id()) {
            http_response_code(403); echo view('errors/403', []); return;
        }

        $m = Media::find((int) $req['media_id']);
        if (!$m) { http_response_code(404); return; }
        $abs = storage_path((string) $m['file_path']);
        if (!is_file($abs)) { http_response_code(404); return; }

        // Consume the token FIRST (single-use, race-safe). If another request
        // already consumed it, bail out.
        if (!DownloadRequest::consume((int) $req['id'])) {
            http_response_code(410); echo view('errors/link-expired', []); return;
        }

        Database::execute(
            "INSERT INTO download_logs (media_id, user_id, ip_address, user_agent, session_id, bytes_sent)
             VALUES (?,?,?,?,?,?)",
            [$m['id'], Auth::id(), client_ip(), substr(ua(), 0, 500), session_id(), filesize($abs)]
        );
        Media::bumpDownload((int) $m['id']);
        ActivityLog::record('download.approved.use', 'media', (int) $m['id'], ['request_id' => (int) $req['id']]);

        $filename = ((string) $m['title']) . '.' . pathinfo($abs, PATHINFO_EXTENSION);
        FileResponse::download($abs, (string) $m['mime_type'], $filename);
    }

    private function canAlreadyDownload(array $m): bool
    {
        if (Auth::isSuperAdmin()) return true;
        if (empty($m['is_downloadable'])) {
            // Not flagged downloadable - only a legacy explicit grant counts.
            return (bool) Database::scalar(
                "SELECT 1 FROM media_download_grants
                 WHERE media_id = ? AND user_id = ? AND (expires_at IS NULL OR expires_at > NOW())",
                [$m['id'], Auth::id()]
            );
        }
        if (!empty($m['download_expiry']) && strtotime((string) $m['download_expiry']) < time()) return false;
        return true;
    }
}
