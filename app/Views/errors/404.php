<?php $this->extend('layouts/auth'); $title = 'Page not found'; ?>
<section class="error-stage glass">
    <div class="error-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 0 1 4.5 1.5c0 1.5-2 2-2 3"/><path d="M12 17h.01"/></svg>
    </div>
    <div class="error-code">404</div>
    <h1 class="error-title">Page not found</h1>
    <p class="error-msg">The page you're looking for doesn't exist, may have been moved, or the link is broken.</p>
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
