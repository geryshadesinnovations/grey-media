<?php
use App\Models\DownloadRequest;
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
// Defensive: if the migration that adds download_requests hasn't run yet,
// don't let a missing table break every admin page.
try { $pendingDl = DownloadRequest::pendingCount(); } catch (\Throwable $e) { $pendingDl = 0; }
$items = [
    '/admin'            => ['Overview',  'M3 13l9-9 9 9v8a2 2 0 0 1-2 2h-4v-7H9v7H5a2 2 0 0 1-2-2z'],
    '/admin/users'      => ['Users',     'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zm6 8c0-3-4-5-10-5S2 16 2 19v2h20z'],
    '/admin/categories' => ['Categories','M3 4h7v7H3zM14 4h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
    '/admin/companies'  => ['Companies', 'M3 21h18M3 10h18M3 7l9-4 9 4M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3'],
    '/admin/download-requests' => ['Downloads', 'M12 5v14M5 12l7 7 7-7'],
    '/admin/analytics'  => ['Analytics', 'M3 3v18h18M7 14l3-3 3 3 5-6'],
    '/admin/activity'   => ['Activity',  'M3 12h4l3-9 4 18 3-9h4'],
];
?>
<button class="nav-toggle admin-nav-toggle" type="button" data-drawer-open="admin-drawer" aria-label="Open admin menu">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    Admin menu
</button>
<aside class="admin-nav drawer" id="admin-drawer">
    <div class="drawer-head">
        <span class="drawer-title">Admin</span>
        <button class="drawer-close" type="button" data-drawer-close aria-label="Close menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="drawer-body">
        <h4>Admin</h4>
        <ul>
            <?php foreach ($items as $href => [$label, $path]): $a = $current === $href ? 'active' : ''; ?>
            <li><a class="<?= $a ?>" href="<?= url($href) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="<?= $path ?>"/></svg>
                <?= e($label) ?>
                <?php if ($href === '/admin/download-requests' && $pendingDl > 0): ?>
                <span class="nav-badge"><?= (int) $pendingDl ?></span>
                <?php endif; ?>
            </a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</aside>
