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
                slideMode: false,      // stack every slide so the user can scroll
                keyBoardShortCut: false,
                mediaProcess: false,
            });

            // PPTXjs renders asynchronously; hide the loader once slides appear.
            const stage = document.getElementById('ppt-viewer');
            const wrap = stage.closest('.ppt-viewer-wrap') || stage.parentElement;

            // Scale each fixed-width slide down to fit the container width, so
            // presentations open fully fitted on mobile (no horizontal scroll).
            // We capture each slide's natural width once, then use CSS `zoom`
            // (which reflows the layout box, unlike transform) to fit.
            const fitSlides = () => {
                const avail = (wrap ? wrap.clientWidth : stage.clientWidth) - 24;
                if (avail <= 0) return;
                stage.querySelectorAll('.slide').forEach((sl) => {
                    if (!sl.dataset.naturalW) {
                        sl.style.zoom = '';
                        sl.dataset.naturalW = String(parseFloat(sl.style.width) || sl.offsetWidth || 960);
                    }
                    const nat = parseFloat(sl.dataset.naturalW) || 960;
                    sl.style.zoom = String(Math.min(1, avail / nat));
                });
            };
            let fitTimer = null;
            window.addEventListener('resize', () => {
                clearTimeout(fitTimer);
                fitTimer = setTimeout(fitSlides, 150);
            });

            const done = () => {
                if (stage.childElementCount > 0) {
                    if (status && status.parentNode) status.remove();
                    fitSlides();
                    return true;
                }
                return false;
            };
            done();
            // PPTXjs adds slides one-by-one; keep fitting as they appear, and
            // fit again once rendering settles.
            let settle = null;
            const observer = new MutationObserver(() => {
                if (stage.childElementCount > 0 && status && status.parentNode) status.remove();
                fitSlides();
                clearTimeout(settle);
                settle = setTimeout(fitSlides, 150);
            });
            observer.observe(stage, { childList: true, subtree: true });

            // Safety timeout: if nothing rendered, surface a message; otherwise
            // stop observing and do a final fit.
            setTimeout(() => {
                observer.disconnect();
                if (stage.childElementCount === 0) {
                    showMessage('This presentation could not be displayed. If you have permission, you can download the original file instead.');
                } else {
                    if (status && status.parentNode) status.remove();
                    fitSlides();
                }
            }, 20000);
        } catch (err) {
            console.warn('[ppt-viewer]', err);
            showMessage('This presentation could not be displayed in your browser. If you have permission, you can download the original file instead.');
        }
    })();
})();
