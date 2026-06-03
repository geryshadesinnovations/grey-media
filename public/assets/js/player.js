/* Greyshades - secure HTML5 video player with HLS.js fallback to MP4.
 *
 * A single, YouTube-style settings (gear) menu sits in the top-right of the
 * player. It contains two rows - "Quality" and "Playback speed" - each of
 * which opens a submenu of choices. This keeps the main control bar clean.
 *
 * Quality switching is only possible through HLS.js (adaptive renditions:
 * 240p / 480p / 720p / 1080p). Native HLS (Safari) and the plain MP4 fallback
 * manage quality themselves, so the gear menu is only built for the HLS.js
 * path. Playback speed is always offered there too.
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
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none">' +
        '<circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>';
    const chevron = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>';
    const backChevron = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>';

    const SPEEDS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
    const speedLabel = (r) => (r === 1 ? 'Normal' : r + 'x');
    const esc = (s) => String(s).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /**
     * Build the gear settings menu. `hls` may be an HLS.js instance (enables
     * the Quality row) or null (speed-only).
     */
    const buildSettingsMenu = (hls) => {
        if (!stage) return;
        const levels = hls ? (hls.levels || []) : [];
        const hasQuality = levels.length >= 2;

        const wrap = document.createElement('div');
        wrap.className = 'gs-settings';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gs-settings-btn';
        btn.setAttribute('aria-label', 'Settings');
        btn.innerHTML = gearSvg;

        const panel = document.createElement('div');
        panel.className = 'gs-settings-panel';
        panel.hidden = true;

        wrap.appendChild(btn);
        wrap.appendChild(panel);
        stage.appendChild(wrap);

        const qualityOptions = () => {
            const opts = [{ label: 'Auto', value: -1 }];
            levels
                .map((l, i) => ({ height: l.height, index: i }))
                .sort((a, b) => (b.height || 0) - (a.height || 0))
                .forEach(({ height, index }) => {
                    opts.push({ label: height ? height + 'p' : ('Level ' + (index + 1)), value: index });
                });
            return opts;
        };

        const currentQualityLabel = () => {
            if (!hls) return '';
            const lvl = hls.levels[hls.currentLevel];
            if (hls.autoLevelEnabled || hls.currentLevel === -1) {
                return 'Auto' + (lvl && lvl.height ? ' (' + lvl.height + 'p)' : '');
            }
            return lvl && lvl.height ? lvl.height + 'p' : 'Auto';
        };
        const currentSpeedLabel = () => speedLabel(video.playbackRate || 1);

        const isQualityActive = (val) => val === -1
            ? hls.autoLevelEnabled
            : (!hls.autoLevelEnabled && hls.currentLevel === val);

        const row = (key, label, value) =>
            '<button type="button" class="gs-set-row" data-row="' + key + '">' +
                '<span>' + label + '</span>' +
                '<span class="gs-set-val">' + esc(value) + chevron + '</span>' +
            '</button>';
        const back = (title) =>
            '<button type="button" class="gs-set-back" data-back="1">' + backChevron + '<span>' + title + '</span></button>';
        const choice = (kind, value, label, active) =>
            '<button type="button" class="gs-set-choice' + (active ? ' active' : '') + '" data-kind="' + kind + '" data-value="' + value + '">' +
                esc(label) + '</button>';

        let view = 'main';
        const render = () => {
            if (view === 'quality') {
                panel.innerHTML = back('Quality') +
                    qualityOptions().map(o => choice('q', o.value, o.label, isQualityActive(o.value))).join('');
            } else if (view === 'speed') {
                panel.innerHTML = back('Playback speed') +
                    SPEEDS.map(s => choice('s', s, speedLabel(s), Math.abs((video.playbackRate || 1) - s) < 0.001)).join('');
            } else {
                panel.innerHTML =
                    (hasQuality ? row('quality', 'Quality', currentQualityLabel()) : '') +
                    row('speed', 'Playback speed', currentSpeedLabel());
            }
        };

        panel.addEventListener('click', (e) => {
            const rowEl = e.target.closest('[data-row]');
            if (rowEl) { view = rowEl.dataset.row; render(); return; }
            if (e.target.closest('[data-back]')) { view = 'main'; render(); return; }
            const choiceEl = e.target.closest('[data-kind]');
            if (choiceEl) {
                if (choiceEl.dataset.kind === 'q' && hls) {
                    hls.currentLevel = parseInt(choiceEl.dataset.value, 10); // -1 => Auto
                } else if (choiceEl.dataset.kind === 's') {
                    video.playbackRate = parseFloat(choiceEl.dataset.value);
                }
                view = 'main';
                render();
            }
        });

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            panel.hidden = !panel.hidden;
            if (!panel.hidden) { view = 'main'; render(); }
        });
        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target)) panel.hidden = true;
        });
        if (hls) {
            hls.on(window.Hls.Events.LEVEL_SWITCHED, () => {
                if (!panel.hidden && view === 'main') render();
            });
        }

        render();
    };

    if (hlsSrc) {
        if (window.Hls && window.Hls.isSupported()) {
            const hls = new window.Hls({ enableWorker: true, lowLatencyMode: false, capLevelToPlayerSize: true });
            hls.loadSource(hlsSrc);
            hls.attachMedia(video);
            hls.on(window.Hls.Events.MANIFEST_PARSED, () => buildSettingsMenu(hls));
            hls.on(window.Hls.Events.ERROR, (_, data) => {
                if (data.fatal) {
                    console.warn('[player] HLS fatal, falling back to MP4', data);
                    hls.destroy();
                    const s = stage && stage.querySelector('.gs-settings');
                    if (s) s.remove();
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
