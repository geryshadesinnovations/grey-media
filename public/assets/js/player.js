/* Greyshades - secure HTML5 video player with HLS.js fallback to MP4.
 *
 * When the video is served as an adaptive HLS bundle (multiple renditions:
 * 240p / 480p / 720p / 1080p) we render a custom quality selector so users can
 * pin a specific resolution or leave it on Auto (adaptive bitrate). Quality
 * switching is only possible through HLS.js; native HLS (Safari) and the plain
 * MP4 fallback manage quality themselves, so the selector is hidden there.
 */
(() => {
    'use strict';
    const video = document.getElementById('gs-video');
    if (!video) return;

    const stage  = video.closest('.media-stage') || video.parentElement;
    const hlsSrc = video.dataset.hlsSrc;
    const mp4Src = video.dataset.mp4Src;

    const startNative = () => {
        if (mp4Src) video.src = mp4Src;
    };

    const gearSvg =
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';

    /**
     * Build the quality menu for an HLS.js instance once the manifest is known.
     * Levels are listed high -> low, with an Auto (adaptive) option on top.
     */
    const buildQualityMenu = (hls) => {
        const levels = hls.levels || [];
        if (levels.length < 2 || !stage) return; // nothing to switch between

        const wrap = document.createElement('div');
        wrap.className = 'gs-quality';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gs-quality-btn';
        btn.setAttribute('aria-label', 'Video quality');
        btn.innerHTML = gearSvg + '<span class="gs-quality-label">Auto</span>';

        const menu = document.createElement('div');
        menu.className = 'gs-quality-menu';
        menu.hidden = true;

        wrap.appendChild(btn);
        wrap.appendChild(menu);
        stage.appendChild(wrap);

        // Options: Auto + each rendition, sorted by height descending.
        const options = [{ label: 'Auto', value: -1 }];
        levels
            .map((l, i) => ({ height: l.height, bitrate: l.bitrate, index: i }))
            .sort((a, b) => (b.height || 0) - (a.height || 0))
            .forEach(({ height, index }) => {
                options.push({ label: height ? height + 'p' : ('Level ' + (index + 1)), value: index });
            });

        const currentLabel = () => {
            const lvl = hls.levels[hls.currentLevel];
            if (hls.autoLevelEnabled || hls.currentLevel === -1) {
                return 'Auto' + (lvl && lvl.height ? ' · ' + lvl.height + 'p' : '');
            }
            return lvl && lvl.height ? lvl.height + 'p' : 'Auto';
        };

        const render = () => {
            menu.innerHTML = '';
            options.forEach((opt) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'gs-quality-item';
                const active = opt.value === -1
                    ? hls.autoLevelEnabled
                    : (!hls.autoLevelEnabled && hls.currentLevel === opt.value);
                if (active) item.classList.add('active');
                item.textContent = opt.label;
                item.addEventListener('click', () => {
                    hls.currentLevel = opt.value; // -1 => Auto (adaptive)
                    menu.hidden = true;
                    updateLabel();
                    render();
                });
                menu.appendChild(item);
            });
        };

        const updateLabel = () => {
            const label = wrap.querySelector('.gs-quality-label');
            if (label) label.textContent = currentLabel();
        };

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.hidden = !menu.hidden;
            if (!menu.hidden) render();
        });
        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target)) menu.hidden = true;
        });
        // Keep the label in sync as adaptive mode changes renditions.
        hls.on(window.Hls.Events.LEVEL_SWITCHED, updateLabel);

        render();
        updateLabel();
    };

    if (hlsSrc) {
        if (window.Hls && window.Hls.isSupported()) {
            const hls = new window.Hls({ enableWorker: true, lowLatencyMode: false, capLevelToPlayerSize: true });
            hls.loadSource(hlsSrc);
            hls.attachMedia(video);
            hls.on(window.Hls.Events.MANIFEST_PARSED, () => buildQualityMenu(hls));
            hls.on(window.Hls.Events.ERROR, (_, data) => {
                if (data.fatal) {
                    console.warn('[player] HLS fatal, falling back to MP4', data);
                    hls.destroy();
                    const q = stage && stage.querySelector('.gs-quality');
                    if (q) q.remove();
                    startNative();
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari / native HLS - browser manages adaptive quality itself.
            video.src = hlsSrc;
        } else {
            startNative();
        }
    } else {
        startNative();
    }

    // Disable native context menu on the video itself
    video.addEventListener('contextmenu', (e) => e.preventDefault());

    // Track watch progress (best-effort).
    let lastReport = 0;
    video.addEventListener('timeupdate', () => {
        if (!video.duration || video.duration === Infinity) return;
        const pct = Math.floor((video.currentTime / video.duration) * 100);
        if (pct - lastReport >= 25) {
            lastReport = pct;
            // Phase 2: POST /api/watch-progress
        }
    });
})();
