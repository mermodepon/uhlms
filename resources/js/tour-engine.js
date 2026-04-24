/**
 * Virtual Tour Engine — Guest-facing 360° viewer.
 * Uses PanoramaViewer (Photo Sphere Viewer) for rendering, with HTML markers and gyroscope.
 */
import { PanoramaViewer } from './panorama-viewer.js';
import * as THREE from 'three';

const HOTSPOT_COLORS = {
    navigate:        '#3b82f6',
    info:            '#f59e0b',
    bookmark:        '#8b5cf6',
    'external-link': '#10b981',
};

const AUTO_TOUR_PROFILES = {
    fast: { cycleMs: 6000, panMs: 4200, label: 'Fast' },
    normal: { cycleMs: 8000, panMs: 6000, label: 'Normal' },
    slow: { cycleMs: 10000, panMs: 7600, label: 'Slow' },
};

class VirtualTourEngine {
    constructor(containerId, options = {}) {
        this.container         = document.getElementById(containerId);
        this.viewer            = null;

        this.waypoints           = [];
        this.currentWaypoint     = null;
        this.startWaypoint       = options.startWaypoint || '';
        this.apiBase             = options.apiBase || '/api/tour';
        this.currentRoomType     = null;
        this.currentRoom         = null;
        this.bookmarks           = this._loadBookmarks();
        this._roomInfoCardOpen   = false;
        this._infoCardHotspotId  = null;
        this._audioEl            = null;
        this._audioHotspotId     = null;
        this._autoTourActive     = false;
        this._autoTourTimer      = null;
        this._autoTourTickTimer  = null;
        this._autoTourPanRaf     = null;
        this._webXRTest          = null;
        let savedAutoTourProfile = null;
        try {
            savedAutoTourProfile = localStorage.getItem('tour_auto_tour_profile');
        } catch (_) {
            savedAutoTourProfile = null;
        }
        this._autoTourProfile    = this._normalizeAutoTourProfile(savedAutoTourProfile);
        this._autoTourCycleMs    = AUTO_TOUR_PROFILES[this._autoTourProfile].cycleMs;
        this._autoTourPanMs      = AUTO_TOUR_PROFILES[this._autoTourProfile].panMs;
        this._autoTourStepStart  = 0;
        this._reducedMotion      = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
        this.previewMode         = options.previewMode || window.location.search.includes('preview');

        // Auto-hide UI state
        this._uiIdleTimer        = null;
        this._uiIdleDelayMs      = 4000;  // 4 seconds
        this._uiHidden           = false;
        this._uiManuallyHidden   = false;  // Manual override via toggle button

        // Date-aware availability state
        this._checkIn  = null;   // 'YYYY-MM-DD'
        this._checkOut = null;   // 'YYYY-MM-DD'
        this._guests   = 1;

        this.onRoomDoorReached   = options.onRoomDoorReached   || (() => {});
        this.onReservationOpened = options.onReservationOpened || (() => {});

        // UI refs
        this.overlay           = document.getElementById('room-info-overlay');
        this.reservationModal  = document.getElementById('reservation-modal');
        this.minimap           = document.getElementById('minimap');
        this.loadingIndicator  = document.getElementById('loading-indicator');
        this.narrationTooltip  = document.getElementById('narration-tooltip');
        this.progressIndicator = document.getElementById('progress-indicator');
        this.roomInfoBtn       = document.getElementById('room-info-btn');
        this.autoTourHud       = document.getElementById('auto-tour-hud');
        this.autoTourCountdown = document.getElementById('auto-tour-countdown');
        this.autoTourFill      = document.getElementById('auto-tour-progress-fill');
        this.autoTourSpeedButtons = Array.from(document.querySelectorAll('.auto-tour-speed-btn'));

        this._init();
    }

    // ── Viewer setup ──────────────────────────────────────────────────────────

    _init() {
        if (!this.container) return;

        this.viewer = new PanoramaViewer({
            container:    this.container,
            defaultYaw:   0,
            defaultPitch: 0,
        });

        this._bindAutoTourSettings();

        // Hotspot click → handle action
        this.viewer.addEventListener('select-marker', (e) => {
            if (this._autoTourActive) {
                this.stopAutoTour();
                this._showToast('Auto Tour paused for manual interaction.', 'info');
            }
            const data = e.marker.config.data;
            if (data?.isRoomInfo) {
                if (this._roomInfoCardOpen) {
                    this._closeInSceneCard();
                } else {
                    this._openInSceneCard();
                }
                return;
            }
            if (e.marker.config.id === 'info-card') return;
            // room-info-card: do NOT close on any click — the card contains interactive
            // form inputs which would bubble up to select-marker; closing is handled by
            // the X button (closeAction) inside the card.
            if (e.marker.config.id === 'room-info-card') return;
            const hs = data?.hotspot;
            if (!hs) return;
            this._handleHotspotAction(hs);
        });

        this._initAsync();
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    async _initAsync() {
        this.showLoading();
        await this.loadWaypoints();
        // Deep link: honour ?scene=slug in URL
        const urlSlug = this._getUrlScene();
        const start = urlSlug || this.startWaypoint || this.waypoints[0]?.slug;
        if (start) await this.navigateToWaypoint(start);
        this.hideLoading();
        this.setupKeyboardControls();
        this.setupBookmarks();
        // Listen for browser back/forward navigation
        window.addEventListener('popstate', () => {
            const slug = this._getUrlScene();
            if (slug && slug !== this.currentWaypoint?.slug) this.navigateToWaypoint(slug);
        });
        setTimeout(() => {
            this.minimap?.classList.remove('hidden');
            this.progressIndicator?.classList.remove('hidden');
        }, 1000);

        // Setup auto-hide UI controls
        this._setupAutoHideUI();
    }

    // ── URL deep linking ──────────────────────────────────────────────────────

    _getUrlScene() {
        return new URLSearchParams(window.location.search).get('scene') || null;
    }

    _pushUrlScene(slug) {
        if (this.previewMode) return;
        const url = new URL(window.location.href);
        url.searchParams.set('scene', slug);
        window.history.pushState({ scene: slug }, '', url.toString());
    }

    // ── Auto-hide UI ──────────────────────────────────────────────────────────

    _setupAutoHideUI() {
        // Elements to auto-hide
        this._hidableElements = [
            document.querySelector('.vr-controls'),
            document.getElementById('minimap'),
            document.getElementById('help-btn'),
            document.getElementById('room-info-btn'),
            document.getElementById('mobile-settings-btn'),
        ].filter(Boolean);

        // Show UI on any user interaction
        const interactions = ['mousemove', 'mousedown', 'touchstart', 'touchmove', 'keydown', 'wheel'];
        interactions.forEach(evt => {
            this.container.addEventListener(evt, () => this._onUserActivity(), { passive: true });
        });

        // Start the idle timer immediately
        this._resetUIIdleTimer();
    }

    _onUserActivity() {
        // Show UI if hidden (but only if not manually overridden)
        if (this._uiHidden && !this._uiManuallyHidden) {
            this._showUI();
        }
        // Reset the idle timer (unless manually hidden)
        if (!this._uiManuallyHidden) {
            this._resetUIIdleTimer();
        }
    }

    _resetUIIdleTimer() {
        clearTimeout(this._uiIdleTimer);
        // Don't hide UI when Auto Tour countdown is visible or manually overridden
        if (this._autoTourActive || this._uiManuallyHidden) return;
        this._uiIdleTimer = setTimeout(() => this._hideUI(), this._uiIdleDelayMs);
    }

    _showUI() {
        this._uiHidden = false;
        this._hidableElements.forEach(el => el?.classList.remove('ui-hidden'));
    }

    _hideUI() {
        this._uiHidden = true;
        this._hidableElements.forEach(el => el?.classList.add('ui-hidden'));
        document.querySelector('.vr-controls')?.classList.remove('mobile-open');
    }

    toggleUIVisibility() {
        if (this._uiHidden) {
            // Show UI
            this._showUI();
            this._uiManuallyHidden = false;
            this._resetUIIdleTimer();  // Resume auto-hide
            this._syncToggleUIBtn(false);
        } else {
            // Hide UI
            this._hideUI();
            this._uiManuallyHidden = true;
            clearTimeout(this._uiIdleTimer);  // Prevent auto-show
            this._syncToggleUIBtn(true);
        }
    }

    _syncToggleUIBtn(hidden) {
        const hideIcon = document.getElementById('ui-hide-icon');
        const showIcon = document.getElementById('ui-show-icon');
        const btn = document.getElementById('toggle-ui-btn');
        if (hideIcon) hideIcon.style.display = hidden ? 'none' : '';
        if (showIcon) showIcon.style.display = hidden ? '' : 'none';
        if (btn) btn.title = hidden ? 'Show controls (H)' : 'Hide controls (H)';
    }

    async loadWaypoints() {
        try {
            const res  = await fetch(`${this.apiBase}/waypoints`);
            const data = await res.json();
            if (data.success) {
                this.waypoints = data.data;
                this.renderMinimap();
                this.updateProgressIndicator();
            }
        } catch (e) { console.error('loadWaypoints:', e); }
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    async navigateToWaypoint(slug) {
        const wp = this.waypoints.find(w => w.slug === slug);
        if (!wp) return;

        // Stop any audio playing from the previous scene
        if (this._audioEl) {
            this._audioEl.pause();
            this._audioEl = null;
            this._audioHotspotId = null;
        }

        this.showLoading();
        this.currentWaypoint = wp;

        await this.viewer.setPanorama(wp.panorama_image, {
            transition: { effect: 'fade', duration: 400 },
            position: {
                yaw:   `${wp.default_yaw   || 0}deg`,
                pitch: `${wp.default_pitch || 0}deg`,
            },
            ...(wp.default_zoom != null ? { zoom: wp.default_zoom } : {}),
        });

        this._buildHotspots(wp);
        
        // Fetch room data BEFORE hiding loading screen so marker is ready on first click
        if (wp.is_room_related && (wp.linked_room_type_id || wp.linked_room_id)) {
            await this._fetchRoomInfo(wp);
            if (this.roomInfoBtn) this.roomInfoBtn.classList.add('visible');
        } else {
            if (this.roomInfoBtn) this.roomInfoBtn.classList.remove('visible');
            this.hideRoomInfoOverlay();
            this.currentRoomType = null;
            this.currentRoom = null;
        }
        
        this.hideLoading();
        this.updateProgressIndicator();
        this.highlightCurrentOnMinimap(slug);
        this._pushUrlScene(slug);
        this._resetHotspotFocus = true; // signal setupKeyboardControls to reset Tab index
        if (wp.narration) this.showNarration(wp.narration);

        // Preload adjacent panoramas in the background
        this._preloadAdjacentScenes(slug);
    }

    // ── Preloading ────────────────────────────────────────────────────────────

    _preloadAdjacentScenes(currentSlug) {
        const idx = this.waypoints.findIndex(w => w.slug === currentSlug);
        const toPreload = [];
        if (idx > 0)                         toPreload.push(this.waypoints[idx - 1]);
        if (idx < this.waypoints.length - 1) toPreload.push(this.waypoints[idx + 1]);
        // Also preload navigate-hotspot targets in this scene
        (this.currentWaypoint?.hotspots || [])
            .filter(h => h.action_type === 'navigate' && h.action_target)
            .forEach(h => {
                const wp = this.waypoints.find(w => w.slug === h.action_target);
                if (wp) toPreload.push(wp);
            });
        this._preloaded = this._preloaded || new Set();
        toPreload.forEach(wp => {
            if (wp?.panorama_image && !this._preloaded.has(wp.slug)) {
                this._preloaded.add(wp.slug);
                new window.Image().src = wp.panorama_image;
            }
        });
    }

    navigatePrevious() {
        if (this._autoTourActive) {
            this.stopAutoTour();
            this._showToast('Auto Tour paused for manual navigation.', 'info');
        }
        if (!this.currentWaypoint) return;
        const i = this.waypoints.findIndex(w => w.slug === this.currentWaypoint.slug);
        if (i > 0) this.navigateToWaypoint(this.waypoints[i - 1].slug);
    }

    navigateNext() {
        if (this._autoTourActive) {
            this.stopAutoTour();
            this._showToast('Auto Tour paused for manual navigation.', 'info');
        }
        if (!this.currentWaypoint) return;
        const i = this.waypoints.findIndex(w => w.slug === this.currentWaypoint.slug);
        if (i < this.waypoints.length - 1) this.navigateToWaypoint(this.waypoints[i + 1].slug);
    }

    // ── Hotspot markers ───────────────────────────────────────────────────────

    _buildHotspots(wp) {
        this._roomInfoCardOpen = false;
        this._infoCardHotspotId = null;
        this.viewer.clearMarkers();
        if (!wp.hotspots) return;

        wp.hotspots.filter(h => h.is_active !== false).forEach(h => {
            this.viewer.addMarker({
                id:       `hs-${h.id}`,
                position: { yaw: `${h.yaw}deg`, pitch: `${h.pitch}deg` },
                tooltip:  { content: h.title, position: 'top center' },
                data:     { hotspot: h },
                sprite: {
                    style:   'circle',
                    icon:    h.icon || 'chevron-up',
                    bgColor: HOTSPOT_COLORS[h.action_type] || '#6b7280',
                    opacity: 1,
                    size:    h.size || 3,  // Apply size from hotspot (1-5 scale)
                },
            });
        });

        if (wp.is_room_related && wp.linked_room_type_id) {
            const infoYaw   = wp.room_info_yaw   ?? wp.default_yaw   ?? 0;
            const infoPitch = wp.room_info_pitch ?? ((wp.default_pitch ?? 0) + 15);
            this.viewer.addMarker({
                id:       'room-info-marker',
                position: { yaw: `${infoYaw}deg`, pitch: `${infoPitch}deg` },
                data:     { isRoomInfo: true },
                sprite:   { style: 'badge', icon: '🏠', label: 'Room Info',
                             bgColor: 'linear-gradient(135deg,#00491E,#02681E)',
                             textColor: '#ffffff', iconColor: '#ffffff' },
            });
        }
    }

    _markerHtml(hs) {
        const bg  = HOTSPOT_COLORS[hs.action_type] || '#6b7280';
        const svg = VirtualTourEngine.iconSvg(hs.icon || 'chevron-up', 18, '#fff');
        return `<div class="tour-hotspot-marker" style="background:${bg}">${svg}</div>`;
    }

    static iconSvg(id, size = 16, color = 'currentColor') {
        const s = size, c = color;
        const icons = {
            'chevron-up':        `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 15 12 9 18 15"/></svg>`,
            'chevron-down':      `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>`,
            'chevron-left':      `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>`,
            'chevron-right':     `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`,
            'chevron-up-right':  `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 17 17 7"/><polyline points="7 7 17 7 17 17"/></svg>`,
            'chevron-down-right':`<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 7 17 17"/><polyline points="17 7 17 17 7 17"/></svg>`,
            'chevron-down-left': `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 7 7 17"/><polyline points="7 7 7 17 17 17"/></svg>`,
            'chevron-up-left':   `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 17 7 7"/><polyline points="17 7 7 7 7 17"/></svg>`,
            'info':              `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`,
            'link':              `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>`,
            'pin':               `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`,
            'warning':           `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        };
        return icons[id] || icons['chevron-up'];
    }

    _handleHotspotAction(hs) {
        // User is taking manual control — stop any running auto-tour
        if (this._autoTourActive) this.stopAutoTour();
        switch (hs.action_type) {
            case 'navigate':
                if (hs.action_target) this.navigateToWaypoint(hs.action_target);
                break;
            case 'external-link':
                if (hs.action_target) window.open(hs.action_target, '_blank', 'noopener,noreferrer');
                break;
            case 'bookmark':
                this._toggleBookmark(hs);
                break;
            case 'info':
                if (this._infoCardHotspotId === hs.id) {
                    this._closeInfoCard();
                } else {
                    this._openInfoCard(hs);
                }
                break;
            case 'audio':
                if (hs.action_target) this._toggleAudio(hs);
                break;
            case 'video':
                if (hs.action_target) window.open(hs.action_target, '_blank', 'noopener,noreferrer');
                break;
        }
    }

    // ── In-scene info card ────────────────────────────────────────────────────

    _openInfoCard(hs) {
        this._infoCardHotspotId = hs.id;
        try { this.viewer.removeMarker('info-card'); } catch (e) {}

        const imageUrls = this._mediaImageUrls(hs);
        const spriteOpts = {
            style: 'card',
            title: hs.title || '',
            body:  hs.description || '',
            closeAction: 'tourEngine._closeInfoCard()',
        };
        if (hs.media_type === 'video' && hs.media_url) {
            const vid = this._extractYouTubeId(hs.media_url);
            if (vid) spriteOpts.mediaYouTubeId = vid;
        } else if (imageUrls.length === 1) {
            spriteOpts.mediaUrl = imageUrls[0];
        } else if (imageUrls.length > 1) {
            spriteOpts.mediaGallery = imageUrls;
        }

        this.viewer.addMarker({
            id:       'info-card',
            position: { yaw: `${hs.yaw}deg`, pitch: `${parseFloat(hs.pitch) + 15}deg` },
            data:     { hotspot: hs },
            sprite:   spriteOpts,
        });
    }

    _closeInfoCard() {
        this._infoCardHotspotId = null;
        try { this.viewer.removeMarker('info-card'); } catch (e) {}
    }

    _mediaImageUrls(hs) {
        if (!['image', 'gallery'].includes(hs?.media_type) || !hs?.media_url) {
            return [];
        }

        return String(hs.media_url).split('\n').map(u => u.trim()).filter(Boolean);
    }

    _infoCardHtml(hs) {
        const hasText  = !!(hs.description && hs.description.trim());
        const hasMedia = !!(hs.media_type && hs.media_url);
        const imageUrls = this._mediaImageUrls(hs);

        let mediaHtml = '';
        if (hasMedia) {
            if (hs.media_type === 'video') {
                const vid = this._extractYouTubeId(hs.media_url);
                if (vid) {
                    const src = this._buildYouTubeEmbedUrl(vid);
                    mediaHtml = `<div style="position:relative;padding-top:56.25%;background:#000;overflow:hidden;flex-shrink:0">`
                        + `<iframe src="${src}" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="autoplay;encrypted-media;fullscreen" allowfullscreen loading="lazy"></iframe>`
                        + `</div>`;
                }
            } else if (imageUrls.length === 1) {
                mediaHtml = `<div style="flex-shrink:0;overflow:hidden">`
                    + `<img src="${imageUrls[0]}" style="width:100%;display:block;max-height:240px;object-fit:cover" onerror="this.parentElement.style.display='none'" loading="lazy">`
                    + `</div>`;
            } else if (imageUrls.length > 1) {
                const imgs = imageUrls.map(url =>
                    `<img src="${url}" style="height:160px;width:auto;flex-shrink:0;display:block;border-radius:6px;object-fit:cover" onerror="this.style.display='none'" loading="lazy">`
                ).join('');
                mediaHtml = `<div style="display:flex;gap:8px;overflow-x:auto;padding:10px 14px;background:#f9fafb;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch">${imgs}</div>`;
            }
        }

        const bodyParts = [];
        if (hasText)  bodyParts.push(`<div style="padding:14px 14px ${hasMedia ? '8px' : '14px'};font-size:13px;color:#374151;line-height:1.6">${hs.description}</div>`);
        if (mediaHtml) bodyParts.push(mediaHtml);

        const hasBody = bodyParts.length > 0;

        return `<div style="background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.6);width:340px;font-family:sans-serif;display:flex;flex-direction:column;overflow:hidden;max-height:90vh">`
            + `<div style="background:linear-gradient(135deg,#00491E,#02681E);color:white;padding:14px 16px;position:relative;flex-shrink:0">`
            + `<button onclick="tourEngine._closeInfoCard();event.stopPropagation()" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,.2);border:none;color:white;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:14px;line-height:26px;text-align:center">✕</button>`
            + `<h2 style="font-size:16px;font-weight:700;margin:0 32px 0 0">${hs.title || ''}</h2>`
            + `</div>`
            + (hasBody ? `<div style="overflow-y:auto;flex:1;border-radius:0 0 12px 12px">${bodyParts.join('')}</div>` : '')
            + `</div>`;
    }

    _extractYouTubeId(url) {
        if (!url) return null;
        const patterns = [
            /[?&]v=([a-zA-Z0-9_-]{11})/,
            /youtu\.be\/([a-zA-Z0-9_-]{11})/,
            /\/embed\/([a-zA-Z0-9_-]{11})/,
            /\/shorts\/([a-zA-Z0-9_-]{11})/,
        ];
        for (const p of patterns) {
            const m = url.match(p);
            if (m) return m[1];
        }
        return null;
    }

    _buildYouTubeEmbedUrl(videoId) {
        if (!videoId) return '';

        const params = new URLSearchParams({
            rel: '0',
            playsinline: '1',
            modestbranding: '1',
        });

        if (window.location?.origin) {
            params.set('origin', window.location.origin);
        }

        return `https://www.youtube.com/embed/${videoId}?${params.toString()}`;
    }

    // ── Date-aware availability helpers ──────────────────────────────────────

    _setCheckIn(val)  { this._checkIn  = val || null; }
    _setCheckOut(val) { this._checkOut = val || null; }
    _setGuests(val)   { this._guests   = Math.max(1, parseInt(val, 10) || 1); }

    _computeNights() {
        if (!this._checkIn || !this._checkOut) return 0;
        return Math.max(1, Math.round((new Date(this._checkOut) - new Date(this._checkIn)) / 86400000));
    }

    _computePriceEstimateHtml(rt) {
        if (!this._checkIn || !this._checkOut || !rt.base_rate) return '';
        const nights    = this._computeNights();
        const perPerson = rt.pricing_type === 'per_person';
        const total     = perPerson ? rt.base_rate * nights * this._guests : rt.base_rate * nights;
        const formatted = new Intl.NumberFormat('en-PH', {
            style: 'currency', currency: 'PHP', minimumFractionDigits: 0, maximumFractionDigits: 0,
        }).format(total);
        const guestNote = perPerson ? ` · ${this._guests} guest(s)` : '';
        return `<div style="margin-top:8px;padding:8px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0">`
             + `<div style="font-size:11px;font-weight:700;color:#166534">Estimated total: ${formatted}</div>`
             + `<div style="font-size:10px;color:#6b7280">${nights} night(s)${guestNote}</div>`
             + `</div>`;
    }

    async _checkDateAvailability(rtId) {
        if (!this._checkIn || !this._checkOut) {
            this._showToast('Please select check-in and check-out dates.', 'error');
            return;
        }
        try {
            const url = new URL(`${this.apiBase}/room-type/${rtId}/availability`, window.location.href);
            url.searchParams.set('check_in',  this._checkIn);
            url.searchParams.set('check_out', this._checkOut);
            url.searchParams.set('guests',    this._guests);
            const res  = await fetch(url);
            const data = await res.json();
            if (data.success) {
                this.currentRoomType = data.data;
                // Update both overlay panel AND in-scene card
                this._populateRoomInfoOverlay(data.data, false);
                this._closeInSceneCard();
                this._openInSceneCard();
            } else {
                this._showToast(data.message || 'Could not check availability.', 'error');
            }
        } catch (e) {
            this._showToast('Network error. Please try again.', 'error');
        }
    }

    async _checkSpecificRoomAvailability() {
        if (this.currentRoomType?.id) {
            return this._checkDateAvailability(this.currentRoomType.id);
        }
    }

    // ── In-scene room info card ───────────────────────────────────────────────

    _openInSceneCard() {
        // Check for room or room type data
        const hasSpecificRoom = false;
        const hasRoomType = Boolean(this.currentRoomType);
        
        if (!hasSpecificRoom && !hasRoomType) return;
        if (!this.currentWaypoint) return;
        
        const wp = this.currentWaypoint;
        const yaw = wp.room_info_yaw ?? wp.default_yaw ?? 0;
        const pitch = wp.room_info_pitch ?? ((wp.default_pitch ?? 0) + 15);
        
        // Extract display data from either room or room type
        let name, description, price, tags, count, roomSharingType, availText, isPrivateRoom, otherAvailCount;
        
        if (hasSpecificRoom) {
            const room = this.currentRoom;
            const roomType = room.room_type || this.currentRoomType;
            name = roomType?.name || 'This Room';
            description = roomType?.description || '';
            price = roomType?.pricing_display || roomType?.formatted_price || '';
            tags = (roomType?.amenities || []).map(a => a.name);
            count = room.is_available ? 1 : 0;
            roomSharingType = roomType?.room_sharing_type || '';
            isPrivateRoom = roomType?.is_private ?? false;
            otherAvailCount = room.other_available_count ?? null;
            if (room.is_available) {
                availText = 'Available';
            } else {
                availText = 'Unavailable';
            }
        } else {
            const rt = this.currentRoomType;
            name = rt.name || '';
            description = rt.description || '';
            price = rt.pricing_display || rt.formatted_price || '';
            tags = (rt.amenities || []).map(a => a.name);
            count = rt.available_rooms_count;
            roomSharingType = rt.room_sharing_type || '';
            availText = count != null ? `${count} room(s) available` : '';
            isPrivateRoom = rt.is_private ?? false;
        }

        // Hide the compact trigger while the card is open
        try {
            this.viewer.updateMarker({
                id: 'room-info-marker',
                sprite: { style: 'circle', icon: 'chevron-up', bgColor: '#00491E', opacity: 0.01, size: 4 },
            });
        } catch (e) {}

        try { this.viewer.removeMarker('room-info-card'); } catch (e) {}

        const headerBadge = count != null
            ? hasSpecificRoom
                ? `${count > 0 ? '✓' : '✗'} ${availText}`
                : `${count > 0 ? '✓' : '✗'} ${count} avail.`
            : undefined;

        // ── Date availability widget ──────────────────────────────────────────
        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

        const roomTypeId = hasSpecificRoom 
            ? (this.currentRoom.room_type?.id || this.currentRoomType?.id)
            : this.currentRoomType.id;
        const priceEstHtml = roomTypeId ? this._computePriceEstimateHtml(hasSpecificRoom ? this.currentRoom.room_type : this.currentRoomType) : '';

        let availResultHtml = '';
        if (count != null && this._checkIn && this._checkOut) {
            const bg = count > 0 ? '#f0fdf4' : '#fef2f2';
            const bd = count > 0 ? '#bbf7d0' : '#fecaca';
            const clr = count > 0 ? '#166534' : '#991b1b';
            const ico = count > 0 ? '✓' : '✗';
            availResultHtml = `<div style="margin-top:6px;padding:6px 8px;background:${bg};border-radius:6px;border:1px solid ${bd}">`
                + `<div style="font-size:11px;font-weight:700;color:${clr}">${ico} ${availText}</div>`
                + `</div>`;
        }

        const inputStyle = 'width:100%;font-size:10px;border:1px solid #d1d5db;border-radius:4px;padding:3px 5px;box-sizing:border-box;height:26px';

        // Build availability widget - hide Guests field for private rooms
        let availWidget = '';
        if (!this.previewMode) {
            availWidget = `<div style="border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fafafa">`
              + `<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:6px">📅 Check Availability</div>`
              + `<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:4px">`
              +   `<div><div style="font-size:9px;color:#9ca3af;margin-bottom:1px">Check-in</div>`
              +   `<input type="date" value="${this._checkIn || ''}" min="${today}" onclick="event.stopPropagation()" onchange="tourEngine._setCheckIn(this.value)" style="${inputStyle}"></div>`
              +   `<div><div style="font-size:9px;color:#9ca3af;margin-bottom:1px">Check-out</div>`
              +   `<input type="date" value="${this._checkOut || ''}" min="${tomorrow}" onclick="event.stopPropagation()" onchange="tourEngine._setCheckOut(this.value)" style="${inputStyle}"></div>`
              + `</div>`;
            
            // For private rooms: full-width Check button. For dorm rooms: show Guests input
            if (isPrivateRoom) {
                availWidget += `<div style="margin-bottom:4px">`
                  +   `<button onclick="tourEngine.${hasSpecificRoom ? '_checkSpecificRoomAvailability()' : `_checkDateAvailability(${roomTypeId})`};event.stopPropagation()" style="width:100%;background:#1d4ed8;color:white;border:none;padding:6px;border-radius:6px;font-weight:600;font-size:10px;cursor:pointer;height:26px;box-sizing:border-box">🔍 Check</button>`
                  + `</div>`;
            } else {
                availWidget += `<div style="display:flex;gap:4px;align-items:flex-end;margin-bottom:4px">`
                  +   `<div style="flex:0 0 90px"><div style="font-size:9px;color:#9ca3af;margin-bottom:1px">Guests</div>`
                  +   `<input type="number" value="${this._guests}" min="1" max="20" onclick="event.stopPropagation()" onchange="tourEngine._setGuests(this.value)" style="${inputStyle}"></div>`
                  +   `<button onclick="tourEngine.${hasSpecificRoom ? '_checkSpecificRoomAvailability()' : `_checkDateAvailability(${roomTypeId})`};event.stopPropagation()" style="flex:1;background:#1d4ed8;color:white;border:none;padding:6px;border-radius:6px;font-weight:600;font-size:10px;cursor:pointer;height:26px;box-sizing:border-box">🔍 Check</button>`
                  + `</div>`;
            }
            
            availWidget += priceEstHtml + availResultHtml + `</div>`;
        }

        const roomIsUnavailable = hasSpecificRoom && count === 0;
        const typeHasAvailability = hasSpecificRoom && otherAvailCount != null && otherAvailCount > 0;

        // Build contextual note about other rooms when this specific room is unavailable
        let otherRoomsNote = '';
        if (hasSpecificRoom) {
            if (roomIsUnavailable && typeHasAvailability) {
                otherRoomsNote = `<div style="margin-bottom:6px;padding:6px 8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;font-size:10px;color:#166534;line-height:1.4">`
                    + `✓ <strong>${otherAvailCount} other room(s)</strong> of this type are available — you can still request this room type during reservation review.`
                    + `</div>`;
            } else if (roomIsUnavailable && otherAvailCount === 0) {
                otherRoomsNote = `<div style="margin-bottom:6px;padding:6px 8px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:10px;color:#991b1b;line-height:1.4">`
                    + `✗ No other rooms of this type are currently available.`
                    + `</div>`;
            }
        }

        const ctaLabel = hasSpecificRoom && !roomIsUnavailable
            ? '🏨 Request Reservation'
            : hasSpecificRoom && typeHasAvailability
                ? '🏨 Request This Room Type'
                : '🏨 Request Reservation';

        const disclaimer = hasSpecificRoom
            ? `<div style="text-align:center;font-size:10px;color:#9ca3af;margin-top:6px;line-height:1.4">Room assignment is finalized by staff during reservation review.</div>`
            : '';

        // Determine if reservation button should be disabled
        let isButtonDisabled = false;
        let showExploreButton = false;
        
        if (hasSpecificRoom) {
            // Specific room: disable if both room AND room type unavailable
            isButtonDisabled = roomIsUnavailable && !typeHasAvailability;
            showExploreButton = isButtonDisabled;
        } else {
            // Room type: disable if count is 0
            isButtonDisabled = count === 0;
            showExploreButton = isButtonDisabled;
        }

        const buttonStyle = isButtonDisabled
            ? 'width:100%;background:#FFC600;color:#00491E;border:none;padding:8px;border-radius:6px;font-weight:700;font-size:11px;opacity:0.5;cursor:not-allowed;pointer-events:none'
            : 'width:100%;background:#FFC600;color:#00491E;border:none;padding:8px;border-radius:6px;font-weight:700;font-size:11px;cursor:pointer';
        
        const buttonText = isButtonDisabled ? '🏨 No Rooms Available' : ctaLabel;
        
        const exploreButtonHtml = showExploreButton
            ? `<button onclick="window.location.href='/rooms';event.stopPropagation()" style="width:100%;background:#2563eb;color:white;border:none;padding:8px;border-radius:6px;font-weight:700;font-size:11px;cursor:pointer">🏠 Explore Other Room Types</button>`
            : '';

        const buttons = this.previewMode ? '' :
            `<div style="display:flex;flex-direction:column;gap:6px">`
          + availWidget
          + otherRoomsNote
          + `<div style="display:flex;flex-direction:column;gap:5px;align-items:center;margin-top:4px">`
          +   `<button onclick="tourEngine.openReservationModal();event.stopPropagation()" style="${buttonStyle}">${buttonText}</button>`
          +   exploreButtonHtml
          +   `<button onclick="window.location.href='/reserve';event.stopPropagation()" style="width:100%;background:#00491E;color:white;border:none;padding:8px;border-radius:6px;font-weight:700;font-size:11px;cursor:pointer">📝 Full Reservation Form</button>`
          + `</div>`
          + disclaimer
          + `</div>`;

        this.viewer.addMarker({
            id: 'room-info-card',
            position: { yaw: `${yaw}deg`, pitch: `${pitch}deg` },
            data: { isRoomInfoCard: true },
            sprite: {
                style: 'card',
                title: name,
                subtitle: roomSharingType,
                body: description,
                price,
                tags,
                headerBadge,
                headerBadgeColor: count > 0 ? '#86efac' : '#fca5a5',
                closeAction: 'tourEngine._closeInSceneCard()',
                buttons,
            },
        });
        this._roomInfoCardOpen = true;
    }

    _closeInSceneCard() {
        try { this.viewer.removeMarker('room-info-card'); } catch (e) {}
        // Restore compact trigger marker
        try {
            this.viewer.updateMarker({
                id:     'room-info-marker',
                sprite: { style: 'badge', icon: '🏠', label: 'Room Info',
                           bgColor: 'linear-gradient(135deg,#00491E,#02681E)',
                           textColor: '#ffffff', iconColor: '#ffffff' },
            });
        } catch (e) {}
        this._roomInfoCardOpen = false;
    }

    _inSceneCardHtml(data, isSpecificRoom = false) {
        let name, count, availText, availColor, pricing, description, amenities, sharingType;

        if (isSpecificRoom) {
            // Specific room data
            name = data.room_type?.name || 'This Room';
            count = data.is_available ? 1 : 0;
            const isPrivate = data.is_private_room ?? data.room_type?.is_private ?? false;
            if (data.is_available) {
                availText = 'Available';
            } else {
                availText = 'Unavailable';
            }
            availColor = data.is_available ? '#86efac' : '#fca5a5';
            pricing = data.room_type?.pricing_display || data.room_type?.formatted_price || '';
            description = data.room_type?.description || '';
            amenities = data.room_type?.amenities || [];
            sharingType = data.room_type?.room_sharing_type || '';
        } else {
            // Room type data
            name = data.name || '';
            count = data.available_rooms_count;
            availText = count != null ? `${count} room(s) available` : '';
            availColor = count > 0 ? '#86efac' : '#fca5a5';
            pricing = data.pricing_display || data.formatted_price || '';
            description = data.description || '';
            amenities = data.amenities || [];
            sharingType = data.room_sharing_type || '';
        }

        const amenitiesTags = amenities
            .map(a => `<span style="display:inline-block;background:#f3f4f6;color:#374151;font-size:11px;padding:3px 8px;border-radius:999px;margin:2px">${a.name}</span>`)
            .join('');

        const buttons = this.previewMode ? '' : `
            <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;align-items:center">
                <button onclick="tourEngine.openReservationModal()" style="width:85%;background:#FFC600;color:#00491E;border:none;padding:10px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">\uD83C\uDFE8 Request Reservation</button>
                <button onclick="window.location.href='/reserve'" style="width:85%;background:#00491E;color:white;border:none;padding:10px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">\uD83D\uDCDD Full Reservation Form</button>
            </div>`;

        return `<div style="background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.6);width:360px;font-family:sans-serif;display:flex;flex-direction:column;max-height:90vh">
            <div style="background:linear-gradient(135deg,#00491E,#02681E);color:white;padding:16px;position:relative;border-radius:12px 12px 0 0;flex-shrink:0">
                <button onclick="tourEngine._closeInSceneCard()" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,.2);border:none;color:white;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:14px;line-height:26px;text-align:center">✕</button>
                <h2 style="font-size:17px;font-weight:700;margin:0 32px 6px 0">${name}</h2>
                ${sharingType ? `<span style="display:inline-block;background:rgba(255,255,255,.2);font-size:11px;padding:2px 8px;border-radius:999px">${sharingType}</span>` : ''}
                ${availText ? `<div style="margin-top:6px;font-size:12px;font-weight:600;color:${availColor}">${availText}</div>` : ''}
            </div>
            <div style="padding:14px;overflow-y:auto;border-radius:0 0 12px 12px">
                ${description ? `<p style="color:#6b7280;font-size:12px;margin:0 0 10px">${description}</p>` : ''}
                ${pricing ? `<div style="margin-bottom:10px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-bottom:2px">Price</div><div style="font-size:19px;font-weight:700;color:#d97706">${pricing}</div></div>` : ''}
                ${amenitiesTags ? `<div style="margin-bottom:10px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-bottom:4px">Amenities</div>${amenitiesTags}</div>` : ''}
                ${buttons}
            </div>
        </div>`;
    }

    // ── Motion / WebXR ───────────────────────────────────────────────────────
    async startWebXRTest() {
        if (!('xr' in navigator)) {
            this._showToast('WebXR is not available in this browser.', 'error');
            return;
        }

        const panoramaUrl = this.currentWaypoint?.panorama_image;
        if (!panoramaUrl) {
            this._showToast('No panorama is loaded for WebXR testing.', 'error');
            return;
        }

        if (this._webXRTest) await this.stopWebXRTest();

        let session;
        try {
            session = await navigator.xr.requestSession('immersive-vr', {
                optionalFeatures: ['local-floor', 'bounded-floor'],
            });
        } catch (error) {
            console.error('WebXR session request failed:', error);
            this._showToast(error?.message || 'Could not start immersive VR session.', 'error');
            return;
        }

        const layer = document.createElement('div');
        layer.style.cssText = 'position:fixed;inset:0;background:#000;z-index:10000;overflow:hidden';
        document.body.appendChild(layer);

        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.xr.enabled = true;
        renderer.xr.setReferenceSpaceType('local');
        renderer.setClearColor(0x202020, 1);
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        renderer.setSize(window.innerWidth, window.innerHeight);
        layer.appendChild(renderer.domElement);

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x202020);
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / Math.max(1, window.innerHeight), 0.1, 1000);
        const loader = new THREE.TextureLoader();
        loader.setCrossOrigin('anonymous');
        const contentGroup = new THREE.Group();
        scene.add(contentGroup);

        let texture = null;
        // Match Photo Sphere Viewer's equirectangular mesh orientation so stored yaw/pitch
        // coordinates land on the same panorama pixels in the WebXR test path.
        const geometry = new THREE.SphereGeometry(100, 64, 32, -Math.PI / 2, Math.PI * 2, 0, Math.PI).scale(-1, 1, 1);
        const material = new THREE.MeshBasicMaterial({ color: 0x111111, depthTest: false, depthWrite: false });
        const sphere = new THREE.Mesh(geometry, material);
        contentGroup.add(sphere);

        const hotspotGroup = new THREE.Group();
        const panelGroup = new THREE.Group();
        const statusGroup = new THREE.Group();
        const cameraFollower = new THREE.Group();
        contentGroup.add(hotspotGroup);
        scene.add(panelGroup, cameraFollower);
        cameraFollower.add(statusGroup);

        const interactive = [];
        const raycaster = new THREE.Raycaster();
        const tempMatrix = new THREE.Matrix4();
        const textTextures = new Set();
        const XR_RADIUS = 9;
        const XR_ROOM_SCALE = { x: 2.8, y: 0.82 };
        let hoveredObject = null;
        let infoPanelAnchor = null;
        const roundRect = (ctx, x, y, width, height, radius) => {
            const r = Math.min(radius, width / 2, height / 2);
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + width, y, x + width, y + height, r);
            ctx.arcTo(x + width, y + height, x, y + height, r);
            ctx.arcTo(x, y + height, x, y, r);
            ctx.arcTo(x, y, x + width, y, r);
            ctx.closePath();
        };

        const makeTextTexture = (lines, options = {}) => {
            const canvas = document.createElement('canvas');
            const width = options.width || 512;
            const height = options.height || 160;
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, width, height);
            ctx.fillStyle = options.background || 'rgba(0,73,30,0.92)';
            roundRect(ctx, 0, 0, width, height, options.radius || 28);
            ctx.fill();
            if (options.border) {
                ctx.strokeStyle = options.border;
                ctx.lineWidth = 6;
                ctx.stroke();
            }
            ctx.fillStyle = options.color || '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = options.font || 'bold 42px sans-serif';
            const rows = Array.isArray(lines) ? lines : [lines];
            const lineHeight = options.lineHeight || 46;
            const startY = height / 2 - ((rows.length - 1) * lineHeight) / 2;
            rows.forEach((line, index) => ctx.fillText(String(line), width / 2, startY + index * lineHeight));
            const tex = new THREE.CanvasTexture(canvas);
            tex.colorSpace = THREE.SRGBColorSpace;
            textTextures.add(tex);
            return tex;
        };

        const drawXRIcon = (ctx, icon, x, y, size, color = '#ffffff') => {
            ctx.save();
            ctx.strokeStyle = color;
            ctx.fillStyle = color;
            ctx.lineWidth = Math.max(8, size * 0.1);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            const line = (...points) => {
                ctx.beginPath();
                ctx.moveTo(x + points[0][0] * size, y + points[0][1] * size);
                points.slice(1).forEach(([px, py]) => ctx.lineTo(x + px * size, y + py * size));
                ctx.stroke();
            };

            const chevrons = {
                'chevron-up': [[-0.28, 0.14], [0, -0.18], [0.28, 0.14]],
                'chevron-down': [[-0.28, -0.14], [0, 0.18], [0.28, -0.14]],
                'chevron-left': [[0.14, -0.28], [-0.18, 0], [0.14, 0.28]],
                'chevron-right': [[-0.14, -0.28], [0.18, 0], [-0.14, 0.28]],
                'chevron-up-right': [[-0.18, 0.22], [0.22, -0.18], [0.22, 0.18], [0.22, -0.18], [-0.14, -0.18]],
                'chevron-down-right': [[-0.18, -0.22], [0.22, 0.18], [0.22, -0.18], [0.22, 0.18], [-0.14, 0.18]],
                'chevron-down-left': [[0.18, -0.22], [-0.22, 0.18], [-0.22, -0.18], [-0.22, 0.18], [0.14, 0.18]],
                'chevron-up-left': [[0.18, 0.22], [-0.22, -0.18], [-0.22, 0.18], [-0.22, -0.18], [0.14, -0.18]],
            };

            if (chevrons[icon]) {
                line(...chevrons[icon]);
            } else if (icon === 'info') {
                ctx.beginPath();
                ctx.arc(x, y, size * 0.34, 0, Math.PI * 2);
                ctx.stroke();
                line([0, -0.02], [0, 0.2]);
                ctx.beginPath();
                ctx.arc(x, y - size * 0.22, size * 0.035, 0, Math.PI * 2);
                ctx.fill();
            } else if (icon === 'link') {
                ctx.beginPath();
                ctx.ellipse(x - size * 0.14, y + size * 0.06, size * 0.2, size * 0.12, -0.7, 0, Math.PI * 2);
                ctx.stroke();
                ctx.beginPath();
                ctx.ellipse(x + size * 0.14, y - size * 0.06, size * 0.2, size * 0.12, -0.7, 0, Math.PI * 2);
                ctx.stroke();
            } else if (icon === 'pin') {
                ctx.beginPath();
                ctx.arc(x, y - size * 0.08, size * 0.22, 0, Math.PI * 2);
                ctx.stroke();
                line([0, 0.14], [0, 0.34]);
            } else if (icon === 'warning') {
                line([0, -0.3], [0.34, 0.28], [-0.34, 0.28], [0, -0.3]);
                line([0, -0.08], [0, 0.08]);
                ctx.beginPath();
                ctx.arc(x, y + size * 0.2, size * 0.035, 0, Math.PI * 2);
                ctx.fill();
            } else {
                line([-0.28, 0.14], [0, -0.18], [0.28, 0.14]);
            }

            ctx.restore();
        };

        const makeHotspotTexture = (icon, options = {}) => {
            const canvas = document.createElement('canvas');
            const size = options.size || 256;
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, size, size);
            ctx.save();
            ctx.shadowColor = 'rgba(0,0,0,0.42)';
            ctx.shadowBlur = size * 0.06;
            ctx.shadowOffsetY = size * 0.03;
            ctx.beginPath();
            ctx.arc(size / 2, size / 2, size * 0.37, 0, Math.PI * 2);
            ctx.fillStyle = options.background || '#6b7280';
            ctx.fill();
            ctx.restore();

            ctx.beginPath();
            ctx.arc(size / 2, size / 2, size * 0.37, 0, Math.PI * 2);
            ctx.lineWidth = size * 0.042;
            ctx.strokeStyle = '#ffffff';
            ctx.stroke();
            drawXRIcon(ctx, icon || 'chevron-up', size / 2, size / 2, size * 0.62, options.color || '#ffffff');
            const tex = new THREE.CanvasTexture(canvas);
            tex.colorSpace = THREE.SRGBColorSpace;
            textTextures.add(tex);
            return tex;
        };

        const showXRStatus = (lines, type = 'info') => {
            clearGroup(statusGroup);
            const background = type === 'error' ? 'rgba(153,27,27,0.95)' : 'rgba(17,24,39,0.92)';
            const tex = makeTextTexture(lines, {
                width: 900,
                height: 260,
                background,
                color: '#ffffff',
                font: 'bold 44px sans-serif',
                lineHeight: 64,
                border: type === 'error' ? '#fecaca' : '#FFC600',
            });
            const panel = makePlane(tex, new THREE.Vector3(0, 0, -3), { x: 3.2, y: 0.95 }, { action: 'noop' }, {
                depthTest: false,
                renderOrder: 100,
            });
            statusGroup.add(panel);
        };

        const yawPitchToVector = (yawDeg, pitchDeg, radius = XR_RADIUS) => {
            const yaw = THREE.MathUtils.degToRad(parseFloat(yawDeg) || 0);
            const pitch = THREE.MathUtils.degToRad(parseFloat(pitchDeg) || 0);
            return new THREE.Vector3(
                -Math.sin(yaw) * Math.cos(pitch) * radius,
                Math.sin(pitch) * radius,
                Math.cos(yaw) * Math.cos(pitch) * radius,
            );
        };

        const makePlane = (textureMap, position, scale, data, options = {}) => {
            const plane = new THREE.Mesh(
                new THREE.PlaneGeometry(scale.x, scale.y),
                new THREE.MeshBasicMaterial({
                    map: textureMap,
                    transparent: true,
                    side: THREE.DoubleSide,
                    depthTest: options.depthTest ?? true,
                    depthWrite: false,
                }),
            );
            plane.position.copy(position);
            plane.renderOrder = options.renderOrder ?? 20;
            plane.userData = {
                ...data,
                baseScale: new THREE.Vector3(1, 1, 1),
                billboard: options.billboard ?? true,
            };
            interactive.push(plane);
            return plane;
        };

        const clearGroup = (group) => {
            [...group.children].forEach((child) => {
                group.remove(child);
                const index = interactive.indexOf(child);
                if (index !== -1) interactive.splice(index, 1);
                const materialToDispose = child.material;
                if (materialToDispose?.map) {
                    textTextures.delete(materialToDispose.map);
                    materialToDispose.map.dispose();
                }
                if (materialToDispose) materialToDispose.dispose();
                child.geometry?.dispose?.();
            });
            hoveredObject = null;
        };

        const closePanel = (resetInfoAnchor = true) => {
            clearGroup(panelGroup);
            for (let i = interactive.length - 1; i >= 0; i--) {
                if (interactive[i].userData?.panel) interactive.splice(i, 1);
            }
            if (resetInfoAnchor) infoPanelAnchor = null;
        };

        const truncateXRText = (value, max = 64) => {
            const text = String(value || '').replace(/\s+/g, ' ').trim();
            if (text.length <= max) return text;
            return `${text.slice(0, max - 3).trim()}...`;
        };

        const showInfoPanel = async (hs, imageIndex = 0, keepPosition = false) => {
            closePanel(!keepPosition);
            if (!hs) return;

            const hasVideo = hs.media_type === 'video' && hs.media_url;
            const imageUrls = this._mediaImageUrls(hs);
            const hasImage = imageUrls.length > 0;
            const currentImageIndex = hasImage
                ? Math.max(0, Math.min(imageUrls.length - 1, imageIndex))
                : 0;
            const lines = [
                truncateXRText(hs.title || 'Information', 42),
                truncateXRText(hs.description || '', 70),
                hasVideo ? 'Video content available' : '',
                hasImage ? `Image ${currentImageIndex + 1} of ${imageUrls.length}` : '',
            ].filter(Boolean);

            const up = new THREE.Vector3(0, 1, 0);
            if (!infoPanelAnchor || infoPanelAnchor.hotspotId !== hs.id) {
                const forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camera.quaternion);
                forward.y = 0;
                if (forward.lengthSq() < 0.001) forward.set(0, 0, -1);
                forward.normalize();
                const right = new THREE.Vector3(1, 0, 0).applyQuaternion(camera.quaternion);
                right.y = 0;
                if (right.lengthSq() < 0.001) right.crossVectors(forward, up);
                right.normalize();
                infoPanelAnchor = {
                    hotspotId: hs.id,
                    center: camera.position.clone().add(forward.clone().multiplyScalar(3.15)),
                    right,
                };
            }
            const { center, right } = infoPanelAnchor;
            const panelPosition = (x = 0, y = 0) => center.clone().add(right.clone().multiplyScalar(x)).add(up.clone().multiplyScalar(y));
            const panel = makePlane(makeTextTexture(lines, {
                width: 820,
                height: hasImage ? 230 : 340,
                background: 'rgba(255,255,255,0.96)',
                color: '#111827',
                font: 'bold 38px sans-serif',
                lineHeight: 56,
                border: '#00491E',
            }), panelPosition(0, hasImage ? 0.62 : 0), { x: 2.65, y: hasImage ? 0.62 : 1.1 }, { panel: true, action: 'noop' });
            panelGroup.add(panel);

            const buttons = [];
            if (hasImage) {
                const imageUrl = new URL(imageUrls[currentImageIndex], window.location.href).href;
                try {
                    const imageTexture = await loader.loadAsync(imageUrl);
                    imageTexture.colorSpace = THREE.SRGBColorSpace;
                    const image = imageTexture.image;
                    const aspect = image?.width && image?.height ? image.width / image.height : 16 / 9;
                    const maxWidth = 2.35;
                    const maxHeight = 0.82;
                    let imageWidth = maxWidth;
                    let imageHeight = imageWidth / aspect;
                    if (imageHeight > maxHeight) {
                        imageHeight = maxHeight;
                        imageWidth = imageHeight * aspect;
                    }
                    const imagePlane = makePlane(imageTexture, panelPosition(0, -0.12), { x: imageWidth, y: imageHeight }, { panel: true, action: 'noop' });
                    panelGroup.add(imagePlane);
                    clearGroup(statusGroup);
                } catch (error) {
                    console.error('WebXR info image load failed:', error);
                }

                if (imageUrls.length > 1) {
                    buttons.push(makePlane(makeTextTexture('Prev', {
                        width: 360,
                        height: 100,
                        background: '#FFC600',
                        color: '#00491E',
                        font: 'bold 34px sans-serif',
                    }), panelPosition(-0.95, -0.82), { x: 0.9, y: 0.25 }, {
                        panel: true,
                        action: 'info-image',
                        hotspot: hs,
                        imageIndex: (currentImageIndex - 1 + imageUrls.length) % imageUrls.length,
                    }));

                    buttons.push(makePlane(makeTextTexture('Next', {
                        width: 360,
                        height: 100,
                        background: '#FFC600',
                        color: '#00491E',
                        font: 'bold 34px sans-serif',
                    }), panelPosition(0.95, -0.82), { x: 0.9, y: 0.25 }, {
                        panel: true,
                        action: 'info-image',
                        hotspot: hs,
                        imageIndex: (currentImageIndex + 1) % imageUrls.length,
                    }));
                }

            }

            if (hasVideo) {
                const videoId = this._extractYouTubeId(hs.media_url);
                const videoUrl = videoId ? `https://www.youtube.com/watch?v=${videoId}` : hs.media_url;
                buttons.push(makePlane(makeTextTexture('Open Video', {
                    width: 512,
                    height: 120,
                    background: '#FFC600',
                    color: '#00491E',
                    font: 'bold 40px sans-serif',
                }), panelPosition(0, -1.05), { x: 1.6, y: 0.38 }, { panel: true, action: 'open-url', url: videoUrl }));
            }

            const closeY = hasImage ? (buttons.length > 1 ? -1.14 : -0.95) : (hasVideo ? -1.5 : -1.05);
            buttons.push(makePlane(makeTextTexture('Close', {
                width: 360,
                height: 100,
                background: '#374151',
                color: '#ffffff',
                font: 'bold 34px sans-serif',
            }), panelPosition(0, closeY), { x: 0.9, y: 0.25 }, { panel: true, action: 'close-panel' }));

            panelGroup.add(...buttons);
        };

        const showRoomInfoPanel = () => {
            closePanel();
            if (!this.currentRoomType) return;
            const rt = this.currentRoomType;
            const count = rt.available_rooms_count;
            const amenities = (rt.amenities || []).map(a => a.name).filter(Boolean);
            const amenitiesText = amenities.length
                ? `Amenities: ${amenities.slice(0, 3).join(', ')}${amenities.length > 3 ? '...' : ''}`
                : '';
            const description = truncateXRText(rt.description || '', 52);
            const lines = [
                rt.name || 'Room Type',
                rt.room_sharing_type || '',
                rt.pricing_display || rt.formatted_price || '',
                count != null ? `${count} room(s) available` : 'Availability available in form',
                description,
                truncateXRText(amenitiesText, 58),
            ].filter(Boolean);
            const forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camera.quaternion);
            const center = camera.position.clone().add(forward.multiplyScalar(3.2));
            const panel = makePlane(makeTextTexture(lines, {
                width: 1024,
                height: 520,
                background: 'rgba(255,255,255,0.96)',
                color: '#00491E',
                font: 'bold 34px sans-serif',
                lineHeight: 66,
                border: '#FFC600',
            }), center.clone().add(new THREE.Vector3(0, 0.22, 0)), { x: 3.05, y: 1.55 }, { panel: true, action: 'noop' });
            panelGroup.add(panel);

            const request = makePlane(makeTextTexture('Request Reservation', {
                width: 512,
                height: 120,
                background: '#FFC600',
                color: '#00491E',
                font: 'bold 40px sans-serif',
            }), center.clone().add(new THREE.Vector3(0, -1.05, 0)), { x: 1.6, y: 0.38 }, { panel: true, action: 'reservation' });
            const full = makePlane(makeTextTexture('Full Form', {
                width: 512,
                height: 120,
                background: '#00491E',
                color: '#ffffff',
                font: 'bold 40px sans-serif',
            }), center.clone().add(new THREE.Vector3(0, -1.5, 0)), { x: 1.6, y: 0.38 }, { panel: true, action: 'reserve-page' });
            const close = makePlane(makeTextTexture('Close', {
                width: 360,
                height: 100,
                background: '#374151',
                color: '#ffffff',
                font: 'bold 34px sans-serif',
            }), center.clone().add(new THREE.Vector3(0, 1.18, 0)), { x: 0.9, y: 0.25 }, { panel: true, action: 'close-panel' });
            panelGroup.add(request, full, close);
        };

        const rebuildHotspots = () => {
            clearGroup(hotspotGroup);
            interactive.length = 0;
            closePanel();
            const spots = (this.currentWaypoint?.hotspots || []).filter(h => h.is_active !== false);
            spots.forEach((hs) => {
                const color = HOTSPOT_COLORS[hs.action_type] || '#6b7280';
                const sizeScale = { 1: 0.6, 2: 0.8, 3: 1.0, 4: 1.25, 5: 1.5 }[hs.size || 3] ?? 1.0;
                const planeSize = 0.62 * sizeScale;
                const hotspot = makePlane(makeHotspotTexture(hs.icon || 'chevron-up', {
                    background: color,
                }), yawPitchToVector(hs.yaw, hs.pitch), { x: planeSize, y: planeSize }, { action: 'hotspot', hotspot: hs });
                hotspotGroup.add(hotspot);
            });
            if (this.currentWaypoint?.is_room_related && this.currentWaypoint?.linked_room_type_id) {
                const yaw = this.currentWaypoint.room_info_yaw ?? this.currentWaypoint.default_yaw ?? 0;
                const pitch = this.currentWaypoint.room_info_pitch ?? ((this.currentWaypoint.default_pitch ?? 0) + 15);
                const roomInfo = makePlane(makeTextTexture('Room Info', {
                    width: 420,
                    height: 120,
                    background: '#00491E',
                    color: '#FFC600',
                    border: '#FFC600',
                    font: 'bold 36px sans-serif',
                }), yawPitchToVector(yaw, pitch), XR_ROOM_SCALE, { action: 'room-info' });
                hotspotGroup.add(roomInfo);
            }
        };

        const loadXRWaypoint = async (slug) => {
            const wp = this.waypoints.find(w => w.slug === slug);
            if (!wp?.panorama_image) return;
            this.currentWaypoint = wp;
            contentGroup.rotation.y = THREE.MathUtils.degToRad(parseFloat(wp.default_yaw) || 0) + Math.PI;
            if (wp.is_room_related && wp.linked_room_type_id) {
                await this._fetchRoomInfo(wp);
            } else {
                this.currentRoomType = null;
                this.currentRoom = null;
            }
            showXRStatus(['Loading panorama...', wp.name || '']);
            try {
                const nextTexture = await loader.loadAsync(new URL(wp.panorama_image, window.location.href).href);
                nextTexture.colorSpace = THREE.SRGBColorSpace;
                material.map?.dispose();
                material.map = nextTexture;
                material.color.set(0xffffff);
                material.needsUpdate = true;
                texture = nextTexture;
                clearGroup(statusGroup);
                rebuildHotspots();
            } catch (error) {
                console.error('WebXR panorama load failed:', error);
                showXRStatus(['Could not load panorama', 'Exit VR and try again'], 'error');
            }
        };

        const handleXRAction = async (target) => {
            const data = target?.userData || {};
            if (data.action === 'room-info') {
                showRoomInfoPanel();
            } else if (data.action === 'close-panel') {
                closePanel();
            } else if (data.action === 'reservation') {
                await this.stopWebXRTest();
                this.openReservationModal();
            } else if (data.action === 'reserve-page') {
                await this.stopWebXRTest();
                window.location.href = '/reserve';
            } else if (data.action === 'open-url' && data.url) {
                await this.stopWebXRTest();
                window.open(data.url, '_blank', 'noopener,noreferrer');
            } else if (data.action === 'info-image' && data.hotspot) {
                await showInfoPanel(data.hotspot, data.imageIndex || 0, true);
            } else if (data.action === 'hotspot') {
                const hs = data.hotspot;
                if (hs?.action_type === 'navigate' && hs.action_target) {
                    await loadXRWaypoint(hs.action_target);
                } else if (hs?.action_type === 'info') {
                    await showInfoPanel(hs);
                } else if (hs?.action_type === 'external-link' && hs.action_target) {
                    await this.stopWebXRTest();
                    window.open(hs.action_target, '_blank', 'noopener,noreferrer');
                } else if (hs?.action_type === 'bookmark') {
                    this._toggleBookmark(hs);
                }
            }
        };

        const selectFromController = (controller) => {
            tempMatrix.identity().extractRotation(controller.matrixWorld);
            raycaster.ray.origin.setFromMatrixPosition(controller.matrixWorld);
            raycaster.ray.direction.set(0, 0, -1).applyMatrix4(tempMatrix);
            const hit = raycaster.intersectObjects(interactive, false)[0];
            if (hit) handleXRAction(hit.object);
        };

        const getControllerHit = (controller) => {
            tempMatrix.identity().extractRotation(controller.matrixWorld);
            raycaster.ray.origin.setFromMatrixPosition(controller.matrixWorld);
            raycaster.ray.direction.set(0, 0, -1).applyMatrix4(tempMatrix);
            return raycaster.intersectObjects(interactive, false)[0] || null;
        };

        const setHoveredObject = (next) => {
            if (hoveredObject === next) return;
            if (hoveredObject?.userData?.baseScale) {
                hoveredObject.scale.copy(hoveredObject.userData.baseScale);
                if (hoveredObject.material) hoveredObject.material.opacity = 1;
            }
            hoveredObject = next;
            if (hoveredObject?.userData?.baseScale) {
                hoveredObject.scale.copy(hoveredObject.userData.baseScale).multiplyScalar(1.16);
                if (hoveredObject.material) hoveredObject.material.opacity = 0.92;
            }
        };

        const updateBillboards = () => {
            interactive.forEach((object) => {
                if (object.userData?.billboard && !statusGroup.children.includes(object)) {
                    object.lookAt(camera.position);
                }
            });
        };

        const updateControllerHover = () => {
            let bestHit = null;
            controllers.forEach((controller) => {
                const hit = getControllerHit(controller);
                const active = !!hit;
                if (controller.userData.lineMaterial) {
                    controller.userData.lineMaterial.color.set(active ? '#FFC600' : '#ffffff');
                    controller.userData.lineMaterial.opacity = active ? 0.95 : 0.55;
                }
                if (!bestHit && hit) bestHit = hit;
            });
            setHoveredObject(bestHit?.object || null);
        };

        const makeController = (index) => {
            const controller = renderer.xr.getController(index);
            controller.addEventListener('selectstart', () => selectFromController(controller));
            const lineMaterial = new THREE.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.55 });
            const line = new THREE.Line(
                new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(0, 0, 0), new THREE.Vector3(0, 0, -10)]),
                lineMaterial,
            );
            line.name = 'xr-test-controller-ray';
            controller.userData.lineMaterial = lineMaterial;
            controller.add(line);
            scene.add(controller);
            return controller;
        };
        const controllers = [makeController(0), makeController(1)];

        const onResize = () => {
            camera.aspect = window.innerWidth / Math.max(1, window.innerHeight);
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        };
        window.addEventListener('resize', onResize);

        let cleanupCalled = false;
        const cleanup = () => {
            if (cleanupCalled) return; // Prevent double cleanup
            cleanupCalled = true;
            
            renderer.setAnimationLoop(null);
            window.removeEventListener('resize', onResize);
            closePanel();
            clearGroup(hotspotGroup);
            clearGroup(statusGroup);
            controllers.forEach((controller) => {
                controller.children.forEach((child) => {
                    child.geometry?.dispose?.();
                    child.material?.dispose?.();
                });
                scene.remove(controller);
            });
            textTextures.forEach(tex => tex.dispose());
            texture?.dispose();
            material.dispose();
            geometry.dispose();
            renderer.dispose();
            layer.remove();
            this._webXRTest = null;
            
            // Restore the panorama viewer
            if (this.currentWaypoint?.slug) {
                this.navigateToWaypoint(this.currentWaypoint.slug).catch(() => {});
            }
        };

        session.addEventListener('end', cleanup, { once: true });
        await renderer.xr.setSession(session);

        this._webXRTest = { session, cleanup };
        renderer.setAnimationLoop(() => {
            cameraFollower.position.copy(camera.position);
            cameraFollower.quaternion.copy(camera.quaternion);
            updateBillboards();
            updateControllerHover();
            renderer.render(scene, camera);
        });
        showXRStatus(['Loading WebXR tour...', 'Please wait']);
        await loadXRWaypoint(this.currentWaypoint.slug);
        this._showToast('WebXR test session started.', 'success');
    }

    async stopWebXRTest() {
        if (!this._webXRTest) return;
        const { session, cleanup } = this._webXRTest;
        
        // Always run cleanup, even if session.end() fails
        try {
            if (session?.end) {
                await session.end();
            }
        } catch (error) {
            console.warn('Session end error:', error);
        } finally {
            // Ensure cleanup runs to restore the viewer
            if (cleanup) {
                cleanup();
            }
        }
    }

    async toggleGyroscope() {
        if (!this.viewer) return;
        await this.viewer.toggleGyroscope();
        return this.viewer.isGyroscopeEnabled();
    }

    // ── Gyroscope plugin compatibility ────────────────────────────────────────

    get gyroscopePlugin() {
        return {
            isEnabled: () => this.viewer?.isGyroscopeEnabled() ?? false,
        };
    }

    // ── Room info overlay ─────────────────────────────────────────────────────

    async _fetchRoomInfo(wp) {
        try {
            if (wp.linked_room_type_id) {
                const url = new URL(`${this.apiBase}/room-type/${wp.linked_room_type_id}/availability`, window.location.href);
                if (this._checkIn)    url.searchParams.set('check_in',  this._checkIn);
                if (this._checkOut)   url.searchParams.set('check_out', this._checkOut);
                if (this._guests > 1) url.searchParams.set('guests',    this._guests);
                const res  = await fetch(url);
                const data = await res.json();
                if (data.success) {
                    this.currentRoomType = data.data;
                    this.currentRoom = null;
                    this._populateRoomInfoOverlay(data.data, false);
                }
            }
        } catch (e) { console.error('_fetchRoomInfo:', e); }
    }

    _populateRoomInfoOverlay(data, isSpecificRoom = false) {
        const ov = this.overlay;
        if (!ov) return;
        const setText = (sel, val) => { const el = ov.querySelector(sel); if (el) el.textContent = val ?? ''; };

        // Determine if private room
        const isPrivate = isSpecificRoom 
            ? (data.is_private_room ?? data.room_type?.is_private ?? false)
            : (data.is_private ?? false);

        // Hide/show guests field based on room type
        const guestsContainer = ov.querySelector('.flex.gap-2.items-end.mb-2');
        const guestsField = ov.querySelector('#overlay-guests');
        const guestsFieldContainer = guestsField?.parentElement;
        const checkButton = guestsContainer?.querySelector('button');
        
        if (guestsContainer && guestsFieldContainer && checkButton) {
            if (isPrivate) {
                // Hide guests field, make check button full width
                guestsFieldContainer.classList.add('hidden');
                checkButton.classList.remove('flex-1');
                checkButton.classList.add('w-full');
            } else {
                // Show guests field, normal layout
                guestsFieldContainer.classList.remove('hidden');
                checkButton.classList.remove('w-full');
                checkButton.classList.add('flex-1');
            }
            
            // Update check button onclick handler
            if (isSpecificRoom) {
                checkButton.setAttribute('onclick', 'tourEngine._checkSpecificRoomAvailability()');
            } else {
                checkButton.setAttribute('onclick', `tourEngine._checkDateAvailability(${data.id})`);
            }
        }

        // Handle room vs room type display
        if (isSpecificRoom) {
            // Specific room: show room type name
            setText('.room-name', data.room_type?.name || 'This Room');
            setText('.room-type-badge', data.room_type?.room_sharing_type || '');
            setText('.room-description', data.room_type?.description || '');
            setText('.room-price', data.room_type?.pricing_display || data.room_type?.formatted_price || '');

            // Show disclaimer for specific rooms
            const disclaimerEl = ov.querySelector('#overlay-room-disclaimer');
            if (disclaimerEl) disclaimerEl.classList.remove('hidden');

            // Update CTA label and state based on room + type availability
            const requestBtn = ov.querySelector('#overlay-request-btn');
            const exploreBtn = ov.querySelector('#overlay-explore-btn');
            
            const roomUnavailable = !data.is_available;
            const typeHasOthers = data.other_available_count != null && data.other_available_count > 0;
            
            if (requestBtn) {
                if (roomUnavailable && !typeHasOthers) {
                    // Both this room AND the room type are unavailable - disable button
                    requestBtn.disabled = true;
                    requestBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                    requestBtn.classList.remove('hover:bg-yellow-500');
                    requestBtn.textContent = '🏨 No Rooms Available';
                    if (exploreBtn) exploreBtn.classList.remove('hidden');
                } else if (roomUnavailable && typeHasOthers) {
                    // This room unavailable but others available - change text, keep enabled
                    requestBtn.disabled = false;
                    requestBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                    requestBtn.classList.add('hover:bg-yellow-500');
                    requestBtn.textContent = '🏨 Request This Room Type';
                    if (exploreBtn) exploreBtn.classList.add('hidden');
                } else {
                    // Room is available - normal state
                    requestBtn.disabled = false;
                    requestBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                    requestBtn.classList.add('hover:bg-yellow-500');
                    requestBtn.textContent = '🏨 Request Reservation';
                    if (exploreBtn) exploreBtn.classList.add('hidden');
                }
            }
            
            const avail = ov.querySelector('.availability-badge');
            if (avail) {
                let availabilityHtml = '';
                
                // Part 1: This room's status
                if (data.is_available) {
                    availabilityHtml = '<div class="text-green-300 font-semibold">✓ Available</div>';
                } else {
                    availabilityHtml = '<div class="text-red-300 font-semibold">✗ Currently Unavailable</div>';
                }
                
                // Part 2: Other rooms of the same type (aggregate count only)
                if (data.room_type && data.other_available_count !== undefined) {
                    const roomTypeName = data.room_type.name || 'this type';
                    
                    if (data.other_available_count > 0) {
                        const roomText = data.other_available_count === 1 ? 'room' : 'rooms';
                        if (!data.is_available) {
                            // Room is unavailable — make this prominent and actionable
                            availabilityHtml += `<div class="mt-2 px-3 py-2 bg-green-700 bg-opacity-40 border border-green-400 border-opacity-50 rounded-lg text-xs leading-snug">`
                                + `<span class="text-green-200 font-semibold">✓ ${data.other_available_count} ${roomTypeName} ${roomText} available</span>`
                                + `<br><span class="text-green-300 opacity-80">You can still request this room type — specific room assignment is confirmed during review.</span>`
                                + `</div>`;
                        } else {
                            availabilityHtml += `<div class="text-gray-300 text-xs mt-2">📊 ${data.other_available_count} other ${roomTypeName} ${roomText} available</div>`;
                        }
                    } else if (data.other_available_count === 0) {
                        availabilityHtml += `<div class="text-gray-400 text-xs mt-2">📊 No other ${roomTypeName} rooms available</div>`;
                    }
                }
                
                avail.innerHTML = availabilityHtml;
                avail.className = 'availability-badge mt-3 text-sm';
            }
            
            const amenitiesEl = ov.querySelector('.room-amenities');
            if (amenitiesEl && data.room_type?.amenities) {
                amenitiesEl.innerHTML = data.room_type.amenities.map(a =>
                    `<span class="inline-block bg-[#FFC600] text-[#00491E] text-xs px-2 py-1 rounded-full mr-2 mb-2">${a.name}</span>`
                ).join('');
            }
        } else {
            // Room type: existing behavior
            setText('.room-name', data.name || '');
            setText('.room-type-badge', data.room_sharing_type || '');
            setText('.room-description', data.description || '');
            setText('.room-price', data.pricing_display || data.formatted_price || '');

            // Hide disclaimer + reset CTA label for room types
            const disclaimerEl = ov.querySelector('#overlay-room-disclaimer');
            if (disclaimerEl) disclaimerEl.classList.add('hidden');

            const count = data.available_rooms_count;
            const avail = ov.querySelector('.availability-badge');
            if (avail) {
                avail.textContent = count != null ? `${count} room(s) available` : '';
                avail.className = 'availability-badge mt-3 text-sm font-semibold '
                    + (count > 0 ? 'text-green-300' : 'text-red-300');
            }

            // Control button states based on availability (for room types)
            const requestBtn = ov.querySelector('#overlay-request-btn');
            const exploreBtn = ov.querySelector('#overlay-explore-btn');
            
            if (requestBtn) {
                if (count === 0) {
                    // No rooms available - disable reservation, show explore alternative
                    requestBtn.disabled = true;
                    requestBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                    requestBtn.classList.remove('hover:bg-yellow-500');
                    requestBtn.textContent = '🏨 No Rooms Available';
                    if (exploreBtn) exploreBtn.classList.remove('hidden');
                } else {
                    // Rooms available - enable reservation, hide explore
                    requestBtn.disabled = false;
                    requestBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                    requestBtn.classList.add('hover:bg-yellow-500');
                    requestBtn.textContent = '🏨 Request Reservation';
                    if (exploreBtn) exploreBtn.classList.add('hidden');
                }
            }

            const amenitiesEl = ov.querySelector('.room-amenities');
            if (amenitiesEl && data.amenities) {
                amenitiesEl.innerHTML = data.amenities.map(a =>
                    `<span class="inline-block bg-[#FFC600] text-[#00491E] text-xs px-2 py-1 rounded-full mr-2 mb-2">${a.name}</span>`
                ).join('');
            }
        }
    }

    toggleRoomInfoOverlay() {
        if (!this.overlay) return;
        if (this.overlay.classList.contains('slide-in')) {
            this.hideRoomInfoOverlay();
        } else {
            this.showRoomInfoOverlay();
        }
    }

    showRoomInfoOverlay() {
        if (!this.overlay) return;
        this.overlay.classList.remove('hidden');
        // force reflow so the CSS transition plays
        void this.overlay.offsetWidth;
        this.overlay.classList.add('slide-in');
        if (this.currentWaypoint) this.onRoomDoorReached(this.currentWaypoint);
    }

    hideRoomInfoOverlay() {
        if (!this.overlay) return;
        this.overlay.classList.remove('slide-in');
        // re-hide after the slide-out transition finishes (300 ms)
        setTimeout(() => this.overlay.classList.add('hidden'), 310);
    }

    // ── Reservation modal ─────────────────────────────────────────────────────

    openReservationModal() {
        if (this.reservationModal) {
            this.reservationModal.removeAttribute('hidden');
            this.reservationModal.classList.remove('hidden');
            this.reservationModal.style.display = 'flex';
            this.reservationModal.style.visibility = 'visible';
            this.reservationModal.style.opacity = '1';
            this.reservationModal.style.pointerEvents = 'auto';
        }
        
        if (this.currentRoomType) {
            const roomTypeIdEl = document.getElementById('preferred_room_type_id');
            const roomIdEl = document.getElementById('preferred_room_id');
            if (roomTypeIdEl) roomTypeIdEl.value = this.currentRoomType.id || '';
            if (roomIdEl) roomIdEl.value = '';
            this.onReservationOpened(this.currentRoomType);
        }
        
        // Pre-fill dates and occupants from the availability widget state
        if (this._checkIn) {
            const ciEl = document.getElementById('check_in_date');
            if (ciEl) ciEl.value = this._checkIn;
        }
        if (this._checkOut) {
            const coEl = document.getElementById('check_out_date');
            if (coEl) coEl.value = this._checkOut;
        }
        if (this._guests > 1) {
            const gEl = document.getElementById('number_of_occupants');
            if (gEl) gEl.value = this._guests;
        }
    }

    closeReservationModal() {
        if (this.reservationModal) {
            this.reservationModal.style.setProperty('display', 'none', 'important');
            this.reservationModal.style.visibility = 'hidden';
            this.reservationModal.style.opacity = '0';
            this.reservationModal.style.pointerEvents = 'none';
            this.reservationModal.classList.add('hidden');
            this.reservationModal.setAttribute('hidden', 'hidden');
        }
    }

    async submitReservation(formData) {
        try {
            const res  = await fetch(`${this.apiBase}/reserve`, {
                method: 'POST',
                headers: {
                    'Content-Type':  'application/json',
                    'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(formData),
            });

            let data = null;
            let rawBody = '';
            try {
                data = await res.clone().json();
            } catch {
                // Some environments return a non-JSON body even when HTTP status is successful.
                rawBody = await res.text();
            }

            if (res.ok && (data?.success ?? true)) {
                const payload = data && typeof data === 'object'
                    ? data
                    : {
                        success: true,
                        message: 'Reservation submitted successfully!',
                        data: {},
                    };

                this.closeReservationModal();
                const reservationModal = document.getElementById('reservation-modal');
                if (reservationModal) {
                    reservationModal.style.display = 'none';
                    reservationModal.classList.add('hidden');
                }
                this._showToast(payload.message || 'Reservation submitted!', 'success');

                const successModal = document.getElementById('reservation-success-modal');
                const referenceEl = document.getElementById('success-reference');
                const trackLinkEl = document.getElementById('success-track-link');
                if (referenceEl) referenceEl.textContent = payload.data?.reference_number || 'Submitted';
                if (trackLinkEl && payload.data?.track_url) trackLinkEl.href = payload.data.track_url;
                if (successModal) successModal.style.display = 'flex';

                return { success: true, data: payload, rawBody };
            }

            const errors = data?.errors
                ? Object.values(data.errors).flat().join('\n')
                : null;
            const fallbackMessage = res.ok
                ? 'Submission failed.'
                : `Submission failed (${res.status}).`;
            this._showToast(errors || data?.message || fallbackMessage, 'error');
            return { success: false, data };
        } catch (e) {
            this._showToast('Network error. Please try again.', 'error');
            return {
                success: false,
                error: e,
            };
        }
    }

    // ── Narration ─────────────────────────────────────────────────────────────

    showNarration(text) {
        if (!this.narrationTooltip || !text) return;
        const p = this.narrationTooltip.querySelector('.narration-text');
        if (p) p.textContent = text;
        this.narrationTooltip.classList.add('visible');
        clearTimeout(this._narrationTimer);
        this._narrationTimer = setTimeout(() => this.hideNarration(), 6000);
    }

    hideNarration() {
        this.narrationTooltip?.classList.remove('visible');
    }

    // ── Minimap ───────────────────────────────────────────────────────────────

    renderMinimap() {
        const list = document.querySelector('#minimap .minimap-waypoints');
        if (!list) return;
        list.innerHTML = '';
        this.waypoints.forEach(wp => {
            const btn = document.createElement('button');
            btn.dataset.slug = wp.slug;
            btn.className    = 'minimap-waypoint-btn w-full px-2 py-1.5 rounded text-left text-sm';
            btn.setAttribute('role', 'menuitem');
            btn.setAttribute('aria-label', `Go to ${wp.name}`);
            btn.innerHTML    = `<div style="display:flex;align-items:center;gap:6px">`
                             + `<span class="wp-here-dot" style="display:none;width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0" aria-hidden="true"></span>`
                             + `<div><div class="font-medium text-gray-800">${wp.name}</div>`
                             + (wp.type_label ? `<div class="text-xs text-gray-500">${wp.type_label}</div>` : '')
                             + `</div></div>`;
            btn.addEventListener('click', () => this.navigateToWaypoint(wp.slug));
            list.appendChild(btn);
        });
    }

    highlightCurrentOnMinimap(slug) {
        const list = document.querySelector('#minimap .minimap-waypoints');
        if (!list) return;
        list.querySelectorAll('.minimap-waypoint-btn').forEach(el => {
            const isActive = el.dataset.slug === slug;
            el.classList.toggle('bg-blue-500', isActive);
            el.classList.toggle('text-white',  isActive);
            const dot = el.querySelector('.wp-here-dot');
            if (dot) dot.style.display = isActive ? 'block' : 'none';
            if (isActive) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    }

    updateProgressIndicator() {
        if (!this.progressIndicator || !this.currentWaypoint) return;
        const i = this.waypoints.findIndex(w => w.slug === this.currentWaypoint.slug);
        this.progressIndicator.textContent = `Stop ${i + 1} of ${this.waypoints.length}`;
    }

    // ── Loading ───────────────────────────────────────────────────────────────

    showLoading() { this.loadingIndicator?.classList.remove('hidden'); }
    hideLoading()  { this.loadingIndicator?.classList.add('hidden'); }

    // ── Bookmarks ─────────────────────────────────────────────────────────────

    _loadBookmarks() {
        try { return JSON.parse(localStorage.getItem('tour_bookmarks') || '[]'); } catch { return []; }
    }

    _saveBookmarks() {
        localStorage.setItem('tour_bookmarks', JSON.stringify(this.bookmarks));
    }

    setupBookmarks() {
        this._renderBookmarks();
    }

    _toggleBookmark(hs) {
        const key      = `${this.currentWaypoint?.slug}-${hs.id}`;
        const existing = this.bookmarks.findIndex(b => b.key === key);
        if (existing >= 0) {
            this.bookmarks.splice(existing, 1);
            this._showToast('Bookmark removed.', 'info');
        } else {
            this.bookmarks.push({
                key,
                slug:  this.currentWaypoint?.slug,
                name:  this.currentWaypoint?.name,
                label: hs.title,
            });
            this._showToast('Bookmarked!', 'success');
        }
        this._saveBookmarks();
        this._renderBookmarks();
    }

    _renderBookmarks() {
        const list = document.getElementById('bookmark-list');
        if (!list) return;
        if (this.bookmarks.length === 0) {
            list.innerHTML = '<li class="text-gray-400 text-sm">No bookmarks yet.</li>';
            return;
        }
        list.innerHTML = this.bookmarks
            .map(b => `<li class="bookmark-item" data-slug="${b.slug}">${b.name}: ${b.label}</li>`)
            .join('');
        list.querySelectorAll('.bookmark-item').forEach(el => {
            el.addEventListener('click', () => this.navigateToWaypoint(el.dataset.slug));
        });
    }

    // ── Keyboard controls ─────────────────────────────────────────────────────

    setupKeyboardControls() {
        const held = new Set();
        const SPEED = 0.02; // radians per frame for A/D rotation
        let _navCooldown = false;
        let _focusedHotspotIdx = -1;
        const isTyping = () => ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);

        document.addEventListener('keydown', (e) => {
            if (isTyping()) return;
            const k = e.key;

            if (
                this._autoTourActive
                && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'a', 'A', 'd', 'D', 'w', 'W', 's', 'S', 'Tab', 'Enter'].includes(k)
            ) {
                this.stopAutoTour();
                this._showToast('Auto Tour paused for manual navigation.', 'info');
            }

            // A / D / ←/ → — smooth yaw rotation via RAF loop
            if (['ArrowLeft', 'ArrowRight', 'a', 'A', 'd', 'D'].includes(k)) {
                e.preventDefault();
                held.add(k);
            }

            // W / S / ↑ / ↓ — navigate to nearest marker in facing / opposite direction
            if (['ArrowUp', 'ArrowDown', 'w', 'W', 's', 'S'].includes(k)) {
                e.preventDefault();
                if (!_navCooldown) {
                    _navCooldown = true;
                    setTimeout(() => _navCooldown = false, 600);
                    const forward = k === 'ArrowUp' || k === 'w' || k === 'W';
                    this._navigateToNearest(forward);
                }
            }

            // +/- zoom
            if (k === '+' || k === '=') this.viewer?.zoom(this.viewer.getZoomLevel() + 5);
            if (k === '-')               this.viewer?.zoom(this.viewer.getZoomLevel() - 5);

            // Tab — cycle through active hotspots, rotating view to each
            if (k === 'Tab') {
                e.preventDefault();
                if (this._resetHotspotFocus) { _focusedHotspotIdx = -1; this._resetHotspotFocus = false; }
                const hotspots = (this.currentWaypoint?.hotspots || []).filter(h => h.is_active !== false);
                if (!hotspots.length) return;
                _focusedHotspotIdx = e.shiftKey
                    ? (_focusedHotspotIdx - 1 + hotspots.length) % hotspots.length
                    : (_focusedHotspotIdx + 1) % hotspots.length;
                const hs = hotspots[_focusedHotspotIdx];
                this.viewer?.rotate({ yaw: `${hs.yaw}deg`, pitch: `${hs.pitch}deg` });
                this._showToast(hs.title || 'Hotspot', 'info');
            }

            // Enter — activate the focused hotspot
            if (k === 'Enter' && _focusedHotspotIdx >= 0) {
                const hotspots = (this.currentWaypoint?.hotspots || []).filter(h => h.is_active !== false);
                const hs = hotspots[_focusedHotspotIdx];
                if (hs) this._handleHotspotAction(hs);
            }

            // Escape — stop auto-tour first, then close open cards
            if (k === 'Escape') {
                if (this._autoTourActive) this.stopAutoTour();
                else if (this._roomInfoCardOpen) this._closeInSceneCard();
                else if (this._infoCardHotspotId) this._closeInfoCard();
            }

            // H — toggle UI visibility
            if (k === 'h' || k === 'H') {
                e.preventDefault();
                this.toggleUIVisibility();
            }
        });

        document.addEventListener('keyup', (e) => { held.delete(e.key); });

        const loop = () => {
            if (held.size && this.viewer) {
                const pos = this.viewer.getPosition();
                let { yaw, pitch } = pos;
                if (held.has('ArrowLeft')  || held.has('a') || held.has('A')) yaw -= SPEED;
                if (held.has('ArrowRight') || held.has('d') || held.has('D')) yaw += SPEED;
                this.viewer.rotate({ yaw, pitch });
            }
            requestAnimationFrame(loop);
        };
        requestAnimationFrame(loop);
    }

    /**
     * Navigate to the nearest navigate-type hotspot in the forward (facing) or
     * backward (opposite) direction relative to the current view yaw.
     */
    _navigateToNearest(forward) {
        const hotspots = this.currentWaypoint?.hotspots;
        if (!hotspots?.length || !this.viewer) return;

        const targets = hotspots.filter(h => h.is_active !== false && h.action_type === 'navigate' && h.action_target);
        if (!targets.length) return;

        const currentYawRad = this.viewer.getPosition().yaw;
        const currentYawDeg = currentYawRad * 180 / Math.PI;
        const refYaw = forward ? currentYawDeg : currentYawDeg + 180;

        // Shortest angular distance (handles -180/+180 wrap)
        const angDist = (a, b) => {
            let d = ((a - b) % 360 + 360) % 360;
            if (d > 180) d -= 360;
            return Math.abs(d);
        };

        let best = null, bestDist = Infinity;
        for (const h of targets) {
            const dist = angDist(parseFloat(h.yaw), refYaw);
            if (dist < bestDist) { bestDist = dist; best = h; }
        }

        if (best) this.navigateToWaypoint(best.action_target);
    }

    // ── Audio playback ────────────────────────────────────────────────────────

    _toggleAudio(hs) {
        if (this._audioEl && this._audioHotspotId === hs.id) {
            this._audioEl.pause();
            this._audioEl = null;
            this._audioHotspotId = null;
            this._showToast('Audio stopped.', 'info');
            return;
        }
        if (this._audioEl) { this._audioEl.pause(); this._audioEl = null; }
        const audio = new Audio(hs.action_target);
        audio.addEventListener('ended', () => {
            this._audioEl = null;
            this._audioHotspotId = null;
        });
        audio.addEventListener('error', () => {
            this._audioEl = null;
            this._audioHotspotId = null;
            this._showToast('Audio playback failed.', 'error');
        });
        this._audioEl = audio;
        this._audioHotspotId = hs.id;
        audio.play().catch(() => {
            this._audioEl = null;
            this._audioHotspotId = null;
            this._showToast('Could not play audio.', 'error');
        });
        this._showToast(`▶ ${hs.title}`, 'info');
    }

    // ── Auto-tour ─────────────────────────────────────────────────────────────

    startAutoTour() {
        if (!this.waypoints.length) {
            this._showToast('No tour stops available for Auto Tour.', 'error');
            return;
        }

        this._autoTourActive = true;
        this._syncAutoTourBtn(true);

        // Show UI and keep it visible during Auto Tour
        this._showUI();
        this._uiManuallyHidden = false;  // Clear manual override
        this._syncToggleUIBtn(false);
        clearTimeout(this._uiIdleTimer);  // Prevent hiding while Auto Tour runs

        this._runAutoTourStep();
        const profileLabel = AUTO_TOUR_PROFILES[this._autoTourProfile].label;
        this._showToast(`Auto Tour started (${profileLabel}) - cinematic pan enabled. Press Esc to stop.`, 'info');
    }

    stopAutoTour() {
        this._autoTourActive = false;
        this._clearAutoTourTimers();
        this._setAutoTourHud(false);
        this._syncAutoTourBtn(false);

        // Resume auto-hide behavior
        this._resetUIIdleTimer();
    }

    toggleAutoTour() {
        if (this._autoTourActive) {
            this.stopAutoTour();
            this._showToast('Auto Tour stopped.', 'info');
        } else {
            this.startAutoTour();
        }
    }

    _syncAutoTourBtn(active) {
        document.getElementById('auto-tour-btn')?.classList.toggle('active', active);
        const playIcon = document.getElementById('auto-tour-play-icon');
        const stopIcon = document.getElementById('auto-tour-stop-icon');
        if (playIcon) playIcon.style.display = active ? 'none' : '';
        if (stopIcon) stopIcon.style.display = active ? '' : 'none';
        const t = document.getElementById('auto-tour-btn-text');
        if (t) t.textContent = active ? 'Stop Tour' : 'Auto Tour';
    }

    _normalizeAutoTourProfile(profile) {
        return Object.prototype.hasOwnProperty.call(AUTO_TOUR_PROFILES, profile)
            ? profile
            : 'normal';
    }

    _bindAutoTourSettings() {
        if (!this.autoTourSpeedButtons.length) return;
        this._syncAutoTourProfileButtons(this._autoTourProfile);
        this.autoTourSpeedButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                this.setAutoTourProfile(btn.dataset.profile);
            });
        });
    }

    setAutoTourProfile(profile, opts = {}) {
        const { persist = true, notify = true } = opts;
        const normalized = this._normalizeAutoTourProfile(profile);
        const changed = normalized !== this._autoTourProfile;

        this._autoTourProfile = normalized;
        this._autoTourCycleMs = AUTO_TOUR_PROFILES[normalized].cycleMs;
        this._autoTourPanMs = AUTO_TOUR_PROFILES[normalized].panMs;

        this._syncAutoTourProfileButtons(normalized);

        if (persist) {
            try {
                localStorage.setItem('tour_auto_tour_profile', normalized);
            } catch (_) {
                // Ignore persistence failures in private browsing or restricted environments.
            }
        }

        if (this._autoTourActive && changed) {
            this._runAutoTourStep();
        }

        if (notify && changed) {
            this._showToast(`Auto Tour speed set to ${AUTO_TOUR_PROFILES[normalized].label}.`, 'info');
        }
    }

    _syncAutoTourProfileButtons(activeProfile) {
        if (!this.autoTourSpeedButtons.length) return;
        this.autoTourSpeedButtons.forEach((btn) => {
            const isActive = btn.dataset.profile === activeProfile;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    _clearAutoTourTimers() {
        clearTimeout(this._autoTourTimer);
        clearInterval(this._autoTourTickTimer);
        if (this._autoTourPanRaf) cancelAnimationFrame(this._autoTourPanRaf);
        this._autoTourTimer = null;
        this._autoTourTickTimer = null;
        this._autoTourPanRaf = null;
    }

    _setAutoTourHud(active) {
        if (!this.autoTourHud) return;
        this.autoTourHud.classList.toggle('hidden', !active);
        if (!active) {
            if (this.autoTourCountdown) this.autoTourCountdown.textContent = 'Auto Tour idle';
            if (this.autoTourFill) this.autoTourFill.style.width = '0%';
        }
    }

    _runAutoTourStep() {
        if (!this._autoTourActive) return;

        this._clearAutoTourTimers();
        this._autoTourStepStart = Date.now();
        this._setAutoTourHud(true);
        this._startAutoTourPan();
        this._startAutoTourCountdown();

        this._autoTourTimer = setTimeout(async () => {
            if (!this._autoTourActive) return;
            const i = this.waypoints.findIndex(w => w.slug === this.currentWaypoint?.slug);
            const next = i < this.waypoints.length - 1
                ? this.waypoints[i + 1]
                : this.waypoints[0];

            if (next) await this.navigateToWaypoint(next.slug);
            if (this._autoTourActive) this._runAutoTourStep();
        }, this._autoTourCycleMs);
    }

    _startAutoTourPan() {
        if (this._reducedMotion || !this.viewer) return;

        const start = performance.now();
        const base = this.viewer.getPosition();
        const baseYaw = base?.yaw || 0;
        const basePitch = base?.pitch || 0;
        const yawAmplitude = 40 * Math.PI / 180;  // Wider horizontal sweep (was 24°)
        const pitchAmplitude = 4 * Math.PI / 180;  // More vertical motion (was 2.4°)
        const yawSway = 5 * Math.PI / 180;  // More horizontal sway (was 3.2°)

        const tick = (now) => {
            if (!this._autoTourActive || !this.viewer) return;

            const elapsed = now - start;
            const progress = Math.min(1, elapsed / this._autoTourPanMs);
            const eased = 0.5 - 0.5 * Math.cos(progress * Math.PI);
            const yaw = baseYaw + (eased * yawAmplitude) + (Math.sin(progress * Math.PI * 2) * yawSway);
            const pitch = basePitch + Math.sin(progress * Math.PI * 2) * pitchAmplitude;
            this.viewer.rotate({ yaw, pitch });

            if (progress < 1) {
                this._autoTourPanRaf = requestAnimationFrame(tick);
            }
        };

        this._autoTourPanRaf = requestAnimationFrame(tick);
    }

    _startAutoTourCountdown() {
        const holdMs = this._autoTourCycleMs - this._autoTourPanMs;
        const update = () => {
            if (!this._autoTourActive) return;

            const elapsed = Date.now() - this._autoTourStepStart;
            const remaining = Math.max(0, this._autoTourCycleMs - elapsed);
            const pct = Math.max(0, Math.min(100, (elapsed / this._autoTourCycleMs) * 100));
            if (this.autoTourFill) this.autoTourFill.style.width = `${pct}%`;

            if (this.autoTourCountdown) {
                const remainingSec = Math.ceil(remaining / 1000);
                this.autoTourCountdown.textContent = remaining <= holdMs
                    ? `Next scene in ${remainingSec}s`
                    : `Panning... ${remainingSec}s`;
            }
        };

        update();
        this._autoTourTickTimer = setInterval(update, 100);
    }

    // ── Toast ─────────────────────────────────────────────────────────────────

    _showToast(msg, type = 'info') {
        const el = document.getElementById('toast-notification');
        if (!el) {
            if (type === 'error') {
                window.alert(msg);
            } else {
                console.log(`[tour:${type}] ${msg}`);
            }
            return;
        }
        el.textContent = msg;
        el.className   = `toast toast-${type} visible`;
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => el.classList.remove('visible'), 3500);
    }
}

window.VirtualTourEngine = VirtualTourEngine;

// Cache bust: 2026-04-21 13:11:49
