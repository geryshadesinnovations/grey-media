/* Greyshades upload form
 * - Drag & drop file picker with progress + media preview
 * - Category selection drives the section (no separate section checkboxes)
 * - Mutual-exclusion between Gimmick and Art (data-exclusive="gimmick-art")
 * - The user picks specific subcategories only — the parent / root category
 *   ("Gimmick", "Art", "Hybrid", "Events") is no longer a selectable
 *   checkbox in the UI. The backend auto-attaches the entire ancestor
 *   chain when storing the upload so dashboard filters keep working.
 * - Live "Selected" summary chips at the top of the categories panel
 * - Live count badge on each category accordion header
 *
 * IMPORTANT: the category checkboxes live in <aside class="upload-meta">,
 * which is OUTSIDE the <form id="upload-form">. They submit correctly via
 * the `form="upload-form"` attribute, but DOM queries against the form
 * element only walk descendants, so we query the document scope instead
 * via catCheckboxes().
 */
(() => {
    'use strict';

    // This script powers BOTH the Upload form (#upload-form) and the Edit
    // Media form (#edit-form). The file-dropzone / submit wiring only exists
    // on the upload page; the category-selection logic (counts, summary
    // chips, mutual exclusion) must run on both. We therefore look up either
    // form and guard the upload-only pieces with element existence checks.
    const form     = document.getElementById('upload-form') || document.getElementById('edit-form');
    if (!form) return;
    const input    = document.getElementById('file-input');
    const drop     = document.getElementById('drop-area');
    const browse   = document.getElementById('browse-btn');
    const info     = document.getElementById('file-info');
    const progress = document.getElementById('progress-wrap');
    const bar      = document.getElementById('progress-bar');
    const result   = document.getElementById('upload-result');
    // Title input lives in the meta aside, not in the form. Find via owner doc.
    const titleInput = document.querySelector('input[name="title"][form="upload-form"]')
                    ?? form.querySelector('input[name="title"]');
    const submitBtn  = document.getElementById('upload-submit-btn');

    const previewWrap  = document.getElementById('upload-preview');
    const previewVideo = document.getElementById('preview-video');
    const previewImage = document.getElementById('preview-image');
    const previewPdf   = document.getElementById('preview-pdf');
    const previewPpt   = document.getElementById('preview-ppt');

    /* ---------- Thumbnail dropzone wiring ---------- */
    const thumbArea     = document.getElementById('thumbnail-area');
    const thumbInput    = document.getElementById('thumbnail-input');
    const thumbBrowse   = document.getElementById('thumb-browse-btn');
    const thumbInfo     = document.getElementById('thumb-info');
    const thumbPreview  = document.getElementById('thumb-preview');

    /** Types that REQUIRE a custom thumbnail upload (video / ppt / pdf). */
    const TYPES_NEEDING_THUMB = new Set(['video', 'ppt', 'pdf']);

    /** Determine high-level type from a File object. */
    const fileType = (file) => {
        const t = file.type || '';
        const ext = file.name.split('.').pop().toLowerCase();
        if (t.startsWith('video/') || ext === 'mp4') return 'video';
        if (t.startsWith('image/') || ['png','jpg','jpeg','webp','gif'].includes(ext)) return 'image';
        if (t === 'application/pdf' || ext === 'pdf') return 'pdf';
        if (['ppt','pptx'].includes(ext) || t.includes('powerpoint') || t.includes('presentation')) return 'ppt';
        return 'other';
    };

    /** Show/hide the thumbnail dropzone based on the main file type. */
    const updateThumbnailVisibility = (kind) => {
        if (!thumbArea) return;
        if (TYPES_NEEDING_THUMB.has(kind)) {
            thumbArea.hidden = false;
        } else {
            thumbArea.hidden = true;
            // Clear any previously selected thumbnail when switching to image
            if (thumbInput) thumbInput.value = '';
            if (thumbInfo)  { thumbInfo.hidden = true; thumbInfo.innerHTML = ''; }
            if (thumbPreview) { thumbPreview.hidden = true; thumbPreview.removeAttribute('src'); }
        }
    };

    const showThumbInfo = (file) => {
        if (!thumbInfo) return;
        thumbInfo.hidden = false;
        thumbInfo.innerHTML =
            '<div><strong>' + escapeHtml(file.name) + '</strong></div>' +
            '<div class="muted small">' + escapeHtml(file.type || 'image') + ' · ' + bytes(file.size) + '</div>';
    };

    const showThumbPreview = (file) => {
        if (!thumbPreview) return;
        thumbPreview.src = URL.createObjectURL(file);
        thumbPreview.hidden = false;
    };

    const selectThumbnail = (file) => {
        if (!thumbInput) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        thumbInput.files = dt.files;
        showThumbInfo(file);
        showThumbPreview(file);
    };

    if (thumbArea) {
        thumbArea.addEventListener('click', (e) => {
            if (e.target.closest('button') || e.target.closest('a')) return;
            thumbInput?.click();
        });
        if (thumbBrowse) thumbBrowse.addEventListener('click', () => thumbInput?.click());

        ['dragenter','dragover'].forEach(ev =>
            thumbArea.addEventListener(ev, (e) => { e.preventDefault(); thumbArea.classList.add('dragover'); }));
        ['dragleave','drop'].forEach(ev =>
            thumbArea.addEventListener(ev, (e) => { e.preventDefault(); thumbArea.classList.remove('dragover'); }));

        thumbArea.addEventListener('drop', (e) => {
            const f = e.dataTransfer.files?.[0];
            if (f && /^image\//.test(f.type || '')) selectThumbnail(f);
        });
        thumbInput?.addEventListener('change', () => {
            if (thumbInput.files?.length) selectThumbnail(thumbInput.files[0]);
        });
    }

    const summaryWrap  = document.getElementById('cat-summary');
    const summaryChips = summaryWrap?.querySelector('.cat-summary-chips');

    /** All category checkboxes wherever they live in the DOM. */
    const catCheckboxes = () =>
        document.querySelectorAll('input[type="checkbox"][name="categories[]"]');

    const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const bytes = (n) => {
        const u = ['B','KB','MB','GB','TB']; let i = 0;
        while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
        return n.toFixed(2) + ' ' + u[i];
    };

    /* ---------- File picker ---------- */
    const showInfo = (file) => {
        info.hidden = false;
        info.innerHTML =
            '<div><strong>' + escapeHtml(file.name) + '</strong></div>' +
            '<div class="muted small">' + escapeHtml(file.type || 'unknown') + ' · ' + bytes(file.size) + '</div>';
        if (titleInput && !titleInput.value.trim()) {
            titleInput.value = file.name.replace(/\.[^.]+$/, '');
        }
    };

    const showPreview = (file) => {
        [previewVideo, previewImage, previewPdf, previewPpt].forEach(el => { if (el) el.style.display = 'none'; });
        if (!previewWrap) return;
        previewWrap.classList.remove('visible');
        const type = file.type || '';
        const ext = file.name.split('.').pop().toLowerCase();
        if (type.startsWith('video/') || ext === 'mp4') {
            previewVideo.src = URL.createObjectURL(file);
            previewVideo.style.display = 'block';
            previewWrap.classList.add('visible');
        } else if (type.startsWith('image/') || ['png','jpg','jpeg','webp','gif'].includes(ext)) {
            previewImage.src = URL.createObjectURL(file);
            previewImage.style.display = 'block';
            previewWrap.classList.add('visible');
        } else if (type === 'application/pdf' || ext === 'pdf') {
            previewPdf.style.display = 'block';
            previewWrap.classList.add('visible');
        } else if (['ppt','pptx'].includes(ext) || type.includes('powerpoint') || type.includes('presentation')) {
            previewPpt.style.display = 'block';
            previewWrap.classList.add('visible');
        }
    };

    const selectFile = (file) => {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        showInfo(file);
        showPreview(file);
        // Reveal/hide the thumbnail dropzone based on file type
        updateThumbnailVisibility(fileType(file));
    };

    // The dropzone, file input, browse button and live preview only exist on
    // the Upload page. The Edit page reuses this script purely for category
    // selection, so skip all of this when those elements aren't present.
    if (drop && input) {
    drop.addEventListener('click', (e) => {
        if (e.target.closest('button') || e.target.closest('a')) return;
        input.click();
    });
    if (browse) browse.addEventListener('click', () => input.click());

    ['dragenter','dragover'].forEach(ev =>
        drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev =>
        drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('dragover'); }));

    drop.addEventListener('drop', (e) => {
        const f = e.dataTransfer.files?.[0];
        if (f) selectFile(f);
    });
    input.addEventListener('change', () => {
        if (input.files?.length) selectFile(input.files[0]);
    });
    } // end file-picker wiring (upload page only)

    /* ---------- Validation feedback ---------- */
    // Surfaces a friendly error as a toast (visible no matter how far the user
    // has scrolled), scrolls to + highlights the offending field, and also
    // mirrors the message inline near the dropzone when that element exists.
    const failValidation = (target, msg, opts = {}) => {
        if (window.gsToast) window.gsToast(msg, 'error');
        if (result) { result.className = 'upload-result error'; result.textContent = msg; result.hidden = false; }
        if (target) {
            target.classList.add('field-invalid');
            setTimeout(() => target.classList.remove('field-invalid'), 2600);
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (opts.focus && typeof target.focus === 'function') {
                try { target.focus({ preventScroll: true }); } catch (_) { target.focus(); }
            }
        }
    };
    // Back-compat alias used elsewhere.
    const showError = (msg) => failValidation(null, msg);

    /* ---------- Submit (upload page only) ---------- */
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (!input.files?.length) {
                failValidation(drop, 'Please choose a file to upload first.');
                return;
            }
            const checked = [...catCheckboxes()].filter(cb => cb.checked).length;
            if (checked === 0) {
                failValidation(document.querySelector('.cat-grid'), 'Please pick at least one category for this file.');
                return;
            }
            if (titleInput && !titleInput.value.trim()) {
                failValidation(titleInput, 'Please enter a title for this file.', { focus: true });
                return;
            }
            const kind = fileType(input.files[0]);
            if (TYPES_NEEDING_THUMB.has(kind) && (!thumbInput || !thumbInput.files?.length)) {
                failValidation(thumbArea, 'A thumbnail image is required for this ' + kind.toUpperCase() + ' file. Please add one.');
                return;
            }
            submit();
        });
    }

    const submit = () => {
        const fd  = new FormData(form);
        const xhr = new XMLHttpRequest();
        progress.hidden = false; bar.style.width = '0%';
        result.hidden = true; result.classList.remove('success','error','duplicate');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Uploading...'; }

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) bar.style.width = ((e.loaded / e.total) * 100).toFixed(1) + '%';
        });
        xhr.addEventListener('load', () => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Upload file';
            }
            try {
                const data = JSON.parse(xhr.responseText);
                if (xhr.status >= 200 && xhr.status < 300 && data.ok) {
                    if (data.duplicate) {
                        result.className = 'upload-result duplicate';
                        result.innerHTML = (data.message || 'Duplicate file detected.') + ' <a href="' + data.media.url + '">View existing</a>';
                    } else {
                        result.className = 'upload-result success';
                        result.innerHTML = 'Upload successful! <a href="' + data.media.url + '">View media</a>';
                        input.value = ''; info.hidden = true;
                        if (previewWrap) previewWrap.classList.remove('visible');
                        [previewVideo, previewImage, previewPdf, previewPpt].forEach(el => { if (el) el.style.display = 'none'; });
                        // Reset thumbnail dropzone
                        if (thumbInput) thumbInput.value = '';
                        if (thumbInfo)  { thumbInfo.hidden = true; thumbInfo.innerHTML = ''; }
                        if (thumbPreview) { thumbPreview.hidden = true; thumbPreview.removeAttribute('src'); }
                        if (thumbArea) thumbArea.hidden = true;
                    }
                } else {
                    result.className = 'upload-result error';
                    result.textContent = (data && data.error) || 'Upload failed.';
                }
            } catch (err) {
                result.className = 'upload-result error';
                result.textContent = 'Server error during upload.';
            }
            result.hidden = false;
            setTimeout(() => { progress.hidden = true; bar.style.width = '0%'; }, 800);
        });
        xhr.addEventListener('error', () => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Upload file';
            }
            result.className = 'upload-result error';
            result.textContent = 'Network error.';
            result.hidden = false;
            progress.hidden = true;
        });
        xhr.open('POST', form.action);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.send(fd);
    };

    /* ---------- Category selection logic ---------- */

    /**
     * Enforce mutual exclusion between checkboxes that share a data-exclusive
     * group BUT belong to a different data-cat-root (e.g. Gimmick vs Art).
     * Inside the same root the checkboxes are always allowed together.
     */
    const applyExclusion = (changed) => {
        const group = changed.dataset.exclusive;
        if (!group || !changed.checked) return;
        const myRoot = changed.dataset.catRoot;

        catCheckboxes().forEach(cb => {
            if (cb === changed) return;
            if (cb.dataset.exclusive !== group) return;
            if (cb.dataset.catRoot === myRoot) return; // same root is fine
            cb.checked = false;
        });
    };

    /** Display name for a root card by its slug (for exclusion messages). */
    const cardName = (rootSlug) => {
        const el = document.querySelector('.cat-card[data-cat-root="' + CSS.escape(rootSlug) + '"] .cat-card-name');
        return el?.textContent?.trim() || rootSlug;
    };

    /**
     * When a card is "disabled" by the exclusion rule, dim it visually AND show
     * an explicit message telling the user exactly what to unselect, e.g.
     * "Please unselect Art to enable Gimmick."
     */
    const refreshCardStates = () => {
        const groupActiveRoots = new Map();
        catCheckboxes().forEach(cb => {
            if (!cb.checked || !cb.dataset.exclusive) return;
            const set = groupActiveRoots.get(cb.dataset.exclusive) || new Set();
            set.add(cb.dataset.catRoot);
            groupActiveRoots.set(cb.dataset.exclusive, set);
        });

        document.querySelectorAll('.cat-card[data-exclusive]').forEach(card => {
            const group = card.dataset.exclusive;
            const root  = card.dataset.catRoot;
            const active = groupActiveRoots.get(group);
            const dim = active && active.size > 0 && !active.has(root);
            card.classList.toggle('dimmed', !!dim);

            const msgEl = card.querySelector('.cat-card-msg');
            if (msgEl) {
                if (dim) {
                    const activeRoot = [...active][0];
                    const thisName = card.querySelector('.cat-card-name')?.textContent?.trim() || 'this category';
                    msgEl.textContent = 'Please unselect ' + cardName(activeRoot) + ' to enable ' + thisName + '.';
                    msgEl.hidden = false;
                } else {
                    msgEl.hidden = true;
                    msgEl.textContent = '';
                }
            }
        });
    };

    /** Update count badge on each card header + the summary chips at top. */
    const refreshCounts = () => {
        document.querySelectorAll('.cat-card').forEach(card => {
            const ticked = card.querySelectorAll('input[type="checkbox"][name="categories[]"]:checked').length;
            const badge = card.querySelector('.cat-card-count');
            if (badge) {
                badge.textContent = String(ticked);
                badge.hidden = ticked === 0;
            }
            card.classList.toggle('has-selection', ticked > 0);
        });

        if (summaryWrap && summaryChips) {
            const ticked = [...catCheckboxes()].filter(cb => cb.checked);
            if (ticked.length === 0) {
                summaryWrap.hidden = true;
                summaryChips.innerHTML = '';
            } else {
                summaryWrap.hidden = false;
                summaryChips.innerHTML = ticked.map(cb => {
                    const label = cb.closest('label')?.querySelector('.cat-pick-label')?.textContent?.trim() || '';
                    // Prefix the chip with the implicit parent root name so
                    // the user can see at a glance that picking "Pop-up"
                    // means the file lives under "Gimmick".
                    const card = cb.closest('.cat-card');
                    const rootName = card?.querySelector('.cat-card-name')?.textContent?.trim() || '';
                    const display = rootName && rootName !== label ? rootName + ' › ' + label : label;
                    return '<button type="button" class="cat-summary-chip" data-uncheck="' + escapeHtml(cb.value) + '">' +
                           escapeHtml(display) +
                           '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>' +
                           '</button>';
                }).join('');
            }
        }
    };

    /** Wire up summary chips so clicking a chip removes that selection. */
    summaryChips?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-uncheck]');
        if (!chip) return;
        const val = chip.getAttribute('data-uncheck');
        const cb = document.querySelector('input[type="checkbox"][name="categories[]"][value="' + CSS.escape(val) + '"]');
        if (cb) {
            cb.checked = false;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    /** Master change handler for category checkboxes. */
    catCheckboxes().forEach(cb => {
        cb.addEventListener('change', () => {
            applyExclusion(cb);
            refreshCardStates();
            refreshCounts();
        });
    });

    // Initial paint
    refreshCardStates();
    refreshCounts();

    /* ---------- Edit page: modern file pickers + replace validation ---------- */
    // Reflect chosen filenames in the styled .file-field component.
    document.querySelectorAll('.file-field-input').forEach(inp => {
        inp.addEventListener('change', () => {
            const field  = inp.closest('.file-field');
            const nameEl = field?.querySelector('[data-file-name]');
            const has    = !!inp.files?.length;
            if (nameEl) {
                nameEl.textContent = has
                    ? inp.files[0].name
                    : (inp.id === 'replace-thumb' ? 'JPG · PNG · WEBP' : 'No file selected');
            }
            field?.classList.toggle('has-file', has);
            field?.classList.remove('field-invalid');
        });
    });

    const editForm = document.getElementById('edit-form');
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            const fileInput  = document.getElementById('replace-file');
            const thumbInput2 = document.getElementById('replace-thumb');
            const section    = document.getElementById('replace-section');
            if (!fileInput || !section) return;

            const replacing  = !!fileInput.files?.length;
            const needsThumb = section.dataset.needsThumb === '1';
            const type       = (section.dataset.mediaType || 'file').toUpperCase();

            // Thumbnail is mandatory ONLY when actually replacing a video/ppt/pdf.
            if (replacing && needsThumb && (!thumbInput2 || !thumbInput2.files?.length)) {
                e.preventDefault();
                // Undo the global progress bar / disabled button that the generic
                // submit handler kicked off, since we're blocking this submit.
                window.GSLoading?.finish?.();
                setTimeout(() => {
                    const btn = editForm.querySelector('button[type="submit"]');
                    if (btn) { btn.disabled = false; btn.classList.remove('is-loading'); }
                }, 10);

                const target = thumbInput2?.closest('.file-field') || section;
                if (window.gsToast) {
                    window.gsToast('A thumbnail image is required when replacing a ' + type + ' file.', 'error');
                }
                if (target) {
                    target.classList.add('field-invalid');
                    setTimeout(() => target.classList.remove('field-invalid'), 2600);
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
})();
