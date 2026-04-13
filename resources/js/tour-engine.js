/**
 * Virtual Tour Engine — Guest-facing 360° viewer.
 * Uses PanoramaViewer (Photo Sphere Viewer) for rendering, with HTML markers, gyroscope, and stereo.
 */
import { PanoramaViewer } from './panorama-viewer.js';

const HOTSPOT_COLORS = {
    navigate:        '#3b82f6',
    info:            '#f59e0b',
    bookmark:        '#8b5cf6',
    'external-link': '#10b981',
};

class VirtualTourEngine {
    constructor(containerId, options = {}) {
        this.container         = document.getElementById(containerId);
        this.viewer            = null;

        this.waypoints           = [];
        this.currentWaypoint     = null;
        this.startWaypoint       = options.startWaypoint || '';
        this.apiBase             = options.apiBase || '/api/tour';
        this.vrActive            = false;
        this.currentRoomType     = null;
        this.bookmarks           = this._loadBookmarks();
        this._roomInfoCardOpen   = false;
        this._infoCardHotspotId  = null;
        this._audioEl            = null;
        this._audioHotspotId     = null;
        this._autoTourActive     = false;
        this._autoTourTimer      = null;
        this.previewMode         = options.previewMode || window.location.search.includes('preview');

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

        // Listen for VR state changes (WebXR session start/end)
        this.viewer.addEventListener('vr-changed', (e) => {
            this.vrActive = e.active;
            this._syncVRBtn(e.active);
        });

        // Hotspot click → handle action
        this.viewer.addEventListener('select-marker', (e) => {
            const data = e.marker.config.data;
            if (data?.isRoomInfo) {
                if (this._roomInfoCardOpen) {
                    this._closeInSceneCard();
                } else {
                    this._openInSceneCard();
                }
                return;
            }
            if (e.marker.config.id === 'info-card') { this._closeInfoCard(); return; }
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

    _syncVRBtn(isVR) {
        document.getElementById('vr-mode-btn')?.classList.toggle('active', isVR);
        const t = document.getElementById('vr-btn-text');
        if (t) t.textContent = isVR ? 'Exit VR' : 'VR Mode';
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
        this.hideLoading();
        this.updateProgressIndicator();
        this.highlightCurrentOnMinimap(slug);
        this._pushUrlScene(slug);
        this._resetHotspotFocus = true; // signal setupKeyboardControls to reset Tab index
        if (wp.narration) this.showNarration(wp.narration);

        if (wp.is_room_related && wp.linked_room_type_id) {
            this._fetchRoomInfo(wp);
            if (this.roomInfoBtn) this.roomInfoBtn.classList.add('visible');
        } else {
            if (this.roomInfoBtn) this.roomInfoBtn.classList.remove('visible');
            this.hideRoomInfoOverlay();
            this.currentRoomType = null;
        }

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
        if (!this.currentWaypoint) return;
        const i = this.waypoints.findIndex(w => w.slug === this.currentWaypoint.slug);
        if (i > 0) this.navigateToWaypoint(this.waypoints[i - 1].slug);
    }

    navigateNext() {
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

        const spriteOpts = {
            style: 'card',
            title: hs.title || '',
            body:  hs.description || '',
        };
        if (hs.media_type === 'video' && hs.media_url) {
            const vid = this._extractYouTubeId(hs.media_url);
            if (vid) spriteOpts.mediaYouTubeId = vid;
        } else if (hs.media_type === 'image' && hs.media_url) {
            spriteOpts.mediaUrl = hs.media_url;
        } else if (hs.media_type === 'gallery' && hs.media_url) {
            const urls = hs.media_url.split('\n').map(u => u.trim()).filter(Boolean);
            if (urls.length) spriteOpts.mediaGallery = urls;
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

    _infoCardHtml(hs) {
        const hasText  = !!(hs.description && hs.description.trim());
        const hasMedia = !!(hs.media_type && hs.media_url);

        let mediaHtml = '';
        if (hasMedia) {
            if (hs.media_type === 'video') {
                const vid = this._extractYouTubeId(hs.media_url);
                if (vid) {
                    const src = `https://www.youtube-nocookie.com/embed/${vid}?rel=0`;
                    mediaHtml = `<div style="position:relative;padding-top:56.25%;background:#000;overflow:hidden;flex-shrink:0">`
                        + `<iframe src="${src}" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="autoplay;encrypted-media;fullscreen" allowfullscreen loading="lazy"></iframe>`
                        + `</div>`;
                }
            } else if (hs.media_type === 'image') {
                mediaHtml = `<div style="flex-shrink:0;overflow:hidden">`
                    + `<img src="${hs.media_url}" style="width:100%;display:block;max-height:240px;object-fit:cover" onerror="this.parentElement.style.display='none'" loading="lazy">`
                    + `</div>`;
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
                // Reopen card to reflect date-specific availability + price estimate
                this._closeInSceneCard();
                this._openInSceneCard();
            } else {
                this._showToast(data.message || 'Could not check availability.', 'error');
            }
        } catch (e) {
            this._showToast('Network error. Please try again.', 'error');
        }
    }

    // ── In-scene room info card ───────────────────────────────────────────────

    _openInSceneCard() {
        if (!this.currentRoomType || !this.currentWaypoint) return;
        const wp    = this.currentWaypoint;
        const yaw   = wp.room_info_yaw   ?? wp.default_yaw   ?? 0;
        const pitch = wp.room_info_pitch ?? ((wp.default_pitch ?? 0) + 15);
        const rt    = this.currentRoomType;

        // Hide the compact trigger while the card is open
        try {
            this.viewer.updateMarker({
                id:     'room-info-marker',
                sprite: { style: 'circle', icon: 'chevron-up', bgColor: '#00491E', opacity: 0.01, size: 4 },
            });
        } catch (e) {}

        try { this.viewer.removeMarker('room-info-card'); } catch (e) {}

        const price = rt.pricing_display || rt.formatted_price || '';
        const tags  = (rt.amenities || []).map(a => a.name);
        const count = rt.available_rooms_count;
        const headerBadge = count != null
            ? `${count > 0 ? '✓' : '✗'} ${count} avail.`
            : undefined;

        // ── Date availability widget ──────────────────────────────────────────
        const today    = new Date().toISOString().split('T')[0];
        const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

        const priceEstHtml = this._computePriceEstimateHtml(rt);

        let availResultHtml = '';
        if (count != null && this._checkIn && this._checkOut) {
            const bg  = count > 0 ? '#f0fdf4' : '#fef2f2';
            const bd  = count > 0 ? '#bbf7d0' : '#fecaca';
            const clr = count > 0 ? '#166534' : '#991b1b';
            const ico = count > 0 ? '✓' : '✗';
            availResultHtml = `<div style="margin-top:6px;padding:6px 8px;background:${bg};border-radius:6px;border:1px solid ${bd}">`
                + `<div style="font-size:11px;font-weight:700;color:${clr}">${ico} ${count} room(s) available for your dates</div>`
                + `</div>`;
        }

        const inputStyle = 'width:100%;font-size:11px;border:1px solid #d1d5db;border-radius:6px;padding:4px 6px;box-sizing:border-box';

        const availWidget = this.previewMode ? '' :
            `<div style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fafafa">`
          + `<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:8px">📅 Check Availability</div>`
          + `<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px">`
          +   `<div><div style="font-size:10px;color:#9ca3af;margin-bottom:2px">Check-in</div>`
          +   `<input type="date" value="${this._checkIn || ''}" min="${today}" onclick="event.stopPropagation()" onchange="tourEngine._setCheckIn(this.value)" style="${inputStyle}"></div>`
          +   `<div><div style="font-size:10px;color:#9ca3af;margin-bottom:2px">Check-out</div>`
          +   `<input type="date" value="${this._checkOut || ''}" min="${tomorrow}" onclick="event.stopPropagation()" onchange="tourEngine._setCheckOut(this.value)" style="${inputStyle}"></div>`
          + `</div>`
          + `<div style="display:flex;gap:6px;align-items:flex-end;margin-bottom:6px">`
          +   `<div style="flex:0 0 110px"><div style="font-size:10px;color:#9ca3af;margin-bottom:2px">Guests</div>`
          +   `<input type="number" value="${this._guests}" min="1" max="20" onclick="event.stopPropagation()" onchange="tourEngine._setGuests(this.value)" style="${inputStyle}"></div>`
          +   `<button onclick="tourEngine._checkDateAvailability(${rt.id});event.stopPropagation()" style="flex:1;background:#1d4ed8;color:white;border:none;padding:6px;border-radius:6px;font-weight:600;font-size:11px;cursor:pointer">🔍 Check</button>`
          + `</div>`
          + priceEstHtml
          + availResultHtml
          + `</div>`;

        const buttons = this.previewMode ? '' :
            `<div style="display:flex;flex-direction:column;gap:8px">`
          + availWidget
          + `<div style="display:flex;flex-direction:column;gap:6px;align-items:center;margin-top:4px">`
          +   `<button onclick="tourEngine.openReservationModal();event.stopPropagation()" style="width:100%;background:#FFC600;color:#00491E;border:none;padding:10px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">🏨 Request Reservation</button>`
          +   `<button onclick="window.location.href='/reserve';event.stopPropagation()" style="width:100%;background:#00491E;color:white;border:none;padding:10px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">📝 Full Reservation Form</button>`
          + `</div>`
          + `</div>`;

        this.viewer.addMarker({
            id:       'room-info-card',
            position: { yaw: `${yaw}deg`, pitch: `${pitch}deg` },
            data:     { isRoomInfoCard: true },
            sprite: {
                style:            'card',
                title:            rt.name || '',
                subtitle:         rt.room_sharing_type || '',
                body:             rt.description || '',
                price,
                tags,
                headerBadge,
                headerBadgeColor: count > 0 ? '#86efac' : '#fca5a5',
                closeAction:      'tourEngine._closeInSceneCard()',
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

    _inSceneCardHtml(rt) {
        const count      = rt.available_rooms_count;
        const availText  = count != null ? `${count} room(s) available` : '';
        const availColor = count > 0 ? '#86efac' : '#fca5a5';

        const amenitiesTags = (rt.amenities || [])
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
                <h2 style="font-size:17px;font-weight:700;margin:0 32px 6px 0">${rt.name || ''}</h2>
                ${rt.room_sharing_type ? `<span style="display:inline-block;background:rgba(255,255,255,.2);font-size:11px;padding:2px 8px;border-radius:999px">${rt.room_sharing_type}</span>` : ''}
                ${availText ? `<div style="margin-top:6px;font-size:12px;font-weight:600;color:${availColor}">${availText}</div>` : ''}
            </div>
            <div style="padding:14px;overflow-y:auto;border-radius:0 0 12px 12px">
                ${rt.description ? `<p style="color:#6b7280;font-size:12px;margin:0 0 10px">${rt.description}</p>` : ''}
                ${rt.pricing_display || rt.formatted_price ? `<div style="margin-bottom:10px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-bottom:2px">Price</div><div style="font-size:19px;font-weight:700;color:#d97706">${rt.pricing_display || rt.formatted_price}</div></div>` : ''}
                ${amenitiesTags ? `<div style="margin-bottom:10px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-bottom:4px">Amenities</div>${amenitiesTags}</div>` : ''}
                ${buttons}
            </div>
        </div>`;
    }

    // ── VR / Stereo ───────────────────────────────────────────────────────────

    async toggleVR() {
        if (!this.viewer) return;
        await this.viewer.toggleVR();
        this.vrActive = this.viewer.vrActive;
    }

    // ── Gyroscope ─────────────────────────────────────────────────────────────

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
            const url = new URL(`${this.apiBase}/room-type/${wp.linked_room_type_id}/availability`, window.location.href);
            if (this._checkIn)    url.searchParams.set('check_in',  this._checkIn);
            if (this._checkOut)   url.searchParams.set('check_out', this._checkOut);
            if (this._guests > 1) url.searchParams.set('guests',    this._guests);
            const res  = await fetch(url);
            const data = await res.json();
            if (data.success) {
                this.currentRoomType = data.data;
                this._populateRoomInfoOverlay(data.data);
            }
        } catch (e) { console.error('_fetchRoomInfo:', e); }
    }

    _populateRoomInfoOverlay(rt) {
        const ov = this.overlay;
        if (!ov) return;
        const setText = (sel, val) => { const el = ov.querySelector(sel); if (el) el.textContent = val ?? ''; };

        setText('.room-name',        rt.name        || '');
        setText('.room-type-badge',  rt.room_sharing_type || '');
        setText('.room-description', rt.description || '');
        setText('.room-price',       rt.pricing_display || rt.formatted_price || '');

        const count = rt.available_rooms_count;
        const avail = ov.querySelector('.availability-badge');
        if (avail) {
            avail.textContent = count != null ? `${count} room(s) available` : '';
            avail.className = 'availability-badge mt-3 text-sm font-semibold '
                + (count > 0 ? 'text-green-300' : 'text-red-300');
        }

        const amenitiesEl = ov.querySelector('.room-amenities');
        if (amenitiesEl && rt.amenities) {
            amenitiesEl.innerHTML = rt.amenities
                .map(a => `<span class="inline-block bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full">${a.name}</span>`)
                .join('');
        }

        // Sync overlay date widget with engine state
        const overlayIn     = document.getElementById('overlay-check-in');
        const overlayOut    = document.getElementById('overlay-check-out');
        const overlayGuests = document.getElementById('overlay-guests');
        if (overlayIn  && this._checkIn)  overlayIn.value  = this._checkIn;
        if (overlayOut && this._checkOut) overlayOut.value = this._checkOut;
        if (overlayGuests) overlayGuests.value = this._guests;

        // Price estimate in overlay
        const overlayPriceEl = document.getElementById('overlay-price-estimate');
        if (overlayPriceEl) {
            const est = this._computePriceEstimateHtml(rt);
            if (est) { overlayPriceEl.innerHTML = est; overlayPriceEl.classList.remove('hidden'); }
            else     { overlayPriceEl.classList.add('hidden'); }
        }

        // Date-specific availability result in overlay
        const overlayAvailEl = document.getElementById('overlay-avail-result');
        if (overlayAvailEl) {
            if (count != null && this._checkIn && this._checkOut) {
                const bg  = count > 0 ? '#f0fdf4' : '#fef2f2';
                const bd  = count > 0 ? 'border-green-200' : 'border-red-200';
                const clr = count > 0 ? 'text-green-800' : 'text-red-800';
                const ico = count > 0 ? '✓' : '✗';
                overlayAvailEl.innerHTML = `<div style="background:${bg}" class="text-sm font-semibold p-2 rounded-lg border ${bd} ${clr}">${ico} ${count} room(s) available for your dates</div>`;
                overlayAvailEl.classList.remove('hidden');
            } else {
                overlayAvailEl.classList.add('hidden');
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
        if (this.reservationModal) this.reservationModal.style.display = 'flex';
        if (this.currentRoomType) {
            const sel = document.getElementById('preferred_room_type_id');
            if (sel) sel.value = this.currentRoomType.id || '';
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
        if (this.reservationModal) this.reservationModal.style.display = 'none';
    }

    async submitReservation(formData) {
        try {
            const res  = await fetch('/reserve', {
                method: 'POST',
                headers: {
                    'Content-Type':  'application/json',
                    'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(formData),
            });
            const data = await res.json();
            if (data.success) {
                this.closeReservationModal();
                this._showToast(data.message || 'Reservation submitted!', 'success');
                return true;
            }
            this._showToast(data.message || 'Submission failed.', 'error');
            return false;
        } catch (e) {
            this._showToast('Network error. Please try again.', 'error');
            return false;
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

    startAutoTour(intervalMs = 12000) {
        this._autoTourActive = true;
        this._syncAutoTourBtn(true);
        const advance = () => {
            if (!this._autoTourActive) return;
            const i = this.waypoints.findIndex(w => w.slug === this.currentWaypoint?.slug);
            const next = i < this.waypoints.length - 1
                ? this.waypoints[i + 1]
                : this.waypoints[0]; // loop back to start
            if (next) this.navigateToWaypoint(next.slug);
            this._autoTourTimer = setTimeout(advance, intervalMs);
        };
        this._autoTourTimer = setTimeout(advance, intervalMs);
        this._showToast('Auto Tour started — advancing every 12s. Press Esc to stop.', 'info');
    }

    stopAutoTour() {
        this._autoTourActive = false;
        clearTimeout(this._autoTourTimer);
        this._autoTourTimer = null;
        this._syncAutoTourBtn(false);
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
        const t = document.getElementById('auto-tour-btn-text');
        if (t) t.textContent = active ? '⏹ Stop Tour' : '▶ Auto Tour';
    }

    // ── Toast ─────────────────────────────────────────────────────────────────

    _showToast(msg, type = 'info') {
        const el = document.getElementById('toast-notification');
        if (!el) return;
        el.textContent = msg;
        el.className   = `toast toast-${type} visible`;
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => el.classList.remove('visible'), 3500);
    }
}

window.VirtualTourEngine = VirtualTourEngine;
