<?php $this->extend('layouts/auth'); $title = 'Access denied'; ?>
<section class="error-stage glass">
    <div class="error-icon error-icon--danger">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><path d="M12 15v2"/></svg>
    </div>
    <div class="error-code">403</div>
    <h1 class="error-title">Access denied</h1>
    <p class="error-msg">You don't have permission to view this resource. If you think this is a mistake, contact an administrator.</p>
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
