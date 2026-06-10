/* Greyshades - in-browser PowerPoint (.pptx) viewer.
 *
 * When the server was unable to convert a presentation to PDF (e.g. LibreOffice
 * isn't installed), we render the original .pptx file directly in the browser
 * using PPTXjs so the user can still view every slide inline - no download and
 * no external tab required.
 *
 * The file is fetched same-origin via the short-lived stream token, so the
 * private storage rules and audit trail are preserved.
 *
 * Presentation experience:
 *   - One slide is shown at a time, with Previous / Next controls (and the
 *     keyboard arrows) to move between slides - a proper presentation mode.
 *   - Each slide is scaled to fit the stage (both width AND height), so a
 *     presentation always opens fully fitted with no overflow, even on phones.
 *   - PPTXjs lays text out in absolutely-positioned boxes at their authored
 *     pixel coordinates; we keep that intact (see the scoped box-model reset in
 *     app.css) so text lands exactly where PowerPoint placed it instead of
 *     wrapping/shifting.
 *
 * PPTXjs + its dependencies are loaded lazily from the CDN. If anything fails
 * (offline CDN, a legacy binary .ppt that PPTXjs can't parse, etc.) we degrade
 * gracefully to a clear message instead of breaking the page.
 */
(() => {
    'use strict';

    const mount = document.getElementById('ppt-viewer');
    if (!mount) return;

    const fileUrl = mount.dataset.src;
    const status  = document.getElementById('ppt-status');

    const showMessage = (msg) => {
        if (!status) return;
        status.classList.add('ppt-status-error');
        status.innerHTML = '';
        const p = document.createElement('span');
        p.textContent = msg;
        status.appendChild(p);
    };

    // PPTXjs is distributed on GitHub; jsDelivr serves it (and its bundled
    // helpers) from the gh/ endpoint. jQuery comes from npm.
    const GH  = 'https://cdn.jsdelivr.net/gh/meshesha/PPTXjs@master';
    const cssFiles = [GH + '/css/pptxjs.css', GH + '/css/nv.d3.min.css'];
    const jsFiles  = [
        'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
        GH + '/js/jszip.min.js',
        GH + '/js/filereader.js',
        GH + '/js/d3.min.js',
        GH + '/js/nv.d3.min.js',
        GH + '/js/pptxjs.js',
        GH + '/js/divs2slides.js',
    ];

    const loadCss = (href) => new Promise((resolve) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = resolve;
        link.onerror = resolve; // missing CSS is only cosmetic - keep going
        document.head.appendChild(link);
    });

    const loadScript = (src) => new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = () => reject(new Error('Failed to load ' + src));
        document.head.appendChild(s);
    });

    (async () => {
        try {
            await Promise.all(cssFiles.map(loadCss));
            // Scripts must load in order (jQuery -> helpers -> pptxjs).
            for (const src of jsFiles) {
                await loadScript(src);
            }

            const $ = window.jQuery;
            if (!$ || !$.fn || typeof $.fn.pptxToHtml !== 'function') {
                throw new Error('PPTXjs failed to initialise');
            }

            $('#ppt-viewer').pptxToHtml({
                pptxFileUrl: fileUrl,
                slideMode: false,      // render every slide; we drive the nav ourselves
                keyBoardShortCut: false,
                mediaProcess: false,
            });

            const stage = document.getElementById('ppt-viewer');
            const wrap  = stage.closest('.ppt-viewer-wrap') || stage.parentElement;

            // ---- Presentation state ------------------------------------------------
            let holders = [];     // one .ppt-slide-holder per slide (direct children)
            let current = 0;      // index of the visible slide
            let nav = null, prevBtn = null, nextBtn = null, counter = null;

            const arrow = (dir) =>
                dir === 'left'
                    ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>'
                    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';

            const onKey = (e) => {
                if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
                if (e.key === 'ArrowLeft' || e.key === 'PageUp') { go(current - 1); }
                else if (e.key === 'ArrowRight' || e.key === 'PageDown') { go(current + 1); }
            };

            const buildNav = () => {
                if (nav) return;
                nav = document.createElement('div');
                nav.className = 'ppt-nav';

                prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.setAttribute('aria-label', 'Previous slide');
                prevBtn.innerHTML = arrow('left') + '<span>Previous</span>';

                counter = document.createElement('span');
                counter.className = 'ppt-count';

                nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.setAttribute('aria-label', 'Next slide');
                nextBtn.innerHTML = '<span>Next</span>' + arrow('right');

                nav.append(prevBtn, counter, nextBtn);
                wrap.insertBefore(nav, stage);

                prevBtn.addEventListener('click', () => go(current - 1));
                nextBtn.addEventListener('click', () => go(current + 1));
                window.addEventListener('keydown', onKey);
            };

            // Wrap each freshly-rendered slide in a holder and flatten the
            // markup so every holder is a direct child of the stage (PPTXjs may
            // nest slides inside a wrapper). Idempotent - safe to call repeatedly
            // as PPTXjs streams slides in.
            const collectSlides = () => {
                stage.querySelectorAll('.slide').forEach((sl) => {
                    if (sl.dataset.wrapped) return;
                    sl.dataset.natW = String(parseFloat(sl.style.width) || sl.offsetWidth || 960);
                    sl.dataset.natH = String(parseFloat(sl.style.height) || sl.offsetHeight || 540);
                    const holder = document.createElement('div');
                    holder.className = 'ppt-slide-holder';
                    sl.parentNode.insertBefore(holder, sl);
                    holder.appendChild(sl);
                    sl.dataset.wrapped = '1';
                });
                // Hoist any holder that ended up nested to be a direct child.
                stage.querySelectorAll('.ppt-slide-holder').forEach((h) => {
                    if (h.parentNode !== stage) stage.appendChild(h);
                });
                // Drop now-empty wrapper divs PPTXjs left behind (keeps the grid
                // centering exact).
                Array.from(stage.children).forEach((ch) => {
                    if (!ch.classList.contains('ppt-slide-holder') &&
                        ch.tagName === 'DIV' && ch.children.length === 0) {
                        ch.remove();
                    }
                });
                holders = Array.from(stage.querySelectorAll(':scope > .ppt-slide-holder'));
            };

            // Scale the given slide to fit the stage in BOTH dimensions, so it
            // never overflows. Uniform scale => no distortion.
            const fit = (holder) => {
                if (!holder) return;
                const sl = holder.querySelector('.slide');
                if (!sl) return;
                const natW = parseFloat(sl.dataset.natW) || 960;
                const natH = parseFloat(sl.dataset.natH) || 540;
                const availW = Math.max(40, stage.clientWidth - 24);
                const availH = Math.max(40, stage.clientHeight - 24);
                const scale = Math.min(availW / natW, availH / natH);
                sl.style.transformOrigin = 'top left';
                sl.style.transform = 'scale(' + scale + ')';
                holder.style.width  = (natW * scale) + 'px';
                holder.style.height = (natH * scale) + 'px';
            };

            const render = () => {
                if (!holders.length) return;
                current = Math.max(0, Math.min(current, holders.length - 1));
                holders.forEach((h, i) => { h.style.display = (i === current) ? 'block' : 'none'; });
                fit(holders[current]);
                if (counter) counter.textContent = (current + 1) + ' / ' + holders.length;
                if (prevBtn) prevBtn.disabled = current === 0;
                if (nextBtn) nextBtn.disabled = current === holders.length - 1;
            };

            const go = (i) => {
                if (!holders.length || i < 0 || i > holders.length - 1) return;
                current = i;
                render();
                stage.scrollTop = 0;
                stage.scrollLeft = 0;
            };

            const refresh = () => {
                collectSlides();
                if (holders.length) {
                    buildNav();
                    if (status && status.parentNode) status.remove();
                    render();
                }
            };

            // Initial pass, then keep up as PPTXjs adds slides one-by-one.
            refresh();
            let settle = null;
            const observer = new MutationObserver(() => {
                refresh();
                clearTimeout(settle);
                settle = setTimeout(refresh, 150);
            });
            observer.observe(stage, { childList: true, subtree: true });

            // Keep the active slide fitted on resize / orientation change.
            let fitTimer = null;
            window.addEventListener('resize', () => {
                clearTimeout(fitTimer);
                fitTimer = setTimeout(() => fit(holders[current]), 150);
            });

            // Safety timeout: surface a message if nothing rendered; otherwise
            // stop observing and do a final fit.
            setTimeout(() => {
                observer.disconnect();
                if (!holders.length && stage.querySelectorAll('.slide').length === 0) {
                    showMessage('This presentation could not be displayed. If you have permission, you can download the original file instead.');
                } else {
                    refresh();
                }
            }, 20000);
        } catch (err) {
            console.warn('[ppt-viewer]', err);
            showMessage('This presentation could not be displayed in your browser. If you have permission, you can download the original file instead.');
        }
    })();
})();
