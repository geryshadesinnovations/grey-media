<?php
/**
 * @var array $requests
 * @var int   $pending
 */
use App\Core\Csrf;
$this->extend('layouts/app');

$badge = static function (string $status): array {
    return match ($status) {
        'approved' => ['Approved', 'badge-ok'],
        'rejected' => ['Rejected', 'badge-danger'],
        default    => ['Pending',  'badge-warn'],
    };
};
?>
<div class="admin-shell">
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
        <a class="back-link" href="<?= url('/admin') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to Admin
        </a>
        <h1>Download Requests <?php if ($pending > 0): ?><span class="badge badge-warn"><?= (int) $pending ?> pending</span><?php endif; ?></h1>
        <p class="muted">Approving a request gives that user a single-use download link. After one download, it is automatically revoked.</p>

        <?php if (empty($requests)): ?>
        <div class="empty-state glass">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            <h3>No download requests</h3>
            <p>When users request access to non-downloadable files, they'll show up here for review.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap glass">
        <table class="table">
            <thead><tr><th>Requested</th><th>User</th><th>Media</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): [$label, $cls] = $badge((string) $r['status']); ?>
                <tr>
                    <td><?= e(date('d M Y, H:i', strtotime((string) $r['created_at']))) ?></td>
                    <td><?= e($r['user_name']) ?><br><small class="muted"><?= e($r['user_username']) ?></small></td>
                    <td><a href="<?= url('/media/' . $r['media_uuid']) ?>"><?= e($r['media_title']) ?></a><br><small class="muted"><?= strtoupper((string) $r['media_type']) ?></small></td>
                    <td><?= e($r['reason'] ?? '') ?: '<span class="muted">—</span>' ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $label ?></span>
                        <?php if ($r['status'] === 'approved' && !empty($r['used_at'])): ?><br><small class="muted">downloaded</small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <div class="row-actions">
                            <form method="post" action="<?= url('/admin/download-requests/' . (int) $r['id'] . '/approve') ?>" style="margin:0">
                                <?= Csrf::field() ?>
                                <button class="btn-primary small" type="submit">Approve</button>
                            </form>
                            <form method="post" action="<?= url('/admin/download-requests/' . (int) $r['id'] . '/reject') ?>" style="margin:0">
                                <?= Csrf::field() ?>
                                <button class="btn-danger small" type="submit">Reject</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <small class="muted"><?= $r['reviewer_name'] ? 'by ' . e($r['reviewer_name']) : '—' ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
</div>
