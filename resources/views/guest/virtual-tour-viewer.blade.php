@extends('layouts.guest')

@section('title', 'Virtual Tour')

@push('styles')
<style>
    /* Tour Viewer Container */
    #tour-viewer {
        width: 100%;
        max-width: 1200px;
        height: 75vh;
        margin: 2rem auto;
        position: relative;
        background: #000;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }

    #tour-viewer:fullscreen,
    #tour-viewer:-webkit-full-screen {
        max-width: 100%;
        height: 100vh;
        margin: 0;
        border-radius: 0;
    }

    #panorama-container {
        width: 100%;
        height: 100%;
    }

    /* Loading Indicator */
    #loading-indicator {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 100;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        text-align: center;
    }

    .spinner {
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top: 4px solid #FFC600;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Room Info Overlay - HIDDEN (using in-scene card only) */
    #room-info-overlay {
        display: none !important;
    }

    .overlay-header {
        background: linear-gradient(135deg, #00491E 0%, #02681E 100%);
        color: white;
        padding: 1.5rem;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .overlay-content {
        padding: 1.5rem;
    }

    /* Mini-map */
    #minimap {
        position: absolute;
        bottom: 4rem;
        right: 1rem;
        background: white;
        opacity: 1;
        transition: opacity 0.4s ease-in-out;
        border-radius: 0.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        z-index: 40;
        max-height: 300px;
        overflow-y: auto;
        width: 200px;
    }

    #minimap.ui-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .minimap-waypoints {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        padding: 0.5rem;
    }

    .minimap-waypoint-btn {
        text-align: left;
        transition: all 0.2s;
    }

    .minimap-waypoint-btn:hover {
        background-color: #f3f4f6;
    }

    .minimap-waypoint-btn.bg-blue-500 {
        background-color: #3b82f6;
        color: white;
    }

    /* Progress Indicator */
    #progress-indicator {
        position: absolute;
        top: 1rem;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        z-index: 40;
    }

    /* Narration Tooltip */
    #narration-tooltip {
        position: absolute;
        bottom: 4rem;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.85);
        color: white;
        padding: 1rem 2rem;
        border-radius: 0.5rem;
        max-width: 600px;
        text-align: center;
        z-index: 40;
        display: none;
    }
    #narration-tooltip.visible {
        display: block;
    }

    /* Navigation Controls */
    .nav-controls {
        position: absolute;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 1rem;
        z-index: 40;
    }

    .nav-btn {
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 1rem;
    }

    .nav-btn:hover {
        background: rgba(0, 0, 0, 0.9);
    }

    .nav-btn:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }

    /* Exit Tour Button */
    .top-right-controls {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: flex;
        gap: 0.5rem;
        z-index: 50;
    }

    .top-right-controls button,
    .top-right-controls a {
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s;
        text-decoration: none;
    }

    .top-right-controls button:hover,
    .top-right-controls a:hover {
        background: rgba(0, 0, 0, 0.9);
    }

    .top-right-controls .exit-btn {
        background: rgba(220, 38, 38, 0.9);
    }

    .top-right-controls .exit-btn:hover {
        background: rgba(185, 28, 28, 1);
    }

    .top-right-controls .toggle-ui-btn {
        background: rgba(0, 0, 0, 0.7);
        width: 36px;
        height: 36px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-right-controls .toggle-ui-btn:hover {
        background: rgba(0, 0, 0, 0.9);
    }

    .top-right-controls .toggle-ui-btn svg {
        width: 20px;
        height: 20px;
    }

    .top-right-controls svg {
        width: 16px;
        height: 16px;
    }

    /* VR Controls */
    .vr-controls {
        position: absolute;
        top: 1rem;
        left: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        z-index: 50;
        opacity: 1;
        transition: opacity 0.4s ease-in-out;
    }

    .vr-controls.ui-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .vr-btn {
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .vr-btn:hover {
        background: rgba(0, 0, 0, 0.9);
    }

    .vr-btn.active {
        background: rgba(139, 92, 246, 0.9);
    }

    .vr-btn.active:hover {
        background: rgba(124, 58, 237, 1);
    }

    #auto-tour-hud {
        width: 170px;
        background: rgba(0, 0, 0, 0.72);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 0.5rem;
        padding: 0.45rem 0.55rem;
        color: #fff;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.28);
    }

    #auto-tour-countdown {
        font-size: 0.72rem;
        line-height: 1.2;
        margin-bottom: 0.35rem;
        opacity: 0.95;
        letter-spacing: 0.01em;
    }

    #auto-tour-progress {
        width: 100%;
        height: 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }

    #auto-tour-progress-fill {
        width: 0%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #fbbf24 0%, #34d399 100%);
        transition: width 100ms linear;
    }

    #auto-tour-settings {
        width: 170px;
        background: rgba(0, 0, 0, 0.62);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 0.5rem;
        padding: 0.45rem 0.55rem;
    }

    #auto-tour-settings label {
        display: block;
        font-size: 0.68rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 0.3rem;
        letter-spacing: 0.02em;
    }

    .auto-tour-speed-options {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.3rem;
    }

    .auto-tour-speed-btn {
        border: 1px solid rgba(255, 255, 255, 0.24);
        background: rgba(17, 24, 39, 0.86);
        color: #f3f4f6;
        border-radius: 0.35rem;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.33rem 0.2rem;
        line-height: 1;
        cursor: pointer;
        transition: all 0.18s ease;
    }

    .auto-tour-speed-btn:hover {
        background: rgba(31, 41, 55, 0.95);
    }

    .auto-tour-speed-btn.active,
    .auto-tour-speed-btn[aria-pressed="true"] {
        border-color: rgba(251, 191, 36, 0.9);
        background: rgba(251, 191, 36, 0.2);
        color: #fef3c7;
        box-shadow: 0 0 0 1px rgba(251, 191, 36, 0.22) inset;
    }

    #auto-tour-btn.active {
        background: rgba(239, 68, 68, 0.85);
    }
    #auto-tour-btn.active:hover {
        background: rgba(220, 38, 38, 0.95);
    }
    #minimap-search:focus {
        border-color: #3b82f6;
        outline: none;
    }

    .vr-btn svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    /* Reservation Modal */
    #reservation-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 60;
        align-items: center;
        justify-content: center;
        display: none;
    }

    .modal-content {
        background: white;
        border-radius: 0.75rem;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        background: linear-gradient(135deg, #00491E 0%, #02681E 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 0.75rem 0.75rem 0 0;
    }

    .modal-body {
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #374151;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 1rem;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-submit {
        background: #FFC600;
        color: #00491E;
        border: none;
        padding: 1rem 2rem;
        border-radius: 0.5rem;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        width: 100%;
        transition: all 0.2s;
    }

    .btn-submit:hover {
        background: #e6b200;
    }

    .btn-close-modal {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: transparent;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
    }

    /* Responsive */
    @media (max-width: 768px) {
        #room-info-overlay {
            width: 100vw;
        }

        #minimap {
            display: none;
        }

        .modal-content {
            width: 95%;
        }

        #auto-tour-hud {
            width: 160px;
            padding: 0.4rem 0.5rem;
        }

        #auto-tour-settings {
            width: 160px;
            padding: 0.4rem 0.5rem;
        }

        #auto-tour-countdown {
            font-size: 0.68rem;
        }
    }

    /* Room Info Button - HIDDEN (using in-scene card only) */
    #room-info-btn {
        display: none !important;
    }

    /* Panorama Viewer Marker Styling — injected by panorama-viewer.js for consistency */

    /* Help Button */
    #help-btn {
        background: rgba(0, 73, 30, 0.85) !important;
        transition: opacity 0.4s ease-in-out;
    }
    #help-btn:hover {
        background: rgba(0, 73, 30, 1) !important;
    }
    #help-btn.ui-hidden {
        opacity: 0;
        pointer-events: none;
    }

    /* Help Modal */
    .help-card {
        background: white;
        border-radius: 0.75rem;
        width: 100%;
        max-width: 660px;
        max-height: 87vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    }

    .help-header {
        background: linear-gradient(135deg, #00491E 0%, #02681E 100%);
        color: white;
        padding: 1.1rem 1.5rem;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        border-radius: 0.75rem 0.75rem 0 0;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .help-header h2 {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 0 0 0.125rem;
    }
    .help-header p {
        font-size: 0.78rem;
        opacity: 0.75;
        margin: 0;
    }
    .help-header > button {
        background: rgba(255,255,255,0.15) !important;
        border: none !important;
        color: white !important;
        width: 28px !important;
        height: 28px !important;
        min-width: 28px;
        border-radius: 50% !important;
        cursor: pointer;
        font-size: 1rem;
        line-height: 1;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0;
        padding: 0 !important;
        gap: 0 !important;
        transition: background 0.2s;
    }
    .help-header > button:hover {
        background: rgba(255,255,255,0.3) !important;
    }

    .help-body {
        padding: 1.25rem 1.5rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    .help-section h3 {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #00491E;
        margin: 0 0 0.625rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }
    .help-section h3 svg {
        width: 13px;
        height: 13px;
        flex-shrink: 0;
    }

    .help-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .help-list li {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        font-size: 0.8rem;
        line-height: 1.4;
        color: #374151;
    }
    .help-list li strong {
        color: #111827;
    }

    .help-icon {
        font-size: 0.95rem;
        flex-shrink: 0;
        margin-top: 0.05rem;
        line-height: 1;
    }

    .help-kbd {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 26px;
        height: 22px;
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-bottom-width: 2px;
        border-radius: 4px;
        font-size: 0.68rem;
        font-weight: 700;
        font-family: ui-monospace, monospace;
        color: #374151;
        flex-shrink: 0;
        padding: 0 3px;
        margin-top: 0.05rem;
    }

    .help-markers {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .help-marker-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.8rem;
        color: #374151;
    }
    .help-marker-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .help-marker-dot.nav {
        background: #3b82f6;
        border: 2px solid white;
        box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    }
    .help-marker-dot.info {
        background: #FFC600;
        border: 2px solid white;
        box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    }
    .help-marker-dot.room {
        background: linear-gradient(135deg, #00491E, #02681E);
        border: 2px solid #FFC600;
        box-shadow: 0 1px 4px rgba(0,0,0,0.3);
        border-radius: 999px;
    }

    .help-footer {
        padding: 0.875rem 1.5rem;
        border-top: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        position: sticky;
        bottom: 0;
        background: white;
        border-radius: 0 0 0.75rem 0.75rem;
    }
    .help-dont-show {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.78rem;
        color: #6b7280;
        cursor: pointer;
        user-select: none;
    }
    .help-close-btn {
        background: linear-gradient(135deg, #00491E 0%, #02681E 100%);
        color: white;
        border: none;
        padding: 0.6rem 1.25rem;
        border-radius: 0.5rem;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .help-close-btn:hover {
        opacity: 0.9;
    }

    @media (max-width: 560px) {
        .help-body {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<!-- Tour Viewer -->
<div id="tour-viewer">
    <!-- Loading Indicator -->
    <div id="loading-indicator" class="hidden">
        <div class="spinner"></div>
        <p class="text-white">Loading panorama...</p>
    </div>

    <!-- Panorama Container -->
    <div id="panorama-container"></div>

    <!-- Progress Indicator -->
    <div id="progress-indicator" class="hidden">Stop 0 of 0</div>

    <!-- Navigation Controls -->
    <div class="nav-controls">
        <button id="nav-previous" class="nav-btn" onclick="tourEngine.navigatePrevious()">
            ← Previous
        </button>
        <button id="nav-next" class="nav-btn" onclick="tourEngine.navigateNext()">
            Next →
        </button>
    </div>

    <!-- Top-right controls -->
    <div class="top-right-controls">
        <button id="help-btn" onclick="openTourHelp()" title="How to navigate this tour">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17" stroke-width="3"/>
            </svg>
            <span>Help</span>
        </button>
        <button onclick="toggleFullscreen()" title="Toggle fullscreen">
            <svg id="fs-expand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
            </svg>
            <svg id="fs-compress-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                <polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/>
            </svg>
            <span id="fs-btn-text">Fullscreen</span>
        </button>
        <button id="toggle-ui-btn" class="toggle-ui-btn" onclick="tourEngine.toggleUIVisibility()" title="Hide/Show controls (H)">
            <svg id="ui-hide-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="ui-show-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        </button>
        <button class="exit-btn" onclick="window.location.href='{{ route('guest.virtual-tours') }}'">
            ✕ Exit Tour
        </button>
        <button id="room-info-btn" onclick="tourEngine.toggleRoomInfoOverlay()" title="View room information">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Room Info</span>
        </button>
    </div>

    <!-- Stereo Controls -->
    <div class="vr-controls">
        <button id="vr-mode-btn" class="vr-btn" onclick="toggleVRMode()" title="Split-screen stereo panorama mode (mobile/headset browser)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 8a2 2 0 012-2h16a2 2 0 012 2v8a2 2 0 01-2 2h-4.764l-1.427-2.14a2 2 0 00-3.618 0L8.764 18H4a2 2 0 01-2-2V8z"/>
                <circle cx="8" cy="12" r="2"/>
                <circle cx="16" cy="12" r="2"/>
            </svg>
            <span id="vr-btn-text">Stereo Mode</span>
        </button>
        <button id="gyro-btn" class="vr-btn" onclick="toggleGyro()" title="Enable gyroscope control (tilt device to look around)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                <line x1="12" y1="18" x2="12.01" y2="18"/>
            </svg>
            <span id="gyro-btn-text">Gyroscope</span>
        </button>
        <button id="auto-tour-btn" class="vr-btn" onclick="tourEngine.toggleAutoTour()" title="Auto-advance with gentle panning and a visible countdown">
            <svg id="auto-tour-play-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="5 3 19 12 5 21 5 3"/>
            </svg>
            <svg id="auto-tour-stop-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                <rect x="6" y="6" width="12" height="12" rx="1" ry="1"/>
            </svg>
            <span id="auto-tour-btn-text">Auto Tour</span>
        </button>
        <div id="auto-tour-settings">
            <label id="auto-tour-speed-label">Tour Speed</label>
            <div class="auto-tour-speed-options" role="group" aria-labelledby="auto-tour-speed-label" aria-label="Auto tour speed">
                <button type="button" class="auto-tour-speed-btn" data-profile="fast" aria-pressed="false">Fast</button>
                <button type="button" class="auto-tour-speed-btn" data-profile="normal" aria-pressed="true">Normal</button>
                <button type="button" class="auto-tour-speed-btn" data-profile="slow" aria-pressed="false">Slow</button>
            </div>
        </div>
        <div id="auto-tour-hud" class="hidden" aria-live="polite" aria-atomic="true">
            <div id="auto-tour-countdown">Auto Tour idle</div>
            <div id="auto-tour-progress" role="progressbar" aria-label="Time before next scene">
                <div id="auto-tour-progress-fill"></div>
            </div>
        </div>
    </div>

    <!-- Mini-map -->
    <div id="minimap" class="hidden">
        <div class="p-3 border-b border-gray-200">
            <h3 class="font-bold text-sm text-gray-900" style="margin-bottom:6px">Tour Map</h3>
            <input id="minimap-search" type="text" placeholder="Search scenes…" oninput="filterMinimapScenes(this.value)"
                   style="width:100%;padding:4px 8px;font-size:11px;border:1px solid #e5e7eb;border-radius:5px;background:#f9fafb;">
        </div>
        <div class="minimap-waypoints"></div>
    </div>

    <!-- Narration Tooltip -->
    <div id="narration-tooltip">
        <p class="narration-text"></p>
    </div>

    <!-- Room Info Overlay -->
    {{-- Room Info Overlay - REMOVED (using in-scene card only) --}}

    <!-- Reservation Modal -->
    @if(!request()->has('preview'))
    <div id="reservation-modal" class="hidden">
        <div class="modal-content relative">
            <div class="modal-header">
                <h2 class="text-2xl font-bold">Request Reservation</h2>
                <p class="text-sm opacity-90 mt-1"><span id="modal-room-name">Selected room type</span></p>
                <button onclick="tourEngine.closeReservationModal()" class="btn-close-modal">
                    ✕
                </button>
            </div>

            <div class="modal-body">
                <div id="reservation-errors" class="mb-4"></div>

                <form id="reservation-form" onsubmit="handleReservationSubmit(event)">
                    <input type="hidden" id="preferred_room_type_id" name="preferred_room_type_id">
                    <input type="hidden" id="preferred_room_id" name="preferred_room_id">
                    <input type="hidden" name="source" value="virtual_tour">
                    @honeypot

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="guest_first_name">First Name *</label>
                            <input type="text" id="guest_first_name" name="guest_first_name" required>
                        </div>

                        <div class="form-group">
                            <label for="guest_last_name">Last Name *</label>
                            <input type="text" id="guest_last_name" name="guest_last_name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="guest_email">Email *</label>
                        <input type="email" id="guest_email" name="guest_email" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="guest_phone">Phone</label>
                            <input type="tel" id="guest_phone" name="guest_phone">
                        </div>

                        <div class="form-group">
                            <label for="guest_age">Age</label>
                            <input type="number" id="guest_age" name="guest_age" min="1" max="120">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="guest_gender">Gender *</label>
                        <select id="guest_gender" name="guest_gender" required>
                            <option value="">Select...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="check_in_date">Check-in Date *</label>
                            <input type="date" id="check_in_date" name="check_in_date" required>
                        </div>

                        <div class="form-group">
                            <label for="check_out_date">Check-out Date *</label>
                            <input type="date" id="check_out_date" name="check_out_date" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="number_of_occupants">Number of Occupants *</label>
                        <input type="number" id="number_of_occupants" name="number_of_occupants" min="1" max="20" value="1" required>
                    </div>

                    <div class="form-group">
                        <label for="guest_address">Address</label>
                        <textarea id="guest_address" name="guest_address" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="special_requests">Special Requests</label>
                        <textarea id="special_requests" name="special_requests" rows="3" 
                                  placeholder="Any special requirements or questions..."></textarea>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-blue-800">
                            <strong>ℹ️ Room Preference:</strong> Your selected room will be noted as a preference. 
                            Our staff will do their best to assign it during review, subject to availability.
                        </p>
                    </div>

                    <button type="submit" class="btn-submit">
                        Submit Reservation Request
                    </button>

                    <p class="text-xs text-gray-500 mt-4 text-center">
                        This will create a pending reservation. You'll receive a reference number to track and complete your booking.
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- Reservation Success Modal -->
    <div id="reservation-success-modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 70; display: none; align-items: center; justify-content: center;">
        <div class="modal-content text-center p-8">
            <div class="mb-4">
                <svg class="w-16 h-16 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Reservation Submitted!</h2>
            <p class="text-gray-600 mb-4">Your reservation request has been submitted successfully.</p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-gray-600 mb-1">Reference Number:</p>
                <p id="success-reference" class="text-2xl font-bold text-blue-600"></p>
            </div>
            <p class="text-sm text-gray-600 mb-2">Please save this reference number to track your reservation status.</p>
            <p class="text-xs text-blue-700 mb-6">
                ℹ️ Your room preference has been noted and will be considered during staff review.
            </p>
            <div class="space-y-3">
                <a id="success-track-link" href="#" class="block w-full bg-cmu-green text-white font-bold py-3 px-4 rounded-lg hover:bg-green-800 transition-colors">
                    Track Reservation
                </a>
                <button onclick="document.getElementById('reservation-success-modal').style.display='none'" 
                        class="block w-full bg-gray-200 text-gray-800 font-bold py-3 px-4 rounded-lg hover:bg-gray-300 transition-colors">
                    Continue Tour
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Tour Help Modal -->
    <div id="tour-help-modal" onclick="if(event.target===this)closeTourHelp()" aria-modal="true" role="dialog" aria-label="Tour navigation guide" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px);">
        <div class="help-card">
            <div class="help-header">
                <div>
                    <h2>How to Navigate the Tour</h2>
                    <p>Your 360° virtual tour guide</p>
                </div>
                <button onclick="closeTourHelp()" aria-label="Close guide">✕</button>
            </div>
            <div class="help-body">
                <!-- Looking Around -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/></svg>
                        Looking Around
                    </h3>
                    <ul class="help-list">
                        <li>
                            <span class="help-icon">🖱️</span>
                            <div><strong>Click &amp; Drag</strong><br>Hold and drag anywhere to look around the scene</div>
                        </li>
                        <li>
                            <span class="help-icon">📱</span>
                            <div><strong>Touch &amp; Swipe</strong><br>Swipe with one finger to pan the view on mobile</div>
                        </li>
                        <li>
                            <span class="help-icon">🔍</span>
                            <div><strong>Scroll / Pinch</strong><br>Scroll wheel or pinch two fingers to zoom in and out</div>
                        </li>
                        <li>
                            <span class="help-kbd">A</span><span class="help-kbd" style="margin-left:2px;">D</span>
                            <div><strong>A / D or ← / →</strong><br>Rotate the camera left or right</div>
                        </li>
                    </ul>
                </div>

                <!-- Moving Around -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M12 12v4m0 4h.01M8 20h8"/></svg>
                        Moving Between Spots
                    </h3>
                    <ul class="help-list">
                        <li>
                            <span class="help-kbd">W</span>
                            <div><strong>Move Forward</strong><br>Navigate toward the nearest marker you're facing</div>
                        </li>
                        <li>
                            <span class="help-kbd">S</span>
                            <div><strong>Move Backward</strong><br>Navigate toward the nearest marker behind you</div>
                        </li>
                        <li>
                            <span class="help-kbd">↑</span><span class="help-kbd" style="margin-left:2px;">↓</span>
                            <div><strong>Arrow Keys</strong><br>Same as W/S — step forward or backward</div>
                        </li>
                        <li>
                            <span class="help-icon">🔵</span>
                            <div><strong>Click Nav Markers</strong><br>Click the blue arrow markers to move to connected locations</div>
                        </li>
                        <li>
                            <span class="help-icon">⬅️ ➡️</span>
                            <div><strong>Previous / Next</strong><br>The bottom buttons step through the tour in sequence</div>
                        </li>
                    </ul>
                </div>

                <!-- Markers -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Scene Markers
                    </h3>
                    <div class="help-markers">
                        <div class="help-marker-row">
                            <div class="help-marker-dot nav"></div>
                            <div><strong>Blue arrow</strong> — Navigate to a linked spot</div>
                        </div>
                        <div class="help-marker-row">
                            <div class="help-marker-dot info"></div>
                            <div><strong>Yellow circle</strong> — See an information tooltip</div>
                        </div>
                        <div class="help-marker-row">
                            <div class="help-marker-dot room"></div>
                            <div><strong>Green pill</strong> — View room details &amp; pricing</div>
                        </div>
                    </div>
                </div>

                <!-- Controls Reference -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/></svg>
                        On-Screen Controls
                    </h3>
                    <ul class="help-list">
                        <li>
                            <span class="help-icon">🥽</span>
                            <div><strong>Stereo Mode</strong><br>Split-screen stereo panorama mode for mobile/headset browser viewing (top-left)</div>
                        </li>
                        <li>
                            <span class="help-icon">📡</span>
                            <div><strong>Gyroscope</strong><br>Tilt your phone to look around — available on supported mobile devices only (top-left)</div>
                        </li>
                        <li>
                            <span class="help-icon">⛶</span>
                            <div><strong>Fullscreen</strong><br>Expand the tour to fill your entire screen (top-right)</div>
                        </li>
                        <li>
                            <span class="help-icon">🏠</span>
                            <div><strong>Room Info</strong><br>Appears on room scenes — click for pricing details (top-right)</div>
                        </li>
                        <li>
                            <span class="help-icon">🗺️</span>
                            <div><strong>Tour Map</strong><br>Click any waypoint to jump there. Use the search bar to filter scenes by name (bottom-right)</div>
                        </li>
                        <li>
                            <span class="help-icon">▶️</span>
                            <div><strong>Auto Tour</strong><br>Advances automatically through every scene. Press Esc or click the button to stop (bottom-left)</div>
                        </li>
                        <li>
                            <span class="help-kbd">H</span>
                            <div><strong>Hide/Show UI</strong><br>Toggle visibility of all controls for maximum immersion. Click the eye icon (top-right) or press H to toggle</div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="help-footer">
                <label class="help-dont-show">
                    <input type="checkbox" id="help-no-show-again">
                    Don't show this on next visit
                </label>
                <button onclick="closeTourHelp()" class="help-close-btn">Got it, let's explore!</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/tour-engine.js'])
<script>
    // Initialize tour engine when page loads
    let tourEngine;

    document.addEventListener('DOMContentLoaded', function() {
        tourEngine = new VirtualTourEngine('panorama-container', {
            startWaypoint: '{{ $startWaypoint }}',
            apiBase: '/api/tour',
            onRoomDoorReached: function(waypoint) {
                console.log('Reached room door:', waypoint.name);
            },
            onReservationOpened: function(roomType) {
                const roomTypeName = roomType?.name || tourEngine?.currentRoom?.room_type?.name || tourEngine?.currentRoomType?.name || 'Selected room type';
                const modalRoomName = document.getElementById('modal-room-name');
                if (modalRoomName) {
                    modalRoomName.textContent = roomTypeName;
                }
                console.log('Opened reservation for:', roomTypeName);
            }
        });

        // Show minimap after waypoints load
        setTimeout(() => {
            document.getElementById('minimap').classList.remove('hidden');
            document.getElementById('progress-indicator').classList.remove('hidden');
        }, 2000);

        // Hide gyroscope button if not supported
        if (typeof DeviceOrientationEvent === 'undefined') {
            const gyroBtn = document.getElementById('gyro-btn');
            if (gyroBtn) {
                gyroBtn.style.display = 'none';
                console.log('Gyroscope not supported on this device - button hidden');
            }
        }
    });

    // Fullscreen toggle
    function toggleFullscreen() {
        const viewer = document.getElementById('tour-viewer');
        if (!document.fullscreenElement) {
            viewer.requestFullscreen().catch(err => {
                console.error('Fullscreen error:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }

    // Elements that must remain visible inside any fullscreen context
    const _fsOverlays = ['room-info-overlay', 'reservation-modal', 'reservation-success-modal'].map(id => document.getElementById(id)).filter(Boolean);
    const _fsOverlayHome = document.getElementById('tour-viewer');

    function _syncOverlaysToFullscreen() {
        const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
        if (fsEl && fsEl !== _fsOverlayHome && !fsEl.contains(_fsOverlays[0])) {
            // Viewer (or another element) went fullscreen — move overlays inside it so they remain visible
            _fsOverlays.forEach(el => fsEl.appendChild(el));
        } else if (!fsEl) {
            // Exiting fullscreen — move overlays back to #tour-viewer
            _fsOverlays.forEach(el => {
                if (!_fsOverlayHome.contains(el)) _fsOverlayHome.appendChild(el);
            });
        }
    }

    // Update fullscreen button icon on change
    document.addEventListener('fullscreenchange', () => {
        const isFs = !!document.fullscreenElement;
        document.getElementById('fs-expand-icon').style.display = isFs ? 'none' : '';
        document.getElementById('fs-compress-icon').style.display = isFs ? '' : 'none';
        document.getElementById('fs-btn-text').textContent = isFs ? 'Exit Fullscreen' : 'Fullscreen';
        _syncOverlaysToFullscreen();
    });

    // Safari / iOS WebKit prefix
    document.addEventListener('webkitfullscreenchange', _syncOverlaysToFullscreen);

    // Stereo mode toggle (split-screen panorama)
    async function toggleVRMode() {
        if (!tourEngine) return;
        await tourEngine.toggleVR();
        const btn = document.getElementById('vr-mode-btn');
        const text = document.getElementById('vr-btn-text');
        if (tourEngine.vrActive) {
            btn.classList.add('active');
            text.textContent = 'Exit Stereo';
        } else {
            btn.classList.remove('active');
            text.textContent = 'Stereo Mode';
        }
    }

    // Gyroscope toggle
    async function toggleGyro() {
        if (!tourEngine) return;
        
        try {
            await tourEngine.toggleGyroscope();
            
            const btn = document.getElementById('gyro-btn');
            const text = document.getElementById('gyro-btn-text');
            const isActive = tourEngine.gyroscopePlugin?.isEnabled();
            
            if (isActive) {
                btn.classList.add('active');
                text.textContent = 'Gyro ON';
                tourEngine._showToast('Gyroscope enabled - tilt your device to look around', 'success');
            } else {
                btn.classList.remove('active');
                text.textContent = 'Gyroscope';
            }
        } catch (error) {
            console.error('Gyroscope error:', error);
            
            let errorMessage = 'Gyroscope not available on this device';
            
            if (error.message?.includes('denied') || error.message?.includes('permission')) {
                errorMessage = 'Permission denied. Enable in Settings → Safari → Motion & Orientation Access';
            } else if (window.location.protocol === 'http:' && window.location.hostname !== 'localhost') {
                errorMessage = 'Gyroscope requires HTTPS connection on mobile devices';
            } else if (error.message?.includes('not initialized')) {
                errorMessage = 'Gyroscope not supported on this device';
            }
            
            tourEngine._showToast(errorMessage, 'error');
        }
    }

    // Handle reservation form submission
    async function handleReservationSubmit(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
        }
        
        const formData = {
            guest_first_name: document.getElementById('guest_first_name').value,
            guest_last_name: document.getElementById('guest_last_name').value,
            guest_email: document.getElementById('guest_email').value,
            guest_phone: document.getElementById('guest_phone').value,
            guest_age: document.getElementById('guest_age').value,
            guest_gender: document.getElementById('guest_gender').value,
            guest_address: document.getElementById('guest_address').value,
            preferred_room_type_id: document.getElementById('preferred_room_type_id').value,
            preferred_room_id: document.getElementById('preferred_room_id')?.value || null,
            check_in_date: document.getElementById('check_in_date').value,
            check_out_date: document.getElementById('check_out_date').value,
            number_of_occupants: document.getElementById('number_of_occupants').value,
            special_requests: document.getElementById('special_requests').value,
            source: 'virtual_tour'
        };

        if (!formData.preferred_room_type_id) {
            alert('Please open Room Info from a room scene before submitting a reservation request.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.originalText || 'Submit Reservation Request';
            }
            return;
        }

        try {
            const result = await tourEngine.submitReservation(formData);
            const didSucceed = Boolean(
                result?.success ||
                result?.data?.success ||
                result?.data?.data?.reference_number
            );

            if (didSucceed) {
                const formEl = document.getElementById('reservation-form');
                if (formEl) formEl.reset();

                // Keep close behavior resilient even if engine return shape changes.
                const reservationModal = document.getElementById('reservation-modal');
                if (reservationModal) {
                    reservationModal.style.setProperty('display', 'none', 'important');
                    reservationModal.style.visibility = 'hidden';
                    reservationModal.style.opacity = '0';
                    reservationModal.style.pointerEvents = 'none';
                    reservationModal.classList.add('hidden');
                    reservationModal.setAttribute('hidden', 'hidden');
                }

                if (tourEngine?.closeReservationModal) {
                    tourEngine.closeReservationModal();
                }
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.originalText || 'Submit Reservation Request';
            }
        }
    }

    // Tour Help guide
    function openTourHelp() {
        document.getElementById('tour-help-modal').style.display = 'flex';
    }

    function closeTourHelp() {
        document.getElementById('tour-help-modal').style.display = 'none';
        if (document.getElementById('help-no-show-again').checked) {
            localStorage.setItem('tour_help_seen', '1');
        }
    }

    // Auto-show on first visit (1.5 s delay so panorama starts loading first)
    document.addEventListener('DOMContentLoaded', function() {
        if (!localStorage.getItem('tour_help_seen')) {
            setTimeout(openTourHelp, 1500);
        }
    });

    function filterMinimapScenes(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll('#minimap .minimap-waypoint-btn').forEach(btn => {
            btn.style.display = (!q || btn.textContent.toLowerCase().includes(q)) ? '' : 'none';
        });
    }

    // Close help with Escape key (only when help modal is open)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('tour-help-modal').style.display === 'flex') {
            e.stopImmediatePropagation();
            closeTourHelp();
        }
    }, true); // capture phase so it fires before panorama handlers
</script>
@endpush
