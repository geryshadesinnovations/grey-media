<?php
/**
 * @var array  $result   Paged media result (rows/total/page/pages)
 * @var string $sort
 * @var array  $favIds   Media ids the user has favorited (all rows here are favorites)
 */
$this->extend('layouts/app');
$rows  = $result['rows'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];
?>
<div class="favorites-page" data-favorites-page>
    <div class="content-header">
        <h2>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align:-3px;color:var(--accent)"><path d="M12 21l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.18z"/></svg>
            My Favorites
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
</div>
