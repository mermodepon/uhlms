/**
 * PanoramaViewer — Photo Sphere Viewer wrapper.
 *
 * Exposes the same API that tour-engine.js and tour-editor.js expect,
 * backed by @photo-sphere-viewer/core + plugins.
 */
import { Viewer } from '@photo-sphere-viewer/core';
import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
import { GyroscopePlugin } from '@photo-sphere-viewer/gyroscope-plugin';
import { StereoPlugin } from '@photo-sphere-viewer/stereo-plugin';
import '@photo-sphere-viewer/core/index.css';
import '@photo-sphere-viewer/markers-plugin/index.css';

const DEG2RAD = Math.PI / 180;
const RAD2DEG = 180 / Math.PI;

// PSV zoom is 0-100 where 100 = fully zoomed in.  Map our 0-100 linearly.
// PSV default FOV ≈ 65°;  we match that with defaultZoomLvl = 50.

class PanoramaViewer {

    constructor(options = {}) {
        this.container = options.container;
        if (!this.container) throw new Error('PanoramaViewer: container is required');

        this._eventListeners = {};
        this._vrActive = false;
        this._markerConfigs = new Map();  // id → config passed to addMarker

        const plugins = [
            [MarkersPlugin, { markers: [] }],
            [GyroscopePlugin, { touchmove: false, absolutePosition: false }],
            [StereoPlugin],
        ];

        const defaultYaw   = (options.defaultYaw   || 0);
        const defaultPitch = (options.defaultPitch || 0);
        const defaultZoom  = options.defaultZoomLvl ?? 50;

        const viewerOpts = {
            container: this.container,
            defaultYaw:   `${defaultYaw}deg`,
            defaultPitch: `${defaultPitch}deg`,
            defaultZoomLvl: defaultZoom,
            navbar: false,
            touchmoveTwoFingers: false,
            mousewheelCtrlKey: false,
            plugins,
        };

        if (options.panorama) {
            viewerOpts.panorama = options.panorama;
        }

        // Inject marker keyframe animations (idempotent)
        PanoramaViewer._injectStyles();

        this._psv = new Viewer(viewerOpts);
        this._markers = this._psv.getPlugin(MarkersPlugin);
        this._gyro    = this._psv.getPlugin(GyroscopePlugin);
        this._stereo  = this._psv.getPlugin(StereoPlugin);

        // Wire PSV events → our event bus
        this._psv.addEventListener('ready', () => {
            this._emit('ready');
        });

        this._psv.addEventListener('click', (e) => {
            this._emit('click', {
                data: { yaw: e.data.yaw, pitch: e.data.pitch },
            });
        });

        this._psv.addEventListener('zoom-updated', (e) => {
            this._emit('zoom-updated', { zoomLevel: e.zoomLevel });
        });

        this._psv.addEventListener('position-updated', (e) => {
            this._emit('position-updated', {
                position: {
                    yaw: e.position.yaw,
                    pitch: e.position.pitch,
                },
            });
        });

        this._markers.addEventListener('select-marker', (e) => {
            const id = e.marker.id;
            const cfg = this._markerConfigs.get(id);
            this._emit('select-marker', {
                marker: {
                    config: cfg || { id, data: e.marker.data },
                },
            });
        });

    }

    // ── Public API ───────────────────────────────────────────────────────────

    onReady(fn) {
        if (this._psv.isReady) { fn(); return; }
        this._psv.addEventListener('ready', fn, { once: true });
    }

    async setPanorama(url, options = {}) {
        if (!url) return;
        const pos = options.position || {};
        const zoomLvl = options.zoom;

        const psvOpts = {};
        if (pos.yaw != null || pos.pitch != null) {
            psvOpts.position = {};
            if (pos.yaw != null) psvOpts.position.yaw = pos.yaw;
            if (pos.pitch != null) psvOpts.position.pitch = pos.pitch;
        }
        if (zoomLvl != null) psvOpts.zoom = zoomLvl;

        const transition = options.transition || {};
        if (transition.effect === 'fade' || transition.effect === 'black') {
            psvOpts.transition = {
                speed: transition.duration || 400,
                rotation: false,
                effect: 'fade',
            };
        } else {
            psvOpts.transition = false;
        }

        psvOpts.showLoader = false;

        await this._psv.setPanorama(url, psvOpts);
    }

    getPosition() {
        const pos = this._psv.getPosition();
        return { yaw: pos.yaw, pitch: pos.pitch };
    }

    rotate(pos) {
        const opts = {};
        if (pos.yaw != null) opts.yaw = pos.yaw;
        if (pos.pitch != null) opts.pitch = pos.pitch;
        this._psv.rotate(opts);
    }

    getZoomLevel() {
        return this._psv.getZoomLevel();
    }

    zoom(level) {
        const c = Math.max(0, Math.min(100, level));
        this._psv.zoom(c);
    }

    zoomIn()  { this.zoom(this.getZoomLevel() + 5); }
    zoomOut() { this.zoom(this.getZoomLevel() - 5); }

    // ── Markers ──────────────────────────────────────────────────────────────

    /**
     * Add a marker.
     *
     * Accepts config with:
     *   { id, position: { yaw, pitch }, html, tooltip, data, sprite? }
     *
     * If config.html is provided, it's used directly as an HTML marker.
     * If config.sprite is provided, we generate HTML from the sprite descriptor.
     * The `sprite` property is our Three.js-era abstraction — we convert it
     * back to HTML/CSS for PSV.
     */
    addMarker(config) {
        // Remove existing marker with same id silently
        try { this._markers.removeMarker(config.id); } catch (_) {}

        const marker = {
            id: config.id,
            position: config.position,
            data: config.data || {},
            anchor: 'center center',
        };

        // Tooltip
        if (config.tooltip) {
            marker.tooltip = config.tooltip;
        }

        // Determine HTML content + size
        if (config.html) {
            marker.html = config.html;
        } else if (config.sprite) {
            const style = config.sprite.style || 'circle';
            marker.html = this._spriteToHtml(config.sprite, config);
            if (style === 'circle') {
                marker.size = { width: 40, height: 40 };
            }
        } else {
            marker.html = '<div style="width:20px;height:20px;background:#ccc;border-radius:50%"></div>';
            marker.size = { width: 20, height: 20 };
        }

        this._markers.addMarker(marker);
        this._markerConfigs.set(config.id, { ...config });
    }

    /**
     * Convert a sprite descriptor (Three.js era) to HTML for PSV.
     */
    _spriteToHtml(sprite, config) {
        const style = sprite.style || 'circle';

        if (style === 'card') {
            return this._cardSpriteToHtml(sprite);
        }

        if (style === 'badge') {
            return this._badgeSpriteToHtml(sprite);
        }

        // Circle hotspot — all styles inlined so it works on any page
        const bg = sprite.bgColor || '#6b7280';
        const opacity = sprite.opacity ?? 1;
        const icon = sprite.icon || 'chevron-up';
        const svg = PanoramaViewer.iconSvg(icon, 18, '#fff');

        let border = sprite.dashed ? '2px dashed #fff' : '2px solid #fff';
        let cursor = sprite.noCursor ? 'default' : 'pointer';
        let animation = sprite.selected || sprite.dashed
            ? 'none'
            : 'pv-hotspot-pulse 2.2s ease-in-out infinite';
        let boxShadow = sprite.selected
            ? '0 0 0 3px #fff, 0 0 0 6px #3b82f6'
            : '';

        return `<div class="pv-hotspot-circle" style="width:36px;height:36px;border-radius:50%;background:${bg};opacity:${opacity};border:${border};${boxShadow ? 'box-shadow:' + boxShadow + ';' : ''}display:flex;align-items:center;justify-content:center;cursor:${cursor};filter:drop-shadow(0 2px 4px rgba(0,0,0,.4));animation:${animation};transition:transform 0.2s">${svg}</div>`;
    }

    _badgeSpriteToHtml(sprite) {
        const icon  = sprite.icon || '🏠';
        const label = sprite.label || '';
        const dashed = sprite.dashed;
        const opacity = sprite.opacity ?? 1;

        let borderStyle = dashed ? 'dashed' : 'solid';
        let cursor = sprite.noCursor ? 'default' : 'pointer';
        let opacityStyle = opacity < 1 ? `opacity:${opacity};` : '';
        let hiddenStyle  = opacity < 0.05 ? 'width:1px;height:1px;overflow:hidden;pointer-events:none;' : '';

        return `<div class="pv-badge-marker" style="display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#00491E 0%,#02681E 100%);color:#FFC600;font-size:13px;font-weight:700;padding:8px 14px;border-radius:999px;border:2px ${borderStyle} #FFC600;cursor:${cursor};white-space:nowrap;animation:pv-badge-pulse 2.4s ease-in-out infinite;transition:transform 0.2s;${opacityStyle}${hiddenStyle}"><span>${icon}</span> ${label}</div>`;
    }

    _cardSpriteToHtml(sprite) {
        // Info card or room-info card — build a styled HTML card
        const title    = sprite.title || '';
        const subtitle = sprite.subtitle || '';
        const body     = sprite.body || '';
        const price    = sprite.price || '';
        const tags     = Array.isArray(sprite.tags) ? sprite.tags : [];
        const badge    = sprite.headerBadge || '';
        const badgeClr = sprite.headerBadgeColor || '#86efac';

        // Media
        let mediaHtml = '';
        if (sprite.mediaYouTubeId) {
            const src = `https://www.youtube-nocookie.com/embed/${sprite.mediaYouTubeId}?rel=0`;
            mediaHtml = `<div style="position:relative;padding-top:56.25%;background:#000;overflow:hidden;flex-shrink:0">`
                + `<iframe src="${src}" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="autoplay;encrypted-media;fullscreen" allowfullscreen loading="lazy"></iframe>`
                + `</div>`;
        } else if (sprite.mediaGallery?.length > 0) {
            const imgs = sprite.mediaGallery.map(url =>
                `<img src="${url}" style="height:160px;width:auto;flex-shrink:0;display:block;border-radius:6px;object-fit:cover" onerror="this.style.display='none'" loading="lazy">`
            ).join('');
            mediaHtml = `<div style="display:flex;gap:8px;overflow-x:auto;padding:10px 14px;background:#f9fafb;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch">${imgs}</div>`;
        } else if (sprite.mediaUrl) {
            mediaHtml = `<div style="flex-shrink:0;overflow:hidden">`
                + `<img src="${sprite.mediaUrl}" style="width:100%;display:block;max-height:240px;object-fit:cover" onerror="this.parentElement.style.display='none'" loading="lazy">`
                + `</div>`;
        }

        const buttons = sprite.buttons || '';
        const closeAction = sprite.closeAction || '';

        const amenitiesTags = tags.map(a =>
            `<span style="display:inline-block;background:#f3f4f6;color:#374151;font-size:11px;padding:3px 8px;border-radius:999px;margin:2px">${a}</span>`
        ).join('');

        const closeBtn = closeAction
            ? `<button onclick="${closeAction};event.stopPropagation()" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,.2);border:none;color:white;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:14px;line-height:26px;text-align:center">✕</button>`
            : `<div style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,.2);color:white;width:26px;height:26px;border-radius:50%;font-size:14px;line-height:26px;text-align:center">✕</div>`;

        return `<div style="background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.6);width:360px;font-family:sans-serif;display:flex;flex-direction:column;overflow:hidden;max-height:90vh;pointer-events:auto">`
            + `<div style="background:linear-gradient(135deg,#00491E,#02681E);color:white;padding:14px 16px;position:relative;flex-shrink:0">`
            + closeBtn
            + `<h2 style="font-size:16px;font-weight:700;margin:0 32px 0 0">${title}</h2>`
            + (subtitle ? `<span style="display:inline-block;margin-top:4px;background:rgba(255,255,255,.2);font-size:11px;padding:2px 8px;border-radius:999px">${subtitle}</span>` : '')
            + (badge ? `<div style="position:absolute;top:10px;right:42px;font-size:11px;font-weight:700;color:${badgeClr}">${badge}</div>` : '')
            + `</div>`
            + mediaHtml
            + (body ? `<div style="padding:14px;font-size:13px;color:#374151;line-height:1.6">${body}</div>` : '')
            + (price ? `<div style="padding:0 14px 10px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-bottom:2px">Price</div><div style="font-size:19px;font-weight:700;color:#d97706">${price}</div></div>` : '')
            + (amenitiesTags ? `<div style="padding:0 14px 14px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-bottom:4px">Amenities</div>${amenitiesTags}</div>` : '')
            + (buttons ? `<div style="padding:0 14px 14px">${buttons}</div>` : '')
            + `</div>`;
    }

    updateMarker(updates) {
        const id = updates.id;
        const existing = this._markerConfigs.get(id);
        if (!existing) throw new Error(`Marker "${id}" not found`);

        if (updates.position && !updates.html && !updates.sprite) {
            // Position-only update
            this._markers.updateMarker({
                id,
                position: updates.position,
            });
            existing.position = updates.position;
            return;
        }

        // Full rebuild
        const merged = { ...existing, ...updates };
        this.removeMarker(id);
        this.addMarker(merged);
    }

    removeMarker(id) {
        this._markers.removeMarker(id);
        this._markerConfigs.delete(id);
    }

    getMarker(id) {
        const cfg = this._markerConfigs.get(id);
        if (!cfg) throw new Error(`Marker "${id}" not found`);
        const m = this._markers.getMarker(id);
        return {
            config: {
                ...cfg,
                position: { yaw: `${m.config.position.yaw}`, pitch: `${m.config.position.pitch}` },
            },
        };
    }

    clearMarkers() {
        this._markers.clearMarkers();
        this._markerConfigs.clear();
    }

    // ── VR / Stereo ──────────────────────────────────────────────────────────

    get vrActive() { return this._vrActive; }

    async toggleVR() {
        try {
            if (this._vrActive) {
                this._stereo.stop();
                this._vrActive = false;
            } else {
                await this._stereo.start();
                this._vrActive = true;
            }
        } catch (e) {
            console.warn('Stereo/cardboard VR not available:', e);
            this._vrActive = false;
        }
        this._emit('vr-changed', { active: this._vrActive });
    }

    async toggleStereo() { return this.toggleVR(); }
    get stereoEnabled()  { return this._vrActive; }

    // ── Gyroscope ────────────────────────────────────────────────────────────

    async toggleGyroscope() {
        if (!this._gyro) {
            console.error('Gyroscope plugin not available');
            throw new Error('Gyroscope plugin not initialized');
        }
        
        console.log('Toggle gyroscope - currently enabled:', this._gyro.isEnabled());
        
        try {
            if (this._gyro.isEnabled()) {
                console.log('Stopping gyroscope');
                this._gyro.stop();
            } else {
                console.log('Starting gyroscope');
                
                // iOS 13+ requires explicit permission request
                if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                    console.log('Requesting device orientation permission (iOS)');
                    const permission = await DeviceOrientationEvent.requestPermission();
                    console.log('Permission result:', permission);
                    if (permission !== 'granted') {
                        throw new Error('Device orientation permission denied');
                    }
                }
                
                await this._gyro.start();
                console.log('Gyroscope started successfully');
            }
        } catch (e) {
            console.error('Gyroscope toggle failed:', e.message);
            throw e;
        }
    }

    isGyroscopeEnabled() {
        return this._gyro ? this._gyro.isEnabled() : false;
    }

    // ── Events ───────────────────────────────────────────────────────────────

    addEventListener(event, cb)    { (this._eventListeners[event] ??= []).push(cb); }
    removeEventListener(event, cb) {
        const l = this._eventListeners[event]; if (!l) return;
        const i = l.indexOf(cb); if (i >= 0) l.splice(i, 1);
    }

    _emit(event, data = {}) {
        const l = this._eventListeners[event];
        if (l) l.forEach(fn => fn(data));
    }

    // ── Cleanup ──────────────────────────────────────────────────────────────

    destroy() {
        this.clearMarkers();
        this._psv.destroy();
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    static _injectStyles() {
        if (PanoramaViewer._stylesInjected) return;
        PanoramaViewer._stylesInjected = true;
        const sheet = document.createElement('style');
        sheet.textContent = `
            @keyframes pv-hotspot-pulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.5); }
                50%      { box-shadow: 0 0 0 8px rgba(255,255,255,0); }
            }
            @keyframes pv-badge-pulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(255,198,0,0.5), 0 4px 12px rgba(0,0,0,0.5); }
                50%      { box-shadow: 0 0 0 10px rgba(255,198,0,0), 0 4px 12px rgba(0,0,0,0.5); }
            }
            .pv-hotspot-circle:hover { transform: scale(1.2); animation: none !important; }
            .pv-badge-marker:hover { transform: scale(1.1); }
        `;
        document.head.appendChild(sheet);
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
            'bookmark':          `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>`,
        };
        return icons[id] || icons['chevron-up'];
    }
}

export { PanoramaViewer };
