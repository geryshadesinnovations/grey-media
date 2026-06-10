<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Favorite;
use App\Models\Media;

/**
 * Per-user Favorites.
 *
 *   GET  /favorites                 -> the signed-in user's favorites collection
 *   POST /favorites/toggle/{uuid}   -> add/remove a media item (heart button)
 *
 * Everything is scoped to the current user, so favorites are private.
 */
final class FavoritesController
{
    /** The user's personal favorites collection. */
    public function index(): void
    {
        $userId  = (int) Auth::id();
        $allowed = Auth::allowedSections();

        $sort    = (string) ($_GET['sort']     ?? 'newest');
        $page    = (int)    ($_GET['page']     ?? 1);
        $perPage = (int)    ($_GET['per_page'] ?? 24);

        $result = Media::search(
            ['favorite_user_id' => $userId],
            $allowed,
            $sort,
            $page,
            $perPage
        );

        echo view('favorites/index', [
            'result' => $result,
            'sort'   => $sort,
            'favIds' => Favorite::idsForUser($userId),
        ]);
    }

    /** Toggle a media item in/out of the user's favorites. */
    public function toggle(string $uuid): void
    {
        Csrf::verifyOrFail();

        $m = Media::findByUuid($uuid);
        if (!$m) { $this->fail(404, 'Media not found.'); return; }

        // Only allow favoriting media the user is actually permitted to see.
        $codes = Media::sectionCodesFor((int) $m['id'], (int) $m['section_id']);
        if (!Auth::canAccessSections($codes)) { $this->fail(403, 'You cannot access this media.'); return; }

        $favorited = Favorite::toggle((int) Auth::id(), (int) $m['id']);
        ActivityLog::record($favorited ? 'media.favorite' : 'media.unfavorite', 'media', (int) $m['id']);

        if ($this->wantsJson()) {
            $this->json([
                'ok'        => true,
                'favorited' => $favorited,
                'count'     => Favorite::countForUser((int) Auth::id()),
            ]);
            return;
        }

        flash('success', $favorited ? 'Added to favorites.' : 'Removed from favorites.');
        redirect('/media/' . $m['uuid']);
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
        if ($this->wantsJson()) {
            $this->json(['ok' => false, 'error' => $message], $code);
            return;
        }
        http_response_code($code);
        flash('error', $message);
        redirect('/dashboard');
    }
}
