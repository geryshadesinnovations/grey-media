/* Greyshades - responsive in-page document viewer (PDF + PPT-as-PDF).
 *
 * Why this exists: the browser's native PDF iframe does not reliably "fit to
 * width" on mobile (documents open too large and need horizontal scrolling).
 * This viewer renders each page with PDF.js onto a canvas sized to the
 * container width, so documents open perfectly fitted edge-to-edge on any
 * screen, while still allowing manual zoom in/out. Pages render lazily so
 * large documents stay fast and light on memory.
 *
 * If PDF.js cannot load (e.g. CDN blocked), it gracefully falls back to the
 * original native <iframe> behaviour so the document is still viewable.
 */
(() => {
    'use strict';

    const mount  = document.getElementById('doc-viewer');
    if (!mount) return;
    const src    = mount.dataset.src;
    const status = document.getElementById('doc-status');

    const PDFJS_URL  = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
    const WORKER_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';

    const MIN = 0.5, MAX = 5, STEP = 1.25;

    // ---- Graceful fallback: the old native iframe (still fits-width-ish on desktop)
    const fallback = () => {
        mount.innerHTML = '';
        const f = document.createElement('iframe');
        f.src = src + '#toolbar=0&navpanes=0&scrollbar=1&view=FitH';
        f.title = 'Document';
        f.style.cssText = 'width:100%;height:100%;border:0;display:block';
        mount.appendChild(f);
        if (status && status.parentNode) status.remove();
    };

    const loadPdfjs = () => new Promise((res, rej) => {
        if (window.pdfjsLib) return res(window.pdfjsLib);
        const s = document.createElement('script');
        s.src = PDFJS_URL;
        s.onload = () => window.pdfjsLib ? res(window.pdfjsLib) : rej(new Error('pdfjsLib missing'));
        s.onerror = () => rej(new Error('failed to load pdf.js'));
        document.head.appendChild(s);
    });

    let pdf = null, zoom = 1, fitScale = 1, baseRatio = 1.414;
    let pagesWrap = null, zoomLabel = null, pageEls = [], rendered = {}, io = null;

    const dpr = () => Math.min(window.devicePixelRatio || 1, 2);
    const containerWidth = () => Math.max(140, (pagesWrap ? pagesWrap.clientWidth : mount.clientWidth) - 24);

    const buildUI = () => {
        mount.innerHTML = '';
        const bar = document.createElement('div');
        bar.className = 'doc-toolbar';
        const mk = (txt, label) => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = txt; b.setAttribute('aria-label', label);
            return b;
        };
        const out = mk('\u2212', 'Zoom out');
        const fit = mk('Fit', 'Fit to width');
        const inn = mk('+', 'Zoom in');
        zoomLabel = document.createElement('span');
        zoomLabel.className = 'doc-zoom-label';
        bar.append(out, zoomLabel, inn, fit);

        pagesWrap = document.createElement('div');
        pagesWrap.className = 'doc-pages';
        mount.append(bar, pagesWrap);

        out.addEventListener('click', () => setZoom(zoom / STEP));
        inn.addEventListener('click', () => setZoom(zoom * STEP));
        fit.addEventListener('click', () => setZoom(1));
    };

    const updateLabel = () => { if (zoomLabel) zoomLabel.textContent = Math.round(zoom * 100) + '%'; };

    // Measure page 1 to get the fit-to-width scale and aspect ratio.
    const computeFit = async () => {
        const p1 = await pdf.getPage(1);
        const vp = p1.getViewport({ scale: 1 });
        baseRatio = vp.height / vp.width;
        fitScale = containerWidth() / vp.width;   // scale where page width == container width
    };

    const renderPage = async (i) => {
        if (rendered[i]) return;
        rendered[i] = true;
        const el = pageEls[i];
        if (!el) return;
        try {
            const page = await pdf.getPage(i);
            const cssScale = fitScale * zoom;                 // 1.0 zoom = fit width
            const vp = page.getViewport({ scale: cssScale * dpr() });
            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(vp.width);
            canvas.height = Math.floor(vp.height);
            const ctx = canvas.getContext('2d', { alpha: false });
            await page.render({ canvasContext: ctx, viewport: vp }).promise;
            el.innerHTML = '';
            el.style.height = 'auto';
            el.appendChild(canvas);
        } catch (e) {
            rendered[i] = false; // allow a retry on next scroll
        }
    };

    // (Re)build the page placeholders at the current zoom, then lazy-render.
    const layout = () => {
        if (io) io.disconnect();
        pagesWrap.innerHTML = '';
        pageEls = []; rendered = {};
        // At zoom 1 the page width equals the container width (fit-to-width).
        // Zooming in/out simply multiplies that width; placeholders use page-1
        // aspect ratio so the scroll height is correct before lazy render.
        const wpx = containerWidth() * zoom;
        for (let i = 1; i <= pdf.numPages; i++) {
            const d = document.createElement('div');
            d.className = 'doc-page';
            d.style.width = wpx + 'px';
            d.style.height = (wpx * baseRatio) + 'px';
            d.dataset.p = i;
            pageEls[i] = d;
            pagesWrap.appendChild(d);
        }
        io = new IntersectionObserver((entries) => {
            entries.forEach((en) => { if (en.isIntersecting) renderPage(+en.target.dataset.p); });
        }, { root: pagesWrap, rootMargin: '600px 0px' });
        pageEls.forEach((d) => { if (d) io.observe(d); });
    };

    const setZoom = (z) => {
        zoom = Math.max(MIN, Math.min(MAX, z));
        updateLabel();
        layout();
    };

    let resizeTimer = null;
    const onResize = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(async () => {
            await computeFit();
            layout();
        }, 200);
    };

    (async () => {
        try {
            await loadPdfjs();
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER_URL;
            buildUI();
            pdf = await window.pdfjsLib.getDocument({ url: src, withCredentials: true }).promise;
            await computeFit();
            updateLabel();
            layout();
            if (status && status.parentNode) status.remove();
            window.addEventListener('resize', onResize);
        } catch (e) {
            console.warn('[doc-viewer] falling back to native iframe:', e);
            fallback();
        }
    })();
})();
