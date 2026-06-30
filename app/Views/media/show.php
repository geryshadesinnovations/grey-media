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
 * @var array $related
 * @var array $favIds
 * @var array $followedCats
 * @var ?string $approvedToken
 * @var bool $hasPendingReq
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
                <?php foreach ($categories as $c): $cid = (int) $c['id']; $following = in_array($cid, $followedCats ?? [], true); ?>
                <span class="chip-follow">
                    <a class="chip" href="<?= url('/dashboard?category=' . $cid) ?>"><?= e($c['name']) ?></a>
                    <button type="button"
                            class="follow-btn <?= $following ? 'is-following' : '' ?>"
                            data-follow-toggle
                            data-follow-action="<?= e(url('/categories/' . $cid . '/follow')) ?>"
                            aria-pressed="<?= $following ? 'true' : 'false' ?>"
                            title="<?= $following ? 'Following - you get notified of new uploads' : 'Follow for new-upload alerts' ?>"
                            aria-label="Toggle follow for <?= e($c['name']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                        <span class="follow-label"><?= $following ? 'Following' : 'Follow' ?></span>
                    </button>
                </span>
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

            <?php /* Download button shows when the user can actually download
                   this file (direct permission, an explicit grant, or super
                   admin). Otherwise we offer the request-approval flow. */ ?>
            <?php if ($canDownload): ?>
                <a class="btn-primary" href="<?= url('/download/' . $media['uuid']) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    Download
                </a>
            <?php elseif (!empty($approvedToken)): ?>
                <?php /* An admin approved this user's request: single-use link. */ ?>
                <a class="btn-primary" href="<?= url('/download/approved/' . $approvedToken) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    Download (approved · one-time)
                </a>
            <?php elseif ($hasPendingReq): ?>
                <button class="btn-ghost" type="button" disabled title="An admin is reviewing your request">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                    Download requested
                </button>
            <?php else: ?>
                <?php /* Direct download disabled for this user -> request flow. */ ?>
                <details class="dl-request">
                    <summary class="btn-ghost">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                        Request download
                    </summary>
                    <form method="post" action="<?= url('/media/' . $media['uuid'] . '/download-request') ?>" class="dl-request-form">
                        <?= Csrf::field() ?>
                        <label>
                            <span>Reason (optional)</span>
                            <input type="text" name="reason" maxlength="500" placeholder="Why do you need this file?">
                        </label>
                        <button type="submit" class="btn-primary btn-block">Send request to admin</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php /* Share: generate a secure, expiring, login-free link. */ ?>
            <details class="share-box">
                <summary class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
                    Share
                </summary>
                <form class="share-form" data-share-form action="<?= e(url('/media/' . $media['uuid'] . '/share')) ?>" method="post">
                    <?= Csrf::field() ?>
                    <label>
                        <span>Link expires after</span>
                        <select name="duration">
                            <option value="1h">1 hour</option>
                            <option value="6h">6 hours</option>
                            <option value="24h" selected>24 hours</option>
                            <option value="7d">7 days</option>
                            <option value="30d">30 days</option>
                        </select>
                    </label>
                    <button type="submit" class="btn-primary btn-block">Generate link</button>
                </form>
                <div class="share-result" data-share-result hidden>
                    <input type="text" class="share-link-input" data-share-link readonly>
                    <button type="button" class="btn-ghost" data-share-copy>Copy</button>
                    <p class="muted share-expiry" data-share-expiry></p>
                </div>
            </details>

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

<?php if (!empty($related)): ?>
<section class="related-media">
    <div class="content-header">
        <h2>Related media</h2>
        <span class="muted">You might also like</span>
    </div>
    <div class="media-grid">
        <?php foreach ($related as $m) { include __DIR__ . '/../dashboard/_card.php'; } ?>
    </div>
</section>
<?php endif; ?>

<?php $this->section('scripts'); ?>
<?php $mainType = $media['media_type']; ?>
<?php if ($mainType === 'video'): ?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js" defer></script>
<script src="<?= asset('js/player.js') ?>" defer></script>
<?php elseif ($mainType === 'pdf' || ($mainType === 'ppt' && !empty($media['preview_path']))): ?>
<script src="<?= asset('js/doc-viewer.js') ?>" defer></script>
<?php elseif ($mainType === 'ppt' && empty($media['preview_path'])): ?>
<script src="<?= asset('js/ppt-viewer.js') ?>" defer></script>
<?php endif; ?>
<?php $this->endSection(); ?>
