/**
 * Virtual Tour Engine — Guest-facing 360° viewer.
 * Uses PanoramaViewer (Photo Sphere Viewer) for rendering, with HTML markers and gyroscope.
 */
import { PanoramaViewer } from './panorama-viewer.js';
import * as THREE from 'three';

const HOTSPOT_COLORS = {
    navigate:        '#3b82f6',
    'previous-scene': '#ef4444',
    info:            '#f59e0b',
    bookmark:        '#8b5cf6',
    'external-link': '#10b981',
};

const TOUR_GUIDE_STORAGE_KEY = 'tour_guide_seen_v1';

const AUTO_TOUR_PROFILES = {
    fast: { cycleMs: 12000, panMs: 10000, label: 'Fast' },
    normal: { cycleMs: 16000, panMs: 14000, label: 'Normal' },
    slow: { cycleMs: 22000, panMs: 19000, label: 'Slow' },
};

class VirtualTourEngine {
    constructor(containerId, options = {}) {
        this.container         = document.getElementById(containerId);
        this.viewer            = null;

        this.waypoints           = [];
        this.currentWaypoint     = null;
        this._sceneHistory       = [];
        this.startWaypoint       = options.startWaypoint || '';
        this.apiBase             = options.apiBase || '/api/tour';
        this.reserveUrl          = options.reserveUrl || '/reserve';
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

        // Manual UI visibility state
        this._uiIdleTimer        = null;
        this._uiHidden           = false;
        this._uiManuallyHidden   = false;  // Manual override via toggle button

        // Date-aware availability state
        this._checkIn  = this._initialDateFromQuery('check_in') || this._todayString();   // 'YYYY-MM-DD'
        this._checkOut = this._initialDateFromQuery('check_out') || this._addDays(this._checkIn, 1);   // 'YYYY-MM-DD'
        this._guests   = this._initialGuestsFromQuery();
        this._ensureCheckoutAfterCheckIn();

        this.onRoomDoorReached   = options.onRoomDoorReached   || (() => {});
        this.onReservationOpened = options.onReservationOpened || (() => {});

        // UI refs
        this.overlay           = document.getElementById('room-info-overlay');
        this.reservationModal  = document.getElementById('reservation-modal');
        this.minimap           = document.getElementById('minimap');
        this.loadingIndicator  = document.getElementById('loading-indicator');
        this.narrationTooltip  = document.getElementById('narration-tooltip');
        this.gazeTooltip       = document.getElementById('gaze-tooltip');
        this.progressIndicator = document.getElementById('progress-indicator');
        this.navSceneName      = document.getElementById('nav-scene-name-text');
        this.roomInfoBtn       = document.getElementById('room-info-btn');
        this.tourGuideLayer    = document.getElementById('tour-guide-layer');
        this.tourGuideBubble   = document.getElementById('tour-guide-bubble');
        this.tourGuideSpotlight = document.getElementById('tour-guide-spotlight');
        this.tourGuideTitle    = document.getElementById('tour-guide-title');
        this.tourGuideCopy     = document.getElementById('tour-guide-copy');
        this.tourGuideStep     = document.getElementById('tour-guide-step');
        this.tourGuideNextBtn  = document.getElementById('tour-guide-next');
        this.tourGuideDismissBtn = document.getElementById('tour-guide-dismiss');
        this.autoTourHud       = document.getElementById('auto-tour-hud');
        this.autoTourCountdown = document.getElementById('auto-tour-countdown');
        this.autoTourFill      = document.getElementById('auto-tour-progress-fill');
        this.autoTourSpeedButtons = Array.from(document.querySelectorAll('.auto-tour-speed-btn'));
        this._gazeHotspot      = null;
        this._gazeCheckEnabled = false;
        this._gazeActivationMs = 1800;
        this._gazeDwellHotspotId = null;
        this._gazeDwellStartAt = 0;
        this._gazeActivationInFlight = false;
        this._navigationSequence = 0;
        this._focusedGazeMarkerId = null;
        this._tourGuideActive = false;
        this._tourGuideIndex = 0;
        this._tourGuideSteps = [];
        this._tourGuideAutoStarted = false;
        this._tourGuideRepositionTimer = null;
        this._tourGuideResizeHandler = () => this._scheduleTourGuideReposition();
        this._mediaLightbox = null;
        this._mediaLightboxKeyHandler = (event) => this._handleMediaLightboxKeydown(event);

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

        this.viewer.addEventListener('card-close', (e) => {
            const markerId = e.marker?.config?.id;
            if (markerId === 'room-info-card') {
                this._closeInSceneCard();
            } else if (markerId === 'info-card') {
                this._closeInfoCard();
            }
        });

        this._initAsync();
        this._bindTourGuideControls();
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    async _initAsync() {
        this.showLoading();
        try {
            await this.loadWaypoints();
            const urlSlug = this._getUrlScene();
            const start = urlSlug || this.startWaypoint || this._getDefaultWaypoint()?.slug;
            if (start) {
                await this.navigateToWaypoint(start, {
                    historyMode: 'replace',
                    suppressInvalidSceneToast: !urlSlug,
                    allowFallback: true,
                });
            }
        } catch (error) {
            console.error('Virtual tour initialization failed:', error);
            this._showToast('The virtual tour could not finish loading. Please refresh and try again.', 'error');
        } finally {
            this.hideLoading();
        }
        this.setupKeyboardControls();
        this.setupBookmarks();
        this._setupGazeDetection();
        // Listen for browser back/forward navigation
        window.addEventListener('popstate', () => {
            const slug = this._getUrlScene();
            const nextScene = slug || this.startWaypoint || this._getDefaultWaypoint()?.slug;
            if (nextScene && nextScene !== this.currentWaypoint?.slug) {
                this.navigateToWaypoint(nextScene, {
                    historyMode: 'replace',
                    suppressInvalidSceneToast: !slug,
                    allowFallback: true,
                });
            }
        });
        setTimeout(() => {
            this.minimap?.classList.remove('hidden');
            this.progressIndicator?.classList.remove('hidden');
        }, 1000);

        // Setup auto-hide UI controls
        this._setupAutoHideUI();
        this._queueTourGuideAutoStart();
    }

    // ── URL deep linking ──────────────────────────────────────────────────────

    _getUrlScene() {
        return new URLSearchParams(window.location.search).get('scene') || null;
    }

    _setUrlScene(slug, historyMode = 'push') {
        if (this.previewMode) return;
        const url = new URL(window.location.href);
        url.searchParams.set('scene', slug);
        const state = { scene: slug };

        if (historyMode === 'replace') {
            window.history.replaceState(state, '', url.toString());
            return;
        }

        if (this.currentWaypoint?.slug === slug && this._getUrlScene() === slug) return;
        window.history.pushState(state, '', url.toString());
    }

    _findWaypointBySlug(slug) {
        if (!slug) return null;
        return this.waypoints.find(w => w.slug === slug) || null;
    }

    _getDefaultWaypoint() {
        return this._findWaypointBySlug(this.startWaypoint)
            || this.waypoints[0]
            || null;
    }

    _resolveWaypointRequest(slug, { allowFallback = false } = {}) {
        const requested = this._findWaypointBySlug(slug);
        if (requested) {
            return {
                waypoint: requested,
                requestedSlug: slug,
                usedFallback: false,
            };
        }

        if (!allowFallback) {
            return {
                waypoint: null,
                requestedSlug: slug,
                usedFallback: false,
            };
        }

        const fallback = this._getDefaultWaypoint();
        return {
            waypoint: fallback,
            requestedSlug: slug,
            usedFallback: Boolean(slug && fallback),
        };
    }

    _escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    _normalizeHttpUrl(value, { allowRelative = false } = {}) {
        const raw = String(value ?? '').trim();
        if (!raw) return null;

        try {
            const parsed = allowRelative
                ? new URL(raw, window.location.origin)
                : new URL(raw);

            if (!['http:', 'https:'].includes(parsed.protocol)) {
                return null;
            }

            if (!allowRelative && !/^[a-zA-Z][a-zA-Z\d+\-.]*:/.test(raw)) {
                return null;
            }

            return parsed.href;
        } catch (_) {
            return null;
        }
    }

    _openSafeExternalUrl(url) {
        const safeUrl = this._normalizeHttpUrl(url);
        if (!safeUrl) {
            this._showToast('This link is unavailable or invalid.', 'error');
            return false;
        }

        window.open(safeUrl, '_blank', 'noopener,noreferrer');
        return true;
    }

    // ── Gaze Detection ────────────────────────────────────────────────────────

    _setupGazeDetection() {
        if (!this.viewer || !this.gazeTooltip) return;

        // Enable gaze detection by default for all interaction modes
        this._gazeCheckEnabled = true;

        // Check gaze on position updates (works for mouse, touch, gyroscope, and VR)
        this.viewer.addEventListener('position-updated', (e) => {
            if (!this._gazeCheckEnabled || this._webXRTest) return;
            this._checkGazeTarget(e.position);
        });
    }

    _checkGazeTarget(position) {
        // Skip gaze detection during cooldown period
        if (this._gazeCooldown) return;
        
        const hotspots = this.currentWaypoint?.hotspots;
        if (!hotspots?.length) {
            this._hideGazeTooltip();
            return;
        }

        const activeHotspots = hotspots.filter(h => h.is_active !== false);

        if (!activeHotspots.length) {
            this._hideGazeTooltip();
            return;
        }

        // Calculate angular distance to each hotspot
        const currentYaw = position.yaw;
        const currentPitch = position.pitch;
        const GAZE_THRESHOLD = 0.15; // ~8.6 degrees in radians

        const angularDistance = (yaw1, pitch1, yaw2, pitch2) => {
            const dYaw = yaw1 - yaw2;
            const dPitch = pitch1 - pitch2;
            return Math.sqrt(dYaw * dYaw + dPitch * dPitch);
        };

        let closestHotspot = null;
        let closestDistance = Infinity;

        for (const hs of activeHotspots) {
            const hsYaw = parseFloat(hs.yaw) * Math.PI / 180;
            const hsPitch = parseFloat(hs.pitch) * Math.PI / 180;
            const dist = angularDistance(currentYaw, currentPitch, hsYaw, hsPitch);

            if (dist < closestDistance && dist < GAZE_THRESHOLD) {
                closestDistance = dist;
                closestHotspot = hs;
            }
        }

        this._syncGazeTarget(closestHotspot);
    }

    _getGazeTooltipContent(hotspot) {
        if (!hotspot) return { title: '', subtitle: '' };

        const title = hotspot.title || this._getDefaultHotspotLabel(hotspot.action_type);
        let subtitle = '';
        
        switch (hotspot.action_type) {
            case 'navigate':
                if (hotspot.action_target) {
                    const targetWaypoint = this.waypoints.find(w => w.slug === hotspot.action_target);
                    subtitle = targetWaypoint ? `→ ${targetWaypoint.name}` : '→ Navigate';
                }
                break;

            case 'previous-scene':
                subtitle = '↩ Exit to previous scene';
                break;
                
            case 'info':
                subtitle = 'ℹ️ View information';
                break;
                
            case 'bookmark':
                const isBookmarked = this._bookmarkedWaypoints?.has?.(this.currentWaypoint?.id);
                subtitle = isBookmarked ? '🔖 Remove bookmark' : '🔖 Bookmark location';
                break;
                
            case 'external-link':
                subtitle = '🔗 Open link';
                break;
                
            case 'audio':
                subtitle = '🔊 Play audio';
                break;
                
            case 'video':
                subtitle = '🎥 Play video';
                break;
            case 'tour-map-toggle':
                subtitle = 'Open Tour Map';
                break;

            case 'tour-map-page':
                subtitle = 'Change Tour Map page';
                break;

            case 'tour-map-scene':
                subtitle = 'Go to this scene';
                break;

            case 'close-panel':
                subtitle = 'Close panel';
                break;
        }
        return { title, subtitle };
    }

    _isGazeActivationModeEnabled() {
        // Auto-activation disabled - users must manually click/tap hotspots
        // Gaze tooltips still provide feedback about what you're looking at
        return false;
    }

    _resetGazeDwellState() {
        this._gazeDwellHotspotId = null;
        this._gazeDwellStartAt = 0;
        this._gazeActivationInFlight = false;
    }

    _setPanoramaGazeFocus(hotspot) {
        if (this._webXRTest) return;

        const nextMarkerId = hotspot?.id ? `hs-${hotspot.id}` : null;
        if (this._focusedGazeMarkerId === nextMarkerId) return;

        if (this._focusedGazeMarkerId) {
            try {
                this.viewer?.setMarkerFocused?.(this._focusedGazeMarkerId, false);
            } catch (_) {}
        }

        this._focusedGazeMarkerId = nextMarkerId;

        if (this._focusedGazeMarkerId) {
            try {
                this.viewer?.setMarkerFocused?.(this._focusedGazeMarkerId, true);
            } catch (_) {
                this._focusedGazeMarkerId = null;
            }
        }
    }

    _syncGazeTarget(hotspot, options = {}) {
        if (!hotspot) {
            this._setPanoramaGazeFocus(null);
            this._resetGazeDwellState();
            this._hideGazeTooltip();
            return;
        }

        this._setPanoramaGazeFocus(options.vr === true ? null : hotspot);

        const activationEnabled = options.forceActivation === true || this._isGazeActivationModeEnabled();
        const now = performance.now();

        if (this._gazeDwellHotspotId !== hotspot.id) {
            this._gazeHotspot = hotspot;
            this._gazeDwellHotspotId = hotspot.id;
            this._gazeDwellStartAt = now;
            this._gazeActivationInFlight = false;
        }

        let status = '';
        let progress = 0;

        if (activationEnabled) {
            const elapsed = now - this._gazeDwellStartAt;
            progress = Math.max(0, Math.min(1, elapsed / this._gazeActivationMs));
            const remainingSeconds = Math.max(0, (this._gazeActivationMs - elapsed) / 1000);
            status = progress >= 1
                ? 'Activating…'
                : `Hold gaze ${remainingSeconds.toFixed(1)}s to activate`;
        }

        if (options.suppressTooltip === true) {
            this._hideGazeTooltip();
        } else {
            this._showGazeTooltip(hotspot, {
                status,
                progress,
                vr: options.vr === true,
            });
        }

        if (activationEnabled && progress >= 1 && !this._gazeActivationInFlight) {
            this._gazeActivationInFlight = true;
            if (typeof options.onActivate === 'function') {
                this._showToast(`Activated: ${hotspot.title || this._getDefaultHotspotLabel(hotspot.action_type)}`, 'info');
                this._resetGazeDwellState();
                this._pauseGazeDetection(1500);
                options.onActivate(hotspot);
            } else {
                this._activateGazeHotspot(hotspot);
            }
        }
    }

    _activateGazeHotspot(hotspot) {
        if (!hotspot) return;
        this._showToast(`Activated: ${hotspot.title || this._getDefaultHotspotLabel(hotspot.action_type)}`, 'info');
        this._resetGazeDwellState();
        this._pauseGazeDetection(1500);
        this._handleHotspotAction(hotspot);
    }

    _showGazeTooltip(hotspot, options = {}) {
        if (!hotspot) return;

        this._gazeHotspot = hotspot;
        const { status = '', progress = 0 } = options;
        let { title, subtitle } = this._getGazeTooltipContent(hotspot);

        if (this._webXRTest?.showVRGazeTooltip) {
            // Room Info badge is self-labelled in VR; suppress the overlay to avoid distraction
            if (options.vr === true && hotspot.action_type === 'room-info') {
                this._webXRTest.hideVRGazeTooltip();
                return;
            }
            if (options.vr === true && hotspot.action_type === 'external-link') {
                subtitle = 'Exit VR and open link in browser';
            }
            this._webXRTest.showVRGazeTooltip(title, subtitle, status);
            return;
        }

        if (!this.gazeTooltip) return;
        const titleEl = this.gazeTooltip.querySelector('.gaze-title');
        const subtitleEl = this.gazeTooltip.querySelector('.gaze-subtitle');
        const statusEl = this.gazeTooltip.querySelector('.gaze-status');
        const progressTrack = this.gazeTooltip.querySelector('.gaze-progress');
        const progressEl = this.gazeTooltip.querySelector('.gaze-progress-fill');

        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = subtitle;
        if (statusEl) {
            statusEl.textContent = status || '';
            statusEl.style.display = status ? 'block' : 'none';
        }
        if (progressTrack) progressTrack.style.display = status ? 'block' : 'none';
        if (progressEl) progressEl.style.width = `${Math.round(progress * 100)}%`;

        this.gazeTooltip.classList.add('visible');
    }

    _getDefaultHotspotLabel(actionType) {
        const labels = {
            'navigate': 'Navigate',
            'previous-scene': 'Exit to Previous Scene',
            'info': 'Information',
            'bookmark': 'Bookmark',
            'external-link': 'External Link',
            'audio': 'Audio',
            'video': 'Video',
        };
        return labels[actionType] || 'Hotspot';
    }

    _hideGazeTooltip() {
        this._gazeHotspot = null;
        this._setPanoramaGazeFocus(null);
        // Handle VR mode
        if (this._webXRTest?.hideVRGazeTooltip) {
            this._webXRTest.hideVRGazeTooltip();
        }
        
        // Handle normal mode (DOM tooltip)
        if (this.gazeTooltip) {
            this.gazeTooltip.classList.remove('visible');
            const statusEl = this.gazeTooltip.querySelector('.gaze-status');
            const progressTrack = this.gazeTooltip.querySelector('.gaze-progress');
            const progressEl = this.gazeTooltip.querySelector('.gaze-progress-fill');
            if (statusEl) {
                statusEl.textContent = '';
                statusEl.style.display = 'none';
            }
            if (progressTrack) progressTrack.style.display = 'none';
            if (progressEl) progressEl.style.width = '0%';
        }
    }

    _pauseGazeDetection(duration = 1000) {
        this._resetGazeDwellState();
        this._hideGazeTooltip();
        this._webXRTest?.clearVRGazeFocus?.();
        this._gazeCooldown = true;
        
        clearTimeout(this._gazeCooldownTimer);
        
        // Allow indefinite pause (e.g., while info card is open)
        if (duration !== Infinity) {
            this._gazeCooldownTimer = setTimeout(() => {
                this._gazeCooldown = false;
            }, duration);
        }
    }

    _resumeGazeDetection() {
        clearTimeout(this._gazeCooldownTimer);
        this._gazeCooldown = false;
        this._resetGazeDwellState();
        this._webXRTest?.clearVRGazeFocus?.();
    }

    // ── Manual UI visibility ─────────────────────────────────────────────────

    _setupAutoHideUI() {
        const isMobileViewport = window.matchMedia('(max-width: 768px)').matches;

        // Elements hidden only when the user manually toggles controls off.
        this._hidableElements = [
            document.querySelector('.vr-controls'),
            document.getElementById('minimap'),
            document.getElementById('help-btn'),
            document.getElementById('room-info-btn'),
            document.getElementById('mobile-settings-btn'),
        ].filter(Boolean);

        // Restore controls on interaction only if they were hidden programmatically.
        const interactions = ['mousemove', 'mousedown', 'touchstart', 'touchmove', 'keydown', 'wheel'];
        interactions.forEach(evt => {
            this.container.addEventListener(evt, () => this._onUserActivity(), { passive: true });
        });

    }

    _onUserActivity() {
        // Show UI if hidden (but only if not manually overridden).
        if (this._uiHidden && !this._uiManuallyHidden) {
            this._showUI();
        }
    }

    _resetUIIdleTimer() {
        clearTimeout(this._uiIdleTimer);
        this._uiIdleTimer = null;
    }

    _showUI() {
        this._uiHidden = false;
        this._hidableElements.forEach(el => el?.classList.remove('ui-hidden'));
        if (this._tourGuideActive) {
            this._showTourGuideStep();
        }
    }

    _hideUI() {
        this._uiHidden = true;
        this._hidableElements.forEach(el => el?.classList.add('ui-hidden'));
        window.closeMobileTourMap?.();
        document.querySelector('.vr-controls')?.classList.remove('mobile-open');
        this.tourGuideLayer?.classList.remove('is-visible');
    }

    toggleUIVisibility() {
        if (this._uiHidden) {
            // Show UI
            this._showUI();
            this._uiManuallyHidden = false;
            this._resetUIIdleTimer();
            this._syncToggleUIBtn(false);
        } else {
            // Hide UI
            this._hideUI();
            this._uiManuallyHidden = true;
            this._resetUIIdleTimer();
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

    // ── Floating tour guide ───────────────────────────────────────────────────

    _bindTourGuideControls() {
        this.tourGuideNextBtn?.addEventListener('click', () => this._advanceTourGuide());
        this.tourGuideDismissBtn?.addEventListener('click', () => this.dismissTourGuide());
        window.addEventListener('resize', this._tourGuideResizeHandler, { passive: true });
        window.addEventListener('orientationchange', this._tourGuideResizeHandler, { passive: true });
    }

    _queueTourGuideAutoStart() {
        if (this._tourGuideAutoStarted || !this.tourGuideLayer || this.previewMode) return;
        this._tourGuideAutoStarted = true;

        if (this._hasSeenTourGuide()) return;

        window.setTimeout(() => {
            this.startTourGuide({ force: false });
        }, 850);
    }

    _hasSeenTourGuide() {
        try {
            return localStorage.getItem(TOUR_GUIDE_STORAGE_KEY) === '1';
        } catch (_) {
            return false;
        }
    }

    _setTourGuideSeen() {
        try {
            localStorage.setItem(TOUR_GUIDE_STORAGE_KEY, '1');
        } catch (_) {}
    }

    startTourGuide({ force = true } = {}) {
        if (!this.tourGuideLayer || !this.tourGuideBubble || !this.currentWaypoint) return false;
        if (!force && this._hasSeenTourGuide()) return false;

        this._tourGuideSteps = this._buildTourGuideSteps();
        if (!this._tourGuideSteps.length) return false;

        this._tourGuideActive = true;
        this._tourGuideIndex = 0;
        this._showTourGuideStep();
        return true;
    }

    dismissTourGuide({ persist = true } = {}) {
        this._tourGuideActive = false;
        this._tourGuideSteps = [];
        this._tourGuideIndex = 0;
        clearTimeout(this._tourGuideRepositionTimer);
        this.tourGuideLayer?.classList.remove('is-visible');
        this.tourGuideLayer?.setAttribute('aria-hidden', 'true');
        this.tourGuideSpotlight?.classList.remove('is-visible');

        if (persist) {
            this._setTourGuideSeen();
        }
    }

    _buildTourGuideSteps() {
        const steps = [
            {
                key: 'look-around',
                title: 'Look around freely',
                copy: 'Drag with your mouse, swipe on your phone, or use the arrow keys to explore the 360 view.',
                anchor: null,
                placement: 'center',
            },
        ];

        const firstHotspot = (this.currentWaypoint?.hotspots || []).find(h => h?.is_active !== false);
        if (firstHotspot) {
            steps.push({
                key: 'hotspots',
                title: 'Use markers to move and discover',
                copy: 'Colored hotspots are the main way to move between scenes, open information, play media, or follow a link.',
                anchor: () => document.getElementById(`psv-marker-hs-${firstHotspot.id}`),
                placement: 'auto',
            });
        }

        if (this.currentWaypoint?.is_room_related && this.currentWaypoint?.linked_room_type_id) {
            steps.push({
                key: 'room-info',
                title: 'View room details and request a stay',
                copy: 'View Details and Request shows pricing, amenities, availability, and reservation actions. You do not need to navigate every corner inside a room.',
                anchor: () => document.getElementById('psv-marker-room-info-marker') || this.roomInfoBtn,
                placement: 'auto',
            });
        }

        steps.push(
            {
                key: 'quick-controls',
                title: 'Quick controls live here',
                copy: 'Open Help anytime, enter Fullscreen for a wider view, hide the interface with the eye button, or return Home when you are done.',
                anchor: () => document.querySelector('.top-right-controls'),
                placement: 'auto',
            },
            {
                key: 'tour-map',
                title: 'Jump with the Tour Map',
                copy: 'Use the map or scene name control to search and jump directly to available rooms, hallways, and common areas.',
                anchor: () => this._getTourMapGuideAnchor(),
                placement: 'auto',
            },
            {
                key: 'sequence-nav',
                title: 'Previous and Next follow the tour order',
                copy: 'These buttons move through the curated scene sequence. Hotspots are better when you want a specific doorway or room.',
                anchor: () => document.querySelector('.nav-controls'),
                placement: 'top',
            },
        );

        return steps;
    }

    _getTourMapGuideAnchor() {
        const isMobile = window.matchMedia?.('(max-width: 768px)')?.matches ?? window.innerWidth <= 768;
        if (isMobile) {
            return document.getElementById('nav-scene-name') || document.getElementById('minimap');
        }

        return document.querySelector('#minimap .minimap-toggle')
            || document.getElementById('minimap')
            || document.getElementById('nav-scene-name');
    }

    _advanceTourGuide() {
        if (!this._tourGuideActive) return;

        if (this._tourGuideIndex >= this._tourGuideSteps.length - 1) {
            this.dismissTourGuide();
            return;
        }

        this._tourGuideIndex += 1;
        this._showTourGuideStep();
    }

    _showTourGuideStep() {
        if (!this._tourGuideActive || !this.tourGuideLayer) return;

        const step = this._tourGuideSteps[this._tourGuideIndex];
        if (!step) {
            this.dismissTourGuide();
            return;
        }

        if (this._isTourGuideSuppressed()) {
            this.tourGuideLayer.classList.remove('is-visible');
            this.tourGuideLayer.setAttribute('aria-hidden', 'true');
            return;
        }

        if (this.tourGuideStep) {
            this.tourGuideStep.textContent = `Guide ${this._tourGuideIndex + 1} of ${this._tourGuideSteps.length}`;
        }
        if (this.tourGuideTitle) this.tourGuideTitle.textContent = step.title;
        if (this.tourGuideCopy) this.tourGuideCopy.textContent = step.copy;
        if (this.tourGuideNextBtn) {
            this.tourGuideNextBtn.textContent = this._tourGuideIndex >= this._tourGuideSteps.length - 1 ? 'Done' : 'Next';
        }

        this.tourGuideLayer.classList.add('is-visible');
        this.tourGuideLayer.setAttribute('aria-hidden', 'false');
        this._scheduleTourGuideReposition();
    }

    _scheduleTourGuideReposition() {
        if (!this._tourGuideActive) return;

        clearTimeout(this._tourGuideRepositionTimer);
        this._tourGuideRepositionTimer = window.setTimeout(() => {
            this._positionTourGuide();
        }, 20);
    }

    _positionTourGuide() {
        if (!this._tourGuideActive || !this.tourGuideLayer || !this.tourGuideBubble) return;

        const step = this._tourGuideSteps[this._tourGuideIndex];
        if (!step || this._isTourGuideSuppressed()) {
            this.tourGuideLayer.classList.remove('is-visible');
            return;
        }

        this.tourGuideLayer.classList.add('is-visible');

        const layerRect = this.tourGuideLayer.getBoundingClientRect();
        const targetEl = typeof step.anchor === 'function' ? step.anchor() : null;
        const targetRect = this._getUsableGuideTargetRect(targetEl, layerRect);

        if (!targetRect || step.placement === 'center') {
            this.tourGuideSpotlight?.classList.remove('is-visible');
            this._placeTourGuideBubble({
                x: layerRect.width / 2,
                y: Math.max(96, layerRect.height * 0.42),
                placement: 'center',
                layerRect,
            });
            return;
        }

        this._placeTourGuideSpotlight(targetRect);

        const preferredPlacement = step.placement === 'top' || step.placement === 'bottom'
            ? step.placement
            : (targetRect.top < layerRect.height * 0.52 ? 'bottom' : 'top');
        const gap = 20;
        const bubbleRect = this.tourGuideBubble.getBoundingClientRect();
        const bubbleHeight = bubbleRect.height || 150;
        const targetCenterX = targetRect.left + targetRect.width / 2;
        let y = preferredPlacement === 'bottom'
            ? targetRect.bottom + gap
            : targetRect.top - bubbleHeight - gap;
        let placement = preferredPlacement;

        if (y < 12) {
            y = targetRect.bottom + gap;
            placement = 'bottom';
        }
        if (y + bubbleHeight > layerRect.height - 12) {
            y = Math.max(12, targetRect.top - bubbleHeight - gap);
            placement = 'top';
        }

        this._placeTourGuideBubble({
            x: targetCenterX,
            y,
            placement,
            layerRect,
        });
    }

    _getUsableGuideTargetRect(targetEl, layerRect) {
        if (!targetEl || typeof targetEl.getBoundingClientRect !== 'function') return null;

        const rect = targetEl.getBoundingClientRect();
        if (rect.width < 2 || rect.height < 2) return null;
        if (rect.bottom < layerRect.top || rect.top > layerRect.bottom) return null;
        if (rect.right < layerRect.left || rect.left > layerRect.right) return null;

        return {
            left: rect.left - layerRect.left,
            top: rect.top - layerRect.top,
            right: rect.right - layerRect.left,
            bottom: rect.bottom - layerRect.top,
            width: rect.width,
            height: rect.height,
        };
    }

    _placeTourGuideSpotlight(rect) {
        if (!this.tourGuideSpotlight) return;

        const pad = Math.max(10, Math.min(18, Math.max(rect.width, rect.height) * 0.18));
        this.tourGuideSpotlight.style.left = `${Math.max(8, rect.left - pad)}px`;
        this.tourGuideSpotlight.style.top = `${Math.max(8, rect.top - pad)}px`;
        this.tourGuideSpotlight.style.width = `${rect.width + pad * 2}px`;
        this.tourGuideSpotlight.style.height = `${rect.height + pad * 2}px`;
        this.tourGuideSpotlight.classList.add('is-visible');
    }

    _placeTourGuideBubble({ x, y, placement, layerRect }) {
        const bubbleRect = this.tourGuideBubble.getBoundingClientRect();
        const halfWidth = (bubbleRect.width || 330) / 2;
        const safeX = Math.min(Math.max(x, halfWidth + 12), Math.max(halfWidth + 12, layerRect.width - halfWidth - 12));
        const maxY = Math.max(12, layerRect.height - (bubbleRect.height || 150) - 12);
        const safeY = Math.min(Math.max(y, 12), maxY);

        this.tourGuideBubble.style.left = `${safeX}px`;
        this.tourGuideBubble.style.top = `${safeY}px`;
        this.tourGuideBubble.dataset.placement = placement || 'center';
    }

    _isTourGuideSuppressed() {
        if (!this.tourGuideLayer) return true;
        if (this.loadingIndicator && !this.loadingIndicator.classList.contains('hidden')) return true;
        if (this._webXRTest || this._roomInfoCardOpen || this._infoCardHotspotId) return true;
        if (this._uiHidden || this._uiManuallyHidden) return true;

        const viewer = document.getElementById('tour-viewer');
        if (viewer?.classList.contains('room-card-open')
            || viewer?.classList.contains('mobile-map-open')
            || viewer?.classList.contains('mobile-settings-open')) {
            return true;
        }

        return this._isElementDisplayed(document.getElementById('tour-help-modal'))
            || this._isElementDisplayed(document.getElementById('tour-media-lightbox'))
            || this._isElementDisplayed(this.reservationModal)
            || this._isElementDisplayed(document.getElementById('reservation-success-modal'));
    }

    _isElementDisplayed(el) {
        if (!el) return false;
        if (el.hidden || el.classList.contains('hidden')) return false;
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
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

    async navigateToWaypoint(slug, options = {}) {
        const {
            historyMode = 'push',
            suppressInvalidSceneToast = false,
            trackSceneHistory = true,
            restoreView = null,
            allowFallback = false,
        } = options;
        const navigationSequence = ++this._navigationSequence;
        const resolution = this._resolveWaypointRequest(slug, { allowFallback });
        const wp = resolution.waypoint;
        if (!wp) {
            if (!suppressInvalidSceneToast && slug) {
                this._showToast('This hotspot is not configured yet.', 'info');
            }
            this.hideLoading();
            return false;
        }
        const sceneHistoryEntry = trackSceneHistory
            ? this._createSceneHistoryEntry(wp.slug)
            : null;
        const restoredView = this._normalizeSceneView(restoreView);
        const targetZoom = restoredView?.zoom ?? wp.default_zoom;

        // Pause gaze detection when navigating
        this._pauseGazeDetection();

        // Stop any audio playing from the previous scene
        if (this._audioEl) {
            this._audioEl.pause();
            this._audioEl = null;
            this._audioHotspotId = null;
        }

        this.showLoading();
        try {
            await this.viewer.setPanorama(wp.panorama_image, {
                transition: { effect: 'fade', duration: 400 },
                position: {
                    yaw:   restoredView?.yaw   ?? `${wp.default_yaw   || 0}deg`,
                    pitch: restoredView?.pitch ?? `${wp.default_pitch || 0}deg`,
                },
                ...(targetZoom != null ? { zoom: targetZoom } : {}),
            });

            if (navigationSequence !== this._navigationSequence) {
                return false;
            }

            if (sceneHistoryEntry) {
                this._pushSceneHistoryEntry(sceneHistoryEntry);
            }

            this.currentWaypoint = wp;
            this._buildHotspots(wp);

            // Fetch room data BEFORE hiding loading screen so marker is ready on first click
            if (wp.is_room_related && (wp.linked_room_type_id || wp.linked_room_id)) {
                await this._fetchRoomInfo(wp);
                if (navigationSequence !== this._navigationSequence) {
                    return false;
                }

                if (this.roomInfoBtn) this.roomInfoBtn.classList.add('visible');
            } else {
                if (this.roomInfoBtn) this.roomInfoBtn.classList.remove('visible');
                this.hideRoomInfoOverlay();
                this.currentRoomType = null;
                this.currentRoom = null;
            }

            this.updateProgressIndicator();
            this.highlightCurrentOnMinimap(wp.slug);
            this._setUrlScene(wp.slug, historyMode);
            this._resetHotspotFocus = true; // signal setupKeyboardControls to reset Tab index
            if (wp.narration) this.showNarration(wp.narration);

            if (resolution.usedFallback && !suppressInvalidSceneToast) {
                this._showToast(`Scene "${resolution.requestedSlug}" was not found. Showing ${wp.name} instead.`, 'info');
            }

            // Preload adjacent panoramas in the background
            this._preloadAdjacentScenes(wp.slug);
            return true;
        } catch (error) {
            console.error(`Failed to navigate to scene "${slug}":`, error);
            if (navigationSequence === this._navigationSequence) {
                this._showToast(`Could not load ${wp.name || 'that scene'}. Please try again.`, 'error');
            }
            return false;
        } finally {
            if (navigationSequence === this._navigationSequence) {
                this.hideLoading();
            }
        }
    }

    _normalizeSceneView(view) {
        if (!view || typeof view !== 'object') return null;

        const normalized = {};
        ['yaw', 'pitch', 'zoom'].forEach((key) => {
            const value = Number(view[key]);
            if (Number.isFinite(value)) {
                normalized[key] = value;
            }
        });

        return Object.keys(normalized).length ? normalized : null;
    }

    _createSceneHistoryEntry(nextSlug, viewOverride = null) {
        const currentSlug = this.currentWaypoint?.slug;
        if (!currentSlug || currentSlug === nextSlug) return null;

        const restoredView = this._normalizeSceneView(viewOverride);
        const currentView = restoredView || this._captureCurrentSceneView();
        if (!currentView) {
            return { slug: currentSlug };
        }

        return {
            slug: currentSlug,
            ...currentView,
        };
    }

    _captureCurrentSceneView() {
        if (!this.viewer) return null;

        const position = this.viewer.getPosition?.();
        const view = {
            yaw: position?.yaw,
            pitch: position?.pitch,
            zoom: this.viewer.getZoomLevel?.(),
        };

        return this._normalizeSceneView(view);
    }

    _pushSceneHistoryEntry(entry) {
        if (!entry?.slug) return;

        const lastEntry = this._sceneHistory[this._sceneHistory.length - 1];
        if (lastEntry?.slug === entry.slug) {
            this._sceneHistory[this._sceneHistory.length - 1] = entry;
        } else {
            this._sceneHistory.push(entry);
        }

        if (this._sceneHistory.length > 50) {
            this._sceneHistory.splice(0, this._sceneHistory.length - 50);
        }
    }

    _getPreviousSceneTarget() {
        const currentSlug = this.currentWaypoint?.slug;

        while (this._sceneHistory.length) {
            const entry = this._sceneHistory.pop();
            const slug = typeof entry === 'string' ? entry : entry?.slug;
            if (slug && slug !== currentSlug && this._findWaypointBySlug(slug)) {
                return {
                    slug,
                    restoreView: typeof entry === 'string'
                        ? null
                        : this._normalizeSceneView(entry),
                };
            }
        }

        const inboundScene = this._getInboundSceneFallback(currentSlug);
        if (inboundScene) {
            return { slug: inboundScene.slug, restoreView: null };
        }

        const idx = this.waypoints.findIndex(w => w.slug === currentSlug);
        if (idx > 0) {
            return { slug: this.waypoints[idx - 1].slug, restoreView: null };
        }

        return null;
    }

    _getInboundSceneFallback(currentSlug) {
        if (!currentSlug || !this.currentWaypoint?.is_room_related) return null;

        const currentOrder = Number(this.currentWaypoint.position_order ?? 0);
        const inboundScenes = this.waypoints.filter((wp) => {
            if (!wp || wp.slug === currentSlug) return false;
            return (wp.hotspots || []).some(h =>
                h.is_active !== false
                && h.action_type === 'navigate'
                && h.action_target === currentSlug
            );
        });

        if (!inboundScenes.length) return null;

        return inboundScenes
            .map(wp => ({
                wp,
                distance: Math.abs(Number(wp.position_order ?? 0) - currentOrder),
            }))
            .sort((a, b) => a.distance - b.distance || Number(a.wp.position_order ?? 0) - Number(b.wp.position_order ?? 0))[0]?.wp || null;
    }

    async navigateToPreviousScene() {
        const target = this._getPreviousSceneTarget();
        if (!target?.slug) {
            this._showToast('No previous scene available.', 'info');
            return false;
        }

        return this.navigateToWaypoint(target.slug, {
            trackSceneHistory: false,
            restoreView: target.restoreView,
        });
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
        this._setRoomCardOpenState(false);
        this._infoCardHotspotId = null;
        this._setPanoramaGazeFocus(null);
        this.viewer.clearMarkers();
        if (!wp.hotspots) return;

        wp.hotspots.filter(h => h.is_active !== false).forEach(h => {
            this.viewer.addMarker({
                id:       `hs-${h.id}`,
                position: { yaw: `${h.yaw}deg`, pitch: `${h.pitch}deg` },
                tooltip:  { content: this._escapeHtml(h.title || ''), position: 'top center' },
                data:     { hotspot: h },
                sprite: {
                    style:   'circle',
                    icon:    h.icon || (h.action_type === 'previous-scene' ? 'chevron-left' : 'chevron-up'),
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
                sprite:   { style: 'badge', icon: '🏠', label: 'View Details and Request',
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
        
        // Pause gaze detection temporarily to prevent immediate reappearance
        this._pauseGazeDetection();
        
        switch (hs.action_type) {
            case 'navigate':
                if (hs.action_target) {
                    this.navigateToWaypoint(hs.action_target);
                } else {
                    this._showToast('This hotspot is not configured yet.', 'info');
                }
                break;
            case 'previous-scene':
                this.navigateToPreviousScene();
                break;
            case 'external-link':
                if (hs.action_target) this._openSafeExternalUrl(hs.action_target);
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
                if (hs.action_target) this._openSafeExternalUrl(hs.action_target);
                break;
        }
    }

    // ── In-scene info card ────────────────────────────────────────────────────

    _openInfoCard(hs) {
        this._infoCardHotspotId = hs.id;
        this.tourGuideLayer?.classList.remove('is-visible');
        
        // Pause gaze detection indefinitely while info card is open
        this._pauseGazeDetection(Infinity);
        
        try { this.viewer.removeMarker('info-card'); } catch (e) {}

        const imageUrls = this._mediaImageUrls(hs);
        const spriteOpts = {
            style: 'card',
            hotspotId: hs.id,
            title: hs.title || '',
            body:  hs.description || '',
            closeAction: 'tourEngine._closeInfoCard()',
        };
        if (hs.media_type === 'video' && hs.media_url) {
            const vid = this._extractYouTubeId(hs.media_url);
            if (vid) spriteOpts.mediaYouTubeId = vid;
            else spriteOpts.mediaVideoUrl = this._normalizeHttpUrl(hs.media_url, { allowRelative: true });
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
        this.closeMediaLightbox();
        
        // Resume gaze detection when info card is closed
        this._resumeGazeDetection();
        
        try { this.viewer.removeMarker('info-card'); } catch (e) {}
        if (this._tourGuideActive) {
            this._showTourGuideStep();
        }
    }

    _mediaImageUrls(hs) {
        if (!['image', 'gallery'].includes(hs?.media_type) || !hs?.media_url) {
            return [];
        }

        return String(hs.media_url)
            .split(/\r?\n|\|/)
            .map(u => this._normalizeHttpUrl(u, { allowRelative: true }))
            .filter(Boolean);
    }

    _cancelViewerPointerState() {
        try {
            this.viewer?.cancelPointerInteraction?.();
        } catch (_) {}
    }

    _findCurrentHotspot(hotspotId) {
        const target = String(hotspotId ?? '');

        return (this.currentWaypoint?.hotspots || []).find((hotspot) => String(hotspot?.id) === target) || null;
    }

    _buildHotspotMediaItems(hs) {
        if (!hs?.media_type || !hs?.media_url) return [];

        if (hs.media_type === 'video') {
            const youtubeId = this._extractYouTubeId(hs.media_url);
            if (youtubeId) {
                return [{
                    type: 'youtube',
                    src: this._buildYouTubeEmbedUrl(youtubeId, { autoplay: true }),
                    title: hs.title || 'Video',
                }];
            }

            const safeVideoUrl = this._normalizeHttpUrl(hs.media_url, { allowRelative: true });
            return safeVideoUrl ? [{
                type: 'video',
                src: safeVideoUrl,
                title: hs.title || 'Video',
            }] : [];
        }

        return this._mediaImageUrls(hs).map((url, index) => ({
            type: 'image',
            src: url,
            title: hs.title || `Image ${index + 1}`,
        }));
    }

    _ensureMediaLightbox() {
        let lightbox = document.getElementById('tour-media-lightbox');
        if (lightbox) return lightbox;

        lightbox = document.createElement('div');
        lightbox.id = 'tour-media-lightbox';
        lightbox.className = 'tour-media-lightbox';
        lightbox.setAttribute('aria-hidden', 'true');
        lightbox.innerHTML = `
            <div class="tour-media-lightbox-backdrop" data-tour-media-close></div>
            <div class="tour-media-lightbox-panel" role="dialog" aria-modal="true" aria-label="Media preview">
                <div class="tour-media-lightbox-header">
                    <div>
                        <h2 class="tour-media-lightbox-title"></h2>
                        <p class="tour-media-lightbox-counter"></p>
                    </div>
                    <button type="button" class="tour-media-lightbox-close" data-tour-media-close aria-label="Close media preview">&times;</button>
                </div>
                <div class="tour-media-lightbox-stage">
                    <button type="button" class="tour-media-lightbox-nav tour-media-lightbox-prev" data-tour-media-prev aria-label="Previous media"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg></button>
                    <div class="tour-media-lightbox-body"></div>
                    <button type="button" class="tour-media-lightbox-nav tour-media-lightbox-next" data-tour-media-next aria-label="Next media"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></button>
                </div>
            </div>
        `;

        (document.getElementById('tour-viewer') || document.body).appendChild(lightbox);
        lightbox.querySelectorAll('[data-tour-media-close]').forEach((el) => {
            el.addEventListener('click', () => this.closeMediaLightbox());
        });
        lightbox.querySelector('[data-tour-media-prev]')?.addEventListener('click', () => this.showPreviousMediaItem());
        lightbox.querySelector('[data-tour-media-next]')?.addEventListener('click', () => this.showNextMediaItem());

        return lightbox;
    }

    openHotspotMediaLightbox(hotspotId, index = 0) {
        const hotspot = this._findCurrentHotspot(hotspotId);
        const items = this._buildHotspotMediaItems(hotspot);
        if (!items.length) {
            this._showToast('This media is unavailable.', 'error');
            return false;
        }

        this.openMediaLightbox(items, index, hotspot?.title || 'Media preview');
        return true;
    }

    openMediaLightbox(items, index = 0, title = 'Media preview') {
        const normalizedItems = Array.isArray(items) ? items.filter(item => item?.type && item?.src) : [];
        if (!normalizedItems.length) return false;

        const lightbox = this._ensureMediaLightbox();
        this._mediaLightbox = {
            items: normalizedItems,
            index: Math.min(Math.max(parseInt(index, 10) || 0, 0), normalizedItems.length - 1),
            title,
            lightbox,
        };

        this.tourGuideLayer?.classList.remove('is-visible');
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        window.addEventListener('keydown', this._mediaLightboxKeyHandler);
        this._renderMediaLightbox();
        return true;
    }

    closeMediaLightbox() {
        const lightbox = this._mediaLightbox?.lightbox || document.getElementById('tour-media-lightbox');
        if (lightbox) {
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            const body = lightbox.querySelector('.tour-media-lightbox-body');
            if (body) body.innerHTML = '';
        }

        this._mediaLightbox = null;
        window.removeEventListener('keydown', this._mediaLightboxKeyHandler);
        if (this._tourGuideActive) {
            this._showTourGuideStep();
        }
    }

    showPreviousMediaItem() {
        if (!this._mediaLightbox || this._mediaLightbox.items.length <= 1) return;
        const total = this._mediaLightbox.items.length;
        this._mediaLightbox.index = (this._mediaLightbox.index - 1 + total) % total;
        this._renderMediaLightbox();
    }

    showNextMediaItem() {
        if (!this._mediaLightbox || this._mediaLightbox.items.length <= 1) return;
        const total = this._mediaLightbox.items.length;
        this._mediaLightbox.index = (this._mediaLightbox.index + 1) % total;
        this._renderMediaLightbox();
    }

    _renderMediaLightbox() {
        if (!this._mediaLightbox) return;

        const { items, index, title, lightbox } = this._mediaLightbox;
        const item = items[index];
        const body = lightbox.querySelector('.tour-media-lightbox-body');
        const titleEl = lightbox.querySelector('.tour-media-lightbox-title');
        const counterEl = lightbox.querySelector('.tour-media-lightbox-counter');
        const prevBtn = lightbox.querySelector('[data-tour-media-prev]');
        const nextBtn = lightbox.querySelector('[data-tour-media-next]');
        const hasMany = items.length > 1;

        if (titleEl) titleEl.textContent = item.title || title || 'Media preview';
        if (counterEl) counterEl.textContent = hasMany ? `${index + 1} / ${items.length}` : '';
        prevBtn?.classList.toggle('is-hidden', !hasMany);
        nextBtn?.classList.toggle('is-hidden', !hasMany);

        if (!body) return;

        if (item.type === 'image') {
            body.innerHTML = `<img src="${this._escapeHtml(item.src)}" alt="${this._escapeHtml(item.title || title || 'Media preview')}" loading="eager">`;
        } else if (item.type === 'youtube') {
            body.innerHTML = `<div class="tour-media-lightbox-video"><iframe src="${this._escapeHtml(item.src)}" allow="autoplay;encrypted-media;fullscreen" allowfullscreen></iframe></div>`;
        } else if (item.type === 'video') {
            body.innerHTML = `<div class="tour-media-lightbox-video"><video src="${this._escapeHtml(item.src)}" controls autoplay playsinline></video></div>`;
        }
    }

    _handleMediaLightboxKeydown(event) {
        if (!this._mediaLightbox) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            this.closeMediaLightbox();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.showPreviousMediaItem();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.showNextMediaItem();
        }
    }

    scrollInfoGallery(trackId, direction) {
        const track = document.getElementById(trackId);
        if (!track) return;

        const distance = Math.max(220, Math.round(track.clientWidth * 0.9));
        track.scrollBy({
            left: direction === 'prev' ? -distance : distance,
            behavior: 'smooth',
        });
    }

    updateInfoGalleryCounter(trackId, counterId) {
        const track = document.getElementById(trackId);
        const counter = document.getElementById(counterId);
        if (!track || !counter) return;

        const slides = Array.from(track.querySelectorAll('[data-gallery-slide]'));
        if (!slides.length) return;

        const trackCenter = track.scrollLeft + (track.clientWidth / 2);
        let activeIndex = 0;
        let activeDistance = Infinity;

        slides.forEach((slide, index) => {
            const slideCenter = slide.offsetLeft + (slide.offsetWidth / 2);
            const distance = Math.abs(slideCenter - trackCenter);
            if (distance < activeDistance) {
                activeDistance = distance;
                activeIndex = index;
            }
        });

        counter.textContent = `${activeIndex + 1} / ${slides.length}`;
    }

    _infoCardHtml(hs) {
        const hasText  = !!(hs.description && hs.description.trim());
        const hasMedia = !!(hs.media_type && hs.media_url);
        const imageUrls = this._mediaImageUrls(hs);
        const hasYouTubeVideo = hs.media_type === 'video' && !!this._extractYouTubeId(hs.media_url);
        const hasImageMedia = imageUrls.length > 0;
        const hasExpandedMediaCard = hasYouTubeVideo || hasImageMedia;
        const cardWidth = hasYouTubeVideo
            ? 'min(620px,calc(100vw - 32px))'
            : (hasImageMedia ? 'min(760px,calc(100vw - 32px))' : '340px');
        const videoMediaPaddingTop = hasYouTubeVideo ? '62.5%' : '56.25%';
        const cardMaxHeight = hasYouTubeVideo
            ? 'min(94vh,860px)'
            : (hasImageMedia ? 'min(92vh,820px)' : '90vh');

        let mediaHtml = '';
        if (hasMedia) {
            if (hs.media_type === 'video') {
                const vid = this._extractYouTubeId(hs.media_url);
                if (vid) {
                    const src = this._buildYouTubeEmbedUrl(vid);
                    mediaHtml = `<div onmouseenter="tourEngine._cancelViewerPointerState();event.stopPropagation()" onpointerenter="tourEngine._cancelViewerPointerState();event.stopPropagation()" onmousemove="event.stopPropagation()" onmousedown="tourEngine._cancelViewerPointerState();event.stopPropagation()" onpointerdown="tourEngine._cancelViewerPointerState();event.stopPropagation()" ontouchstart="tourEngine._cancelViewerPointerState();event.stopPropagation()" style="position:relative;padding-top:${videoMediaPaddingTop};background:#000;overflow:hidden;flex-shrink:0">`
                        + `<iframe src="${src}" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="autoplay;encrypted-media;fullscreen" allowfullscreen loading="lazy"></iframe>`
                        + `<button type="button" onclick="tourEngine.openHotspotMediaLightbox(${parseInt(hs.id, 10)},0);event.stopPropagation()" style="position:absolute;right:12px;bottom:12px;background:rgba(15,23,42,.86);color:white;border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer;box-shadow:0 10px 24px rgba(0,0,0,.28)">View larger</button>`
                        + `</div>`;
                } else {
                    const safeVideoUrl = this._normalizeHttpUrl(hs.media_url, { allowRelative: true });
                    if (safeVideoUrl) {
                        mediaHtml = `<div onmouseenter="tourEngine._cancelViewerPointerState();event.stopPropagation()" onpointerenter="tourEngine._cancelViewerPointerState();event.stopPropagation()" onmousemove="event.stopPropagation()" onmousedown="tourEngine._cancelViewerPointerState();event.stopPropagation()" onpointerdown="tourEngine._cancelViewerPointerState();event.stopPropagation()" ontouchstart="tourEngine._cancelViewerPointerState();event.stopPropagation()" style="position:relative;padding-top:${videoMediaPaddingTop};background:#000;overflow:hidden;flex-shrink:0">`
                            + `<video src="${this._escapeHtml(safeVideoUrl)}" controls playsinline style="position:absolute;inset:0;width:100%;height:100%;background:#000"></video>`
                            + `<button type="button" onclick="tourEngine.openHotspotMediaLightbox(${parseInt(hs.id, 10)},0);event.stopPropagation()" style="position:absolute;right:12px;bottom:12px;background:rgba(15,23,42,.86);color:white;border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer;box-shadow:0 10px 24px rgba(0,0,0,.28)">View larger</button>`
                            + `</div>`;
                    }
                }
            } else if (imageUrls.length === 1) {
                mediaHtml = `<div style="position:relative;flex-shrink:0;overflow:hidden;background:#111827">`
                    + `<button type="button" onclick="tourEngine.openHotspotMediaLightbox(${parseInt(hs.id, 10)},0);event.stopPropagation()" style="display:block;width:100%;border:0;background:transparent;padding:0;cursor:zoom-in">`
                    + `<img class="pv-info-media-img" src="${imageUrls[0]}" style="width:100%;display:block;max-height:${hasExpandedMediaCard ? 'min(56vh,520px)' : '240px'};object-fit:cover" onerror="this.closest('div').style.display='none'" loading="lazy">`
                    + `</button>`
                    + `<button type="button" onclick="tourEngine.openHotspotMediaLightbox(${parseInt(hs.id, 10)},0);event.stopPropagation()" style="position:absolute;right:12px;bottom:12px;background:rgba(15,23,42,.86);color:white;border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer;box-shadow:0 10px 24px rgba(0,0,0,.28)">View larger</button>`
                    + `</div>`;
            } else if (imageUrls.length > 1) {
                const trackId = `info-gallery-track-${String(hs.id).replace(/[^a-zA-Z0-9_-]/g, '')}`;
                const counterId = `info-gallery-counter-${String(hs.id).replace(/[^a-zA-Z0-9_-]/g, '')}`;
                const imgs = imageUrls.map((url, index) =>
                    `<div data-gallery-slide style="min-width:${hasExpandedMediaCard ? 'calc(100% - 8px)' : '220px'};scroll-snap-align:center;scroll-snap-stop:always;flex:0 0 auto">`
                    + `<button type="button" onclick="tourEngine.openHotspotMediaLightbox(${parseInt(hs.id, 10)},${index});event.stopPropagation()" style="display:block;width:100%;border:0;background:transparent;padding:0;cursor:zoom-in">`
                    + `<img class="pv-info-gallery-img" src="${url}" style="width:100%;height:${hasExpandedMediaCard ? 'min(52vh,420px)' : '160px'};display:block;border-radius:10px;object-fit:cover;box-shadow:0 10px 24px rgba(17,24,39,.12)" onerror="this.closest('[data-gallery-slide]').style.display='none'" loading="lazy">`
                    + `</button>`
                    + `</div>`
                ).join('');
                mediaHtml = `<div style="background:#f9fafb;padding:12px 14px 10px">`
                    + `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;color:#6b7280;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase"><span>Image Gallery</span><span id="${counterId}">1 / ${imageUrls.length}</span></div>`
                    + `<div style="position:relative">`
                    + `<button type="button" onclick="tourEngine.scrollInfoGallery('${trackId}','prev');event.stopPropagation()" aria-label="Previous image" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);z-index:2;width:44px;height:44px;border-radius:999px;border:1px solid rgba(255,255,255,.45);background:rgba(15,23,42,.78);color:white;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 12px 26px rgba(0,0,0,.32)"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg></button>`
                    + `<div id="${trackId}" data-gallery-track onscroll="tourEngine.updateInfoGalleryCounter('${trackId}','${counterId}')" onwheel="event.stopPropagation();event.preventDefault();const delta=((event.deltaX||0)+(event.deltaY||0))*(event.deltaMode===1?16:1);this.scrollBy({left:delta,behavior:'auto'});tourEngine.updateInfoGalleryCounter('${trackId}','${counterId}');return false;" style="display:flex;gap:10px;overflow-x:auto;overflow-y:hidden;padding:2px 0 6px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;scrollbar-width:thin">${imgs}</div>`
                    + `<button type="button" onclick="tourEngine.scrollInfoGallery('${trackId}','next');event.stopPropagation()" aria-label="Next image" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);z-index:2;width:44px;height:44px;border-radius:999px;border:1px solid rgba(255,255,255,.45);background:rgba(15,23,42,.78);color:white;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 12px 26px rgba(0,0,0,.32)"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></button>`
                    + `</div>`
                    + `</div>`;
            }
        }

        const bodyParts = [];
        if (mediaHtml) bodyParts.push(mediaHtml);
        if (hasText)  bodyParts.push(`<div style="padding:${hasExpandedMediaCard ? '12px 14px 10px' : `14px 14px ${hasMedia ? '8px' : '14px'}`};font-size:13px;color:#374151;line-height:${hasExpandedMediaCard ? '1.55' : '1.6'}">${this._escapeHtml(hs.description)}</div>`);

        const hasBody = bodyParts.length > 0;
        const interactionShield = `onclick="event.stopPropagation()" onmousedown="tourEngine._cancelViewerPointerState();event.stopPropagation()" onpointerdown="tourEngine._cancelViewerPointerState();event.stopPropagation()" onpointerup="event.stopPropagation()" onmouseup="event.stopPropagation()" onwheel="event.stopPropagation()" ontouchstart="tourEngine._cancelViewerPointerState();event.stopPropagation()" ontouchmove="event.stopPropagation()"`;

        return `<div class="pv-info-card" ${interactionShield} style="background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.6);width:${cardWidth};font-family:var(--guest-font-body);display:flex;flex-direction:column;overflow:hidden;max-height:${cardMaxHeight};pointer-events:auto;touch-action:pan-y">`
            + `<div style="background:linear-gradient(135deg,#00491E,#02681E);color:white;padding:14px 16px;position:relative;flex-shrink:0">`
            + `<button onclick="tourEngine._closeInfoCard();event.stopPropagation()" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,.2);border:none;color:white;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:14px;line-height:26px;text-align:center">✕</button>`
            + `<h2 style="font-size:16px;font-weight:700;margin:0 32px 0 0">${this._escapeHtml(hs.title || '')}</h2>`
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

    _buildYouTubeThumbnailUrl(videoId) {
        if (!videoId) return '';
        return `https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`;
    }

    // ── Date-aware availability helpers ──────────────────────────────────────

    _todayString() {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    _normalizeDateString(value) {
        const raw = String(value || '').trim();
        return /^\d{4}-\d{2}-\d{2}$/.test(raw) ? raw : null;
    }

    _initialDateFromQuery(key) {
        try {
            return this._normalizeDateString(new URLSearchParams(window.location.search).get(key));
        } catch (_) {
            return null;
        }
    }

    _initialGuestsFromQuery() {
        try {
            return Math.max(1, parseInt(new URLSearchParams(window.location.search).get('guests'), 10) || 1);
        } catch (_) {
            return 1;
        }
    }

    _addDays(dateString, days) {
        const base = this._normalizeDateString(dateString) || this._todayString();
        const [year, month, day] = base.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        date.setDate(date.getDate() + days);
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    _ensureDefaultAvailabilityDates() {
        const today = this._todayString();

        if (!this._checkIn || this._checkIn < today) {
            this._checkIn = today;
        }

        this._ensureCheckoutAfterCheckIn();
    }

    _ensureCheckoutAfterCheckIn() {
        const checkIn = this._normalizeDateString(this._checkIn) || this._todayString();
        const minCheckOut = this._addDays(checkIn, 1);

        this._checkIn = checkIn;
        if (!this._checkOut || this._checkOut <= checkIn) {
            this._checkOut = minCheckOut;
        }
    }

    _setCheckIn(val)  {
        this._checkIn = this._normalizeDateString(val) || this._todayString();
        this._ensureDefaultAvailabilityDates();
        this._syncAvailabilityDateInputs();
    }
    _setCheckOut(val) {
        this._checkOut = this._normalizeDateString(val) || null;
        this._ensureDefaultAvailabilityDates();
        this._syncAvailabilityDateInputs();
    }
    _setGuests(val)   {
        const requestedGuests = Math.max(1, parseInt(val, 10) || 1);
        const limit = this._getOccupantLimitForRoomType(this.currentRoomType);
        this._guests = limit && limit.max >= 1 ? Math.min(requestedGuests, limit.max) : requestedGuests;
    }

    _syncAvailabilityDateInputs(root = document) {
        const widgets = root?.matches?.('[data-tour-availability-widget]')
            ? [root]
            : Array.from(root?.querySelectorAll?.('[data-tour-availability-widget]') || []);

        widgets.forEach((widget) => {
            const checkIn = widget.querySelector('[data-tour-check-in]');
            const checkOut = widget.querySelector('[data-tour-check-out]');
            if (!checkIn || !checkOut) return;

            const minCheckOut = this._addDays(this._checkIn, 1);

            checkIn.min = this._todayString();
            checkIn.value = this._checkIn;
            checkOut.min = minCheckOut;
            checkOut.value = this._checkOut;
        });
    }

    _getReservationRoomTypeId() {
        return this.currentRoomType?.id || this.currentRoom?.room_type?.id || null;
    }

    _buildReservationUrl() {
        this._ensureDefaultAvailabilityDates();

        const url = new URL(this.reserveUrl, window.location.href);
        const roomTypeId = this._getReservationRoomTypeId();

        if (roomTypeId) {
            url.searchParams.set('room_type', roomTypeId);
        }

        const capacity = this.currentRoom?.capacity || this.currentRoomType?.capacity;
        if (capacity) {
            url.searchParams.set('capacity', String(capacity));
        }

        url.searchParams.set('check_in', this._checkIn);
        url.searchParams.set('check_out', this._checkOut);
        url.searchParams.set('guests', String(this._guests));
        url.searchParams.set('source', 'virtual_tour');

        return `${url.pathname}${url.search}${url.hash}`;
    }

    goToReservationPage() {
        window.location.href = this._buildReservationUrl();
    }

    _getOccupantLimitForRoomType(roomType = this.currentRoomType) {
        if (!roomType) return { max: 20, isPrivate: true, message: 'Number of Occupants must be no more than 20.' };

        const isPrivate = Boolean(roomType.is_private ?? roomType.room_sharing_type !== 'public');
        if (isPrivate) {
            const roomCountInput = document.getElementById('requested_room_count');
            const roomCount = Math.max(1, parseInt(roomCountInput?.value || '1', 10) || 1);
            const max = Math.max(1, parseInt(roomType.capacity, 10) || 20) * roomCount;
            return {
                max,
                isPrivate,
                message: `This request allows up to ${max} occupants across ${roomCount} room(s).`,
            };
        }

        const max = Math.max(0, parseInt(roomType.available_beds_count ?? roomType.availability_display_count ?? 0, 10) || 0);
        return {
            max,
            isPrivate,
            message: max > 0
                ? `Only ${max} beds are available for these dates.`
                : 'No beds are available for these dates.',
        };
    }

    _applyOccupantLimitToInput(input, limit, forceValidation = true) {
        if (!input || !limit) return;

        input.max = String(Math.max(1, limit.max));
        input.dataset.dynamicMax = String(limit.max);
        input.dataset.validationMaxMessage = limit.message;

        if (limit.max >= 1 && Number(input.value || 0) > limit.max) {
            input.value = String(limit.max);
            this._guests = limit.max;
        }

        const form = input.closest('form');
        if (forceValidation && window.GuestRealtimeValidation && form) {
            window.GuestRealtimeValidation.validateField(input, form, true);
        }
    }

    _syncReservationOccupantLimit(forceValidation = true) {
        const input = document.getElementById('number_of_occupants');
        this._applyOccupantLimitToInput(input, this._getOccupantLimitForRoomType(), forceValidation);
    }

    async _refreshReservationOccupantLimit() {
        const roomTypeId = this._getReservationRoomTypeId();
        if (!roomTypeId || !this._checkIn || !this._checkOut) {
            this._syncReservationOccupantLimit();
            return;
        }

        this._syncReservationOccupantLimit(false);

        try {
            const url = new URL(`${this.apiBase}/room-type/${roomTypeId}/availability`, window.location.href);
            url.searchParams.set('check_in',  this._checkIn);
            url.searchParams.set('check_out', this._checkOut);
            url.searchParams.set('guests',    this._guests);
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                this.currentRoomType = data.data;
                this._syncReservationOccupantLimit();
            }
        } catch (e) {
            this._syncReservationOccupantLimit();
        }
    }

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

    _setAvailabilityButtonLoading(button, isLoading) {
        if (!button) return;

        if (isLoading) {
            if (button.disabled) return;
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.style.opacity = '0.82';
            button.style.cursor = 'wait';
            button.innerHTML = `<span style="display:inline-flex;align-items:center;justify-content:center;gap:7px">`
                + `<svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" style="animation:tour-check-spin .75s linear infinite">`
                + `<circle cx="12" cy="12" r="9" fill="none" stroke="rgba(255,255,255,.34)" stroke-width="3"></circle>`
                + `<path d="M21 12a9 9 0 0 1-9 9" fill="none" stroke="white" stroke-width="3" stroke-linecap="round"></path>`
                + `</svg><span>Checking...</span></span>`;
            return;
        }

        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.style.opacity = '';
        button.style.cursor = '';
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }

    async _checkDateAvailability(rtId, trigger = null) {
        if (trigger?.disabled) return;
        if (!this._checkIn || !this._checkOut) {
            this._showToast('Please select check-in and check-out dates.', 'error');
            return;
        }
        this._setAvailabilityButtonLoading(trigger, true);
        try {
            const url = new URL(`${this.apiBase}/room-type/${rtId}/availability`, window.location.href);
            url.searchParams.set('check_in',  this._checkIn);
            url.searchParams.set('check_out', this._checkOut);
            url.searchParams.set('guests',    this._guests);
            const res  = await fetch(url);
            const data = await res.json();
            if (data.success) {
                this.currentRoomType = data.data;
                this._syncReservationOccupantLimit(false);
                // Update both overlay panel AND in-scene card
                this._populateRoomInfoOverlay(data.data, false);
                this._closeInSceneCard();
                this._openInSceneCard();
            } else {
                this._showToast(data.message || 'Could not check availability.', 'error');
            }
        } catch (e) {
            this._showToast('Network error. Please try again.', 'error');
        } finally {
            this._setAvailabilityButtonLoading(trigger, false);
        }
    }

    async _checkSpecificRoomAvailability(trigger = null) {
        if (this.currentRoomType?.id) {
            return this._checkDateAvailability(this.currentRoomType.id, trigger);
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
        const isMobileViewport = window.matchMedia?.('(max-width: 768px)').matches ?? window.innerWidth <= 768;

        if (isMobileViewport) {
            this.viewer.rotate({ yaw: `${yaw}deg`, pitch: `${pitch}deg` });
        }

        this._ensureDefaultAvailabilityDates();
        
        // Extract display data from either room or room type
        let name, description, price, tags, count, roomSharingType, availText, isPrivateRoom, otherAvailCount;
        
        if (hasSpecificRoom) {
            const room = this.currentRoom;
            const roomType = room.room_type || this.currentRoomType;
            name = this._escapeHtml(roomType?.name || 'This Room');
            description = this._escapeHtml(roomType?.description || '');
            price = this._escapeHtml(roomType?.pricing_display || roomType?.formatted_price || '');
            tags = (roomType?.amenities || []).map(a => this._escapeHtml(a.name));
            count = room.is_available ? 1 : 0;
            roomSharingType = this._escapeHtml(roomType?.room_sharing_type || '');
            isPrivateRoom = roomType?.is_private ?? false;
            otherAvailCount = room.other_available_count ?? null;
            if (room.is_available) {
                availText = 'Available';
            } else {
                availText = 'Unavailable';
            }
        } else {
            const rt = this.currentRoomType;
            name = this._escapeHtml(rt.name || '');
            description = this._escapeHtml(rt.description || '');
            price = this._escapeHtml(rt.pricing_display || rt.formatted_price || '');
            tags = (rt.amenities || []).map(a => this._escapeHtml(a.name));
            count = rt.available_rooms_count;
            roomSharingType = this._escapeHtml(rt.room_sharing_type || '');
            availText = this._escapeHtml(rt.availability_label || (count != null ? `${count} room(s) available` : ''));
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
                : `${count > 0 ? '✓' : '✗'} ${availText}`
            : undefined;

        // ── Date availability widget ──────────────────────────────────────────
        const today = this._todayString();
        const minCheckOut = this._addDays(this._checkIn || today, 1);

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

        const inputStyle = 'width:100%;max-width:100%;min-width:0;font-size:12px;border:1px solid #d7e5db;border-radius:6px;padding:8px 30px 8px 10px;box-sizing:border-box;height:40px;background:#ffffff;color:#1f2937;line-height:1.2;appearance:none;-webkit-appearance:none;overflow:hidden';
        const availabilityCardStyle = 'border:1px solid #dce9df;border-radius:10px;padding:10px;background:linear-gradient(180deg,#f8fcf9 0%,#f3f9f5 100%)';
        const availabilityTitleStyle = 'font-size:11px;font-weight:700;text-transform:uppercase;color:#00491E;margin-bottom:8px;letter-spacing:.04em';
        const availabilityLabelStyle = 'font-size:10px;color:#6b7280;margin-bottom:4px';
        const checkButtonStyle = 'background:linear-gradient(135deg,#00491E,#02681E);color:white;border:none;padding:8px 10px;border-radius:8px;font-weight:700;font-size:11px;cursor:pointer;min-height:40px;box-sizing:border-box';

        // Build availability widget - hide Guests field for private rooms
        let availWidget = '';
        if (!this.previewMode) {
            availWidget = `<div data-tour-availability-widget style="${availabilityCardStyle}">`
              + `<div style="${availabilityTitleStyle}">📅 Check Availability</div>`
              + `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:8px;margin-bottom:8px">`
              +   `<div style="min-width:0"><div style="${availabilityLabelStyle}">Check-in</div>`
              +   `<input type="date" data-tour-check-in value="${this._checkIn || today}" min="${today}" onclick="event.stopPropagation()" onchange="tourEngine._setCheckIn(this.value)" style="${inputStyle}"></div>`
              +   `<div style="min-width:0"><div style="${availabilityLabelStyle}">Check-out</div>`
              +   `<input type="date" data-tour-check-out value="${this._checkOut || minCheckOut}" min="${minCheckOut}" onclick="event.stopPropagation()" onchange="tourEngine._setCheckOut(this.value)" style="${inputStyle}"></div>`
              + `</div>`;
            
            // For private rooms: full-width Check button. For dorm rooms: show Guests input
            if (isPrivateRoom) {
                availWidget += `<div style="margin-bottom:6px">`
                  +   `<button onclick="tourEngine.${hasSpecificRoom ? '_checkSpecificRoomAvailability(this)' : `_checkDateAvailability(${roomTypeId}, this)`};event.stopPropagation()" style="width:100%;${checkButtonStyle}">🔍 Check</button>`
                  + `</div>`;
            } else {
                availWidget += `<div style="display:grid;grid-template-columns:minmax(92px,110px) minmax(0,1fr);gap:8px;align-items:end;margin-bottom:6px">`
                  +   `<div style="min-width:0"><div style="${availabilityLabelStyle}">Guests</div>`
                  +   `<input type="number" value="${this._guests}" min="1" max="20" onclick="event.stopPropagation()" onchange="tourEngine._setGuests(this.value)" style="${inputStyle}"></div>`
                  +   `<button onclick="tourEngine.${hasSpecificRoom ? '_checkSpecificRoomAvailability(this)' : `_checkDateAvailability(${roomTypeId}, this)`};event.stopPropagation()" style="flex:1;${checkButtonStyle}">🔍 Check</button>`
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
            ? `<button onclick="window.location.href='/rooms';event.stopPropagation()" style="width:100%;background:#FFC600;color:#00491E;border:none;padding:8px;border-radius:6px;font-weight:700;font-size:11px;cursor:pointer">🏠 Explore Other Room Types</button>`
            : '';

        const buttons = this.previewMode ? '' :
            `<div style="display:flex;flex-direction:column;gap:6px">`
          + availWidget
          + otherRoomsNote
          + `<div style="display:flex;flex-direction:column;gap:5px;align-items:center;margin-top:4px">`
          +   `<button onclick="tourEngine.openReservationModal();event.stopPropagation()" style="${buttonStyle}">${buttonText}</button>`
          +   exploreButtonHtml
          +   `<button onclick="tourEngine.goToReservationPage();event.stopPropagation()" style="width:100%;background:#00491E;color:white;border:none;padding:8px;border-radius:6px;font-weight:700;font-size:11px;cursor:pointer">📝 Request Multiple Room Types</button>`
          +   `<div style="font-size:10px;color:#6b7280;line-height:1.35;text-align:center">Use the full form when one reservation needs different room types.</div>`
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
                headerBadgeColor: count > 0 ? '#d9f99d' : '#fecaca',
                closeAction: 'tourEngine._closeInSceneCard()',
                buttons,
            },
        });
        this._roomInfoCardOpen = true;
        this._setRoomCardOpenState(true);
        
        // Pause gaze detection indefinitely while room info card is open
        this._pauseGazeDetection(Infinity);
    }

    _closeInSceneCard() {
        try { this.viewer.removeMarker('room-info-card'); } catch (e) {}
        // Restore compact trigger marker
        try {
            this.viewer.updateMarker({
                id:     'room-info-marker',
                sprite: { style: 'badge', icon: '🏠', label: 'View Details and Request',
                           bgColor: 'linear-gradient(135deg,#00491E,#02681E)',
                           textColor: '#ffffff', iconColor: '#ffffff' },
            });
        } catch (e) {}
        this._roomInfoCardOpen = false;
        this._setRoomCardOpenState(false);
        
        // Resume gaze detection when room info card is closed
        this._resumeGazeDetection();
    }

    _setRoomCardOpenState(isOpen) {
        const viewerEl = document.getElementById('tour-viewer');
        viewerEl?.classList.toggle('room-card-open', Boolean(isOpen));

        if (isOpen) {
            window.closeMobileTourSettings?.();
            window.closeMobileTourMap?.();
            this.tourGuideLayer?.classList.remove('is-visible');
        } else if (this._tourGuideActive) {
            this._showTourGuideStep();
        }
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
            availText = data.availability_label || (count != null ? `${count} room(s) available` : '');
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
                <button onclick="tourEngine.openReservationModal()" style="width:85%;background:#FFC600;color:#00491E;border:none;padding:10px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">\uD83C\uDFE8 Request This Room Type</button>
                <button onclick="tourEngine.goToReservationPage()" style="width:85%;background:#00491E;color:white;border:none;padding:10px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">\uD83D\uDCDD Request Multiple Room Types</button>
                <div style="width:85%;font-size:10px;color:#6b7280;line-height:1.35;text-align:center">Use the full form when one reservation needs different room types.</div>
            </div>`;

        return `<div style="background:white;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.6);width:360px;font-family:var(--guest-font-body);display:flex;flex-direction:column;max-height:90vh">
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
        this.tourGuideLayer?.classList.remove('is-visible');

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
        const gazeTooltipGroup = new THREE.Group();
        const cameraFollower = new THREE.Group();
        contentGroup.add(hotspotGroup);
        scene.add(panelGroup, cameraFollower);
        cameraFollower.add(statusGroup, gazeTooltipGroup);

        const interactive = [];
        const xrHotspotObjects = new Map();
        const raycaster = new THREE.Raycaster();
        const tempMatrix = new THREE.Matrix4();
        const textTextures = new Set();
        const XR_RADIUS = 9;
        const XR_ROOM_SCALE = { x: 2.8, y: 0.82 };
        const XR_MAP_PAGE_SIZE = 5;
        const XR_GAZE_UI_ACTIONS = new Set([
            'tour-map-toggle',
            'tour-map-page',
            'tour-map-scene',
            'close-panel',
            'info-image',
            'open-url',
            'reservation',
            'reserve-page',
            'room-info',
        ]);
        let hoveredObject = null;
        let infoPanelAnchor = null;
        let tourMapPage = 0;
        let tourMapFrame = null;
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
            const rawRows = Array.isArray(lines) ? lines : [lines];
            const padding = options.padding ?? 36;
            const maxTextWidth = options.maxTextWidth || Math.max(1, width - padding * 2);
            const wrapRow = (value) => {
                const text = String(value || '').trim();
                if (!options.wrap || !text) return [text];
                const words = text.split(/\s+/);
                const wrapped = [];
                let line = '';
                words.forEach((word) => {
                    const next = line ? `${line} ${word}` : word;
                    if (ctx.measureText(next).width <= maxTextWidth || !line) {
                        line = next;
                    } else {
                        wrapped.push(line);
                        line = word;
                    }
                });
                if (line) wrapped.push(line);
                return wrapped;
            };
            let rows = rawRows.flatMap(wrapRow).filter(Boolean);
            if (options.maxLines && rows.length > options.maxLines) {
                rows = rows.slice(0, options.maxLines);
                const lastIndex = rows.length - 1;
                rows[lastIndex] = rows[lastIndex].replace(/[.…\s]+$/, '') + '...';
                while (ctx.measureText(rows[lastIndex]).width > maxTextWidth && rows[lastIndex].length > 4) {
                    rows[lastIndex] = rows[lastIndex].slice(0, -4).replace(/\s+\S*$/, '') + '...';
                }
            }
            const lineHeight = options.lineHeight || 46;
            const startY = height / 2 - ((rows.length - 1) * lineHeight) / 2;
            rows.forEach((line, index) => ctx.fillText(String(line), width / 2, startY + index * lineHeight, maxTextWidth));
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

        const clearGroup = (group, options = {}) => {
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
            if (options.resetHover !== false) {
                hoveredObject = null;
            }
        };

        const closePanel = (resetInfoAnchor = true, keepTourMapFrame = false) => {
            clearGroup(panelGroup);
            for (let i = interactive.length - 1; i >= 0; i--) {
                if (interactive[i].userData?.panel) interactive.splice(i, 1);
            }
            if (resetInfoAnchor) infoPanelAnchor = null;
            if (!keepTourMapFrame) tourMapFrame = null;
            
            // Resume gaze detection when panel is closed
            this._resumeGazeDetection();
        };

        const showVRGazeTooltip = (title, subtitle, status = '') => {
            clearGroup(gazeTooltipGroup, { resetHover: false });
            if (!title) return;
            
            const lines = [title];
            if (subtitle) lines.push(subtitle);
            if (status) lines.push(status);
            const tex = makeTextTexture(lines, {
                width: 700,
                height: status ? 240 : (subtitle ? 200 : 140),
                background: 'rgba(0,73,30,0.95)',
                color: '#ffffff',
                font: status ? 'bold 34px sans-serif' : (subtitle ? 'bold 38px sans-serif' : 'bold 42px sans-serif'),
                lineHeight: 52,
                border: '#FFC600',
            });
            
            // Position above center of view
            const panel = makePlane(tex, new THREE.Vector3(0, 0.6, -2.5), { x: 2.2, y: status ? 0.78 : (subtitle ? 0.65 : 0.45) }, { action: 'noop' }, {
                depthTest: false,
                renderOrder: 99,
            });
            gazeTooltipGroup.add(panel);
        };

        const hideVRGazeTooltip = () => {
            clearGroup(gazeTooltipGroup, { resetHover: false });
        };

        const truncateXRText = (value, max = 64) => {
            const text = String(value || '').replace(/\s+/g, ' ').trim();
            if (text.length <= max) return text;
            return `${text.slice(0, max - 3).trim()}...`;
        };

        const showInfoPanel = async (hs, imageIndex = 0, keepPosition = false) => {
            closePanel(!keepPosition);
            if (!hs) return;
            
            // Pause gaze detection indefinitely while info panel is open
            this._pauseGazeDetection(Infinity);

            const hasVideo = hs.media_type === 'video' && hs.media_url;
            const videoId = hasVideo ? this._extractYouTubeId(hs.media_url) : null;
            const imageUrls = this._mediaImageUrls(hs);
            const hasImage = imageUrls.length > 0;
            const hasPreviewMedia = hasImage || !!videoId;
            const currentImageIndex = hasImage
                ? Math.max(0, Math.min(imageUrls.length - 1, imageIndex))
                : 0;
            const descriptionText = String(hs.description || '').replace(/\s+/g, ' ').trim();
            const lines = [
                truncateXRText(hs.title || 'Information', 56),
                hasImage ? '' : descriptionText,
                videoId ? 'Preview only in VR. Exit to watch on YouTube.' : (hasVideo ? 'Exit VR to open this video.' : ''),
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
                width: 760,
                height: hasImage ? 170 : (hasPreviewMedia ? 380 : 560),
                background: 'rgba(255,255,255,0.96)',
                color: '#111827',
                font: hasImage ? 'bold 24px sans-serif' : 'bold 30px sans-serif',
                lineHeight: hasImage ? 34 : 43,
                padding: hasImage ? 42 : 70,
                wrap: true,
                maxLines: hasImage ? 2 : (hasPreviewMedia ? 7 : 10),
                border: '#00491E',
            }), panelPosition(0, hasImage ? 1.04 : (hasPreviewMedia ? 0.78 : 0)), { x: hasImage ? 1.75 : 1.95, y: hasImage ? 0.4 : (hasPreviewMedia ? 0.96 : 1.45) }, { panel: true, action: 'noop' });
            panelGroup.add(panel);

            const imageCenterY = descriptionText ? 0.2 : 0.03;
            const imageMaxHeight = descriptionText ? 1.08 : 1.28;
            const descriptionCenterY = -0.68;
            const descriptionHeight = 0.42;
            const navigationHeight = 0.25;
            const closeHeight = 0.25;
            const verticalGap = 0.18;
            let imageHeightForLayout = imageMaxHeight;
            const buttons = [];
            if (hasImage) {
                const imageUrl = new URL(imageUrls[currentImageIndex], window.location.href).href;
                try {
                    const imageTexture = await loader.loadAsync(imageUrl);
                    imageTexture.colorSpace = THREE.SRGBColorSpace;
                    const image = imageTexture.image;
                    const aspect = image?.width && image?.height ? image.width / image.height : 16 / 9;
                    const maxWidth = 2.85;
                    const maxHeight = imageMaxHeight;
                    let imageWidth = maxWidth;
                    let imageHeight = imageWidth / aspect;
                    if (imageHeight > maxHeight) {
                        imageHeight = maxHeight;
                        imageWidth = imageHeight * aspect;
                    }
                    imageHeightForLayout = imageHeight;
                    const imagePlane = makePlane(imageTexture, panelPosition(0, imageCenterY), { x: imageWidth, y: imageHeight }, { panel: true, action: 'noop' });
                    panelGroup.add(imagePlane);
                    clearGroup(statusGroup);
                } catch (error) {
                    console.error('WebXR info image load failed:', error);
                }

                if (descriptionText) {
                    const descriptionPanel = makePlane(makeTextTexture(descriptionText, {
                        width: 900,
                        height: 160,
                        background: 'rgba(255,255,255,0.96)',
                        color: '#374151',
                        font: 'bold 26px sans-serif',
                        lineHeight: 38,
                        padding: 48,
                        wrap: true,
                        maxLines: 3,
                        border: 'rgba(0,73,30,0.28)',
                    }), panelPosition(0, -0.68), { x: 2.45, y: 0.42 }, { panel: true, action: 'noop' });
                    panelGroup.add(descriptionPanel);
                }

                if (imageUrls.length > 1) {
                    const navigationY = descriptionText
                        ? descriptionCenterY - (descriptionHeight / 2) - verticalGap - (navigationHeight / 2)
                        : imageCenterY - (imageHeightForLayout / 2) - verticalGap - (navigationHeight / 2);
                    buttons.push(makePlane(makeTextTexture('Prev', {
                        width: 360,
                        height: 100,
                        background: '#FFC600',
                        color: '#00491E',
                        font: 'bold 34px sans-serif',
                    }), panelPosition(-0.95, navigationY), { x: 0.9, y: navigationHeight }, {
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
                    }), panelPosition(0.95, navigationY), { x: 0.9, y: navigationHeight }, {
                        panel: true,
                        action: 'info-image',
                        hotspot: hs,
                        imageIndex: (currentImageIndex + 1) % imageUrls.length,
                    }));
                }

            } else if (videoId) {
                const thumbnailUrl = this._buildYouTubeThumbnailUrl(videoId);
                try {
                    const thumbnailTexture = await loader.loadAsync(thumbnailUrl);
                    thumbnailTexture.colorSpace = THREE.SRGBColorSpace;
                    const previewPlane = makePlane(thumbnailTexture, panelPosition(0, -0.12), { x: 2.35, y: 1.32 }, { panel: true, action: 'noop' });
                    panelGroup.add(previewPlane);

                    const playBadge = makePlane(makeTextTexture('Preview Only in VR', {
                        width: 520,
                        height: 110,
                        background: 'rgba(17,24,39,0.88)',
                        color: '#ffffff',
                        font: 'bold 38px sans-serif',
                        border: '#ef4444',
                    }), panelPosition(0, -0.58), { x: 1.5, y: 0.3 }, { panel: true, action: 'noop' }, {
                        transparent: true,
                        opacity: 0.98,
                    });
                    panelGroup.add(playBadge);
                    clearGroup(statusGroup);
                } catch (error) {
                    console.error('WebXR YouTube thumbnail load failed:', error);
                }
            }

            if (hasVideo) {
                const videoUrl = videoId ? this._buildYouTubeEmbedUrl(videoId, { autoplay: true }) : hs.media_url;
                buttons.push(makePlane(makeTextTexture(videoId ? 'Exit VR and Watch on YouTube' : 'Exit VR and Open Video', {
                    width: 760,
                    height: 120,
                    background: '#FFC600',
                    color: '#00491E',
                    font: 'bold 40px sans-serif',
                }), panelPosition(0, -1.05), { x: 2.15, y: 0.38 }, { panel: true, action: 'open-url', url: videoUrl }));
            }

            const closeY = hasImage
                ? (buttons.length > 1
                    ? (descriptionText
                        ? descriptionCenterY - (descriptionHeight / 2) - verticalGap - navigationHeight - verticalGap - (closeHeight / 2)
                        : imageCenterY - (imageHeightForLayout / 2) - verticalGap - navigationHeight - verticalGap - (closeHeight / 2))
                    : (descriptionText ? -1.3 : -1.02))
                : (hasVideo ? -1.5 : -1.05);
            buttons.push(makePlane(makeTextTexture('Close', {
                width: 360,
                height: 100,
                background: '#374151',
                color: '#ffffff',
                font: 'bold 34px sans-serif',
            }), panelPosition(0, closeY), { x: 0.9, y: hasImage ? closeHeight : 0.25 }, { panel: true, action: 'close-panel' }));

            panelGroup.add(...buttons);
        };

        const showRoomInfoPanel = () => {
            closePanel();
            if (!this.currentRoomType) return;
            
            // Pause gaze detection indefinitely while room info panel is open
            this._pauseGazeDetection(Infinity);
            
            const rt = this.currentRoomType;
            const count = rt.available_rooms_count;
            const availabilityLabel = rt.availability_label || (count != null ? `${count} room(s) available` : 'Availability available in form');
            const amenities = (rt.amenities || []).map(a => a.name).filter(Boolean);
            const amenitiesText = amenities.length
                ? `Amenities: ${amenities.slice(0, 3).join(', ')}${amenities.length > 3 ? '...' : ''}`
                : '';
            const description = truncateXRText(rt.description || '', 52);
            const lines = [
                rt.name || 'Room Type',
                rt.room_sharing_type || '',
                rt.pricing_display || rt.formatted_price || '',
                availabilityLabel,
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

            const request = makePlane(makeTextTexture('Exit VR to Request a Stay', {
                width: 512,
                height: 120,
                background: '#FFC600',
                color: '#00491E',
                font: 'bold 40px sans-serif',
            }), center.clone().add(new THREE.Vector3(0, -1.05, 0)), { x: 1.6, y: 0.38 }, { panel: true, action: 'reservation' });
            const full = makePlane(makeTextTexture('Exit VR for Full Form', {
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

        const getViewPanelFrame = (distance = 3.15) => {
            const up = new THREE.Vector3(0, 1, 0);
            const forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camera.quaternion);
            forward.y = 0;
            if (forward.lengthSq() < 0.001) forward.set(0, 0, -1);
            forward.normalize();
            const right = new THREE.Vector3(1, 0, 0).applyQuaternion(camera.quaternion);
            right.y = 0;
            if (right.lengthSq() < 0.001) right.crossVectors(forward, up);
            right.normalize();
            const center = camera.position.clone().add(forward.multiplyScalar(distance));
            return {
                position: (x = 0, y = 0) => center.clone().add(right.clone().multiplyScalar(x)).add(up.clone().multiplyScalar(y)),
            };
        };

        const showTourMapPanel = (page = tourMapPage) => {
            // Preserve the existing frame so paginating doesn't shift the panel
            const isPaginating = tourMapFrame !== null;
            closePanel(true, isPaginating);
            this._pauseGazeDetection(Infinity);

            const totalPages = Math.max(1, Math.ceil(this.waypoints.length / XR_MAP_PAGE_SIZE));
            tourMapPage = ((page % totalPages) + totalPages) % totalPages;
            const start = tourMapPage * XR_MAP_PAGE_SIZE;
            const pageWaypoints = this.waypoints.slice(start, start + XR_MAP_PAGE_SIZE);
            if (!tourMapFrame) tourMapFrame = getViewPanelFrame(3.05);
            const frame = tourMapFrame;
            const currentName = this.currentWaypoint?.name || 'Current scene';

            const header = makePlane(makeTextTexture([
                'Tour Map',
                `Current: ${truncateXRText(currentName, 34)}`,
                `Page ${tourMapPage + 1} of ${totalPages}`,
            ], {
                width: 760,
                height: 210,
                background: 'rgba(0,73,30,0.96)',
                color: '#ffffff',
                font: 'bold 32px sans-serif',
                lineHeight: 50,
                padding: 54,
                wrap: true,
                maxLines: 3,
                border: '#FFC600',
            }), frame.position(0, 0.96), { x: 2.25, y: 0.62 }, { panel: true, action: 'noop' });
            panelGroup.add(header);

            pageWaypoints.forEach((wp, index) => {
                const isActive = wp.slug === this.currentWaypoint?.slug;
                const row = makePlane(makeTextTexture([
                    truncateXRText(wp.name || 'Scene', 36),
                    wp.type_label || '',
                ].filter(Boolean), {
                    width: 760,
                    height: 135,
                    background: isActive ? '#FFC600' : 'rgba(17,24,39,0.9)',
                    color: isActive ? '#00491E' : '#ffffff',
                    font: 'bold 30px sans-serif',
                    lineHeight: 40,
                    padding: 48,
                    wrap: true,
                    maxLines: 2,
                    border: isActive ? '#00491E' : 'rgba(255,255,255,0.16)',
                }), frame.position(0, 0.36 - index * 0.36), { x: 2.25, y: 0.3 }, {
                    panel: true,
                    action: 'tour-map-scene',
                    slug: wp.slug,
                });
                panelGroup.add(row);
            });

            if (totalPages > 1) {
                const prev = makePlane(makeTextTexture('Prev Page', {
                    width: 380,
                    height: 100,
                    background: '#374151',
                    color: '#ffffff',
                    font: 'bold 30px sans-serif',
                }), frame.position(-0.72, -1.52), { x: 0.9, y: 0.25 }, {
                    panel: true,
                    action: 'tour-map-page',
                    page: tourMapPage - 1,
                });
                const next = makePlane(makeTextTexture('Next Page', {
                    width: 380,
                    height: 100,
                    background: '#FFC600',
                    color: '#00491E',
                    font: 'bold 30px sans-serif',
                }), frame.position(0.72, -1.52), { x: 0.9, y: 0.25 }, {
                    panel: true,
                    action: 'tour-map-page',
                    page: tourMapPage + 1,
                });
                panelGroup.add(prev, next);
            }

            const close = makePlane(makeTextTexture('Close', {
                width: 320,
                height: 95,
                background: '#111827',
                color: '#ffffff',
                font: 'bold 30px sans-serif',
            }), frame.position(0, totalPages > 1 ? -1.88 : -1.52), { x: 0.72, y: 0.23 }, {
                panel: true,
                action: 'close-panel',
            });
            panelGroup.add(close);
        };

        const rebuildHotspots = () => {
            clearGroup(hotspotGroup);
            interactive.length = 0;
            xrHotspotObjects.clear();
            closePanel();
            const spots = (this.currentWaypoint?.hotspots || []).filter(h => h.is_active !== false);
            spots.forEach((hs) => {
                const color = HOTSPOT_COLORS[hs.action_type] || '#6b7280';
                const sizeScale = { 1: 0.6, 2: 0.8, 3: 1.0, 4: 1.25, 5: 1.5 }[hs.size || 3] ?? 1.0;
                const planeSize = 1.0 * sizeScale; // Increased from 0.62 for better VR visibility
                const hotspot = makePlane(makeHotspotTexture(hs.icon || (hs.action_type === 'previous-scene' ? 'chevron-left' : 'chevron-up'), {
                    background: color,
                }), yawPitchToVector(hs.yaw, hs.pitch), { x: planeSize, y: planeSize }, { action: 'hotspot', hotspot: hs });
                hotspotGroup.add(hotspot);
                xrHotspotObjects.set(hs.id, hotspot);
            });
            if (this.currentWaypoint?.is_room_related && this.currentWaypoint?.linked_room_type_id) {
                const yaw = this.currentWaypoint.room_info_yaw ?? this.currentWaypoint.default_yaw ?? 0;
                const pitch = this.currentWaypoint.room_info_pitch ?? ((this.currentWaypoint.default_pitch ?? 0) + 15);
            const roomInfo = makePlane(makeTextTexture(['View Details', 'and Request'], {
                    width: 420,
                    height: 120,
                    background: '#00491E',
                    color: '#FFC600',
                    border: '#FFC600',
                    font: 'bold 36px sans-serif',
                    lineHeight: 40,
                }), yawPitchToVector(yaw, pitch), XR_ROOM_SCALE, { action: 'room-info' });
                hotspotGroup.add(roomInfo);
            }

            const mapYaw = Number.isFinite(parseFloat(this.currentWaypoint?.default_yaw))
                ? parseFloat(this.currentWaypoint.default_yaw) - 35
                : -35;
            const mapAnchor = makePlane(makeTextTexture('Tour Map', {
                width: 700,
                height: 210,
                background: '#00491E',
                color: '#FFC600',
                border: '#FFC600',
                font: 'bold 58px sans-serif',
            }), yawPitchToVector(mapYaw, -28), { x: 2.15, y: 0.62 }, {
                action: 'tour-map-toggle',
            });
            hotspotGroup.add(mapAnchor);
        };

        const loadXRWaypoint = async (slug, options = {}) => {
            const { trackSceneHistory = true, restoreView = null } = options;
            const wp = this.waypoints.find(w => w.slug === slug);
            if (!wp?.panorama_image) return;
            const sceneHistoryEntry = trackSceneHistory
                ? this._createSceneHistoryEntry(wp.slug, {
                    yaw: contentGroup.rotation.y - Math.PI,
                })
                : null;
            const restoredView = this._normalizeSceneView(restoreView);
            this.currentWaypoint = wp;
            contentGroup.rotation.y = (restoredView?.yaw ?? THREE.MathUtils.degToRad(parseFloat(wp.default_yaw) || 0)) + Math.PI;
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
                if (sceneHistoryEntry) {
                    this._pushSceneHistoryEntry(sceneHistoryEntry);
                }
                rebuildHotspots();
            } catch (error) {
                console.error('WebXR panorama load failed:', error);
                showXRStatus(['Could not load panorama', 'Exit VR and try again'], 'error');
            }
        };

        const handleXRAction = async (target) => {
            const data = target?.userData || {};
            
            // Pause gaze detection when action is triggered
            this._pauseGazeDetection();

            // Actions that leave WebXR: reservation, reserve-page, open-url,
            // and external-link hotspots. Scene, map, panel, image, and
            // bookmark actions stay inside the immersive session.
            
            const launchExternalFromXR = async (url) => {
                const safeUrl = this._normalizeHttpUrl(url);
                if (!safeUrl) {
                    this._showToast('This link is unavailable or invalid.', 'error');
                    return;
                }

                // Reserve the browsing context while the XR click is still a trusted gesture.
                const popup = window.open('about:blank', '_blank');
                await this.stopWebXRTest();

                if (popup && !popup.closed) {
                    try {
                        popup.opener = null;
                        popup.location.replace(safeUrl);
                        popup.focus?.();
                        return;
                    } catch (error) {
                        console.warn('Popup navigation failed, falling back to same-tab navigation.', error);
                    }
                }

                window.location.href = safeUrl;
            };

            if (data.action === 'room-info') {
                showRoomInfoPanel();
            } else if (data.action === 'close-panel') {
                closePanel();
            } else if (data.action === 'tour-map-toggle') {
                if (panelGroup.children.some(child => child.userData?.action === 'tour-map-scene' || child.userData?.action === 'tour-map-page')) {
                    closePanel();
                } else {
                    showTourMapPanel();
                }
            } else if (data.action === 'tour-map-page') {
                showTourMapPanel(data.page || 0);
            } else if (data.action === 'tour-map-scene' && data.slug) {
                closePanel();
                await loadXRWaypoint(data.slug);
            } else if (data.action === 'reservation') {
                await this.exitVRToReservationModal();
            } else if (data.action === 'reserve-page') {
                await this.exitVRToReservationPage();
            } else if (data.action === 'open-url' && data.url) {
                await launchExternalFromXR(data.url);
            } else if (data.action === 'info-image' && data.hotspot) {
                await showInfoPanel(data.hotspot, data.imageIndex || 0, true);
            } else if (data.action === 'hotspot') {
                const hs = data.hotspot;
                if (hs?.action_type === 'navigate' && hs.action_target) {
                    // Pause gaze detection temporarily for navigation
                    this._pauseGazeDetection(1000);
                    await loadXRWaypoint(hs.action_target);
                } else if (hs?.action_type === 'previous-scene') {
                    this._pauseGazeDetection(1000);
                    const target = this._getPreviousSceneTarget();
                    if (target?.slug) {
                        await loadXRWaypoint(target.slug, {
                            trackSceneHistory: false,
                            restoreView: target.restoreView,
                        });
                    } else {
                        this._showToast('No previous scene available.', 'info');
                    }
                } else if (hs?.action_type === 'info') {
                    await showInfoPanel(hs);
                } else if (hs?.action_type === 'external-link' && hs.action_target) {
                    await launchExternalFromXR(hs.action_target);
                } else if (hs?.action_type === 'bookmark') {
                    // Pause gaze detection temporarily for bookmark
                    this._pauseGazeDetection(1000);
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

        const getGazeHit = () => {
            const viewerPose = getXRViewerPose();
            raycaster.ray.origin.copy(viewerPose.origin);
            raycaster.ray.direction.copy(viewerPose.direction);
            return raycaster.intersectObjects(interactive, false)[0] || null;
        };

        const selectFromGaze = () => {
            const hit = getGazeHit();
            if (hit?.object) handleXRAction(hit.object);
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
            // Only override hover when a controller positively hits something.
            // When no controller is active (e.g. phone VR), let the gaze system
            // manage hover state to prevent per-frame flicker.
            if (bestHit?.object) setHoveredObject(bestHit.object);
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
        renderer.domElement.addEventListener('pointerup', selectFromGaze);

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
            renderer.domElement.removeEventListener('pointerup', selectFromGaze);
            closePanel();
            clearGroup(hotspotGroup);
            clearGroup(statusGroup);
            clearGroup(gazeTooltipGroup);
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
            this._gazeCheckEnabled = false;
            this._hideGazeTooltip();
            
            // Restore the panorama viewer
            if (this.currentWaypoint?.slug) {
                this.navigateToWaypoint(this.currentWaypoint.slug).catch(() => {});
            }
        };

        session.addEventListener('end', cleanup, { once: true });
        try {
            await renderer.xr.setSession(session);
        } catch (error) {
            cleanup();
            throw error;
        }
        
        // Enable gaze detection for VR mode
        this._gazeCheckEnabled = true;

        this._webXRTest = { 
            session, 
            cleanup, 
            showVRGazeTooltip, 
            hideVRGazeTooltip,
            clearVRGazeFocus: () => setHoveredObject(null),
        };
        
        // Helper to check VR gaze based on the center view between the XR eyes.
        // Using the base camera direction can bias targeting toward one eye in
        // stereo rendering, which makes users feel like they must "aim left"
        // or "aim right" to hit hotspots in phone-mounted VR.
        const cameraDirection = new THREE.Vector3();
        const xrCameraDirection = new THREE.Vector3();
        const leftEyeDirection = new THREE.Vector3();
        const rightEyeDirection = new THREE.Vector3();
        const xrCameraOrigin = new THREE.Vector3();
        const leftEyeOrigin = new THREE.Vector3();
        const rightEyeOrigin = new THREE.Vector3();
        const getXRViewerPose = () => {
            const xrCamera = renderer.xr.getCamera(camera);

            if (xrCamera?.isArrayCamera && xrCamera.cameras?.length >= 2) {
                const [leftEye, rightEye] = xrCamera.cameras;

                leftEye.getWorldDirection(leftEyeDirection);
                rightEye.getWorldDirection(rightEyeDirection);
                xrCameraDirection
                    .copy(leftEyeDirection)
                    .add(rightEyeDirection)
                    .normalize();

                leftEyeOrigin.setFromMatrixPosition(leftEye.matrixWorld);
                rightEyeOrigin.setFromMatrixPosition(rightEye.matrixWorld);
                xrCameraOrigin
                    .copy(leftEyeOrigin)
                    .add(rightEyeOrigin)
                    .multiplyScalar(0.5);

                return {
                    origin: xrCameraOrigin,
                    direction: xrCameraDirection,
                };
            }

            camera.getWorldDirection(cameraDirection);
            xrCameraOrigin.setFromMatrixPosition(camera.matrixWorld);

            return {
                origin: xrCameraOrigin,
                direction: cameraDirection,
            };
        };

        const checkVRGaze = () => {
            if (!this._gazeCheckEnabled) {
                setHoveredObject(null);
                this._syncGazeTarget(null, { vr: true });
                return;
            }
            // Get the midpoint gaze direction for the XR viewer, not a single eye.
            const viewerPose = getXRViewerPose();
            const viewerDirection = viewerPose.direction;
            raycaster.ray.origin.copy(viewerPose.origin);
            raycaster.ray.direction.copy(viewerDirection);

            const uiObjects = interactive.filter(object => XR_GAZE_UI_ACTIONS.has(object.userData?.action));
            const uiHit = uiObjects.length ? raycaster.intersectObjects(uiObjects, false)[0] : null;
            if (uiHit?.object) {
                const data = uiHit.object.userData || {};
                const wp = data.slug ? this.waypoints.find(waypoint => waypoint.slug === data.slug) : null;
                const targetId = data.slug || String(data.page ?? '');
                const uiGazeLabels = {
                    'tour-map-toggle': 'Tour Map',
                    'tour-map-page': 'Change page',
                    'tour-map-scene': wp?.name || 'Tour scene',
                    'close-panel': 'Close',
                    'info-image': 'Image control',
                    'open-url': 'Open link',
                    'reservation': 'Reservation',
                    'reserve-page': 'Reservation',
                    'room-info': 'View Details and Request',
                };
                const target = {
                    id: `xr-ui-${data.action}-${targetId}`,
                    title: uiGazeLabels[data.action] || '',
                    action_type: data.action,
                };
                setHoveredObject(uiHit.object);
                this._syncGazeTarget(target, {
                    vr: true,
                    suppressTooltip: true,
                });
                return;
            }

            const hotspots = this.currentWaypoint?.hotspots;
            if (!hotspots?.length) {
                setHoveredObject(null);
                this._syncGazeTarget(null, { vr: true });
                return;
            }

            const activeHotspots = hotspots.filter(h => h.is_active !== false);
            if (!activeHotspots.length) {
                setHoveredObject(null);
                this._syncGazeTarget(null, { vr: true });
                return;
            }
            
            // Find the hotspot closest to camera's look direction
            const GAZE_THRESHOLD = 0.15; // ~8.6 degrees = cos(8.6°) ≈ 0.988
            const GAZE_DOT_THRESHOLD = Math.cos(GAZE_THRESHOLD);
            
            let closestHotspot = null;
            let bestDotProduct = GAZE_DOT_THRESHOLD;
            
            for (const hs of activeHotspots) {
                // Get hotspot position in world space
                const hotspotWorldPos = yawPitchToVector(hs.yaw, hs.pitch);
                hotspotWorldPos.applyQuaternion(contentGroup.quaternion);
                hotspotWorldPos.normalize();
                
                // Calculate dot product (1 = perfect alignment, 0 = perpendicular)
                const dotProduct = viewerDirection.dot(hotspotWorldPos);
                
                if (dotProduct > bestDotProduct) {
                    bestDotProduct = dotProduct;
                    closestHotspot = hs;
                }
            }
            
            // Hotspot gaze/dwell is skipped while a panel is open (cooldown active)
            if (this._gazeCooldown) {
                setHoveredObject(null);
                this._syncGazeTarget(null, { vr: true });
                return;
            }

            // Use _syncGazeTarget to handle auto-activation logic
            setHoveredObject(closestHotspot ? xrHotspotObjects.get(closestHotspot.id) || null : null);
            this._syncGazeTarget(closestHotspot, { vr: true });
        };
        
        renderer.setAnimationLoop(() => {
            cameraFollower.position.copy(camera.position);
            cameraFollower.quaternion.copy(camera.quaternion);
            updateBillboards();
            updateControllerHover();
            checkVRGaze();
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
            if (this._tourGuideActive) {
                window.setTimeout(() => this._showTourGuideStep(), 500);
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
                checkButton.setAttribute('onclick', 'tourEngine._checkSpecificRoomAvailability(this)');
            } else {
                checkButton.setAttribute('onclick', `tourEngine._checkDateAvailability(${data.id}, this)`);
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
                    const roomTypeName = this._escapeHtml(data.room_type.name || 'this type');
                    
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
                    `<span class="inline-block bg-[#FFC600] text-[#00491E] text-xs px-2 py-1 rounded-full mr-2 mb-2">${this._escapeHtml(a.name)}</span>`
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
            const canAccommodate = data.can_accommodate_requested_guests ?? (count > 0);
            const avail = ov.querySelector('.availability-badge');
            if (avail) {
                avail.textContent = data.availability_label || (count != null ? `${count} room(s) available` : '');
                avail.className = 'availability-badge mt-3 text-sm font-semibold '
                    + (canAccommodate ? 'text-green-300' : 'text-red-300');
            }

            // Control button states based on availability (for room types)
            const requestBtn = ov.querySelector('#overlay-request-btn');
            const exploreBtn = ov.querySelector('#overlay-explore-btn');
            
            if (requestBtn) {
                if (!canAccommodate) {
                    // No rooms available - disable reservation, show explore alternative
                    requestBtn.disabled = true;
                    requestBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                    requestBtn.classList.remove('hover:bg-yellow-500');
                    requestBtn.textContent = '🏨 Not Available';
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
                    `<span class="inline-block bg-[#FFC600] text-[#00491E] text-xs px-2 py-1 rounded-full mr-2 mb-2">${this._escapeHtml(a.name)}</span>`
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
        this._ensureDefaultAvailabilityDates();
        this.tourGuideLayer?.classList.remove('is-visible');

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
            if (roomTypeIdEl) roomTypeIdEl.value = this.currentRoomType.id || '';
            this.onReservationOpened(this.currentRoomType);
        }
        
        // Pre-fill dates and occupants from the availability widget state
        const ciEl = document.getElementById('check_in_date');
        const coEl = document.getElementById('check_out_date');
        if (ciEl) {
            ciEl.min = this._todayString();
            ciEl.value = this._checkIn;
        }
        if (coEl) {
            coEl.min = this._addDays(this._checkIn, 1);
            coEl.value = this._checkOut;
        }
        if (this._guests > 1) {
            const gEl = document.getElementById('number_of_occupants');
            if (gEl) gEl.value = this._guests;
        }

        this._refreshReservationOccupantLimit();
    }

    async exitVRToReservationModal() {
        if (this._webXRTest) {
            await this.stopWebXRTest();
        }

        this.openReservationModal();
    }

    async exitVRToReservationPage() {
        if (this._webXRTest) {
            await this.stopWebXRTest();
        }

        this.goToReservationPage();
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
        if (this._tourGuideActive) {
            this._showTourGuideStep();
        }
    }

    async submitReservation(formData) {
        try {
            const errorContainer = document.getElementById('reservation-errors');
            if (errorContainer) {
                errorContainer.innerHTML = '';
            }

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
            if (errorContainer && (errors || data?.availability_warning || data?.message)) {
                const message = errors || data?.availability_warning || data?.message || fallbackMessage;
                errorContainer.innerHTML = `<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 whitespace-pre-line">${this._escapeHtml(message)}</div>`;
            }
            this._showToast(errors || data?.availability_warning || data?.message || fallbackMessage, 'error');
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
            const safeName = this._escapeHtml(wp.name);
            const safeTypeLabel = this._escapeHtml(wp.type_label || '');
            const btn = document.createElement('button');
            btn.dataset.slug = wp.slug;
            btn.className    = 'minimap-waypoint-btn w-full px-3 py-3 rounded-xl text-left text-sm';
            btn.setAttribute('role', 'menuitem');
            btn.setAttribute('aria-label', `Go to ${wp.name}`);
            btn.innerHTML    = `<div style="display:flex;align-items:flex-start;gap:10px">`
                             + `<span class="wp-here-dot" style="display:none;width:9px;height:9px;border-radius:50%;background:#22c55e;flex-shrink:0;margin-top:5px;box-shadow:0 0 0 4px rgba(34,197,94,0.16)" aria-hidden="true"></span>`
                             + `<div style="min-width:0;flex:1"><div class="minimap-waypoint-title" style="font-weight:600;color:#f9fafb;line-height:1.25">${safeName}</div>`
                             + (wp.type_label ? `<div class="minimap-waypoint-type" style="font-size:12px;color:rgba(229,231,235,0.68);margin-top:3px;line-height:1.2">${safeTypeLabel}</div>` : '')
                             + `</div></div>`;
            btn.addEventListener('click', () => {
                this.navigateToWaypoint(wp.slug);
                window.closeMobileTourMap?.();
            });
            list.appendChild(btn);
        });
    }

    highlightCurrentOnMinimap(slug) {
        const list = document.querySelector('#minimap .minimap-waypoints');
        if (!list) return;
        list.querySelectorAll('.minimap-waypoint-btn').forEach(el => {
            const isActive = el.dataset.slug === slug;
            el.classList.toggle('is-active', isActive);
            const dot = el.querySelector('.wp-here-dot');
            if (dot) dot.style.display = isActive ? 'block' : 'none';
            if (isActive) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    }

    updateProgressIndicator() {
        if (!this.currentWaypoint) return;
        if (this.progressIndicator) {
            const i = this.waypoints.findIndex(w => w.slug === this.currentWaypoint.slug);
            this.progressIndicator.textContent = `Stop ${i + 1} of ${this.waypoints.length}`;
        }
        if (this.navSceneName) {
            this.navSceneName.textContent = this.currentWaypoint.name || 'Current scene';
        }
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
        const SPEED = 0.02; // radians per frame for A/D/W/S rotation
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

            // W / S / ↑ / ↓ — smooth pitch pan (tilt up / down)
            if (['ArrowUp', 'ArrowDown', 'w', 'W', 's', 'S'].includes(k)) {
                e.preventDefault();
                held.add(k);
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
                if (held.has('ArrowLeft')  || held.has('a') || held.has('A')) yaw   -= SPEED;
                if (held.has('ArrowRight') || held.has('d') || held.has('D')) yaw   += SPEED;
                if (held.has('ArrowUp')    || held.has('w') || held.has('W')) pitch += SPEED;
                if (held.has('ArrowDown')  || held.has('s') || held.has('S')) pitch -= SPEED;
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
        if (!this.viewer || !this.currentWaypoint) return;

        const targets = (this.currentWaypoint.hotspots || []).filter(h => {
            if (h.is_active === false) return false;
            if (h.action_type === 'navigate') return Boolean(h.action_target);
            return h.action_type === 'previous-scene';
        });

        if (forward) {
            const roomInfoTarget = this._getRoomInfoKeyboardTarget();
            if (roomInfoTarget) targets.push(roomInfoTarget);
        }

        if (!targets.length) return;

        const currentYawRad = this.viewer.getPosition().yaw;
        const currentYawDeg = currentYawRad * 180 / Math.PI;

        // Normalize angle to -180 to +180
        const normalize = (angle) => {
            let a = angle % 360;
            if (a > 180) a -= 360;
            if (a < -180) a += 360;
            return a;
        };

        // Check if hotspot is in the correct hemisphere
        const isInHemisphere = (hotspotYaw) => {
            const relative = normalize(parseFloat(hotspotYaw) - currentYawDeg);
            // Forward: -90° to +90° relative to current view
            // Backward: +90° to +270° (or -90° to -270°) relative to current view
            return forward 
                ? (relative >= -90 && relative <= 90)
                : (relative < -90 || relative > 90);
        };

        // Filter targets to only those in the correct hemisphere
        const validTargets = targets.filter(h => isInHemisphere(h.yaw));
        if (!validTargets.length) {
            this._showToast(forward ? 'No scenes ahead' : 'No scenes behind', 'info');
            return;
        }

        // Shortest angular distance (handles -180/+180 wrap)
        const angDist = (a, b) => {
            let d = ((a - b) % 360 + 360) % 360;
            if (d > 180) d -= 360;
            return Math.abs(d);
        };

        const refYaw = forward ? currentYawDeg : currentYawDeg + 180;
        let best = null, bestDist = Infinity;
        for (const h of validTargets) {
            const dist = angDist(parseFloat(h.yaw), refYaw);
            if (dist < bestDist) { bestDist = dist; best = h; }
        }

        if (!best) return;

        if (best.action_type === 'previous-scene') {
            this.navigateToPreviousScene();
            return;
        }

        if (best.action_type === 'room-info') {
            if (this._roomInfoCardOpen) {
                this._closeInSceneCard();
            } else {
                this._openInSceneCard();
            }
            return;
        }

        this.navigateToWaypoint(best.action_target);
    }

    _getRoomInfoKeyboardTarget() {
        const wp = this.currentWaypoint;
        if (!wp?.is_room_related || !wp.linked_room_type_id || !this.currentRoomType) {
            return null;
        }

        return {
            id: 'room-info-marker',
            title: 'View Details and Request',
            yaw: wp.room_info_yaw ?? wp.default_yaw ?? 0,
            pitch: wp.room_info_pitch ?? ((wp.default_pitch ?? 0) + 15),
            action_type: 'room-info',
        };
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
        this._resetUIIdleTimer();

        this._runAutoTourStep();
        const profileLabel = AUTO_TOUR_PROFILES[this._autoTourProfile].label;
        this._showToast(`Auto Tour started (${profileLabel}) - cinematic pan enabled. Press Esc to stop.`, 'info');
    }

    stopAutoTour() {
        this._autoTourActive = false;
        this._clearAutoTourTimers();
        this._setAutoTourHud(false);
        this._syncAutoTourBtn(false);

        // Keep controls visible after Auto Tour; hiding is now only manual.
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
        const focalYaw = base?.yaw || 0;
        const basePitch = base?.pitch || 0;
        const yawSweep = 184 * Math.PI / 180;
        const startYaw = focalYaw - (yawSweep / 2);
        const pitchAmplitude = 5 * Math.PI / 180;
        const pitchLift = 1.5 * Math.PI / 180;

        const tick = (now) => {
            if (!this._autoTourActive || !this.viewer) return;

            const elapsed = now - start;
            const progress = Math.min(1, elapsed / this._autoTourPanMs);
            const eased = 0.5 - 0.5 * Math.cos(progress * Math.PI);
            const yaw = startYaw + (eased * yawSweep);
            const pitch = basePitch
                + Math.sin(progress * Math.PI) * pitchLift
                + Math.sin(progress * Math.PI * 2) * pitchAmplitude;
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
