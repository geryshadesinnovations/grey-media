<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\StreamToken;
use App\Models\CategoryFollow;
use App\Models\Company;
use App\Models\DownloadRequest;
use App\Models\Favorite;
use App\Models\Media;
use App\Models\Section;
use App\Services\MediaProcessor;

final class MediaController
{
    /** Single media detail / preview page. */
    public function show(string $uuid): void
    {
        $m = Media::findByUuid($uuid);
        if (!$m) { http_response_code(404); echo view('errors/404', []); return; }

        $section = Section::find((int) $m['section_id']);
        $mediaSectionCodes = Media::sectionCodesFor((int) $m['id'], (int) $m['section_id']);
        if (!$section || !Auth::canAccessSections($mediaSectionCodes)) {
            http_response_code(403); echo view('errors/403', []); return;
        }

        Media::bumpView((int) $m['id']);
        Database::execute(
            "INSERT INTO view_logs (media_id, user_id, ip_address, user_agent, session_id)
             VALUES (?,?,?,?,?)",
            [$m['id'], Auth::id(), client_ip(), substr(ua(), 0, 500), session_id()]
        );
        Database::execute(
            "UPDATE user_sessions SET current_media_id = ? WHERE id = ?",
            [$m['id'], session_id()]
        );
        ActivityLog::record('media.view', 'media', (int) $m['id']);

        // Issue a short-lived stream/download token for this media
        $streamToken = StreamToken::issue((int) $m['id']);

        $userId      = (int) Auth::id();
        $canDownload = $this->isDownloadable($m);
        // Approved single-use token waiting to be used (drives the "Download
        // (approved)" button), and any pending request state for the UI.
        $approved    = DownloadRequest::usableFor($userId, (int) $m['id']);

        echo view('media/show', [
            'media'        => $m,
            'section'      => $section,
            'categories'   => Media::categoriesFor((int) $m['id']),
            'streamToken'  => $streamToken,
            'canDownload'  => $canDownload,
            'canEdit'      => Auth::canEdit() || Auth::isSuperAdmin(),
            'canDelete'    => Auth::canDelete() || Auth::isSuperAdmin(),
            'isFavorite'   => Favorite::isFavorite($userId, (int) $m['id']),
            'related'      => Media::related((int) $m['id'], Auth::allowedSections(), 8),
            'favIds'       => Favorite::idsForUser($userId),
            'followedCats' => CategoryFollow::followedIds($userId),
            'approvedToken'  => $approved['token'] ?? null,
            'hasPendingReq'  => DownloadRequest::hasPending($userId, (int) $m['id']),
        ]);
    }

    /** Edit form (admin/editor) */
    public function edit(int $id): void
    {
        if (!(Auth::canEdit() || Auth::isSuperAdmin())) { http_response_code(403); echo 'Forbidden'; return; }
        $m = Media::find($id);
        if (!$m) { http_response_code(404); echo view('errors/404', []); return; }

        $sections = array_values(array_filter(
            Section::all(),
            fn ($s) => Auth::canSection($s['code'])
        ));
        $trees = [];
        foreach ($sections as $s) $trees[$s['code']] = \App\Models\Category::tree((int) $s['id']);

        echo view('media/edit', [
            'media'      => $m,
            'categories' => Media::categoriesFor($id),
            'sections'   => $sections,
            'trees'      => $trees,
            'companies'  => Company::all(),
        ]);
    }

    public function update(int $id): void
    {
        Csrf::verifyOrFail();
        if (!(Auth::canEdit() || Auth::isSuperAdmin())) { http_response_code(403); echo 'Forbidden'; return; }

        $m = Media::find($id);
        if (!$m) { http_response_code(404); return; }

        Media::update($id, [
            'title'           => trim((string) ($_POST['title'] ?? $m['title'])),
            'description'     => $_POST['description'] ?? null,
            'keywords'        => $_POST['keywords'] ?? null,
            'is_downloadable' => !empty($_POST['is_downloadable']),
            'is_featured'     => !empty($_POST['is_featured']),
            'is_pinned'       => !empty($_POST['is_pinned']),
            'company_id'      => !empty($_POST['company_id']) ? (int) $_POST['company_id'] : null,
        ]);

        // Optional: replace the underlying file with a new one of the SAME
        // media type. All metadata + relationships (favorites, shares, views,
        // categories, download requests) stay intact because we keep the same
        // media row id and uuid. May redirect on validation error.
        if (!empty($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->handleFileReplacement($id, $m);
        }

        // Re-attach categories with full ancestor expansion (same logic as upload)
        if (isset($_POST['categories'])) {
            $catIds = array_filter(array_map('intval', (array) $_POST['categories']));
            $expandedCatIds = [];
            foreach ($catIds as $cid) {
                foreach (\App\Models\Category::ancestorIds($cid) as $aid) {
                    $expandedCatIds[$aid] = true;
                }
            }
            Media::attachCategories($id, array_keys($expandedCatIds));
        }

        ActivityLog::record('media.edit', 'media', $id);
        flash('success', 'Media updated.');
        redirect('/media/' . $m['uuid']);
    }

    public function destroy(int $id): void
    {
        Csrf::verifyOrFail();
        if (!(Auth::canDelete() || Auth::isSuperAdmin())) { http_response_code(403); echo 'Forbidden'; return; }
        $m = Media::find($id);
        if (!$m) { http_response_code(404); return; }

        // Remove physical files (best effort)
        foreach (['file_path','thumbnail_path','preview_path'] as $col) {
            if (!empty($m[$col])) {
                $abs = storage_path($m[$col]);
                if (is_file($abs)) @unlink($abs);
            }
        }
        if (!empty($m['hls_master'])) {
            $absDir = dirname(storage_path($m['hls_master']));
            if (is_dir($absDir)) {
                foreach (glob($absDir . '/*') ?: [] as $f) @unlink($f);
                @rmdir($absDir);
            }
        }
        Media::delete($id);
        ActivityLog::record('media.delete', 'media', $id);
        flash('success', 'Media deleted.');
        redirect('/dashboard');
    }

    /**
     * Whether the CURRENT user can directly download this item.
     *
     * The media's own "Allow downloads" flag is the gate: when it's on (and any
     * download window hasn't lapsed) every user who can view the item gets the
     * direct download. When it's off, only super admins can (everyone else uses
     * the request/approval workflow). This keeps the button state in lockstep
     * with the latest media settings the moment an admin toggles it.
     */
    private function isDownloadable(array $m): bool
    {
        if (Auth::isSuperAdmin()) return true;
        if (empty($m['is_downloadable'])) return false;
        if (!empty($m['download_expiry']) && strtotime((string) $m['download_expiry']) < time()) {
            return false;
        }
        return true;
    }

    private function allTrees(): array
    {
        $out = [];
        foreach (Section::all() as $s) {
            $out[$s['code']] = \App\Models\Category::tree((int) $s['id']);
        }
        return $out;
    }

    /**
     * Replace the underlying file during edit. The new file MUST be the same
     * media type (video->video, pdf->pdf, etc.). Keeps the media row's id/uuid
     * and every relationship, so favorites/shares/views/categories survive.
     * Flashes an error and redirects back if validation fails.
     */
    private function handleFileReplacement(int $id, array $m): void
    {
        $back = '/media/' . $m['uuid'];
        $file = $_FILES['file'];
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'File replacement failed (upload error ' . (int) $file['error'] . ').');
            redirect($back);
        }

        $mime = $this->detectMime($file['tmp_name'], (string) $file['name']);
        if ($mime === 'image/jpg') $mime = 'image/jpeg';
        $allowed = (array) config('media.allowed_mimes', []);
        if (!in_array($mime, $allowed, true)) {
            flash('error', 'File type not allowed: ' . $mime);
            redirect($back);
        }

        $newType = MediaProcessor::classify($mime);
        if ($newType !== $m['media_type']) {
            flash('error', 'The replacement must be the same media type as the original ('
                . strtoupper((string) $m['media_type']) . '). You uploaded a ' . strtoupper($newType) . '.');
            redirect($back);
        }

        // Store the new original under the SAME uuid (with a random suffix so we
        // never clobber the old file before the DB switch / cleanup).
        $uuid   = (string) $m['uuid'];
        $ext    = $this->extFor($mime, (string) $file['name']);
        $relDir = '/uploads/originals/' . date('Y/m');
        $absDir = storage_path($relDir);
        if (!is_dir($absDir)) @mkdir($absDir, 0775, true);
        $relPath = $relDir . '/' . $uuid . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absPath = storage_path($relPath);
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            flash('error', 'Could not store the replacement file.');
            redirect($back);
        }

        // Dedup hash, but never collide with another media row's unique hash.
        $hash = hash_file('sha256', $absPath) ?: null;
        if ($hash) {
            $other = Media::findByHash($hash);
            if ($other && (int) $other['id'] !== $id) $hash = null;
        }

        // Regenerate derived assets (same routine as upload).
        [$thumbRel, $previewRel, $hlsRel, $duration, $w, $h] =
            MediaProcessor::deriveAll($absPath, $mime, $newType, $uuid);

        // A custom thumbnail upload (optional) always wins.
        $customThumbRel = null;
        if (!empty($_FILES['thumbnail']) && (int) ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tf = $_FILES['thumbnail'];
            $tmime = mime_content_type($tf['tmp_name']) ?: '';
            if (in_array($tmime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $tdir = '/uploads/thumbnails/' . date('Y/m');
                if (!is_dir(storage_path($tdir))) @mkdir(storage_path($tdir), 0775, true);
                $text = $tmime === 'image/png' ? 'png' : ($tmime === 'image/webp' ? 'webp' : 'jpg');
                $customThumbRel = $tdir . '/' . $uuid . '-custom-' . bin2hex(random_bytes(3)) . '.' . $text;
                move_uploaded_file($tf['tmp_name'], storage_path($customThumbRel));
            }
        }

        // Build the column set. We always replace the original file, size, mime
        // and HLS (null clears stale segments). Thumbnail/preview/dimensions are
        // only overwritten when we actually produced new ones, so we never wipe
        // a good existing thumbnail just because the server lacks ffmpeg etc.
        $finalThumb = $customThumbRel ?? $thumbRel;
        $update = [
            'mime_type'  => $mime,
            'media_type' => $newType,
            'file_path'  => $relPath,
            'file_size'  => filesize($absPath) ?: 0,
            'file_hash'  => $hash,
            'hls_master' => $hlsRel,
        ];
        if ($finalThumb !== null) $update['thumbnail_path'] = $finalThumb;
        if ($previewRel !== null) $update['preview_path']  = $previewRel;
        $update['duration_sec'] = $duration; // null is fine (image/pdf)
        if ($w !== null) $update['width']  = $w;
        if ($h !== null) $update['height'] = $h;

        Media::updateFile($id, $update);

        // Clean up old physical files we no longer reference (best effort).
        $keep = array_filter([$relPath, $finalThumb, $previewRel]);
        foreach (['file_path', 'thumbnail_path', 'preview_path'] as $col) {
            $old = $m[$col] ?? null;
            if ($old && !in_array($old, $keep, true)) {
                $abs = storage_path($old);
                if (is_file($abs)) @unlink($abs);
            }
        }
        if (!empty($m['hls_master'])) {
            $oldHlsDir = dirname(storage_path((string) $m['hls_master']));
            if (is_dir($oldHlsDir)) {
                foreach (glob($oldHlsDir . '/*') ?: [] as $f) @unlink($f);
                @rmdir($oldHlsDir);
            }
        }

        ActivityLog::record('media.replace_file', 'media', $id, ['mime' => $mime, 'type' => $newType]);
    }

    private function detectMime(string $path, string $name): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (finfo_file($finfo, $path) ?: '') : '';
        if ($finfo) finfo_close($finfo);

        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'pptx' && in_array($mime, ['application/zip', 'application/x-zip-compressed'], true)) {
            return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        }
        if ($ext === 'ppt' && $mime === 'application/octet-stream') return 'application/vnd.ms-powerpoint';
        return $mime ?: 'application/octet-stream';
    }

    private function extFor(string $mime, string $orig): string
    {
        $orig = strtolower((string) pathinfo($orig, PATHINFO_EXTENSION));
        return match ($mime) {
            'video/mp4'   => 'mp4',
            'image/png'   => 'png',
            'image/jpeg'  => 'jpg',
            'image/webp'  => 'webp',
            'image/gif'   => 'gif',
            'application/pdf' => 'pdf',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            default => $orig ?: 'bin',
        };
    }
}
