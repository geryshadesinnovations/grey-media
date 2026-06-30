<?php
/**
 * @var array $stats
 * @var array $storageByType
 * @var array $viewsSeries
 * @var array $downloadsSeries
 * @var array $uploadsSeries
 * @var array $topViewed
 * @var array $topDownloaded
 * @var array $activeUsers
 * @var array $actions
 */
$this->extend('layouts/app');

/** Render a zero-filled daily series as a lightweight CSS bar chart. */
$chart = static function (array $series): string {
    $max = 0;
    foreach ($series as $p) $max = max($max, (int) $p['count']);
    $max = max(1, $max);
    $html = '<div class="bar-chart">';
    foreach ($series as $p) {
        $c = (int) $p['count'];
        $h = (int) round(($c / $max) * 100);
        $html .= '<div class="bar-col" title="' . e($p['label']) . ': ' . $c . '">'
               . '<div class="bar" style="height:' . max(2, $h) . '%"></div>'
               . '</div>';
    }
    $html .= '</div>';
    return $html;
};

$sumSeries = static fn (array $s): int => array_sum(array_map(static fn ($p) => (int) $p['count'], $s));
?>
<div class="admin-shell">
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
        <a class="back-link" href="<?= url('/admin') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to Admin
        </a>
        <div class="content-header">
            <h1>Analytics</h1>
            <div class="row-actions">
                <a class="btn-ghost" href="<?= url('/admin/analytics/export?report=activity') ?>">Export activity CSV</a>
                <a class="btn-ghost" href="<?= url('/admin/analytics/export?report=downloads') ?>">Export downloads CSV</a>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card glass"><span class="stat-label">Total media</span><span class="stat-value"><?= number_format($stats['media_total']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Storage used</span><span class="stat-value"><?= e(format_bytes($stats['storage_bytes'])) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Views (30d)</span><span class="stat-value"><?= number_format($sumSeries($viewsSeries)) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Downloads (30d)</span><span class="stat-value"><?= number_format($sumSeries($downloadsSeries)) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Uploads (30d)</span><span class="stat-value"><?= number_format($sumSeries($uploadsSeries)) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Active users</span><span class="stat-value"><?= number_format($stats['users_total']) ?></span></div>
        </div>

        <div class="admin-cols">
            <section class="glass">
                <h3>Views · last 30 days <small class="muted">(<?= number_format($sumSeries($viewsSeries)) ?>)</small></h3>
                <?= $chart($viewsSeries) ?>
            </section>
            <section class="glass">
                <h3>Downloads · last 30 days <small class="muted">(<?= number_format($sumSeries($downloadsSeries)) ?>)</small></h3>
                <?= $chart($downloadsSeries) ?>
            </section>
        </div>

        <div class="admin-cols">
            <section class="glass">
                <h3>Uploads · last 30 days <small class="muted">(<?= number_format($sumSeries($uploadsSeries)) ?>)</small></h3>
                <?= $chart($uploadsSeries) ?>
            </section>
            <section class="glass">
                <h3>Storage by type</h3>
                <?php
                    $maxBytes = 1;
                    foreach ($storageByType as $row) $maxBytes = max($maxBytes, (int) $row['bytes']);
                ?>
                <div class="hbar-list">
                    <?php foreach ($storageByType as $row): $pct = (int) round(((int) $row['bytes'] / $maxBytes) * 100); ?>
                    <div class="hbar-row">
                        <span class="hbar-label"><?= strtoupper((string) $row['media_type']) ?> <small class="muted">(<?= number_format($row['items']) ?>)</small></span>
                        <span class="hbar-track"><span class="hbar-fill" style="width:<?= max(3, $pct) ?>%"></span></span>
                        <span class="hbar-val"><?= e(format_bytes((int) $row['bytes'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="admin-cols">
            <section class="glass">
                <h3>Most active users (30d)</h3>
                <?php if (empty($activeUsers)): ?>
                    <p class="muted">No activity yet.</p>
                <?php else: ?>
                <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>User</th><th>Views</th><th>Downloads</th></tr></thead>
                    <tbody>
                    <?php foreach ($activeUsers as $u): ?>
                        <tr><td><?= e($u['name']) ?><br><small class="muted"><?= e($u['username']) ?></small></td><td><?= number_format($u['views']) ?></td><td><?= number_format($u['downloads']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </section>
            <section class="glass">
                <h3>Activity breakdown (30d)</h3>
                <ol class="rank-list">
                    <?php foreach ($actions as $a): ?>
                    <li><span class="rank-title"><?= e($a['action']) ?></span><span class="rank-num"><?= number_format($a['c']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>

        <div class="admin-cols">
            <section class="glass">
                <h3>Top viewed</h3>
                <ol class="rank-list">
                    <?php foreach ($topViewed as $m): ?>
                    <li><span class="rank-title"><?= e($m['title']) ?></span><span class="rank-num"><?= number_format($m['view_count']) ?> views</span></li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <section class="glass">
                <h3>Top downloaded</h3>
                <ol class="rank-list">
                    <?php foreach ($topDownloaded as $m): ?>
                    <li><span class="rank-title"><?= e($m['title']) ?></span><span class="rank-num"><?= number_format($m['download_count']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>
    </section>
</div>
