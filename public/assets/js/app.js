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

    // Generic accordion cards [data-accordion]
    document.querySelectorAll('[data-accordion]').forEach(btn => {
        btn.addEventListener('click', () => {
            const body = btn.nextElementSibling;
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
})();
