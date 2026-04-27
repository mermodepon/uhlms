/**
 * PanoramaViewer — Photo Sphere Viewer wrapper.
 *
 * Exposes the same API that tour-engine.js and tour-editor.js expect,
 * backed by @photo-sphere-viewer/core + plugins.
 */
import { Viewer } from '@photo-sphere-viewer/core';
import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
import { GyroscopePlugin } from '@photo-sphere-viewer/gyroscope-plugin';
import { Vector3 } from 'three';
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
        this._gyroDesired = false;
        this._gyroDirection = new Vector3();
        this._gyroStabilizer = {
            filteredYaw: null,
            filteredPitch: null,
            lastTimestamp: 0,
            smoothing: 0.30,
            yawDeadzone: 0.0016,
            pitchDeadzone: 0.0012,
            maxStepPerFrame: 0.18,
        };
        this._markerConfigs = new Map();  // id → config passed to addMarker

        const plugins = [
            [MarkersPlugin, { markers: [] }],
            [GyroscopePlugin, { touchmove: true, absolutePosition: false, roll: false, moveMode: 'smooth' }],
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
        
        this._installGyroscopeStabilizer();
        this._installGyroscopePersistence();

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
        const shouldRestoreGyroscope = this._gyroDesired;
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

        if (shouldRestoreGyroscope) {
            await this._restoreGyroscopeAfterPanoramaChange();
        }
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

    cancelPointerInteraction() {
        const buildEvent = (type) => {
            if (type.startsWith('pointer') && typeof PointerEvent !== 'undefined') {
                return new PointerEvent(type, { bubbles: true });
            }
            if (type.startsWith('mouse') && typeof MouseEvent !== 'undefined') {
                return new MouseEvent(type, { bubbles: true });
            }
            if (type.startsWith('touch') && typeof Event !== 'undefined') {
                return new Event(type, { bubbles: true, cancelable: true });
            }
            return typeof Event !== 'undefined' ? new Event(type, { bubbles: true }) : null;
        };

        const types = ['pointerup', 'pointercancel', 'mouseup', 'mouseleave', 'touchend'];

        for (const target of [this.container, document, window]) {
            for (const type of types) {
                const event = buildEvent(type);
                if (!event) continue;
                try {
                    target.dispatchEvent(event);
                } catch (_) {}
            }
        }
    }

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
                // Size scale: 1=0.6x, 2=0.8x, 3=1.0x (default), 4=1.25x, 5=1.5x
                const sizeScale = { 1: 0.6, 2: 0.8, 3: 1.0, 4: 1.25, 5: 1.5 };
                const scale = sizeScale[config.sprite.size] ?? 1.0;
                const scaledSize = Math.round(40 * scale);
                marker.size = { width: scaledSize, height: scaledSize };
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

        // Size scale: 1=0.6x, 2=0.8x, 3=1.0x (default), 4=1.25x, 5=1.5x
        const sizeScale = { 1: 0.6, 2: 0.8, 3: 1.0, 4: 1.25, 5: 1.5 };
        const scale = sizeScale[sprite.size] ?? 1.0;
        const scaledSize = 36 * scale;
        const scaledIconSize = 18 * scale;

        let border = sprite.dashed ? '2px dashed #fff' : '2px solid #fff';
        let cursor = sprite.noCursor ? 'default' : 'pointer';
        let animation = sprite.selected || sprite.dashed
            ? 'none'
            : 'pv-hotspot-pulse 2.2s ease-in-out infinite';
        let boxShadow = sprite.selected
            ? '0 0 0 3px #fff, 0 0 0 6px #3b82f6'
            : '';

        return `<div class="pv-hotspot-circle" style="width:${scaledSize}px;height:${scaledSize}px;border-radius:50%;background:${bg};opacity:${opacity};border:${border};${boxShadow ? 'box-shadow:' + boxShadow + ';' : ''}display:flex;align-items:center;justify-content:center;cursor:${cursor};filter:drop-shadow(0 2px 4px rgba(0,0,0,.4));animation:${animation};transition:transform 0.2s">${PanoramaViewer.iconSvg(icon, scaledIconSize, '#fff')}</div>`;
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
        const hasYouTubeVideo = !!sprite.mediaYouTubeId;
        const hasImageMedia = !!(sprite.mediaGallery?.length || sprite.mediaUrl);
        const hasExpandedMediaCard = hasYouTubeVideo || hasImageMedia;
        const cardWidth = hasYouTubeVideo
            ? 'min(560px,calc(100vw - 32px))'
            : (hasImageMedia ? 'min(520px,calc(100vw - 32px))' : 'min(380px,calc(100vw - 32px))');
        const videoMediaPaddingTop = hasYouTubeVideo ? '62.5%' : '56.25%';
        const cardMaxHeight = hasYouTubeVideo
            ? 'min(94vh,860px)'
            : (hasImageMedia ? 'min(92vh,820px)' : 'min(90vh,calc(100dvh - 24px))');

        // Media
        let mediaHtml = '';
        if (sprite.mediaYouTubeId) {
            const src = this._buildYouTubeEmbedUrl(sprite.mediaYouTubeId);
            mediaHtml = `<div style="position:relative;padding-top:${videoMediaPaddingTop};background:#000;overflow:hidden;flex-shrink:0">`
                + `<iframe src="${src}" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="autoplay;encrypted-media;fullscreen" allowfullscreen loading="lazy"></iframe>`
                + `</div>`;
        } else if (sprite.mediaGallery?.length > 0) {
            const imgs = sprite.mediaGallery.map(url =>
                `<div style="min-width:${hasExpandedMediaCard ? 'calc(100% - 8px)' : '220px'};scroll-snap-align:center;scroll-snap-stop:always;flex:0 0 auto">`
                + `<img src="${url}" style="width:100%;height:${hasExpandedMediaCard ? '240px' : '160px'};display:block;border-radius:10px;object-fit:cover;box-shadow:0 10px 24px rgba(17,24,39,.12)" onerror="this.parentElement.style.display='none'" loading="lazy">`
                + `</div>`
            ).join('');
            mediaHtml = `<div style="background:#f9fafb;padding:12px 14px 10px">`
                + `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;color:#6b7280;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase"><span>Image Gallery</span><span>Wheel or swipe</span></div>`
                + `<div data-gallery-track onwheel="event.stopPropagation();event.preventDefault();const delta=((event.deltaX||0)+(event.deltaY||0))*(event.deltaMode===1?16:1);this.scrollBy({left:delta,behavior:'auto'});return false;" style="display:flex;gap:10px;overflow-x:auto;overflow-y:hidden;padding:2px 0 6px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;scrollbar-width:thin">${imgs}</div>`
                + `</div>`;
        } else if (sprite.mediaUrl) {
            mediaHtml = `<div style="flex-shrink:0;overflow:hidden">`
                + `<img src="${sprite.mediaUrl}" style="width:100%;display:block;max-height:${hasExpandedMediaCard ? '360px' : '240px'};object-fit:cover" onerror="this.parentElement.style.display='none'" loading="lazy">`
                + `</div>`;
        }

        const buttons = sprite.buttons || '';
        const closeAction = sprite.closeAction || '';

        const amenitiesTags = tags.map(a =>
            `<span style="display:inline-block;background:#eef6f0;color:#00491E;border:1px solid #dce9df;font-size:11px;padding:3px 8px;border-radius:999px;margin:2px;font-weight:600">${a}</span>`
        ).join('');

        const closeStyle = 'position:absolute;top:10px;right:10px;background:rgba(255,255,255,.2);border:none;color:white;width:26px;height:26px;border-radius:50%;font-size:14px;line-height:1;display:flex;align-items:center;justify-content:center;text-align:center;padding:0';
        const closeBtn = closeAction
            ? `<button onclick="${closeAction};event.stopPropagation()" style="${closeStyle};cursor:pointer">X</button>`
            : `<div style="${closeStyle}">X</div>`;

        const interactionShield = `onclick="event.stopPropagation()" onmousedown="event.stopPropagation()" onpointerdown="event.stopPropagation()" onwheel="event.stopPropagation()" ontouchstart="event.stopPropagation()" ontouchmove="event.stopPropagation()"`;

        return `<div ${interactionShield} style="background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.6);width:${cardWidth};font-family:var(--guest-font-body);display:flex;flex-direction:column;overflow:hidden;max-height:${cardMaxHeight};pointer-events:auto;touch-action:pan-y">`
            + `<div style="background:linear-gradient(135deg,#00491E,#02681E);color:white;padding:14px 16px;position:relative;flex-shrink:0">`
            + closeBtn
            + `<h2 style="font-size:16px;font-weight:700;margin:0 32px 0 0">${title}</h2>`
            + (subtitle ? `<span style="display:inline-block;margin-top:4px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.22);font-size:11px;padding:2px 8px;border-radius:999px;color:#f6f7eb;font-weight:600">${subtitle}</span>` : '')
            + (badge ? `<div style="position:absolute;top:10px;right:42px;font-size:11px;font-weight:700;color:${badgeClr};background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);padding:3px 8px;border-radius:999px;backdrop-filter:blur(2px)">${badge}</div>` : '')
            + `</div>`
            + `<div style="flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y">`
            + mediaHtml
            + (body ? `<div style="padding:${hasExpandedMediaCard ? '12px 14px 10px' : '14px'};font-size:13px;color:#374151;line-height:${hasExpandedMediaCard ? '1.55' : '1.6'}">${body}</div>` : '')
            + (price ? `<div style="padding:0 14px 10px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#6b7280;letter-spacing:.05em;margin-bottom:2px">Price</div><div style="font-size:19px;font-weight:700;color:#d97706">${price}</div></div>` : '')
            + (amenitiesTags ? `<div style="padding:0 14px 14px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#6b7280;letter-spacing:.05em;margin-bottom:4px">Amenities</div>${amenitiesTags}</div>` : '')
            + (buttons ? `<div style="padding:0 14px 14px">${buttons}</div>` : '')
            + `</div>`
            + `</div>`;
    }

    _buildYouTubeEmbedUrl(videoId, options = {}) {
        if (!videoId) return '';

        const params = new URLSearchParams({
            rel: '0',
            playsinline: '1',
            modestbranding: '1',
            fs: '1',
        });

        if (options.autoplay) {
            params.set('autoplay', '1');
        }

        if (window.location?.origin) {
            params.set('origin', window.location.origin);
        }

        return `https://www.youtube.com/embed/${videoId}?${params.toString()}`;
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
    // ── Gyroscope ────────────────────────────────────────────────────────────

    async toggleGyroscope() {
        if (!this._gyro) {
            console.error('Gyroscope plugin not available');
            throw new Error('Gyroscope plugin not initialized');
        }

        const shouldEnable = !(this._gyroDesired || this._gyro.isEnabled());
        await this.setGyroscopeEnabled(shouldEnable);
    }

    async setGyroscopeEnabled(enabled, options = {}) {
        if (!this._gyro) {
            throw new Error('Gyroscope plugin not initialized');
        }

        const { requestPermission = true } = options;
        this._gyroDesired = Boolean(enabled);

        try {
            if (this._gyroDesired) {
                await this._startGyroscope({ requestPermission });
            } else if (this._gyro.isEnabled()) {
                this._gyro.stop();
            }
        } catch (e) {
            console.error('Gyroscope toggle failed:', e.message);
            throw e;
        }

        this._emit('gyroscope-updated', {
            enabled: this.isGyroscopeEnabled(),
            desired: this._gyroDesired,
        });
    }

    async _startGyroscope(options = {}) {
        if (!this._gyro || this._gyro.isEnabled()) {
            return;
        }

        const { requestPermission = true } = options;

        if (
            requestPermission
            && typeof DeviceOrientationEvent !== 'undefined'
            && typeof DeviceOrientationEvent.requestPermission === 'function'
        ) {
            const permission = await DeviceOrientationEvent.requestPermission();
            if (permission !== 'granted') {
                throw new Error('Device orientation permission denied');
            }
        }

        this._resetGyroscopeStabilizer();
        await this._gyro.start();
    }

    async _restoreGyroscopeAfterPanoramaChange() {
        if (!this._gyroDesired) {
            return;
        }

        await new Promise((resolve) => {
            const raf = window.requestAnimationFrame || ((cb) => window.setTimeout(cb, 16));
            raf(resolve);
        });

        try {
            await this._startGyroscope({ requestPermission: false });
        } catch (error) {
            console.warn('Gyroscope restore after scene change failed:', error);
        }

        this._emit('gyroscope-updated', {
            enabled: this.isGyroscopeEnabled(),
            desired: this._gyroDesired,
        });
    }

    isGyroscopeEnabled() {
        return this._gyro ? this._gyro.isEnabled() : false;
    }

    _installGyroscopeStabilizer() {
        if (!this._gyro || typeof this._gyro.__onBeforeRender !== 'function') {
            return;
        }

        this._gyro.addEventListener?.('gyroscope-updated', () => {
            this._resetGyroscopeStabilizer();
        });

        this._gyro.__onBeforeRender = () => {
            if (!this._gyro.isEnabled()) {
                return;
            }

            const controls = this._gyro.controls;
            const state = this._gyro.state;
            if (!controls?.deviceOrientation) {
                return;
            }

            const position = this._psv.getPosition();

            if (state.alphaOffset === null) {
                if (controls.update()) {
                    controls.object.getWorldDirection(this._gyroDirection);
                    const sphericalCoords = this._psv.dataHelper.vector3ToSphericalCoords(this._gyroDirection);
                    state.alphaOffset = sphericalCoords.yaw - position.yaw;
                    this._resetGyroscopeStabilizer();
                }

                return;
            }

            controls.alphaOffset = state.alphaOffset;
            if (!controls.update()) {
                return;
            }

            controls.object.getWorldDirection(this._gyroDirection);
            const sphericalCoords = this._psv.dataHelper.vector3ToSphericalCoords(this._gyroDirection);
            const target = this._getStabilizedGyroscopeTarget({
                yaw: sphericalCoords.yaw,
                pitch: -sphericalCoords.pitch,
            });

            const angle = PanoramaViewer._greatCircleAngle(position, target);
            this._psv.dynamics.position.goto(target, angle < 0.008 ? 0.45 : 1.2);
        };
    }

    _resetGyroscopeStabilizer() {
        this._gyroStabilizer.filteredYaw = null;
        this._gyroStabilizer.filteredPitch = null;
        this._gyroStabilizer.lastTimestamp = 0;
    }

    _installGyroscopePersistence() {
        if (!this._gyro) return;

        let touchActive = false;
        let restartTimer = null;

        // Track touch state
        const handleTouchStart = () => {
            touchActive = true;
            if (restartTimer) {
                clearTimeout(restartTimer);
                restartTimer = null;
            }
        };

        const handleTouchEnd = () => {
            touchActive = false;
            
            // Restart gyroscope after touch ends if user wants it enabled
            if (this._gyroDesired && !this._gyro.isEnabled()) {
                // Small delay to ensure PSV has finished its touch handling
                restartTimer = setTimeout(async () => {
                    if (this._gyroDesired && !this._gyro.isEnabled() && !touchActive) {
                        try {
                            await this._startGyroscope({ requestPermission: false });
                        } catch (e) {
                            console.warn('Auto-restart gyroscope failed:', e.message);
                        }
                    }
                    restartTimer = null;
                }, 300);
            }
        };

        this.container.addEventListener('touchstart', handleTouchStart);
        this.container.addEventListener('touchend', handleTouchEnd);
        this.container.addEventListener('touchcancel', handleTouchEnd);
        
        // Also track pointer events for broader compatibility
        this.container.addEventListener('pointerdown', handleTouchStart);
        this.container.addEventListener('pointerup', handleTouchEnd);
        this.container.addEventListener('pointercancel', handleTouchEnd);
    }

    _getStabilizedGyroscopeTarget(target) {
        const now = (typeof performance !== 'undefined' && typeof performance.now === 'function')
            ? performance.now()
            : Date.now();
        const state = this._gyroStabilizer;

        if (state.filteredYaw == null || state.filteredPitch == null) {
            state.filteredYaw = target.yaw;
            state.filteredPitch = target.pitch;
            state.lastTimestamp = now;

            return { ...target };
        }

        const elapsed = Math.max(8, Math.min(48, now - state.lastTimestamp || 16));
        const frameScale = elapsed / 16;
        const response = 1 - Math.pow(1 - state.smoothing, frameScale);
        const maxStep = state.maxStepPerFrame * frameScale;

        let yawDelta = PanoramaViewer._normalizeAngle(target.yaw - state.filteredYaw);
        let pitchDelta = target.pitch - state.filteredPitch;

        if (Math.abs(yawDelta) < state.yawDeadzone) {
            yawDelta = 0;
        }

        if (Math.abs(pitchDelta) < state.pitchDeadzone) {
            pitchDelta = 0;
        }

        yawDelta = PanoramaViewer._clamp(yawDelta, -maxStep, maxStep);
        pitchDelta = PanoramaViewer._clamp(pitchDelta, -maxStep, maxStep);

        state.filteredYaw = PanoramaViewer._normalizeAngle(state.filteredYaw + (yawDelta * response));
        state.filteredPitch = PanoramaViewer._clamp(state.filteredPitch + (pitchDelta * response), -Math.PI / 2, Math.PI / 2);
        state.lastTimestamp = now;

        return {
            yaw: state.filteredYaw,
            pitch: state.filteredPitch,
        };
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

    static _clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    static _normalizeAngle(angle) {
        let normalized = angle;

        while (normalized <= -Math.PI) normalized += Math.PI * 2;
        while (normalized > Math.PI) normalized -= Math.PI * 2;

        return normalized;
    }

    static _greatCircleAngle(position1, position2) {
        return Math.acos(
            Math.cos(position1.pitch) * Math.cos(position2.pitch) * Math.cos(position1.yaw - position2.yaw)
            + Math.sin(position1.pitch) * Math.sin(position2.pitch)
        );
    }
}

export { PanoramaViewer };
