<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Category;
use App\Models\Company;
use App\Models\Media;
use App\Models\Notification;
use App\Models\Section;
use App\Models\User;
use App\Services\MediaProcessor;

final class UploadController
{
    public function showForm(): void
    {
        if (!Auth::canUpload()) { http_response_code(403); echo view('errors/403', []); return; }

        $sections = array_values(array_filter(
            Section::all(),
            fn ($s) => Auth::canSection($s['code'])
        ));
        $trees = [];
        foreach ($sections as $s) $trees[$s['code']] = Category::tree((int) $s['id']);

        echo view('upload/form', [
            'sections'  => $sections,
            'trees'     => $trees,
            'maxMb'     => (int) config('storage.upload_max_mb', 2048),
            'allowed'   => (array) config('media.allowed_mimes', []),
            'companies' => Company::all(),
        ]);
    }

    public function store(): void
    {
        // Detect post_max_size overrun: if exceeded, $_POST and $_FILES are
        // empty even though Content-Length is large. Return a clear message
        // instead of failing CSRF or "no file uploaded".
        $contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMax    = $this->iniBytes(ini_get('post_max_size'));
        if ($contentLen > 0 && $postMax > 0 && $contentLen > $postMax && empty($_POST) && empty($_FILES)) {
            $this->jsonError(
                'Upload exceeds the server limit (' . ini_get('post_max_size') . '). '
                . 'Please contact the admin to raise post_max_size / upload_max_filesize.',
                413
            );
        }

        Csrf::verifyOrFail();
        if (!Auth::canUpload()) { http_response_code(403); echo 'Forbidden'; return; }

        if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
            $this->jsonError('No file received. The file may be larger than the server allows.');
        }
        $err = (int) $_FILES['file']['error'];
        if ($err !== UPLOAD_ERR_OK) {
            $this->jsonError($this->uploadErrorMessage($err));
        }
        $file = $_FILES['file'];

        // Validate mime
        $mime = $this->detectMime($file['tmp_name'], (string) $file['name']);
        $allowed = (array) config('media.allowed_mimes', []);
        // Accept "image/jpg" alias from older browsers, normalise to image/jpeg
        if ($mime === 'image/jpg') $mime = 'image/jpeg';
        if (!in_array($mime, $allowed, true)) {
            $this->jsonError('File type not allowed: ' . $mime);
        }

        // Categories drive the section now. The section is automatically derived
        // from each selected category's section_id - the first one becomes the
        // primary section_id on the media row, but media_categories rows record
        // every category exactly so dashboard filters keep working.
        $catIds = array_filter(array_map('intval', (array) ($_POST['categories'] ?? [])));
        if (empty($catIds)) {
            $this->jsonError('Please select at least one category.');
        }

        // Look up section for every chosen category, validate user access.
        $marks = implode(',', array_fill(0, count($catIds), '?'));
        $catRows = Database::all(
            "SELECT c.id, c.section_id, s.code AS section_code
             FROM categories c JOIN sections s ON s.id = c.section_id
             WHERE c.id IN ($marks)",
            $catIds
        );
        if (count($catRows) !== count($catIds)) {
            $this->jsonError('One or more selected categories are invalid.');
        }
        // Filter out categories whose section the user cannot access.
        $allowedRows = array_values(array_filter(
            $catRows,
            fn ($r) => Auth::canSection((string) $r['section_code'])
        ));
        if (empty($allowedRows)) {
            $this->jsonError('You do not have permission to upload to the selected categories.');
        }
        $primarySectionId = (int) $allowedRows[0]['section_id'];
        // Re-build the validated category id list (only those the user can access).
        $catIds = array_map(fn ($r) => (int) $r['id'], $allowedRows);

        $title = trim((string) ($_POST['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME)));
        if ($title === '') $this->jsonError('Title is required.');

        // For Video, PPT, PPTX and PDF we require a custom thumbnail upload
        // (these formats can't reliably auto-generate a thumbnail without
        // ffmpeg/libreoffice/imagemagick installed). Images embed their own
        // visual so no thumbnail is needed.
        $type = MediaProcessor::classify($mime);
        if (in_array($type, ['video', 'ppt', 'pdf'], true)) {
            $tn = $_FILES['thumbnail'] ?? null;
            $tnErr = $tn['error'] ?? UPLOAD_ERR_NO_FILE;
            if (!$tn || $tnErr === UPLOAD_ERR_NO_FILE) {
                $this->jsonError('Thumbnail image is required for ' . strtoupper($type) . ' files.');
            }
            if ($tnErr !== UPLOAD_ERR_OK) {
                $this->jsonError('Thumbnail upload failed: ' . $this->uploadErrorMessage((int) $tnErr));
            }
        }

        // Compute file hash for dedup
        $hash = hash_file('sha256', $file['tmp_name']);
        if ($existing = Media::findByHash($hash)) {
            ActivityLog::record('media.upload.duplicate', 'media', (int) $existing['id'], ['hash' => $hash]);
            $this->jsonOk([
                'duplicate' => true,
                'media' => [
                    'uuid' => $existing['uuid'],
                    'url'  => url('/media/' . $existing['uuid']),
                ],
                'message' => 'This file is already in the library.',
            ]);
        }

        // Persist physical file
        $uuid   = $this->uuidv4();
        $ext    = $this->extFor($mime, (string) $file['name']);
        $relDir = '/uploads/originals/' . date('Y/m');
        $absDir = storage_path($relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            $this->jsonError('Could not create storage directory.');
        }
        $relPath = $relDir . '/' . $uuid . '.' . $ext;
        $absPath = storage_path($relPath);
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            $this->jsonError('Could not move uploaded file.');
        }

        // Generate previews
        [$thumbRel, $previewRel, $hlsMasterRel, $duration, $w, $h] = $this->processMedia($absPath, $mime, $type, $uuid);

        // Custom thumbnail upload overrides auto-generated one
        if (!empty($_FILES['thumbnail']) && ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $thumbFile = $_FILES['thumbnail'];
            $thumbMime = mime_content_type($thumbFile['tmp_name']) ?: '';
            if (in_array($thumbMime, ['image/jpeg','image/png','image/webp'], true)) {
                $thumbDir = '/uploads/thumbnails/' . date('Y/m');
                $absThumbDir = storage_path($thumbDir);
                if (!is_dir($absThumbDir)) @mkdir($absThumbDir, 0775, true);
                $thumbExt = match($thumbMime) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg',
                };
                $thumbRel = $thumbDir . '/' . $uuid . '-custom.' . $thumbExt;
                move_uploaded_file($thumbFile['tmp_name'], storage_path($thumbRel));
            }
        }

        // Insert DB row
        $companyId = !empty($_POST['company_id']) ? (int) $_POST['company_id'] : null;
        $mediaId = Media::create([
            'uuid'              => $uuid,
            'section_id'        => $primarySectionId,
            'company_id'        => $companyId,
            'title'             => $title,
            'description'       => $_POST['description'] ?? null,
            'keywords'          => $_POST['keywords'] ?? null,
            'media_type'        => $type,
            'mime_type'         => $mime,
            'file_path'         => $relPath,
            'file_size'         => filesize($absPath) ?: 0,
            'file_hash'         => $hash,
            'thumbnail_path'    => $thumbRel,
            'preview_path'      => $previewRel,
            'hls_master'        => $hlsMasterRel,
            'duration_sec'      => $duration,
            'width'             => $w,
            'height'            => $h,
            'is_downloadable'   => !empty($_POST['is_downloadable']),
            'uploaded_by'       => Auth::id() ?? 0,
            'processing_status' => 'ready',
        ]);

        // Categories / tags. The user only ticks specific subcategories — the
        // backend automatically attaches the entire ancestor chain (including
        // the root "Gimmick"/"Art"/"Hybrid"/"Events" parent) so dashboard
        // category filters work whether the visitor browses by leaf or by
        // root. This replaces the "All <Root>" master checkbox we used to
        // render in the upload form.
        $expandedCatIds = [];
        foreach ($catIds as $cid) {
            foreach (Category::ancestorIds($cid) as $aid) $expandedCatIds[$aid] = true;
        }
        Media::attachCategories($mediaId, array_keys($expandedCatIds));

        ActivityLog::record('media.upload', 'media', $mediaId, [
            'title' => $title, 'mime' => $mime, 'size' => filesize($absPath),
        ]);

        // Notify every user who has access to this media's section(s) - one
        // notification per upload - regardless of whether they follow a
        // category. Section access mirrors Auth (super admins + the per-user
        // can_graphics / can_events flags). The uploader is excluded.
        $sectionCodes = array_values(array_unique(
            array_map(fn ($r) => (string) $r['section_code'], $allowedRows)
        ));
        $recipientIds = array_values(array_filter(
            User::idsWithSectionAccess($sectionCodes),
            fn ($uid) => $uid !== (int) Auth::id()
        ));
        if ($recipientIds) {
            $label = implode(' & ', array_map('ucfirst', $sectionCodes));
            Notification::createMany(
                $recipientIds,
                'upload',
                'New ' . strtoupper($type) . ' in ' . $label,
                '"' . $title . '" was just added to ' . $label . '.',
                url('/media/' . $uuid)
            );
        }

        $this->jsonOk([
            'duplicate' => false,
            'media' => [
                'id'   => $mediaId,
                'uuid' => $uuid,
                'url'  => url('/media/' . $uuid),
            ],
        ]);
    }

    /** @return array{0:?string,1:?string,2:?string,3:?int,4:?int,5:?int} */
    private function processMedia(string $absPath, string $mime, string $type, string $uuid): array
    {
        return MediaProcessor::deriveAll($absPath, $mime, $type, $uuid);
    }

    private function detectMime(string $path, string $name): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (finfo_file($finfo, $path) ?: '') : '';
        if ($finfo) finfo_close($finfo);

        // Refine based on extension for office docs (finfo can return zip for pptx)
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['pptx'], true) && in_array($mime, ['application/zip','application/x-zip-compressed'], true)) {
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

    private function uuidv4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** Convert a PHP ini size string (e.g. "8M", "512K", "2G") to bytes. */
    private function iniBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '') return 0;
        $unit = strtolower(substr($val, -1));
        $num  = (int) $val;
        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    /** Map PHP UPLOAD_ERR_* codes to human-readable messages. */
    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'File is larger than the server allows (upload_max_filesize). Please choose a smaller file.',
            UPLOAD_ERR_FORM_SIZE  => 'File is larger than the form allows.',
            UPLOAD_ERR_PARTIAL    => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary upload folder. Contact admin.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the upload to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            default               => 'Unknown upload error (code ' . $code . ').',
        };
    }

    private function jsonOk(array $data): never
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true] + $data);
        exit;
    }

    private function jsonError(string $msg, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }
}
