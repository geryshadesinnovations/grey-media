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

/** Renders one notification row. */
$row = function (array $n) use ($icon) {
    $unread = (int) $n['is_read'] === 0;
    echo '<li class="notif-item ' . ($unread ? 'is-unread' : '') . '">';
    echo '<span class="notif-ic notif-ic-' . e($n['type']) . '">';
    echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="' . $icon((string) $n['type']) . '"/></svg>';
    echo '</span><div class="notif-main">';
    if (!empty($n['url'])) {
        echo '<a class="notif-title" href="' . e($n['url']) . '">' . e($n['title']) . '</a>';
    } else {
        echo '<span class="notif-title">' . e($n['title']) . '</span>';
    }
    if (!empty($n['body'])) echo '<p class="notif-body">' . e($n['body']) . '</p>';
    echo '<small class="muted">' . e(date('d M Y, H:i', strtotime((string) $n['created_at']))) . '</small>';
    echo '</div></li>';
};
?>
<div class="notifications-page">
    <div class="content-header">
        <h2>Notifications</h2>
        <?php if ($unread > 0): ?>
        <form method="post" action="<?= url('/notifications/read-all') ?>" style="margin:0">
            <?= Csrf::field() ?>
            <button type="submit" class="btn-ghost">Mark all as read</button>
        </form>
        <?php endif; ?>
    </div>

    <p class="muted notif-cleanup-note">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        Notifications you've viewed are automatically cleared 24 hours later.
    </p>

    <!-- ===== Uploads container ===== -->
    <section class="glass notif-block">
        <h3>Uploads</h3>
        <?php if (empty($uploads)): ?>
            <p class="muted notif-block-empty">No new uploads in your sections right now.</p>
        <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($uploads as $n) $row($n); ?>
        </ul>
        <?php endif; ?>
    </section>

    <!-- ===== Other activity ===== -->
    <section class="glass notif-block">
        <h3>Activity</h3>
        <?php if (empty($others)): ?>
            <p class="muted notif-block-empty">Nothing here yet — share links and download updates will appear in this list.</p>
        <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($others as $n) $row($n); ?>
        </ul>
        <?php endif; ?>
    </section>

    <!-- ===== Followed categories ===== -->
    <section class="glass notif-block follows-box">
        <h3>Categories you follow</h3>
        <?php if (empty($follows)): ?>
            <p class="muted">You aren't following any categories yet. Open any media item and use the <strong>Follow</strong> button next to a category.</p>
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
