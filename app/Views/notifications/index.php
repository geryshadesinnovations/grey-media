<?php
/**
 * @var array $uploads  Upload-type notifications (own container)
 * @var array $others   Everything except uploads
 * @var array $follows  Categories the user follows
 * @var int   $unread
 */
use App\Core\Csrf;
$this->extend('layouts/app');

$icon = static function (string $type): string {
    return match ($type) {
        'upload'            => 'M12 19V5M5 12l7-7 7 7',
        'share'             => 'M4 12v8h16v-8M16 6l-4-4-4 4M12 2v14',
        'download_approved' => 'M20 6 9 17l-5-5',
        'download_rejected' => 'M18 6 6 18M6 6l12 12',
        'download_request'  => 'M12 5v14M5 12l7 7 7-7',
        default             => 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9',
    };
};

/** Friendly relative time, with the absolute time kept as a tooltip. */
$timeAgo = static function (string $dt): string {
    $ts = strtotime($dt);
    if ($ts === false) return '';
    $d = max(0, time() - $ts);
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return intdiv($d, 60) . 'm ago';
    if ($d < 86400)  return intdiv($d, 3600) . 'h ago';
    if ($d < 604800) return intdiv($d, 86400) . 'd ago';
    return date('d M Y', $ts);
};

/** Renders one notification row. */
$row = function (array $n) use ($icon, $timeAgo) {
    $unread = (int) $n['is_read'] === 0;
    $type   = (string) $n['type'];
    echo '<li class="notif-item' . ($unread ? ' is-unread' : '') . '" data-type="' . e($type) . '">';
    echo '<span class="notif-ic" data-type="' . e($type) . '">';
    echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="' . $icon($type) . '"/></svg>';
    echo '</span>';
    echo '<div class="notif-main">';
    echo '<div class="notif-row-top">';
    if (!empty($n['url'])) {
        echo '<a class="notif-title" href="' . e($n['url']) . '">' . e($n['title']) . '</a>';
    } else {
        echo '<span class="notif-title">' . e($n['title']) . '</span>';
    }
    echo '<time class="notif-time" title="' . e(date('d M Y, H:i', (int) strtotime((string) $n['created_at']))) . '">'
        . e($timeAgo((string) $n['created_at'])) . '</time>';
    echo '</div>';
    if (!empty($n['body'])) echo '<p class="notif-body">' . e($n['body']) . '</p>';
    echo '</div>';
    if ($unread) echo '<span class="notif-dot" aria-label="Unread"></span>';
    echo '</li>';
};
?>
<div class="notifications-page">
    <header class="notif-head">
        <div class="notif-head-text">
            <h2>Notifications<?php if ($unread > 0): ?><span class="notif-unread-pill"><?= (int) $unread ?> new</span><?php endif; ?></h2>
            <p class="notif-sub muted">Stay on top of uploads, shares and download activity across your sections.</p>
        </div>
        <?php if ($unread > 0): ?>
        <form method="post" action="<?= url('/notifications/read-all') ?>" class="notif-head-action">
            <?= Csrf::field() ?>
            <button type="submit" class="btn-ghost notif-readall">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                Mark all read
            </button>
        </form>
        <?php endif; ?>
    </header>

    <div class="notif-note">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        <span>Notifications you've viewed are automatically cleared 24&nbsp;hours later.</span>
    </div>

    <!-- ===== Uploads ===== -->
    <section class="glass notif-block">
        <div class="notif-block-head">
            <span class="notif-block-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg></span>
            <h3>Uploads</h3>
            <span class="notif-count-badge"><?= count($uploads) ?></span>
        </div>
        <?php if (empty($uploads)): ?>
            <div class="notif-empty">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                <p>No new uploads in your sections right now.</p>
            </div>
        <?php else: ?>
            <ul class="notif-list"><?php foreach ($uploads as $n) $row($n); ?></ul>
        <?php endif; ?>
    </section>

    <!-- ===== Activity ===== -->
    <section class="glass notif-block">
        <div class="notif-block-head">
            <span class="notif-block-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg></span>
            <h3>Activity</h3>
            <span class="notif-count-badge"><?= count($others) ?></span>
        </div>
        <?php if (empty($others)): ?>
            <div class="notif-empty">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                <p>Nothing here yet — share links and download updates will appear in this list.</p>
            </div>
        <?php else: ?>
            <ul class="notif-list"><?php foreach ($others as $n) $row($n); ?></ul>
        <?php endif; ?>
    </section>

    <!-- ===== Followed categories ===== -->
    <section class="glass notif-block follows-box">
        <div class="notif-block-head">
            <span class="notif-block-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
            <h3>Categories you follow</h3>
            <span class="notif-count-badge"><?= count($follows) ?></span>
        </div>
        <?php if (empty($follows)): ?>
            <div class="notif-empty">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                <p>You aren't following any categories yet. Open any media item and use the <strong>Follow</strong> button next to a category.</p>
            </div>
        <?php else: ?>
        <div class="chip-row">
            <?php foreach ($follows as $f): ?>
            <span class="chip-follow">
                <a class="chip" href="<?= url('/dashboard?category=' . (int) $f['id']) ?>"><?= e($f['name']) ?></a>
                <button type="button" class="follow-btn is-following"
                        data-follow-toggle
                        data-follow-action="<?= e(url('/categories/' . (int) $f['id'] . '/follow')) ?>"
                        aria-pressed="true" title="Unfollow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    <span class="follow-label">Following</span>
                </button>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
