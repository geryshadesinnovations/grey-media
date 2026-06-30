<?php $this->extend('layouts/app'); ?>
<div class="error-page">
    <section class="error-stage glass">
        <div class="error-icon error-icon--warn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        </div>
        <h1 class="error-title">This link has expired</h1>
        <p class="error-msg">Approved download links can be used only once and may have expired. If you still need the file, please submit a new download request.</p>
        <div class="error-actions">
            <a class="btn-primary" href="<?= url('/dashboard') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg>
                Back to dashboard
            </a>
            <a class="btn-ghost" href="javascript:history.back()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Go back
            </a>
        </div>
    </section>
</div>
