/* Greyshades - global UI behaviour: theme toggle, popovers, sidebar tree, mobile nav */
(() => {
    'use strict';

    // Theme toggle
    const root = document.documentElement;
    const themeBtn = document.getElementById('theme-toggle');
    const setTheme = (t) => {
        root.setAttribute('data-theme', t);
        document.cookie = 'theme=' + t + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
    };
    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            setTheme(next);
        });
    }

    // Generic popovers (data-popover="<id>")
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-popover]');
        if (trigger) {
            const id = trigger.getAttribute('data-popover');
            const pop = document.getElementById(id);
            if (pop) {
                document.querySelectorAll('.popover').forEach(p => { if (p !== pop) p.hidden = true; });
                pop.hidden = !pop.hidden;
                e.stopPropagation();
                return;
            }
        }
        if (!e.target.closest('.popover')) {
            document.querySelectorAll('.popover').forEach(p => p.hidden = true);
        }
    });

    // Sidebar tree expand/collapse
    document.querySelectorAll('[data-toggle="tree"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const li = btn.closest('.tree-item');
            const kids = li.querySelector('.tree-children');
            if (!kids) return;
            const open = !kids.hasAttribute('hidden');
            if (open) kids.setAttribute('hidden', ''); else kids.removeAttribute('hidden');
            btn.classList.toggle('open', !open);
        });
    });
    // Auto-open paths that contain the active link
    document.querySelectorAll('.tree-link.active').forEach(link => {
        let p = link.closest('.tree-item');
        while (p) {
            const kids = p.querySelector(':scope > .tree-children');
            if (kids) kids.removeAttribute('hidden');
            const t = p.querySelector(':scope > .tree-row > .tree-toggle');
            if (t) t.classList.add('open');
            p = p.parentElement?.closest('.tree-item');
        }
    });

    // Mobile navigation drawers (admin nav + category sidebar)
    (() => {
        const drawers = document.querySelectorAll('.drawer');
        if (!drawers.length) return;

        // Inject the overlay as a sibling of the drawer (same parent) so they
        // share one stacking context. This guarantees the drawer (z-index
        // 1001) always paints above the overlay (z-index 1000), regardless of
        // any stacking context created by ancestor elements.
        const firstDrawer = drawers[0];
        let overlay = document.querySelector('.drawer-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'drawer-overlay';
            firstDrawer.parentNode.insertBefore(overlay, firstDrawer);
        }

        const closeAll = () => {
            document.querySelectorAll('.drawer.open').forEach(d => d.classList.remove('open'));
            overlay.classList.remove('show');
            document.body.classList.remove('drawer-open');
        };
        const open = (drawer) => {
            if (!drawer) return;
            closeAll();
            drawer.classList.add('open');
            overlay.classList.add('show');
            document.body.classList.add('drawer-open');
        };

        document.querySelectorAll('[data-drawer-open]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                open(document.getElementById(btn.getAttribute('data-drawer-open')));
            });
        });
        document.querySelectorAll('[data-drawer-close]').forEach(btn => {
            btn.addEventListener('click', closeAll);
        });

        // Tap/click on the backdrop closes the menu.
        overlay.addEventListener('click', closeAll);
        // Escape closes the menu.
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });
        // Selecting a navigation link inside a drawer closes it (the toggle
        // buttons that only expand the category tree are <button>s, so they
        // are intentionally excluded).
        drawers.forEach(drawer => {
            drawer.addEventListener('click', (e) => {
                if (e.target.closest('a[href]')) closeAll();
            });
        });
        // Reset state if the viewport grows back to desktop.
        window.addEventListener('resize', () => {
            if (window.innerWidth > 960) closeAll();
        });
    })();

    // Auto-dismiss toasts
    document.querySelectorAll('.toast').forEach(t => setTimeout(() => t.remove(), 4000));

    // Reusable toast helper for client-side messages (validation, AJAX, etc.).
    // Shows a single transient toast styled like the server-rendered flash ones.
    const gsToast = (msg, type) => {
        document.querySelectorAll('.toast.js-toast').forEach(t => t.remove());
        const t = document.createElement('div');
        t.className = 'toast js-toast toast-' + (type === 'success' ? 'success' : 'error');
        t.setAttribute('role', 'alert');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 5000);
        return t;
    };
    window.gsToast = gsToast;

    // Generic accordion cards [data-accordion]
    document.querySelectorAll('[data-accordion]').forEach(btn => {
        btn.addEventListener('click', () => {
            // The panel is the next sibling, but skip any non-panel nodes that
            // may sit between the header and its body (e.g. the mutual-exclusion
            // message on Gimmick/Art cards) so the correct body still toggles.
            let body = btn.nextElementSibling;
            while (body && body.classList.contains('cat-card-msg')) {
                body = body.nextElementSibling;
            }
            if (!body) return;
            const isOpen = body.classList.contains('open');
            body.classList.toggle('open', !isOpen);
            btn.classList.toggle('open', !isOpen);
        });
    });

    // Unregister any previously installed service worker (offline feature removed).
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(regs => {
            regs.forEach(r => r.unregister());
        }).catch(() => {});
    }

    // Video card hover-to-play preview
    (() => {
        const isMobile = matchMedia('(hover: none), (max-width: 720px)').matches
            || ('ontouchstart' in window && navigator.maxTouchPoints > 0);

        let active = null; // { card, video }
        let hoverDelay = null;

        const stopPreview = () => {
            if (hoverDelay) { clearTimeout(hoverDelay); hoverDelay = null; }
            if (active) {
                try { active.video.pause(); } catch (_) {}
                active.video.remove();
                active = null;
            }
        };

        const startPreview = (card) => {
            if (active && active.card === card) return;
            stopPreview();
            const src = card.dataset.previewSrc;
            if (!src) return;
            const thumb = card.querySelector('.media-thumb');
            if (!thumb) return;

            const video = document.createElement('video');
            video.src = src;
            video.muted = true;
            video.loop  = true;
            video.playsInline = true;
            video.autoplay = true;
            video.preload  = 'auto';
            video.setAttribute('controlslist', 'nodownload noremoteplayback');
            video.style.cssText =
                'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;' +
                'border-radius:inherit;z-index:2;pointer-events:none;';
            thumb.style.position = 'relative';
            thumb.appendChild(video);
            // Some browsers reject autoplay until the metadata loads
            video.addEventListener('loadedmetadata', () => {
                video.play().catch(() => {});
            }, { once: true });
            video.play().catch(() => {});

            active = { card, video };
        };

        // ---- Desktop: hover-to-play, attached per-card so events are reliable
        if (!isMobile) {
            // Wire up listeners for all current and future cards
            const wireCard = (card) => {
                if (card.dataset.previewWired) return;
                card.dataset.previewWired = '1';
                card.addEventListener('mouseenter', () => {
                    // Tiny delay so a quick scan of the grid doesn't fire 20 videos
                    hoverDelay = setTimeout(() => startPreview(card), 120);
                });
                card.addEventListener('mouseleave', () => {
                    if (hoverDelay) { clearTimeout(hoverDelay); hoverDelay = null; }
                    if (active && active.card === card) stopPreview();
                });
            };
            document.querySelectorAll('.media-card[data-preview-src]').forEach(wireCard);
            // Watch for newly-injected cards (e.g. infinite scroll, search)
            new MutationObserver(records => {
                for (const r of records) {
                    r.addedNodes.forEach(n => {
                        if (n.nodeType !== 1) return;
                        if (n.matches?.('.media-card[data-preview-src]')) wireCard(n);
                        n.querySelectorAll?.('.media-card[data-preview-src]').forEach(wireCard);
                    });
                }
            }).observe(document.body, { childList: true, subtree: true });
        } else {
            // ---- Mobile: tap a video card to start preview, tap elsewhere to stop
            document.addEventListener('touchstart', (e) => {
                if (e.target.closest('[data-fav-toggle]')) return; // let the heart handle its own tap
                const card = e.target.closest('.media-card[data-preview-src]');
                if (card) {
                    if (!active || active.card !== card) startPreview(card);
                } else {
                    stopPreview();
                }
            }, { passive: true });
        }
    })();

    // ---- Favorites ("like") toggle -------------------------------------------
    // A heart button appears on each media card (overlaying the thumbnail) and
    // on the media detail page. Clicking it POSTs to /favorites/toggle/{uuid}
    // and flips the button state without a page reload. On cards the button
    // lives inside the card's <a>, so we intercept in the capture phase and
    // stop the click from navigating.
    (() => {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
        const onFavPage = !!document.querySelector('[data-favorites-page]');

        const setState = (btn, favorited) => {
            btn.classList.toggle('is-fav', favorited);
            btn.setAttribute('aria-pressed', favorited ? 'true' : 'false');
            const label = btn.querySelector('.fav-label');
            if (label) label.textContent = favorited ? 'Favorited' : 'Favorite';
            const title = favorited ? 'Remove from favorites' : 'Add to favorites';
            if (!label) { btn.setAttribute('aria-label', title); btn.setAttribute('title', title); }
        };

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-fav-toggle]');
            if (!btn) return;

            // Never let the click bubble to the card link / form.
            e.preventDefault();
            e.stopPropagation();

            if (btn.dataset.busy === '1') return;
            const action = btn.dataset.favAction;
            if (!action) return;

            btn.dataset.busy = '1';
            fetch(action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(data => {
                    if (!data || data.ok !== true) return;
                    setState(btn, data.favorited);
                    // On the Favorites page, removing a favorite drops the card.
                    if (onFavPage && data.favorited === false) {
                        const card = btn.closest('.media-card');
                        if (card) {
                            card.style.transition = 'opacity .2s ease';
                            card.style.opacity = '0';
                            setTimeout(() => card.remove(), 200);
                        }
                    }
                })
                .catch(() => {
                    // Fallback: if the AJAX call fails, submit the surrounding
                    // form (full page reload) so the action still completes.
                    const form = btn.closest('form.fav-form');
                    if (form) form.submit();
                })
                .finally(() => { btn.dataset.busy = '0'; });
        }, true); // capture phase: beat the <a> navigation / form submit
    })();

    // ---- Follow / unfollow a category ----------------------------------------
    (() => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const csrf = meta ? meta.getAttribute('content') : '';
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-follow-toggle]');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            if (btn.dataset.busy === '1') return;
            const action = btn.dataset.followAction;
            if (!action) return;
            btn.dataset.busy = '1';
            fetch(action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(d => {
                    if (!d || d.ok !== true) return;
                    btn.classList.toggle('is-following', d.following);
                    btn.setAttribute('aria-pressed', d.following ? 'true' : 'false');
                    const lbl = btn.querySelector('.follow-label');
                    if (lbl) lbl.textContent = d.following ? 'Following' : 'Follow';
                    // On the Favorites > Following list, unfollowing drops the card.
                    if (!d.following && btn.dataset.removable !== undefined) {
                        const card = btn.closest('.follow-card');
                        if (card) {
                            card.style.transition = 'opacity .2s ease';
                            card.style.opacity = '0';
                            setTimeout(() => card.remove(), 200);
                        }
                    }
                })
                .catch(() => {})
                .finally(() => { btn.dataset.busy = '0'; });
        }, true);
    })();

    // ---- Share link generation (media detail) --------------------------------
    (() => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const csrf = meta ? meta.getAttribute('content') : '';

        document.querySelectorAll('[data-share-form]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const box = form.closest('.share-box');
                const result = box && box.querySelector('[data-share-result]');
                const linkInput = box && box.querySelector('[data-share-link]');
                const expiry = box && box.querySelector('[data-share-expiry]');
                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                })
                    .then(r => r.ok ? r.json() : Promise.reject(r))
                    .then(d => {
                        if (!d || d.ok !== true) throw new Error('share failed');
                        if (linkInput) linkInput.value = d.url;
                        if (expiry) expiry.textContent = d.expires_human ? 'Expires ' + d.expires_human : '';
                        if (result) result.hidden = false;
                        if (linkInput) { linkInput.focus(); linkInput.select(); }
                    })
                    .catch(() => { form.submit(); })
                    .finally(() => { if (btn) btn.disabled = false; });
            });
        });

        document.addEventListener('click', (e) => {
            const copyBtn = e.target.closest('[data-share-copy]');
            if (!copyBtn) return;
            const box = copyBtn.closest('.share-box');
            const input = box && box.querySelector('[data-share-link]');
            if (!input || !input.value) return;
            const done = () => {
                const t = copyBtn.textContent;
                copyBtn.textContent = 'Copied!';
                setTimeout(() => { copyBtn.textContent = t; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(done).catch(() => { input.select(); try { document.execCommand('copy'); } catch (_) {} done(); });
            } else {
                input.select();
                try { document.execCommand('copy'); } catch (_) {}
                done();
            }
        });
    })();

    // ---- Notification bell ---------------------------------------------------
    (() => {
        const bell = document.getElementById('notif-bell');
        const list = document.getElementById('notif-pop-list');
        const badge = document.getElementById('notif-badge');
        if (!bell || !list) return;

        const meta = document.querySelector('meta[name="csrf-token"]');
        const csrf = meta ? meta.getAttribute('content') : '';
        const feedUrl = list.dataset.feedUrl;
        const readAllUrl = list.dataset.readAllUrl;
        const baseUrl = readAllUrl ? readAllUrl.replace(/\/read-all$/, '') : '';

        const setBadge = (n) => {
            if (!badge) return;
            if (n > 0) { badge.textContent = n > 99 ? '99+' : String(n); badge.hidden = false; }
            else { badge.hidden = true; }
        };

        const render = (data) => {
            setBadge(data.count || 0);
            list.innerHTML = '';
            if (!data.items || !data.items.length) {
                const p = document.createElement('p');
                p.className = 'notif-empty muted';
                p.textContent = 'No notifications yet.';
                list.appendChild(p);
                return;
            }
            data.items.forEach((it) => {
                const a = document.createElement('a');
                a.className = 'notif-pop-item' + (it.is_read ? '' : ' is-unread');
                a.href = it.url || '#';
                a.dataset.id = it.id;
                const t = document.createElement('span'); t.className = 'npi-title'; t.textContent = it.title;
                a.appendChild(t);
                if (it.body) { const b = document.createElement('span'); b.className = 'npi-body muted'; b.textContent = it.body; a.appendChild(b); }
                const ago = document.createElement('span'); ago.className = 'npi-ago muted'; ago.textContent = it.ago || '';
                a.appendChild(ago);
                list.appendChild(a);
            });
        };

        const loadFeed = () => {
            fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(render)
                .catch(() => {});
        };

        loadFeed();
        setInterval(loadFeed, 60000);
        bell.addEventListener('click', () => loadFeed());

        const markAll = document.getElementById('notif-mark-all');
        if (markAll) {
            markAll.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fetch(readAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(() => loadFeed())
                    .catch(() => {});
            });
        }

        list.addEventListener('click', (e) => {
            const item = e.target.closest('.notif-pop-item');
            if (!item) return;
            const id = item.dataset.id;
            const href = item.getAttribute('href');
            if (id && baseUrl) {
                fetch(baseUrl + '/' + id + '/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' }).catch(() => {});
            }
            if (!href || href === '#') e.preventDefault();
        });
    })();

    // ---- Global loading states ----------------------------------------------
    // A thin top progress bar for page transitions + downloads, and an
    // automatic "loading" state on form submit buttons to prevent double
    // submission. AJAX flows (favorites/follow/share/notifications) already
    // set data-busy on their buttons, which the CSS styles as a spinner.
    (() => {
        const bar = document.getElementById('global-progress');
        let active = false;
        let resetTimer = null;

        const start = () => {
            if (!bar || active) return;
            active = true;
            bar.classList.remove('done');
            // force reflow so the width transition restarts
            void bar.offsetWidth;
            bar.classList.add('active');
        };
        const finish = () => {
            if (!bar) return;
            active = false;
            bar.classList.add('done');
            bar.classList.remove('active');
            clearTimeout(resetTimer);
            resetTimer = setTimeout(() => bar.classList.remove('done'), 450);
        };
        // Expose so other modules can show progress for their own async work.
        window.GSLoading = { start, finish };

        // A new page load (including back/forward cache restore) clears the bar.
        window.addEventListener('pageshow', finish);
        window.addEventListener('beforeunload', start);

        const isInternalNav = (a) => {
            if (a.target && a.target !== '_self') return false;
            if (a.hasAttribute('download') || a.dataset.noProgress !== undefined) return false;
            const href = a.getAttribute('href') || '';
            if (!href || href[0] === '#' || /^(javascript|mailto|tel):/i.test(href)) return false;
            try {
                const u = new URL(a.href, location.href);
                if (u.origin !== location.origin) return false;
                if (u.pathname === location.pathname && u.hash) return false;
            } catch (_) { return false; }
            return true;
        };

        document.addEventListener('click', (e) => {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            const a = e.target.closest('a[href]');
            if (!a) return;
            const href = a.getAttribute('href') || '';
            // Downloads don't navigate, so show progress briefly then clear.
            if (a.hasAttribute('download') || /\/download\//.test(href)) {
                start();
                setTimeout(finish, 4000);
                return;
            }
            if (isInternalNav(a)) start();
        });

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;
            // Skip AJAX forms (they handle their own busy state without navigating).
            if (form.matches('[data-ajax], [data-share-form]') || form.id === 'upload-form') return;
            start();
            const btn = form.querySelector('button[type="submit"], button:not([type])');
            if (btn && !btn.disabled) {
                btn.classList.add('is-loading');
                // Disable AFTER the synchronous submit so the button value still posts.
                setTimeout(() => { btn.disabled = true; }, 0);
            }
        }, true);
    })();
})();
