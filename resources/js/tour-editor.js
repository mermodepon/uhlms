/**
 * Virtual Tour Editor — Admin editor for managing panorama scenes & hotspots.
 * Used in the Filament ManageTourHotspots page.
 */
import { PanoramaViewer } from './panorama-viewer.js';

class TourEditor {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.getElementById(container) : container;
        this.viewer = null;
        this.waypoint = options.waypoint || null;
        this.hotspots = options.hotspots || [];
        this.allWaypoints = options.allWaypoints || [];
        this.wire = options.wire || null;

        // State
        this.placing = false;  // false | 'new' | 'move'
        this.placingIcon = 'chevron-up';
        this.selectedHotspotId = null;
        this._placingRoomInfo = false;
        this._repositioningHotspotId = null;
        this._eatNextClick = false;

        // Callbacks
        this.onHotspotPlaced = options.onHotspotPlaced || (() => {});
        this.onHotspotDropped = options.onHotspotDropped || (() => {});
        this.onHotspotMoved   = options.onHotspotMoved   || (() => {});
        this.onHotspotSelected = options.onHotspotSelected || (() => {});
        this.onHotspotDeselected = options.onHotspotDeselected || (() => {});
        this.onRoomInfoPlaced = options.onRoomInfoPlaced || (() => {});
        this.onReady = options.onReady || (() => {})
    }

    init() {
        if (!this.container || !this.waypoint?.panorama_url) {
            console.error('TourEditor: missing container or panorama URL');
            return;
        }

        this.viewer = new PanoramaViewer({
            container: this.container,
            panorama: this.waypoint.panorama_url,
            defaultYaw: this.waypoint.default_yaw || 0,
            defaultPitch: this.waypoint.default_pitch || 0,
            defaultZoomLvl: this.waypoint.default_zoom ?? 50,
        });

        // Build initial markers once viewer is ready
        this.viewer.addEventListener('ready', () => {
            this._buildMarkers().forEach(m => this.viewer.addMarker(m));

            // Apply zoom after ready
            const targetZoom = this.waypoint.default_zoom ?? 50;
            console.log('[TourEditor] ready — waypoint.default_zoom:', this.waypoint.default_zoom, '→ targetZoom:', targetZoom);
            console.log('[TourEditor] ready — getZoomLevel() before zoom():', this.viewer.getZoomLevel());
            this.viewer.zoom(targetZoom);
            console.log('[TourEditor] ready — getZoomLevel() after zoom():', this.viewer.getZoomLevel());
            this.onReady();
        });

        // Click on panorama → place or move hotspot if in placing mode
        this.viewer.addEventListener('click', (e) => {
            const { yaw, pitch } = e.data;
            const yawDeg   = parseFloat((yaw   * 180 / Math.PI).toFixed(4));
            const pitchDeg = parseFloat((pitch * 180 / Math.PI).toFixed(4));

            // Room Info reposition mode
            if (this._placingRoomInfo) {
                // Update preview marker position
                this._previewPos = { yaw: yawDeg, pitch: pitchDeg };
                try {
                    this.viewer.updateMarker({
                        id: 'room-info-reposition-preview',
                        position: { yaw: `${yawDeg}deg`, pitch: `${pitchDeg}deg` },
                    });
                } catch (err) {
                    // First click - create preview marker
                    this.viewer.addMarker({
                        id: 'room-info-reposition-preview',
                        position: { yaw: `${yawDeg}deg`, pitch: `${pitchDeg}deg` },
                        sprite: this._roomInfoPreviewSpriteOpts(),
                    });
                }
                this.onRoomInfoPlaced({ yaw: yawDeg, pitch: pitchDeg });
                return;
            }

            // Hotspot reposition mode
            if (this._repositioningHotspotId) {
                // Update preview marker position
                this._previewPos = { yaw: yawDeg, pitch: pitchDeg };
                try {
                    this.viewer.updateMarker({
                        id: 'hotspot-reposition-preview',
                        position: { yaw: `${yawDeg}deg`, pitch: `${pitchDeg}deg` },
                    });
                } catch (err) {
                    // First click - create preview marker
                    this.viewer.addMarker({
                        id: 'hotspot-reposition-preview',
                        position: { yaw: `${yawDeg}deg`, pitch: `${pitchDeg}deg` },
                        sprite: this._repositionPreviewSpriteOpts(),
                    });
                }
                return;
            }

            // Safety: eat the click that immediately follows a select-marker on preview
            if (this._eatNextClick) { this._eatNextClick = false; return; }

            if (this.placing === 'new') {
                // First click — drop the preview marker and stay in 'move' mode
                this.placing = 'move';
                this._previewPos = { yaw: yawDeg, pitch: pitchDeg };
                this._removePreviewMarker();
                this.viewer.addMarker({
                    id: 'hotspot-preview',
                    position: { yaw: `${yawDeg}deg`, pitch: `${pitchDeg}deg` },
                    sprite: this._previewSpriteOpts(),
                });
                this.onHotspotDropped({ yaw: yawDeg, pitch: pitchDeg, icon: this.placingIcon });
                return;
            }

            if (this.placing === 'move') {
                // Subsequent clicks — just reposition the preview
                this._previewPos = { yaw: yawDeg, pitch: pitchDeg };
                try {
                    this.viewer.updateMarker({
                        id: 'hotspot-preview',
                        position: { yaw: `${yawDeg}deg`, pitch: `${pitchDeg}deg` },
                    });
                } catch (err) {}
                this.onHotspotMoved({ yaw: yawDeg, pitch: pitchDeg });
                return;
            }
        });

        // Click on marker → select it (ignore read-only system markers)
        this.viewer.addEventListener('select-marker', (e) => {
            if (this.placing) {
                // Clicking the preview marker itself while in move mode — eat the
                // next viewer click so it doesn't double-fire a reposition.
                if (e.marker.config.id === 'hotspot-preview') this._eatNextClick = true;
                return;
            }
            // Preview marker persists after confirmation — don't treat it as a real hotspot
            if (e.marker.config.id === 'hotspot-preview') return;
            if (e.marker.config.data?.isSystem) return;
            const markerId = e.marker.config.id;
            const numericId = parseInt(markerId.replace('hotspot-', ''), 10);
            this.selectedHotspotId = numericId;
            this._highlightMarker(numericId);
            const hotspot = this.hotspots.find(h => h.id === numericId);
            if (hotspot) {
                this.onHotspotSelected(hotspot);
            }
        });
    }

    /**
     * Load a different panorama (switch scenes).
     */
    switchScene(waypoint, hotspots) {
        this.waypoint = waypoint;
        this.hotspots = hotspots;
        this.selectedHotspotId = null;
        this.onHotspotDeselected();

        console.log('[TourEditor] switchScene — zoom from waypoint:', waypoint.default_zoom);
        this.viewer.setPanorama(waypoint.panorama_url, {
            transition: {
                effect: 'black',
            },
            position: {
                yaw: `${waypoint.default_yaw || 0}deg`,
                pitch: `${waypoint.default_pitch || 0}deg`,
            },
            zoom: waypoint.default_zoom ?? 50,
        }).then(() => {
            console.log('[TourEditor] switchScene done — getZoomLevel():', this.viewer.getZoomLevel());
            this.viewer.clearMarkers();
            this._buildMarkers().forEach(m => this.viewer.addMarker(m));
        });
    }

    /**
     * Start placement mode.
     */
    startPlacement(icon = 'chevron-up') {
        this.placing = 'new';
        this.placingIcon = icon;
        this.container.style.cursor = 'crosshair';
    }

    /**
     * Cancel placement mode.
     */
    cancelPlacement() {
        this.placing = false;
        this._previewPos = null;
        this.container.style.cursor = '';
        this._removePreviewMarker();
    }

    /**
     * Enter Room Info reposition mode — next click sets room_info_yaw/pitch.
     */
    startRoomInfoPlacement() {
        this._placingRoomInfo = true;
        this.placing = false; // make sure hotspot placement is off
        this._repositioningHotspotId = null;
        this._previewPos = null;
        this._removePreviewMarker();
        this.container.style.cursor = 'crosshair';
        
        // Hide the original Room Info system marker temporarily
        try {
            this.viewer.removeMarker('room-info-system');
        } catch (e) {}
    }

    /**
     * Cancel Room Info reposition mode.
     */
    cancelRoomInfoPlacement() {
        this._placingRoomInfo = false;
        this._previewPos = null;
        this.container.style.cursor = '';
        
        // Remove preview marker
        try {
            this.viewer.removeMarker('room-info-reposition-preview');
        } catch (e) {}
        
        // Restore original markers
        this.viewer.clearMarkers();
        this._buildMarkers().forEach(m => this.viewer.addMarker(m));
    }

    /**
     * Confirm Room Info repositioning and return new position.
     */
    confirmRoomInfoPlacement() {
        if (!this._previewPos || !this._placingRoomInfo) return null;
        
        const newPos = { ...this._previewPos };
        this._placingRoomInfo = false;
        this._previewPos = null;
        this.container.style.cursor = '';
        
        // Remove preview marker
        try {
            this.viewer.removeMarker('room-info-reposition-preview');
        } catch (e) {}
        
        return newPos;
    }

    /**
     * Start hotspot repositioning mode.
     */
    startHotspotRepositioning(hotspotId) {
        this._repositioningHotspotId = hotspotId;
        this.placing = false;
        this._placingRoomInfo = false;
        this._previewPos = null;
        this.container.style.cursor = 'crosshair';
        
        // Hide the original hotspot marker temporarily
        const markerId = `hotspot-${hotspotId}`;
        try {
            this.viewer.removeMarker(markerId);
        } catch (e) {}
    }

    /**
     * Cancel hotspot repositioning mode.
     */
    cancelHotspotRepositioning() {
        this._repositioningHotspotId = null;
        this._previewPos = null;
        this.container.style.cursor = '';
        
        // Remove preview marker
        try {
            this.viewer.removeMarker('hotspot-reposition-preview');
        } catch (e) {}
        
        // Restore original markers
        this.viewer.clearMarkers();
        this._buildMarkers().forEach(m => this.viewer.addMarker(m));
    }

    /**
     * Confirm hotspot repositioning and return new position.
     */
    confirmHotspotRepositioning() {
        if (!this._previewPos || !this._repositioningHotspotId) return null;
        
        const newPos = { ...this._previewPos };
        this._repositioningHotspotId = null;
        this._previewPos = null;
        this.container.style.cursor = '';
        
        // Remove preview marker
        try {
            this.viewer.removeMarker('hotspot-reposition-preview');
        } catch (e) {}
        
        return newPos;
    }

    /**
     * Remove the temporary preview marker.
     */
    _removePreviewMarker() {
        try {
            this.viewer?.removeMarker('hotspot-preview');
        } catch (e) {
            // marker may not exist
        }
    }

    /**
     * Confirm the current preview position and open the edit form.
     * Called by Alpine's confirmPlacement() after the user clicks "Confirm".
     */
    confirmPlacement() {
        this.placing = false;
        this._previewPos = null;
        this.container.style.cursor = '';
        // Retrieve the current position from the preview marker
        let yaw = 0, pitch = 0;
        try {
            const m = this.viewer.getMarker('hotspot-preview');
            yaw   = parseFloat(String(m.config.position.yaw).replace('deg', ''));
            pitch = parseFloat(String(m.config.position.pitch).replace('deg', ''));
        } catch (e) {}
        this.onHotspotPlaced({ yaw, pitch, icon: this.placingIcon });
    }

    /**
     * Build the draggable preview marker HTML — dashed border + ✕ discard button.
     */
    _previewSpriteOpts() {
        const colors = { navigate: '#3b82f6', info: '#f59e0b', bookmark: '#8b5cf6', 'external-link': '#10b981' };
        return {
            style:   'circle',
            icon:    this.placingIcon || 'chevron-up',
            bgColor: colors[this.placingType] || '#f59e0b',
            dashed:  true,
        };
    }

    /**
     * Build the repositioning preview marker sprite options.
     */
    _repositionPreviewSpriteOpts() {
        // Find the hotspot being repositioned to use its original icon and color
        const hotspot = this.hotspots.find(h => h.id === this._repositioningHotspotId);
        const colors = { navigate: '#3b82f6', info: '#f59e0b', bookmark: '#8b5cf6', 'external-link': '#10b981' };
        return {
            style:   'circle',
            icon:    hotspot?.icon || 'chevron-up',
            bgColor: colors[hotspot?.action_type] || '#6b7280',
            dashed:  true,
            size:    hotspot?.size || 3,
        };
    }

    /**
     * Build the Room Info repositioning preview marker sprite options.
     */
    _roomInfoPreviewSpriteOpts() {
        return {
            style: 'badge',
            icon: '📍',
            label: 'Room Info (preview)',
            dashed: true,
            borderColor: '#FFC600',
            bgColor: 'linear-gradient(135deg,#00491E,#02681E)',
            opacity: 0.85,
        };
    }

    /**
     * Update the system Room Info marker position (called after saving to server).
     */
    updateRoomInfoMarkerPosition(yaw, pitch) {
        if (!this.waypoint) return;
        this.waypoint.room_info_yaw   = yaw;
        this.waypoint.room_info_pitch = pitch;
        // Rebuild markers so the system marker moves
        if (this.viewer) {
            this.viewer.clearMarkers();
            this._buildMarkers().forEach(m => this.viewer.addMarker(m));
        }
    }

    /**
     * Refresh markers from current hotspots array.
     */
    refreshMarkers(hotspots) {
        this.hotspots = hotspots;
        if (!this.viewer) return;
        this._previewPos = null;
        this.viewer.clearMarkers();
        this._buildMarkers().forEach(m => this.viewer.addMarker(m));
    }

    /**
     * Deselect current hotspot.
     */
    deselectHotspot() {
        this.selectedHotspotId = null;
        this._unhighlightAll();
        this.onHotspotDeselected();
    }

    /**
     * Build marker configs from hotspots array.
     * Also injects a read-only system marker when the scene is room-linked.
     */
    _buildMarkers() {
        const editorColors = { navigate: '#3b82f6', info: '#f59e0b', bookmark: '#8b5cf6', 'external-link': '#10b981' };
        const markers = this.hotspots.map(h => ({
            id: `hotspot-${h.id}`,
            position: {
                yaw: `${h.yaw}deg`,
                pitch: `${h.pitch}deg`,
            },
            tooltip: {
                content: h.title + (h.action_type === 'navigate' ? ' → ' + (h.action_target || '') : ''),
                position: 'top center',
            },
            data: { hotspot: h },
            sprite: {
                style:    'circle',
                icon:     h.icon || 'chevron-up',
                bgColor:  editorColors[h.action_type] || '#6b7280',
                opacity:  h.is_active ? 1 : 0.5,
                selected: this.selectedHotspotId === h.id,
                size:     h.size || 3,  // Size scale 1-5
                noCursor: true,
            },
        }));

        // System marker — mirrors the Room Info hotspot the guest viewer auto-generates
        if (this.waypoint?.linked_room_type_id) {
            const yaw   = this.waypoint.room_info_yaw   ?? this.waypoint.default_yaw   ?? 0;
            const pitch = this.waypoint.room_info_pitch ?? ((this.waypoint.default_pitch ?? 0) + 15);
            markers.push({
                id: 'room-info-system',
                position: { yaw: `${yaw}deg`, pitch: `${pitch}deg` },
                tooltip: { content: '🔒 Auto-generated Room Info marker', position: 'top center' },
                data: { isSystem: true },
                sprite: {
                    style: 'badge', icon: '📍', label: 'Room Info (system)',
                    dashed: true, borderColor: '#FFC600',
                    bgColor: 'linear-gradient(135deg,#00491E,#02681E)',
                    opacity: 0.85,
                    noCursor: true,
                },
            });
        }

        return markers;
    }

    _markerHtml(hotspot) {
        const iconId = hotspot.icon || 'chevron-up';
        const colors = {
            navigate: '#3b82f6',
            info: '#f59e0b',
            bookmark: '#8b5cf6',
            'external-link': '#10b981',
        };
        const bg = colors[hotspot.action_type] || '#6b7280';
        const opacity = hotspot.is_active ? '1' : '0.5';
        const selected = this.selectedHotspotId === hotspot.id;
        const ring = selected ? 'box-shadow:0 0 0 3px #fff,0 0 0 6px #3b82f6;' : '';
        const svg = TourEditor.iconSvg(iconId, 18, '#fff');

        return `<div style="width:36px;height:36px;border-radius:50%;background:${bg};border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:${opacity};${ring}filter:drop-shadow(0 2px 4px rgba(0,0,0,.4))">${svg}</div>`;
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
        return icons[id] || `<span style="font-size:${s}px">${id}</span>`;
    }

    _highlightMarker(id) {
        // Re-render all markers to update selection ring
        if (!this.viewer) return;
        this.viewer.clearMarkers();
        this._buildMarkers().forEach(m => this.viewer.addMarker(m));
        this._restorePreviewMarker();
    }

    _unhighlightAll() {
        this.selectedHotspotId = null;
        if (!this.viewer) return;
        this.viewer.clearMarkers();
        this._buildMarkers().forEach(m => this.viewer.addMarker(m));
        this._restorePreviewMarker();
    }

    /** Re-add the preview marker after a full rebuild if it was previously placed. */
    _restorePreviewMarker() {
        if (!this._previewPos) return;
        try {
            this.viewer.addMarker({
                id: 'hotspot-preview',
                position: { yaw: `${this._previewPos.yaw}deg`, pitch: `${this._previewPos.pitch}deg` },
                sprite: this._previewSpriteOpts(),
            });
        } catch (e) {}
    }

    destroy() {
        if (this.viewer) {
            this.viewer.destroy();
            this.viewer = null;
        }
    }
}

// Export globally for Blade/Alpine usage
window.TourEditor = TourEditor;
