<?php
/** @var string $__yield */
/** @var array<string,string> $__sections */
use App\Core\Auth;
use App\Core\Csrf;
$user = Auth::user();
$secInspect = (bool) config('security.enable_anti_inspect', true);
$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!doctype html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? config('app.name')) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23111827'/><text x='50' y='62' font-size='52' text-anchor='middle' fill='%23a78bfa' font-family='sans-serif' font-weight='700'>G</text></svg>">
</head>
<body>
<!-- Watermark removed for UI clarity -->

<header class="topbar">
    <a class="brand" href="<?= url('/dashboard') ?>">
        <span class="brand-mark">G</span>
        <span class="brand-name">Greyshades<small>Media Platform</small></span>
    </a>

    <?php if ($user): ?>
    <form class="topbar-search" method="get" action="<?= url('/dashboard') ?>" autocomplete="off" data-search-suggest>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" name="q" placeholder="Search media, categories, companies..." value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-suggest-list">
        <ul class="search-suggest" id="search-suggest-list" role="listbox" hidden></ul>
    </form>

    <nav class="topbar-actions">
        <button class="icon-btn" id="theme-toggle" type="button" title="Toggle theme">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
        </button>
        <a class="icon-btn" href="<?= url('/favorites') ?>" title="My Favorites" aria-label="My Favorites">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        </a>
        <div class="notif-menu">
            <button class="icon-btn notif-bell" id="notif-bell" type="button" data-popover="notif-popover" title="Notifications" aria-label="Notifications">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                <span class="notif-badge" id="notif-badge" hidden>0</span>
            </button>
            <div class="popover notif-popover" id="notif-popover" hidden>
                <div class="popover-header notif-pop-head">
                    <span>Notifications</span>
                    <button type="button" class="link-btn" id="notif-mark-all">Mark all read</button>
                </div>
                <div class="notif-pop-list" id="notif-pop-list" data-feed-url="<?= url('/notifications/feed') ?>" data-read-all-url="<?= url('/notifications/read-all') ?>">
                    <p class="notif-empty muted">Loading…</p>
                </div>
                <a class="notif-pop-foot" href="<?= url('/notifications') ?>">View all notifications</a>
            </div>
        </div>
        <?php if (Auth::canUpload()): ?>
        <a class="btn-primary" href="<?= url('/upload') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            Upload
        </a>
        <?php endif; ?>
        <div class="user-menu">
            <button class="user-chip" type="button" data-popover="user-popover">
                <span class="user-avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
                <span class="user-name"><?= e($user['name']) ?></span>
            </button>
            <div class="popover" id="user-popover" hidden>
                <div class="popover-header">
                    <div><?= e($user['name']) ?></div>
                    <small><?= e($user['username'] ?? '') ?></small>
                    <small class="role-badge"><?= e($user['role_name'] ?? $user['role_code']) ?></small>
                </div>
                <a href="<?= url('/favorites') ?>">My Favorites</a>
                <a href="<?= url('/notifications') ?>">Notifications</a>
                <?php if (Auth::isSuperAdmin() || Auth::canManageUsers()): ?>
                <hr>
                <a href="<?= url('/admin') ?>">Admin Dashboard</a>
                <a href="<?= url('/admin/users') ?>">Users</a>
                <a href="<?= url('/admin/categories') ?>">Categories</a>
                <a href="<?= url('/admin/companies') ?>">Companies</a>
                <a href="<?= url('/admin/activity') ?>">Activity Log</a>
                <hr>
                <?php endif; ?>
                <form method="post" action="<?= url('/logout') ?>">
                    <?= Csrf::field() ?>
                    <button type="submit" class="logout-btn">Sign out</button>
                </form>
            </div>
        </div>
    </nav>
    <?php endif; ?>
</header>

<?php if ($msg = flash('success')): ?>
<div class="toast toast-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="toast toast-error"><?= e($msg) ?></div>
<?php endif; ?>

<main class="app-main">
    <?= $__yield ?>
</main>

<script src="<?= asset('js/app.js') ?>" defer></script>
<script src="<?= asset('js/search-suggest.js') ?>" defer></script>
<?php if ($secInspect): ?>
<script src="<?= asset('js/security.js') ?>" defer></script>
<?php endif; ?>
<?= $__sections['scripts'] ?? '' ?>
</body>
</html>
