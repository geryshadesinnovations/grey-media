<?php
/**
 * @var array $media
 * @var array $section
 * @var array $categories
 * @var string $streamToken
 * @var bool $canDownload
 * @var bool $canEdit
 * @var bool $canDelete
 * @var bool $isFavorite
 */
use App\Core\Csrf;
$this->extend('layouts/app');
$type = $media['media_type'];
$streamUrl = url('/stream/' . $media['uuid'] . '?token=' . $streamToken);
$hlsUrl    = !empty($media['hls_master']) ? url('/stream/' . $media['uuid'] . '/hls/master.m3u8?token=' . $streamToken) : '';
$previewUrl = url('/preview/' . $media['uuid']);
?>
<div class="media-detail no-select">
    <a class="back-link" href="javascript:history.back()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back
    </a>

    <div class="media-stage glass" id="media-stage" data-type="<?= e($type) ?>">
        <?php if ($type === 'video'): ?>
            <video id="gs-video"
                   class="gs-player"
                   controls
                   controlsList="nodownload noremoteplayback noplaybackrate"
                   disablepictureinpicture
                   playsinline
                   preload="metadata"
                   poster="<?= url('/thumb/' . $media['uuid']) ?>"
                   <?= $hlsUrl ? 'data-hls-src="'.e($hlsUrl).'"' : '' ?>
                   data-mp4-src="<?= e($streamUrl) ?>"></video>

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
                <div id="ppt-status" class="ppt-status">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>Rendering presentation…</span>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <p class="muted">This media type cannot be previewed inline.</p>
        <?php endif; ?>
    </div>

    <aside class="media-info glass">
        <h1><?= e($media['title']) ?></h1>
        <div class="info-meta">
            <span class="badge"><?= strtoupper($type) ?></span>
            <span class="badge soft"><?= e($section['name']) ?></span>
            <?php if (!empty($media['duration_sec'])): ?>
                <span class="badge soft"><?= e(format_duration($media['duration_sec'])) ?></span>
            <?php endif; ?>
            <span class="badge soft"><?= e(format_bytes((int) $media['file_size'])) ?></span>
            <span class="muted"><?= e(date('d M Y', strtotime((string) $media['created_at']))) ?></span>
        </div>

        <?php if (!empty($media['description'])): ?>
            <p class="info-desc"><?= nl2br(e($media['description'])) ?></p>
        <?php endif; ?>

        <?php if ($categories): ?>
        <section>
            <h3>Categories</h3>
            <div class="chip-row">
                <?php foreach ($categories as $c): ?>
                <a class="chip" href="<?= url('/dashboard?category=' . (int)$c['id']) ?>"><?= e($c['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="info-actions">
            <?php /* Favorite ("like") toggle - works for every media type.
                   JS intercepts the click and POSTs to /favorites/toggle; the
                   <noscript>-friendly form fallback below still works without JS. */ ?>
            <form method="post" action="<?= url('/favorites/toggle/' . $media['uuid']) ?>" class="fav-form">
                <?= Csrf::field() ?>
                <button type="submit"
                        class="btn-ghost fav-btn fav-btn--detail <?= $isFavorite ? 'is-fav' : '' ?>"
                        data-fav-toggle
                        data-fav-action="<?= e(url('/favorites/toggle/' . $media['uuid'])) ?>"
                        aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                    <span class="fav-label"><?= $isFavorite ? 'Favorited' : 'Favorite' ?></span>
                </button>
            </form>

            <?php /* Download button only shows when 'Allow Download' was
                   checked at upload time. SuperAdmins can still hit the
                   /download/{uuid} route directly if needed for moderation,
                   but the button stays hidden so it never misleads users. */ ?>
            <?php if (!empty($media['is_downloadable']) && $canDownload): ?>
                <a class="btn-primary" href="<?= url('/download/' . $media['uuid']) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    Download
                </a>
            <?php endif; ?>

            <?php if ($canEdit): ?>
                <a class="btn-ghost" href="<?= url('/media/' . $media['id'] . '/edit') ?>">Edit</a>
            <?php endif; ?>

            <?php if ($canDelete): ?>
            <form method="post" action="<?= url('/media/' . $media['id'] . '/delete') ?>" onsubmit="return confirm('Delete this media permanently? This cannot be undone.')">
                <?= Csrf::field() ?>
                <button type="submit" class="btn-danger">Delete</button>
            </form>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php $this->section('scripts'); ?>
<?php if ($type === 'video'): ?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js" defer></script>
<script src="<?= asset('js/player.js') ?>" defer></script>
<?php elseif ($type === 'pdf' || ($type === 'ppt' && !empty($media['preview_path']))): ?>
<script src="<?= asset('js/doc-viewer.js') ?>" defer></script>
<?php elseif ($type === 'ppt' && empty($media['preview_path'])): ?>
<script src="<?= asset('js/ppt-viewer.js') ?>" defer></script>
<?php endif; ?>
<?php $this->endSection(); ?>
