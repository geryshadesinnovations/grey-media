<?php
/**
 * @var array  $result   Paged media result (rows/total/page/pages)
 * @var string $sort
 * @var array  $favIds   Media ids the user has favorited
 * @var array  $follows  Categories the user follows
 */
$this->extend('layouts/app');
$rows  = $result['rows'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];
?>
<div class="favorites-page" data-favorites-page>

    <!-- ============ A. FOLLOWING ============ -->
    <section class="fav-section">
        <div class="content-header">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="vertical-align:-3px;color:var(--accent)"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                Following
            </h2>
            <span class="muted"><?= count($follows) ?> categor<?= count($follows) === 1 ? 'y' : 'ies' ?></span>
        </div>

        <?php if (empty($follows)): ?>
        <div class="empty-state glass compact">
            <p>You aren't following any categories yet. Open any media item and use the <strong>Follow</strong> button next to a category to see it here and get new-upload alerts.</p>
            <a class="btn-ghost" href="<?= url('/dashboard') ?>">Browse categories</a>
        </div>
        <?php else: ?>
        <div class="follow-card-grid">
            <?php foreach ($follows as $f): ?>
            <div class="follow-card glass">
                <a class="follow-card-link" href="<?= url('/dashboard?category=' . (int) $f['id']) ?>">
                    <span class="follow-card-ic">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span class="follow-card-body">
                        <span class="follow-card-name"><?= e($f['name']) ?></span>
                        <span class="follow-card-section muted"><?= e(ucfirst((string) $f['section_code'])) ?></span>
                    </span>
                </a>
                <button type="button" class="follow-btn is-following"
                        data-follow-toggle
                        data-follow-action="<?= e(url('/categories/' . (int) $f['id'] . '/follow')) ?>"
                        data-removable="1"
                        aria-pressed="true" title="Unfollow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    <span class="follow-label">Following</span>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- ============ B. FAVORITES ============ -->
    <section class="fav-section">
        <div class="content-header">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align:-3px;color:var(--accent)"><path d="M12 21l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.18z"/></svg>
                Favorites
            </h2>
            <span class="muted"><?= number_format($total) ?> item<?= $total === 1 ? '' : 's' ?></span>
        </div>

        <?php if (empty($rows)): ?>
        <div class="empty-state glass">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
            <h3>No favorites yet</h3>
            <p>Browse the library and tap the heart on any video, image, PDF or presentation to save it here.</p>
            <a class="btn-primary" href="<?= url('/dashboard') ?>">Browse media</a>
        </div>
        <?php else: ?>
        <div class="media-grid">
            <?php foreach ($rows as $m) { include __DIR__ . '/../dashboard/_card.php'; } ?>
        </div>

        <?php if ($pages > 1):
            $qs = $_GET;
            $build = function ($p) use ($qs) { $qs['page'] = $p; return '?' . http_build_query($qs); };
        ?>
        <nav class="pagination">
            <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $build(max(1, $page - 1)) ?>">‹ Prev</a>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $build(min($pages, $page + 1)) ?>">Next ›</a>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
