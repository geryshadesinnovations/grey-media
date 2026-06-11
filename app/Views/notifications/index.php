<?php
/**
 * @var array $items
 * @var array $follows
 * @var int   $unread
 */
use App\Core\Csrf;
$this->extend('layouts/app');

$icon = static function (string $type): string {
    return match ($type) {
        'upload'            => 'M12 19V5M5 12l7-7 7 7',
        'share'            => 'M4 12v8h16v-8M16 6l-4-4-4 4M12 2v14',
        'download_approved' => 'M20 6 9 17l-5-5',
        'download_rejected' => 'M18 6 6 18M6 6l12 12',
        'download_request'  => 'M12 5v14M5 12l7 7 7-7',
        default             => 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9',
    };
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

    <?php if (empty($items)): ?>
    <div class="empty-state glass">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <h3>No notifications yet</h3>
        <p>You'll be notified about uploads in categories you follow, share links, and download request updates.</p>
    </div>
    <?php else: ?>
    <ul class="notif-list glass">
        <?php foreach ($items as $n): ?>
        <li class="notif-item <?= (int) $n['is_read'] === 0 ? 'is-unread' : '' ?>">
            <span class="notif-ic notif-ic-<?= e($n['type']) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="<?= $icon((string) $n['type']) ?>"/></svg>
            </span>
            <div class="notif-main">
                <?php if (!empty($n['url'])): ?>
                <a class="notif-title" href="<?= e($n['url']) ?>"><?= e($n['title']) ?></a>
                <?php else: ?>
                <span class="notif-title"><?= e($n['title']) ?></span>
                <?php endif; ?>
                <?php if (!empty($n['body'])): ?><p class="notif-body"><?= e($n['body']) ?></p><?php endif; ?>
                <small class="muted"><?= e(date('d M Y, H:i', strtotime((string) $n['created_at']))) ?></small>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <section class="glass follows-box">
        <h3>Categories you follow</h3>
        <?php if (empty($follows)): ?>
            <p class="muted">You aren't following any categories yet. Open any media item and use the <strong>Follow</strong> button next to a category to get new-upload alerts.</p>
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
