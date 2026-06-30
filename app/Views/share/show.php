<?php
/**
 * Public, login-free viewer for a shared media item.
 *
 * @var array  $media
 * @var string $token
 * @var string $type
 */
$this->extend('layouts/share');
$title = $media['title'];
$streamUrl  = url('/s/' . $token . '/stream');
$previewUrl = url('/s/' . $token . '/preview');
$expires = !empty($media['share_expires_at']) ? date('d M Y, H:i', strtotime((string) $media['share_expires_at'])) : null;
?>
<div class="media-detail share-detail">
    <div class="media-stage glass" data-type="<?= e($type) ?>">
        <?php if ($type === 'video'): ?>
            <video class="gs-player" controls controlsList="nodownload noremoteplayback" disablepictureinpicture
                   playsinline preload="metadata" poster="<?= e(url('/s/' . $token . '/thumb')) ?>"
                   src="<?= e($streamUrl) ?>"></video>

        <?php elseif ($type === 'image'): ?>
            <div class="image-stage">
                <img src="<?= e($streamUrl) ?>" alt="<?= e($media['title']) ?>">
            </div>

        <?php elseif ($type === 'pdf'): ?>
            <div class="doc-viewer-wrap">
                <div id="doc-viewer" class="doc-viewer" data-src="<?= e($streamUrl) ?>"></div>
                <div id="doc-status" class="ppt-status"><span class="spinner" aria-hidden="true"></span><span>Loading document…</span></div>
            </div>

        <?php elseif ($type === 'ppt'): ?>
            <?php if (!empty($media['preview_path'])): ?>
            <div class="doc-viewer-wrap">
                <div id="doc-viewer" class="doc-viewer" data-src="<?= e($previewUrl) ?>"></div>
                <div id="doc-status" class="ppt-status"><span class="spinner" aria-hidden="true"></span><span>Loading presentation…</span></div>
            </div>
            <?php else: ?>
            <div class="ppt-viewer-wrap">
                <div id="ppt-viewer" class="pptx-host" data-src="<?= e($streamUrl) ?>"></div>
                <div id="ppt-status" class="ppt-status"><span class="spinner" aria-hidden="true"></span><span>Rendering presentation…</span></div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <p class="muted">This media type cannot be previewed.</p>
        <?php endif; ?>
    </div>

    <aside class="media-info glass">
        <h1><?= e($media['title']) ?></h1>
        <div class="info-meta">
            <span class="badge"><?= strtoupper($type) ?></span>
            <span class="badge soft"><?= e(format_bytes((int) $media['file_size'])) ?></span>
        </div>
        <?php if (!empty($media['description'])): ?>
            <p class="info-desc"><?= nl2br(e($media['description'])) ?></p>
        <?php endif; ?>
        <p class="share-note muted">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            You are viewing a shared item<?php if ($expires): ?>. This link expires on <strong><?= e($expires) ?></strong><?php endif; ?>.
        </p>
    </aside>
</div>

<?php $this->section('scripts'); ?>
<?php if ($type === 'pdf' || ($type === 'ppt' && !empty($media['preview_path']))): ?>
<script src="<?= asset('js/doc-viewer.js') ?>" defer></script>
<?php elseif ($type === 'ppt' && empty($media['preview_path'])): ?>
<script src="<?= asset('js/ppt-viewer.js') ?>" defer></script>
<?php endif; ?>
<?php $this->endSection(); ?>
