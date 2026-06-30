<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Streams a file to the browser with HTTP Range support.
 *
 * Shared by the public share-link viewer and the approved single-use download
 * route so neither has to reimplement byte-range streaming. Mirrors the
 * behaviour of StreamController::serveFile (private there) without changing it.
 */
final class FileResponse
{
    /** Serve a file inline (for in-browser viewing). */
    public static function inline(string $abs, string $mime, bool $allowRange = true): void
    {
        self::send($abs, $mime, $allowRange, null);
    }

    /** Serve a file as a download attachment with the given filename. */
    public static function download(string $abs, string $mime, string $filename): void
    {
        $safe = preg_replace('~[^A-Za-z0-9._-]+~', '_', $filename) ?: 'download';
        self::send($abs, $mime, false, $safe);
    }

    private static function send(string $abs, string $mime, bool $allowRange, ?string $attachment): void
    {
        if (!is_file($abs)) { http_response_code(404); return; }
        $size = filesize($abs);

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('X-Frame-Options: SAMEORIGIN');
        header('Cache-Control: private, no-store');
        header('Accept-Ranges: ' . ($allowRange ? 'bytes' : 'none'));
        if ($attachment !== null) {
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $attachment . '"');
        } else {
            header('Content-Disposition: inline');
        }

        if ($allowRange && !empty($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $r)) {
                $start = $r[1] === '' ? 0 : (int) $r[1];
                $end   = $r[2] === '' ? $size - 1 : (int) $r[2];
                if ($start > $end || $end >= $size) {
                    header("Content-Range: bytes */$size");
                    http_response_code(416);
                    return;
                }
                $length = $end - $start + 1;
                http_response_code(206);
                header("Content-Range: bytes $start-$end/$size");
                header('Content-Length: ' . $length);
                $fp = fopen($abs, 'rb');
                if (!$fp) { http_response_code(500); return; }
                fseek($fp, $start);
                $bufSize = 8192;
                while (!feof($fp) && $length > 0 && !connection_aborted()) {
                    $chunk = fread($fp, min($bufSize, $length));
                    if ($chunk === false) break;
                    echo $chunk;
                    $length -= strlen($chunk);
                    flush();
                }
                fclose($fp);
                exit;
            }
        }

        header('Content-Length: ' . $size);
        readfile($abs);
        exit;
    }
}
