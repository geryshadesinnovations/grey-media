<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Category;
use App\Models\CategoryFollow;

/**
 * Follow / unfollow a category so the user is notified about new uploads in it.
 *
 *   POST /categories/{id}/follow  -> toggle follow state
 */
final class FollowController
{
    public function toggle(int $id): void
    {
        Csrf::verifyOrFail();

        $cat = Category::find($id);
        if (!$cat) { $this->fail(404, 'Category not found.'); return; }

        // Only let users follow categories in sections they can access.
        if (!Auth::isSuperAdmin()) {
            $sectionCode = (string) \App\Core\Database::scalar(
                "SELECT s.code FROM sections s WHERE s.id = ?",
                [(int) $cat['section_id']]
            );
            if (!Auth::canSection($sectionCode)) { $this->fail(403, 'You cannot follow this category.'); return; }
        }

        $following = CategoryFollow::toggle((int) Auth::id(), $id);

        if ($this->wantsJson()) { $this->json(['ok' => true, 'following' => $following]); return; }
        flash('success', $following ? 'You are now following this category.' : 'You unfollowed this category.');
        redirect('/dashboard?category=' . $id);
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
