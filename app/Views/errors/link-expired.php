<?php $this->extend('layouts/app'); ?>
<div class="error-page">
    <div class="empty-state glass">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        <h3>This download link is no longer valid</h3>
        <p>Approved download links can be used only once and may have expired. If you still need the file, please submit a new download request.</p>
        <a class="btn-primary" href="<?= url('/dashboard') ?>">Back to dashboard</a>
    </div>
</div>
