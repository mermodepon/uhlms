@extends('layouts.guest')

@section('title', 'Virtual Tour')
@section('suppressGlobalGuestFlashes', 'true')

@push('styles')
<style>
    /* Tour Viewer Container */
    #tour-viewer {
        width: 100%;
        max-width: 100%;
        height: calc(100vh - 4rem);
        margin: 0;
        position: relative;
        background: #000;
        border-radius: 0;
        overflow: hidden;
        box-shadow: none;
    }

    /* Collapse footer spacing on the tour page */
    footer { margin-top: 0 !important; }

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

    .tour-empty-state {
        width: 100%;
        max-width: 1100px;
        margin: 2rem auto;
        padding: 4rem 1.5rem;
        border-radius: 1rem;
        background:
            radial-gradient(circle at top right, rgba(255, 198, 0, 0.16), transparent 28%),
            linear-gradient(135deg, #f7faf7 0%, #edf7ef 100%);
        border: 1px solid rgba(0, 73, 30, 0.12);
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.08);
    }

    .tour-empty-card {
        max-width: 42rem;
        margin: 0 auto;
        text-align: center;
    }

    .tour-empty-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 0.9rem;
        border-radius: 9999px;
        background: rgba(255, 198, 0, 0.18);
        color: #7a5b00;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .tour-empty-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.9rem;
        margin-top: 1.75rem;
    }

    .tour-empty-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 12rem;
        padding: 0.9rem 1.4rem;
        border-radius: 0.8rem;
        font-weight: 700;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .tour-empty-link:hover {
        transform: translateY(-1px);
    }

    .tour-empty-link-primary {
        background: linear-gradient(135deg, #00491E 0%, #02681E 100%);
        color: white;
        box-shadow: 0 12px 26px rgba(2, 104, 30, 0.22);
    }

    .tour-empty-link-secondary {
        background: #FFC600;
        color: #00491E;
        box-shadow: 0 12px 26px rgba(255, 198, 0, 0.2);
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

    .desktop-left-rail {
        position: absolute;
        top: 1rem;
        left: 1rem;
        width: 320px;
        height: calc(100% - 2rem);
        max-height: calc(100% - 2rem);
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        z-index: 46;
        pointer-events: none;
    }

    .desktop-left-rail > * {
        pointer-events: auto;
    }

    /* Mini-map */
    #minimap {
        position: relative;
        width: 100%;
        min-height: 0;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        background: linear-gradient(180deg, rgba(20, 28, 25, 0.9) 0%, rgba(9, 13, 12, 0.88) 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 1rem;
        box-shadow: 0 16px 34px rgba(0, 0, 0, 0.24);
        backdrop-filter: blur(12px);
        overflow: hidden;
        opacity: 1;
        transition: opacity 0.4s ease-in-out, transform 0.2s ease;
    }

    #minimap.ui-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .minimap-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        padding: 0;
        background: transparent;
        border: 0;
        color: #f9fafb;
        cursor: pointer;
        text-align: left;
    }

    .minimap-title-wrap {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-width: 0;
    }

    .minimap-title-wrap svg {
        width: 17px;
        height: 17px;
        opacity: 0.9;
        flex-shrink: 0;
    }

    .minimap-title-text {
        min-width: 0;
    }

    .minimap-title-label {
        display: block;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }

    .minimap-title-caption {
        display: block;
        margin-top: 0.14rem;
        font-size: 0.72rem;
        color: rgba(229, 231, 235, 0.74);
    }

    .minimap-toggle-icon {
        width: 1.9rem;
        height: 1.9rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.05);
        transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        flex-shrink: 0;
    }

    .minimap-toggle-icon svg {
        width: 14px;
        height: 14px;
    }

    .minimap-toggle:hover .minimap-toggle-icon {
        background: rgba(255, 255, 255, 0.09);
        border-color: rgba(255, 198, 0, 0.3);
    }

    .minimap-header {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        padding: 1rem 1rem 0.85rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0.01) 100%),
            rgba(0, 0, 0, 0.08);
    }

    .minimap-body {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
        max-height: none;
        opacity: 1;
        margin-top: 0.8rem;
        transition: max-height 0.24s ease, opacity 0.18s ease, margin-top 0.24s ease;
    }

    #minimap.is-collapsed .minimap-body {
        max-height: 0;
        opacity: 0;
        margin-top: 0;
        pointer-events: none;
    }

    #minimap.is-collapsed {
        flex: 0 0 auto;
    }

    #minimap.is-collapsed .minimap-header {
        flex: 0 0 auto;
        border-bottom-color: transparent;
    }

    #minimap.is-collapsed .minimap-toggle-icon {
        transform: rotate(-90deg);
    }

    .minimap-waypoints {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0.4rem;
        min-height: 0;
        padding: 0.75rem 0.75rem 2rem;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .minimap-waypoints::-webkit-scrollbar {
        width: 8px;
    }

    .minimap-waypoints::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.16);
        border-radius: 999px;
    }

    .minimap-waypoint-btn {
        text-align: left;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.04);
        transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .minimap-waypoint-btn:hover {
        transform: translateY(-1px);
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 198, 0, 0.22);
    }

    .minimap-waypoint-btn.is-active {
        background: linear-gradient(135deg, rgba(255, 198, 0, 0.28) 0%, rgba(255, 198, 0, 0.12) 100%);
        border-color: rgba(255, 198, 0, 0.7);
        color: #fff9df;
        box-shadow: 0 0 0 1px rgba(255, 198, 0, 0.12) inset, 0 10px 22px rgba(0, 0, 0, 0.18);
    }

    .minimap-waypoint-btn.is-active .minimap-waypoint-type,
    .minimap-waypoint-btn.is-active .minimap-waypoint-title {
        color: inherit;
    }

    /* Progress Indicator */
    #progress-indicator {
        position: absolute;
        top: 1rem;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(15, 23, 42, 0.76);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: white;
        padding: 0.55rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        z-index: 40;
        backdrop-filter: blur(8px);
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

    /* Gaze Tooltip - appears when looking at hotspots in Motion/VR mode */
    #gaze-tooltip {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, calc(-50% - 80px));
        background: rgba(0, 73, 30, 0.95);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        border: 2px solid #FFC600;
        font-size: 0.9rem;
        font-weight: 600;
        text-align: center;
        z-index: 45;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        max-width: 300px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #gaze-tooltip.visible {
        opacity: 1;
    }
    #gaze-tooltip .gaze-subtitle {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-top: 0.25rem;
        font-weight: 400;
    }
    #gaze-tooltip .gaze-status {
        font-size: 0.72rem;
        color: #ffe082;
        margin-top: 0.35rem;
        font-weight: 600;
        display: none;
    }
    #gaze-tooltip .gaze-progress {
        width: 100%;
        height: 0.35rem;
        background: rgba(255, 255, 255, 0.18);
        border-radius: 999px;
        overflow: hidden;
        margin-top: 0.45rem;
        display: none;
    }
    #gaze-tooltip .gaze-progress-fill {
        width: 0%;
        height: 100%;
        background: linear-gradient(90deg, #FFC600 0%, #ffe082 100%);
        border-radius: 999px;
        transition: width 0.08s linear;
    }

    /* Floating tour guide */
    #tour-guide-layer {
        position: absolute;
        inset: 0;
        z-index: 66;
        display: none;
        pointer-events: none;
    }

    #tour-guide-layer.is-visible {
        display: block;
    }

    #tour-guide-spotlight {
        position: absolute;
        display: none;
        border: 2px solid rgba(255, 198, 0, 0.95);
        border-radius: 999px;
        box-shadow:
            0 0 0 9999px rgba(3, 7, 18, 0.18),
            0 0 0 8px rgba(255, 198, 0, 0.16),
            0 14px 32px rgba(0, 0, 0, 0.32);
        transform: translateZ(0);
        transition: left 0.18s ease, top 0.18s ease, width 0.18s ease, height 0.18s ease;
        animation: tour-guide-pulse 1.9s ease-in-out infinite;
    }

    #tour-guide-spotlight.is-visible {
        display: block;
    }

    @keyframes tour-guide-pulse {
        0%, 100% {
            box-shadow:
                0 0 0 9999px rgba(3, 7, 18, 0.18),
                0 0 0 8px rgba(255, 198, 0, 0.16),
                0 14px 32px rgba(0, 0, 0, 0.32);
        }
        50% {
            box-shadow:
                0 0 0 9999px rgba(3, 7, 18, 0.18),
                0 0 0 14px rgba(255, 198, 0, 0.08),
                0 14px 32px rgba(0, 0, 0, 0.32);
        }
    }

    @keyframes tour-check-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .tour-media-lightbox {
        position: absolute;
        inset: 0;
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        padding: clamp(0.75rem, 2vw, 1.5rem);
    }

    .tour-media-lightbox.is-open {
        display: flex;
    }

    .tour-media-lightbox-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(3, 7, 18, 0.88);
        backdrop-filter: blur(8px);
    }

    .tour-media-lightbox-panel {
        position: relative;
        z-index: 1;
        width: min(1180px, 100%);
        max-height: min(92vh, 920px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.16);
        background: rgba(15, 23, 42, 0.96);
        box-shadow: 0 28px 80px rgba(0, 0, 0, 0.52);
    }

    .tour-media-lightbox-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 1rem;
        color: white;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        flex-shrink: 0;
    }

    .tour-media-lightbox-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .tour-media-lightbox-counter {
        margin: 0.2rem 0 0;
        color: rgba(226, 232, 240, 0.76);
        font-size: 0.78rem;
        font-weight: 700;
    }

    .tour-media-lightbox-close {
        width: 2.4rem;
        height: 2.4rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        color: white;
        font-size: 1.65rem;
        line-height: 1;
        cursor: pointer;
    }

    .tour-media-lightbox-stage {
        position: relative;
        min-height: 0;
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #020617;
    }

    .tour-media-lightbox-body {
        width: 100%;
        height: 100%;
        min-height: 280px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: clamp(0.75rem, 2vw, 1.25rem);
    }

    .tour-media-lightbox-body img {
        max-width: 100%;
        max-height: calc(92vh - 6.5rem);
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 0.5rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.38);
    }

    .tour-media-lightbox-video {
        position: relative;
        width: min(100%, 1040px);
        aspect-ratio: 16 / 9;
        background: #000;
        border-radius: 0.65rem;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.38);
    }

    .tour-media-lightbox-video iframe,
    .tour-media-lightbox-video video {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
        background: #000;
    }

    .tour-media-lightbox-nav {
        position: absolute;
        top: 50%;
        z-index: 2;
        width: clamp(2.75rem, 5vw, 4rem);
        height: clamp(2.75rem, 5vw, 4rem);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.78);
        color: white;
        font-size: clamp(2rem, 4vw, 3.5rem);
        line-height: 1;
        transform: translateY(-50%);
        cursor: pointer;
        box-shadow: 0 18px 42px rgba(0, 0, 0, 0.38);
    }

    .tour-media-lightbox-prev {
        left: clamp(0.6rem, 2vw, 1.25rem);
    }

    .tour-media-lightbox-next {
        right: clamp(0.6rem, 2vw, 1.25rem);
    }

    .tour-media-lightbox-nav.is-hidden {
        display: none;
    }

    #tour-guide-bubble {
        position: absolute;
        width: min(330px, calc(100vw - 2rem));
        pointer-events: auto;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.96) 0%, rgba(17, 24, 39, 0.94) 100%);
        color: #f8fafc;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 0.85rem;
        box-shadow: 0 22px 50px rgba(0, 0, 0, 0.38);
        padding: 0.9rem;
        backdrop-filter: blur(16px);
        opacity: 0;
        transform: translate(-50%, 8px);
        transition: opacity 0.18s ease, transform 0.18s ease, left 0.18s ease, top 0.18s ease;
    }

    #tour-guide-layer.is-visible #tour-guide-bubble {
        opacity: 1;
        transform: translate(-50%, 0);
    }

    #tour-guide-bubble::before {
        content: "";
        position: absolute;
        left: 50%;
        width: 14px;
        height: 14px;
        background: rgba(15, 23, 42, 0.96);
        border-left: 1px solid rgba(255, 255, 255, 0.18);
        border-top: 1px solid rgba(255, 255, 255, 0.18);
        transform: translateX(-50%) rotate(45deg);
    }

    #tour-guide-bubble[data-placement="bottom"]::before {
        top: -8px;
    }

    #tour-guide-bubble[data-placement="top"]::before {
        bottom: -8px;
        transform: translateX(-50%) rotate(225deg);
    }

    #tour-guide-bubble[data-placement="center"]::before {
        display: none;
    }

    .tour-guide-kicker {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.45rem;
        color: rgba(255, 248, 214, 0.78);
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .tour-guide-title {
        margin: 0;
        color: #ffffff;
        font-size: 0.98rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .tour-guide-copy {
        margin: 0.45rem 0 0;
        color: rgba(241, 245, 249, 0.82);
        font-size: 0.83rem;
        line-height: 1.45;
    }

    .tour-guide-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.85rem;
    }

    .tour-guide-btn {
        border: 0;
        border-radius: 0.5rem;
        min-height: 36px;
        padding: 0.45rem 0.8rem;
        font-size: 0.78rem;
        font-weight: 800;
        cursor: pointer;
    }

    .tour-guide-btn-secondary {
        background: rgba(255, 255, 255, 0.08);
        color: rgba(248, 250, 252, 0.84);
    }

    .tour-guide-btn-primary {
        background: #FFC600;
        color: #00491E;
    }

    .tour-guide-btn:hover {
        filter: brightness(1.05);
    }

    #tour-viewer.room-card-open #tour-guide-layer,
    #tour-viewer.mobile-map-open #tour-guide-layer,
    #tour-viewer.mobile-settings-open #tour-guide-layer {
        display: none !important;
    }



    /* Navigation Controls */
    .nav-controls {
        position: absolute;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
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

    .nav-scene-name {
        max-width: 18rem;
        padding: 0.72rem 1.25rem;
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.68);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #f9fafb;
        font-size: 0.92rem;
        font-weight: 700;
        line-height: 1.2;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        pointer-events: none;
        backdrop-filter: blur(8px);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
    }

    .nav-scene-name-icon {
        display: none;
        width: 0.95rem;
        height: 0.95rem;
        flex: 0 0 auto;
    }

    .nav-scene-name-text {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Top-right tour controls */
    .top-right-controls {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: flex;
        gap: 0.45rem;
        z-index: 50;
        align-items: center;
        transition: opacity 0.18s ease, transform 0.18s ease;
    }

    .top-right-controls button,
    .top-right-controls a {
        background: rgba(15, 23, 42, 0.62);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0 0.9rem;
        min-height: 40px;
        border-radius: 0.8rem;
        cursor: pointer;
        font-size: 0.84rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        transition: background-color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        text-decoration: none;
        backdrop-filter: blur(8px);
    }

    .top-right-controls button:hover,
    .top-right-controls a:hover {
        background: rgba(15, 23, 42, 0.8);
        border-color: rgba(255, 255, 255, 0.16);
        transform: translateY(-1px);
    }

    .top-right-controls .home-btn {
        background: rgba(0, 73, 30, 0.78);
        display: none;
    }

    #tour-viewer:fullscreen .home-btn,
    #tour-viewer:-webkit-full-screen .home-btn {
        display: flex;
    }

    .top-right-controls .home-btn:hover {
        background: rgba(0, 73, 30, 0.94);
    }

    .top-right-controls .toggle-ui-btn {
        background: rgba(15, 23, 42, 0.62);
        width: 40px;
        min-width: 40px;
        height: 40px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-right-controls .toggle-ui-btn:hover {
        background: rgba(15, 23, 42, 0.8);
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
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 0;
        flex: 0 0 auto;
        z-index: 1;
        opacity: 1;
        transition: opacity 0.4s ease-in-out;
        padding: 0.95rem;
        border-radius: 1rem;
        background: linear-gradient(180deg, rgba(20, 28, 25, 0.88) 0%, rgba(9, 13, 12, 0.86) 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 16px 34px rgba(0, 0, 0, 0.22);
        backdrop-filter: blur(12px);
    }

    .vr-controls.ui-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .vr-controls-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        padding: 0.15rem 0 0.1rem;
        background: transparent;
        border: 0;
        color: #f9fafb;
        cursor: pointer;
        text-align: left;
    }

    .vr-controls-heading {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-width: 0;
    }

    .vr-controls-heading svg {
        width: 17px;
        height: 17px;
        opacity: 0.9;
        flex-shrink: 0;
    }

    .vr-controls-heading-text {
        min-width: 0;
    }

    .vr-controls-heading-label {
        display: block;
        font-size: 0.92rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }

    .vr-controls-heading-caption {
        display: block;
        margin-top: 0.12rem;
        font-size: 0.72rem;
        color: rgba(229, 231, 235, 0.74);
    }

    .vr-controls-toggle-icon {
        width: 1.9rem;
        height: 1.9rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.05);
        transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        flex-shrink: 0;
    }

    .vr-controls-toggle-icon svg {
        width: 14px;
        height: 14px;
    }

    .vr-controls-header:hover .vr-controls-toggle-icon {
        background: rgba(255, 255, 255, 0.09);
        border-color: rgba(255, 198, 0, 0.3);
    }

    .vr-controls-body {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        overflow: hidden;
        max-height: 32rem;
        opacity: 1;
        margin-top: 0.85rem;
        transition: max-height 0.24s ease, opacity 0.18s ease, margin-top 0.24s ease;
    }

    .vr-controls.is-collapsed .vr-controls-body {
        max-height: 0;
        opacity: 0;
        margin-top: 0;
        pointer-events: none;
    }

    .vr-controls.is-collapsed .vr-controls-toggle-icon {
        transform: rotate(-90deg);
    }

    .vr-btn {
        background: rgba(255, 255, 255, 0.04);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.12);
        padding: 0.7rem 1rem;
        border-radius: 0.9rem;
        cursor: pointer;
        font-size: 0.92rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        box-shadow: 0 10px 22px rgba(0, 0, 0, 0.18);
        backdrop-filter: blur(10px);
        transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        white-space: nowrap;
    }

    .vr-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 198, 0, 0.32);
        box-shadow: 0 14px 26px rgba(0, 0, 0, 0.24);
        transform: translateY(-1px);
    }

    .vr-btn.active {
        background: linear-gradient(135deg, rgba(255, 198, 0, 0.24) 0%, rgba(255, 198, 0, 0.12) 100%);
        border-color: rgba(255, 198, 0, 0.7);
        color: #fff7d1;
        box-shadow: 0 12px 28px rgba(82, 56, 0, 0.22);
    }

    .vr-btn.active:hover {
        background: linear-gradient(135deg, rgba(255, 198, 0, 0.3) 0%, rgba(255, 198, 0, 0.16) 100%);
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
        width: 100%;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 0.95rem;
        padding: 0.7rem;
        box-shadow: none;
        backdrop-filter: none;
    }

    #auto-tour-settings label {
        display: block;
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        color: rgba(255, 248, 214, 0.92);
        margin-bottom: 0.55rem;
        letter-spacing: 0.08em;
    }

    .auto-tour-speed-options {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .auto-tour-speed-btn {
        min-width: 0;
        width: 100%;
        border: 1px solid rgba(255, 255, 255, 0.18);
        background: rgba(15, 23, 42, 0.88);
        color: #f3f4f6;
        border-radius: 0.65rem;
        font-size: 0.74rem;
        font-weight: 700;
        padding: 0.52rem 0.4rem;
        line-height: 1.05;
        text-align: center;
        white-space: nowrap;
        cursor: pointer;
        transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .auto-tour-speed-btn:hover {
        background: rgba(30, 41, 59, 0.96);
        border-color: rgba(255, 198, 0, 0.28);
        transform: translateY(-1px);
    }

    .auto-tour-speed-btn.active,
    .auto-tour-speed-btn[aria-pressed="true"] {
        border-color: rgba(255, 198, 0, 0.88);
        background: linear-gradient(135deg, rgba(255, 198, 0, 0.3) 0%, rgba(255, 198, 0, 0.14) 100%);
        color: #fff4bf;
        box-shadow: 0 0 0 1px rgba(255, 198, 0, 0.22) inset, 0 6px 14px rgba(82, 56, 0, 0.2);
    }

    #auto-tour-btn.active {
        background: rgba(239, 68, 68, 0.85);
    }
    #auto-tour-btn.active:hover {
        background: rgba(220, 38, 38, 0.95);
    }
    #minimap-search {
        width: 100%;
        padding: 0.72rem 0.82rem;
        font-size: 0.92rem;
        line-height: 1.2;
        color: #111827;
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 0.8rem;
        background: rgba(255, 255, 255, 0.96);
        transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        caret-color: #111827;
    }

    #minimap-search::placeholder {
        color: #9ca3af;
    }

    #minimap-search:focus {
        border-color: rgba(255, 198, 0, 0.6);
        outline: none;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(255, 198, 0, 0.14);
    }

    .vr-btn svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        opacity: 0.9;
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
        body > nav,
        body > footer {
            display: none !important;
        }

        body {
            background: #000;
        }

        main {
            min-height: 100vh;
        }

        #tour-viewer {
            height: 100vh;
            height: 100dvh;
            margin: 0;
            border-radius: 0;
            max-width: 100%;
            box-shadow: none;
        }

        #room-info-overlay {
            width: 100vw;
        }

        .desktop-left-rail {
            position: static;
            width: auto;
            max-height: none;
            display: block;
            pointer-events: auto;
        }

        #minimap {
            position: absolute;
            left: 0.75rem;
            right: 0.75rem;
            bottom: calc(5.25rem + env(safe-area-inset-bottom));
            z-index: 58;
            width: auto;
            max-height: min(64dvh, calc(100dvh - 8.5rem));
            flex: none;
            opacity: 0;
            transform: translateY(14px);
            pointer-events: none;
            border-radius: 1.1rem;
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        #minimap.is-collapsed {
            flex: none;
        }

        #minimap.is-collapsed .minimap-header {
            flex: 1 1 auto;
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        #minimap.mobile-open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        #minimap.ui-hidden {
            opacity: 0 !important;
            transform: translateY(14px) !important;
            pointer-events: none !important;
        }

        .minimap-header {
            max-height: min(64dvh, calc(100dvh - 8.5rem));
            padding: 0.85rem;
        }

        .minimap-toggle,
        .minimap-body {
            display: flex;
        }

        .minimap-title-label {
            font-size: 0.95rem;
        }

        .minimap-title-caption {
            font-size: 0.68rem;
        }

        .minimap-body {
            min-height: 0;
            max-height: none;
        }

        .minimap-waypoints {
            max-height: calc(64dvh - 7.25rem);
            padding: 0.6rem 0.1rem 0.2rem;
        }

        #minimap-search {
            min-height: 42px;
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

        #progress-indicator {
            top: 0.75rem;
            left: 0.65rem;
            transform: none;
            max-width: calc(100vw - 15.75rem);
            padding: 0.4rem 0.65rem;
            font-size: 0.72rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .top-right-controls {
            top: 0.65rem;
            right: 0.65rem;
            gap: 0.4rem;
            align-items: center;
        }

        .top-right-controls button,
        .top-right-controls a,
        .vr-btn {
            min-width: 42px;
            min-height: 42px;
            padding: 0;
            border-radius: 0.65rem;
            justify-content: center;
            place-items: center;
            background: rgba(17, 24, 39, 0.78);
            backdrop-filter: blur(6px);
        }

        .top-right-controls button span,
        .top-right-controls a span {
            display: none;
        }

        .top-right-controls svg {
            width: 19px;
            height: 19px;
        }

        #help-btn,
        #fullscreen-btn {
            display: none !important;
        }

        .top-right-controls .home-btn {
            width: 42px;
            height: 42px;
            overflow: hidden;
            white-space: nowrap;
            font-size: 0;
            display: inline-grid;
            place-items: center;
            line-height: 1;
        }

        .top-right-controls .home-btn svg {
            width: 19px;
            height: 19px;
        }

        .mobile-glyph {
            display: inline-grid;
            place-items: center;
            width: 1em;
            height: 1em;
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1;
            transform: translateY(-0.02em);
        }

        .top-right-controls .mobile-glyph,
        .nav-btn .mobile-glyph {
            display: inline-grid;
        }

        .nav-label,
        .home-label {
            display: none;
        }

        #mobile-settings-btn {
            display: flex !important;
        }

        #tour-viewer.room-card-open .top-right-controls {
            opacity: 0;
            transform: translateY(-8px);
            pointer-events: none;
        }

        #tour-viewer.room-card-open .vr-controls.mobile-open {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }

        .vr-controls {
            top: 3.85rem;
            left: auto;
            right: 0.65rem;
            width: min(238px, calc(100vw - 1.3rem));
            padding: 0.8rem;
            border-radius: 1.2rem;
            background: linear-gradient(180deg, rgba(20, 28, 25, 0.94) 0%, rgba(9, 13, 12, 0.92) 100%);
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 18px 38px rgba(0, 0, 0, 0.24);
            backdrop-filter: blur(14px);
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
            position: absolute;
        }

        .vr-controls-header {
            display: none;
        }

        .vr-controls-body {
            max-height: none;
            opacity: 1;
            margin-top: 0;
            overflow: visible;
        }

        .vr-controls.mobile-open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        #tour-viewer.mobile-map-open .psv-marker,
        #tour-viewer.mobile-map-open .pv-hotspot-circle,
        #tour-viewer.mobile-map-open .pv-badge-marker,
        #tour-viewer.mobile-map-open #gaze-tooltip,
        #tour-viewer.mobile-settings-open .psv-marker,
        #tour-viewer.mobile-settings-open .pv-hotspot-circle,
        #tour-viewer.mobile-settings-open .pv-badge-marker,
        #tour-viewer.mobile-settings-open #gaze-tooltip {
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .vr-controls.ui-hidden {
            opacity: 0 !important;
            transform: translateY(-6px) !important;
            pointer-events: none !important;
        }

        .vr-btn {
            width: 100%;
            padding: 0.82rem 0.9rem;
            min-height: 48px;
            justify-content: flex-start;
            gap: 0.75rem;
            background: linear-gradient(180deg, rgba(38, 42, 40, 0.96) 0%, rgba(23, 26, 24, 0.94) 100%);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .vr-btn span {
            display: inline;
            font-size: 0.98rem;
        }

        .mobile-drawer-action {
            display: flex !important;
        }

        #auto-tour-settings,
        #auto-tour-hud {
            width: 100%;
            box-shadow: none;
        }

        #auto-tour-settings {
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.045);
            border-color: rgba(255, 255, 255, 0.12);
        }

        #auto-tour-settings label {
            font-size: 0.76rem;
            margin-bottom: 0.65rem;
            color: rgba(255, 248, 214, 0.96);
        }

        .auto-tour-speed-btn {
            min-height: 42px;
            font-size: 0.74rem;
        }

        .auto-tour-speed-btn.active,
        .auto-tour-speed-btn[aria-pressed="true"] {
            background: #FFC600;
            border-color: #ffe27a;
            color: #14351f;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.28) inset, 0 8px 18px rgba(0, 0, 0, 0.24);
        }

        .nav-controls {
            bottom: max(0.8rem, env(safe-area-inset-bottom));
            left: 0;
            right: 0;
            transform: none;
            justify-content: space-between;
            padding: 0 1rem;
            pointer-events: none;
        }

        .nav-scene-name {
            max-width: calc(100vw - 8.75rem);
            padding: 0.58rem 0.85rem;
            font-size: 0.78rem;
            line-height: 1.15;
            background: rgba(17, 24, 39, 0.74);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.38rem;
            pointer-events: auto;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .nav-scene-name:hover {
            background: rgba(17, 24, 39, 0.88);
        }

        .nav-scene-name:active {
            transform: scale(0.97);
        }

        .nav-scene-name:focus-visible {
            outline: 2px solid #FFC600;
            outline-offset: 3px;
        }

        .nav-scene-name[aria-expanded="true"] {
            background: rgba(0, 73, 30, 0.92);
            border-color: rgba(255, 198, 0, 0.72);
            box-shadow: 0 0 0 1px rgba(255, 198, 0, 0.16) inset, 0 10px 24px rgba(0, 0, 0, 0.24);
        }

        .nav-scene-name-icon {
            display: block;
        }

        .nav-btn {
            width: 52px;
            height: 52px;
            padding: 0;
            border-radius: 999px;
            font-size: 0;
            display: inline-grid;
            place-items: center;
            background: rgba(17, 24, 39, 0.74);
            backdrop-filter: blur(6px);
            pointer-events: auto;
            line-height: 1;
        }

        .nav-btn .mobile-glyph {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1;
            transform: none;
            font-family: Arial, Helvetica, sans-serif;
        }

        #narration-tooltip {
            bottom: 4.6rem;
            width: calc(100vw - 2rem);
            padding: 0.75rem 1rem;
            font-size: 0.82rem;
        }
    }

    @media (min-width: 769px) {
        #progress-indicator {
            max-width: 12rem;
        }
    }

    @media (min-width: 769px) and (max-width: 1100px) {
        .desktop-left-rail {
            width: 280px;
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

    #mobile-settings-btn.ui-hidden {
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
    .help-actions {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        flex-wrap: wrap;
        justify-content: flex-end;
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

    .help-replay-guide-btn {
        background: #f3f4f6;
        color: #00491E;
        border: 1px solid #d1d5db;
        padding: 0.6rem 1rem;
        border-radius: 0.5rem;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        white-space: nowrap;
    }

    .help-replay-guide-btn:hover {
        background: #e5e7eb;
    }

    @media (max-width: 560px) {
        .help-body {
            grid-template-columns: 1fr;
        }

        .help-footer {
            align-items: stretch;
            flex-direction: column;
        }

        .help-actions {
            justify-content: stretch;
        }

        .help-replay-guide-btn,
        .help-close-btn {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

@if(!$hasWaypoints)
<section class="tour-empty-state">
    <div class="tour-empty-card">
        <span class="tour-empty-badge">Virtual Tour Unavailable</span>
        <h1 class="mt-5 text-3xl md:text-4xl font-bold text-[#00491E]">No tour scenes are available right now.</h1>
        <p class="mt-4 text-base md:text-lg text-gray-600 leading-relaxed">
            We're currently updating the interactive tour. You can still explore our accommodations and submit a reservation while the tour is being prepared.
        </p>
        <div class="tour-empty-actions">
            <a href="{{ route('guest.rooms', [], false) }}" class="tour-empty-link tour-empty-link-primary">Browse Rooms</a>
            <a href="{{ route('guest.reserve', [], false) }}" class="tour-empty-link tour-empty-link-secondary">Make a Reservation</a>
            <a href="{{ route('guest.home', [], false) }}" class="tour-empty-link bg-white text-[#00491E] border border-[#00491E]/15">Back to Home</a>
        </div>
    </div>
</section>
@else
<!-- Tour Viewer -->
<div id="tour-viewer">
    <!-- Loading Indicator -->
    <div id="loading-indicator" class="hidden">
        <div class="spinner"></div>
        <p class="text-white">Loading panorama...</p>
    </div>

    <!-- Panorama Container -->
    <div id="panorama-container"></div>

    <!-- Floating Tour Guide -->
    <div id="tour-guide-layer" aria-hidden="true">
        <div id="tour-guide-spotlight" aria-hidden="true"></div>
        <div id="tour-guide-bubble" data-placement="center" role="status">
            <div class="tour-guide-kicker">
                <span id="tour-guide-step">Tour guide</span>
            </div>
            <h2 id="tour-guide-title" class="tour-guide-title"></h2>
            <p id="tour-guide-copy" class="tour-guide-copy"></p>
            <div class="tour-guide-actions">
                <button id="tour-guide-dismiss" type="button" class="tour-guide-btn tour-guide-btn-secondary">Skip</button>
                <button id="tour-guide-next" type="button" class="tour-guide-btn tour-guide-btn-primary">Next</button>
            </div>
        </div>
    </div>

    <!-- Progress Indicator -->
    <div id="progress-indicator" class="hidden">Stop 0 of 0</div>

    <!-- Navigation Controls -->
    <div class="nav-controls">
        <button id="nav-previous" class="nav-btn" onclick="tourEngine.navigatePrevious()">
            <span class="mobile-glyph" aria-hidden="true">&larr;</span>
            <span class="nav-label">Previous</span>
        </button>
        <button id="nav-scene-name" class="nav-scene-name" type="button" title="Open Tour Map" aria-label="Open Tour Map" aria-controls="minimap" aria-expanded="false">
            <svg class="nav-scene-name-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 18l-6 3V6l6-3m0 15l6 3m-6-3V3m6 18l6-3V3l-6 3m0 15V6"/>
            </svg>
            <span id="nav-scene-name-text" class="nav-scene-name-text" aria-live="polite" aria-atomic="true">Current scene</span>
        </button>
        <button id="nav-next" class="nav-btn" onclick="tourEngine.navigateNext()">
            <span class="nav-label">Next</span>
            <span class="mobile-glyph" aria-hidden="true">&rarr;</span>
        </button>
    </div>

    <!-- Top-right controls -->
    <div class="top-right-controls">
        <button id="mobile-settings-btn" type="button" title="Tour settings" aria-label="Tour settings" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06A1.65 1.65 0 0015 19.4a1.65 1.65 0 00-1 .6 1.65 1.65 0 00-.4 1.08V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-.6-1 1.65 1.65 0 00-1.08-.4H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-.6 1.65 1.65 0 00.4-1.08V3a2 2 0 014 0v.09A1.65 1.65 0 0015 4.6a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 00.6 1 1.65 1.65 0 001.08.4H21a2 2 0 010 4h-.09A1.65 1.65 0 0019.4 15z"/>
            </svg>
        </button>
        <button id="help-btn" onclick="openTourHelp()" title="How to navigate this tour">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17" stroke-width="3"/>
            </svg>
            <span>Help</span>
        </button>
        <button id="fullscreen-btn" onclick="toggleFullscreen()" title="Toggle fullscreen">
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
        <button class="home-btn" onclick="window.location.href='{{ route('guest.home', [], false) }}'" title="Return to homepage" aria-label="Go to homepage">
            <svg class="home-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span class="home-label">Home</span>
        </button>
        <button id="room-info-btn" onclick="tourEngine.toggleRoomInfoOverlay()" title="View room details and request a stay">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>View Details and Request</span>
        </button>
    </div>

    <div class="desktop-left-rail">
        <!-- Mini-map -->
        <div id="minimap" class="hidden">
            <div class="minimap-header">
                <button type="button" class="minimap-toggle" aria-expanded="true" aria-controls="minimap-body">
                    <span class="minimap-title-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18l-6 3V6l6-3m0 15l6 3m-6-3V3m6 18l6-3V3l-6 3m0 15V6"/>
                        </svg>
                        <span class="minimap-title-text">
                            <span class="minimap-title-label">Tour Map</span>
                            <span class="minimap-title-caption">Scenes, search, and quick jumps</span>
                        </span>
                    </span>
                    <span class="minimap-toggle-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </span>
                </button>
                <div id="minimap-body" class="minimap-body">
                    <input id="minimap-search" type="text" placeholder="Search scenes..." oninput="filterMinimapScenes(this.value)">
                    <div class="minimap-waypoints"></div>
                </div>
            </div>
        </div>

        <!-- Tour Controls -->
        <div class="vr-controls is-collapsed" data-viewing-modes>
            <button type="button" class="vr-controls-header" aria-expanded="false" aria-controls="vr-controls-body">
                <span class="vr-controls-heading">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8h12a3 3 0 013 3v4a3 3 0 01-3 3h-3.5l-1.1-2.2a1.5 1.5 0 00-2.8 0L9.5 18H6a3 3 0 01-3-3v-4a3 3 0 013-3z"/>
                        <circle cx="8" cy="13" r="1.5"/>
                        <circle cx="16" cy="13" r="1.5"/>
                    </svg>
                    <span class="vr-controls-heading-text">
                        <span class="vr-controls-heading-label">Viewing Modes</span>
                        <span class="vr-controls-heading-caption">VR, motion, auto tour, and speed</span>
                    </span>
                </span>
                <span class="vr-controls-toggle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </button>
            <div id="vr-controls-body" class="vr-controls-body">
                <button type="button" class="vr-btn mobile-drawer-action" onclick="openTourHelp();closeMobileTourSettings()" title="How to navigate this tour" style="display:none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17" stroke-width="3"/>
                    </svg>
                    <span>Help</span>
                </button>
                <button type="button" class="vr-btn mobile-drawer-action" onclick="toggleFullscreen();closeMobileTourSettings()" title="Toggle fullscreen" style="display:none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
                    </svg>
                    <span>Fullscreen</span>
                </button>
                <button id="webxr-test-btn" class="vr-btn" onclick="startWebXRTestMode()" title="Enter immersive VR mode">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8h12a3 3 0 013 3v4a3 3 0 01-3 3h-3.5l-1.1-2.2a1.5 1.5 0 00-2.8 0L9.5 18H6a3 3 0 01-3-3v-4a3 3 0 013-3z"/>
                        <circle cx="8" cy="13" r="1.5"/>
                        <circle cx="16" cy="13" r="1.5"/>
                    </svg>
                    <span>VR Mode</span>
                </button>
                <button id="gyro-btn" class="vr-btn" onclick="toggleGyro()" title="Use phone motion to look around">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    <span id="gyro-btn-text">Motion Look</span>
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
        </div>
    </div>

    <!-- Narration Tooltip -->
    <div id="narration-tooltip">
        <p class="narration-text"></p>
    </div>

    <!-- Gaze Tooltip - shows hotspot info when looking at it -->
    <div id="gaze-tooltip">
        <div class="gaze-title"></div>
        <div class="gaze-subtitle"></div>
        <div class="gaze-status"></div>
        <div class="gaze-progress"><div class="gaze-progress-fill"></div></div>
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

                <form id="reservation-form" onsubmit="handleReservationSubmit(event)" data-guest-validate novalidate>
                    <input type="hidden" id="preferred_room_type_id" name="preferred_room_type_id">
                    <input type="hidden" name="source" value="virtual_tour">
                    @honeypot

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="guest_first_name">First Name *</label>
                            <input type="text" id="guest_first_name" name="guest_first_name" value="{{ old('guest_first_name', $guestAccount?->first_name) }}" required maxlength="255">
                        </div>

                        <div class="form-group">
                            <label for="guest_last_name">Last Name *</label>
                            <input type="text" id="guest_last_name" name="guest_last_name" value="{{ old('guest_last_name', $guestAccount?->last_name) }}" required maxlength="255">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="guest_email">Email *</label>
                        <input type="email" id="guest_email" name="guest_email" value="{{ old('guest_email', $guestAccount?->email) }}" required maxlength="255">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="guest_phone">Mobile Number *</label>
                            <input type="tel" id="guest_phone" name="guest_phone" value="{{ old('guest_phone', $guestAccount?->phone) }}" maxlength="20" required
                                   pattern="^(09[0-9]{9}|\+639[0-9]{9}|639[0-9]{9})$"
                                   data-validation-pattern-message="Enter a valid Philippine mobile number, e.g. 09171234567 or +639171234567.">
                        </div>

                        <div class="form-group">
                            <label for="guest_age">Age *</label>
                            <input type="number" id="guest_age" name="guest_age" value="{{ old('guest_age', $guestAccount?->age) }}" min="18" max="120" step="1" data-integer="true" data-validation-min-message="Guest age must be at least 18." required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="guest_gender">Gender *</label>
                        <select id="guest_gender" name="guest_gender" class="guest-select" required>
                            <option value="">Select...</option>
                            <option value="Male" @selected(old('guest_gender', $guestAccount?->gender) === 'Male')>Male</option>
                            <option value="Female" @selected(old('guest_gender', $guestAccount?->gender) === 'Female')>Female</option>
                            <option value="Other" @selected(old('guest_gender', $guestAccount?->gender) === 'Other')>Other</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="check_in_date">Check-in Date *</label>
                            <input type="date" id="check_in_date" name="check_in_date" value="{{ date('Y-m-d') }}" min="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="form-group">
                            <label for="check_out_date">Check-out Date *</label>
                            <input type="date" id="check_out_date" name="check_out_date" value="{{ date('Y-m-d', strtotime('+1 day')) }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="requested_room_count">Rooms Requested *</label>
                            <input type="number" id="requested_room_count" name="requested_room_count" min="1" max="20" step="1" data-integer="true" value="1" required>
                        </div>

                        <div class="form-group">
                            <label for="number_of_occupants">Number of Occupants *</label>
                            <input type="number" id="number_of_occupants" name="number_of_occupants" min="1" max="20" step="1" data-integer="true" data-dynamic-max="20" value="1" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="guest_address">Address</label>
                        <textarea id="guest_address" name="guest_address" rows="2" maxlength="1000">{{ old('guest_address', $guestAccount?->address) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="special_requests">Special Requests</label>
                        <textarea id="special_requests" name="special_requests" rows="3" maxlength="2000"
                                  placeholder="Any special requirements or questions..."></textarea>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-blue-800">
                            <strong>ℹ️ Room Preference:</strong> Your selected room will be noted as a preference. 
                            Our staff will do their best to assign it during review, subject to availability.
                        </p>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                        <label for="availability_acknowledged" class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="availability_acknowledged" name="availability_acknowledged" value="1" class="mt-1">
                            <span class="text-sm text-amber-900">
                                <strong>Submit even if availability looks limited.</strong>
                                Staff can still review your request if the selected room type appears unavailable for your dates or guest count.
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">
                        Request This Room Type
                    </button>

                    <button type="button" onclick="tourEngine.goToReservationPage()" class="mt-3 w-full rounded-lg border border-[#00491E] bg-white px-4 py-3 text-sm font-bold text-[#00491E] transition hover:bg-[#00491E] hover:text-white">
                        Request Multiple Room Types
                    </button>

                    <p class="text-xs text-gray-500 mt-3 text-center">
                        Need different room types in one reservation? Use the full reservation form.
                    </p>

                    <p class="text-xs text-gray-500 mt-4 text-center">
                        This will create a pending reservation request. You'll receive a reference number to track it and follow the next steps.
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
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Reservation Request Submitted!</h2>
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
                <!-- Navigation Controls -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/></svg>
                        Navigation Controls
                    </h3>
                    <ul class="help-list">
                        <li>
                            <div style="display:flex;gap:0.25rem;flex-shrink:0;margin-top:0.05rem;flex-wrap:wrap;">
                                <span class="help-kbd">A</span><span class="help-kbd">D</span><span class="help-kbd">←</span><span class="help-kbd">→</span>
                            </div>
                            <div><strong>Pan Left / Right</strong><br>Press <strong>A</strong> or <strong>←</strong> to rotate the view left, or <strong>D</strong> or <strong>→</strong> to rotate the view right</div>
                        </li>
                        <li>
                            <div style="display:flex;gap:0.25rem;flex-shrink:0;margin-top:0.05rem;flex-wrap:wrap;">
                                <span class="help-kbd">W</span><span class="help-kbd">S</span><span class="help-kbd">↑</span><span class="help-kbd">↓</span>
                            </div>
                            <div><strong>Tilt Up / Down</strong><br>Press <strong>W</strong> or <strong>↑</strong> to pan the view upward, or <strong>S</strong> or <strong>↓</strong> to pan the view downward</div>
                        </li>
                        <li>
                            <span class="help-icon">🖱️</span>
                            <div><strong>Mouse/Touch</strong><br>Drag to look around in any direction, scroll to zoom in/out. On mobile, swipe to pan and pinch to zoom</div>
                        </li>
                        <li>
                            <span class="help-icon">👆</span>
                            <div><strong>Hotspots</strong><br>Click colored markers in the scene to navigate rooms, view info, or access room details (see legends below)</div>
                        </li>
                        <li>
                            <span class="help-icon">📡</span>
                            <div><strong>Motion Look</strong><br>Tilt your phone to look around — available on supported mobile devices only (top-left button)</div>
                        </li>
                        <li>
                            <span class="help-icon">VR</span>
                            <div><strong>VR Mode</strong><br>Enter an immersive WebXR session on supported VR browsers and headsets. Reservation actions exit VR and open the booking flow; external links and videos exit VR and open the destination in your browser.</div>
                        </li>
                    </ul>
                </div>

                <!-- On-Screen Controls -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18m6-18v18M3 9h18M3 15h18"/></svg>
                        On-Screen Controls
                    </h3>
                    <ul class="help-list">
                        <li>
                            <span class="help-icon">⛶</span>
                            <div><strong>Fullscreen</strong><br>Expand the tour to fill your entire screen for an immersive experience (top-right corner)</div>
                        </li>
                        <li>
                            <span class="help-icon">🏠</span>
                            <div><strong>View Details and Request</strong><br>Appears when viewing room scenes — click to see pricing, capacity, amenities, and request options (top-right corner)</div>
                        </li>
                        <li>
                            <span class="help-icon">🗺️</span>
                            <div><strong>Tour Map</strong><br>View all locations and jump instantly to any scene. Use the search bar to filter by name from the left-side panel</div>
                        </li>
                        <li>
                            <span class="help-icon">▶️</span>
                            <div><strong>Auto Tour</strong><br>Sit back and automatically advance through every scene. Press Esc or click the button again to stop (bottom-left corner)</div>
                        </li>
                        <li>
                            <span class="help-kbd">H</span>
                            <div><strong>Hide/Show UI</strong><br>Toggle all controls for maximum immersion. Click the eye icon (top-right) or press <strong>H</strong> key</div>
                        </li>
                    </ul>
                </div>

                <!-- Hotspot Legends -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Hotspot Legends
                    </h3>
                    <div class="help-markers">
                        <div class="help-marker-row">
                            <div class="help-marker-dot nav"></div>
                            <div><strong>Blue Markers</strong> — Navigation hotspots to move between scenes</div>
                        </div>
                        <div class="help-marker-row">
                            <div class="help-marker-dot info"></div>
                            <div><strong>Yellow Markers</strong> — Information hotspots to learn more about facilities and amenities</div>
                        </div>
                        <div class="help-marker-row">
                            <div class="help-marker-dot room"></div>
                            <div><strong>Green/Gold Markers</strong> — Room entry points with detailed info, pricing, and reservation options</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Tips -->
                <div class="help-section">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20"/><circle cx="12" cy="12" r="9"/></svg>
                        Quick Tips
                    </h3>
                    <ul class="help-list">
                        <li>
                            <span class="help-icon">💡</span>
                            <div><strong>Best Experience</strong><br>Use fullscreen mode on desktop for the most immersive view</div>
                        </li>
                        <li>
                            <span class="help-icon">🎯</span>
                            <div><strong>Find Rooms Fast</strong><br>Use the Tour Map's search feature to quickly locate specific room types or areas</div>
                        </li>
                        <li>
                            <span class="help-icon">🔄</span>
                            <div><strong>Reset View</strong><br>If you get disoriented, click any hotspot or use the Tour Map to reorient yourself</div>
                        </li>
                        <li>
                            <span class="help-icon">📱</span>
                            <div><strong>Mobile Users</strong><br>Enable motion sensors if prompted</div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="help-footer">
                <div class="help-actions">
                    <button onclick="replayTourGuide()" class="help-replay-guide-btn" type="button">Show floating guide</button>
                    <button onclick="closeTourHelp()" class="help-close-btn" type="button">Got it, let's explore!</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($hasWaypoints)
@vite(['resources/js/tour-engine.js'])
<script @if(request()->attributes->get('csp_nonce')) nonce="{{ request()->attributes->get('csp_nonce') }}" @endif>
    // Initialize tour engine when page loads
    let tourEngine;

    document.addEventListener('DOMContentLoaded', function() {
        tourEngine = new VirtualTourEngine('panorama-container', {
            startWaypoint: @json($startWaypoint),
            apiBase: '/api/tour',
            reserveUrl: @json(route('guest.reserve', [], false)),
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

        // Hide Motion Look button if not supported
        if (typeof DeviceOrientationEvent === 'undefined') {
            const gyroBtn = document.getElementById('gyro-btn');
            if (gyroBtn) {
                gyroBtn.style.display = 'none';
                console.log('Motion Look not supported on this device - button hidden');
            }
        }

        syncGyroButtonState();
        restoreTourMapPanelState();
        restoreViewingModesPanelState();
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

    const TOUR_MAP_STORAGE_KEY = 'tour_map_expanded';
    const VIEWING_MODES_STORAGE_KEY = 'tour_viewing_modes_expanded';

    function setTourMapPanelExpanded(expanded, { persist = true } = {}) {
        const panel = document.getElementById('minimap');
        const toggle = panel?.querySelector('.minimap-toggle');
        if (!panel || !toggle) return;

        const desktopMode = window.matchMedia('(min-width: 769px)').matches;
        const shouldExpand = desktopMode ? !!expanded : true;

        panel.classList.toggle('is-collapsed', !shouldExpand);
        toggle.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');

        if (persist && desktopMode) {
            localStorage.setItem(TOUR_MAP_STORAGE_KEY, shouldExpand ? '1' : '0');
        }
    }

    function restoreTourMapPanelState() {
        const desktopMode = window.matchMedia('(min-width: 769px)').matches;
        if (!desktopMode) {
            setTourMapPanelExpanded(true, { persist: false });
            return;
        }

        const saved = localStorage.getItem(TOUR_MAP_STORAGE_KEY);
        const shouldExpand = saved !== '0';
        setTourMapPanelExpanded(shouldExpand, { persist: false });
    }

    function toggleTourMapPanel(event) {
        event?.preventDefault();
        event?.stopPropagation();
        if (!window.matchMedia('(min-width: 769px)').matches) {
            closeMobileTourMap();
            return;
        }

        const panel = document.getElementById('minimap');
        if (!panel) return;

        setTourMapPanelExpanded(panel.classList.contains('is-collapsed'));
    }

    function syncMobileTourMapState() {
        const panel = document.getElementById('minimap');
        const button = document.getElementById('nav-scene-name');
        const viewer = document.getElementById('tour-viewer');
        const isOpen = !!panel?.classList.contains('mobile-open');

        button?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        viewer?.classList.toggle('mobile-map-open', isOpen);
        if (!isOpen && tourEngine?._tourGuideActive) {
            setTimeout(() => tourEngine._showTourGuideStep(), 80);
        }
    }

    function syncMobileTourMapTrigger() {
        const button = document.getElementById('nav-scene-name');
        if (!button) return;

        const mobileMode = window.matchMedia('(max-width: 768px)').matches;
        button.tabIndex = mobileMode ? 0 : -1;
        button.toggleAttribute('aria-disabled', !mobileMode);
        button.setAttribute('title', mobileMode ? 'Open Tour Map' : 'Current tour location');
        button.setAttribute('aria-label', mobileMode ? 'Open Tour Map' : 'Current tour location');

        if (!mobileMode) {
            button.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleMobileTourMap(event) {
        event?.preventDefault();
        event?.stopPropagation();
        if (!window.matchMedia('(max-width: 768px)').matches) return;

        const panel = document.getElementById('minimap');
        if (!panel) return;

        tourEngine?._showUI?.();
        if (tourEngine) {
            tourEngine._uiManuallyHidden = false;
            tourEngine._syncToggleUIBtn?.(false);
            tourEngine._resetUIIdleTimer?.();
        }

        closeMobileTourSettings();
        panel.classList.remove('ui-hidden');
        panel.classList.toggle('mobile-open');
        syncMobileTourMapState();

        if (panel.classList.contains('mobile-open')) {
            tourEngine?._hideGazeTooltip?.();
        }
    }

    function closeMobileTourMap() {
        document.getElementById('minimap')?.classList.remove('mobile-open');
        syncMobileTourMapState();
    }

    function setViewingModesPanelExpanded(expanded, { persist = true } = {}) {
        const panel = document.querySelector('[data-viewing-modes]');
        const toggle = panel?.querySelector('.vr-controls-header');
        if (!panel || !toggle) return;

        const desktopMode = window.matchMedia('(min-width: 769px)').matches;
        const shouldExpand = desktopMode ? !!expanded : true;

        panel.classList.toggle('is-collapsed', !shouldExpand);
        toggle.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');

        if (persist && desktopMode) {
            localStorage.setItem(VIEWING_MODES_STORAGE_KEY, shouldExpand ? '1' : '0');
        }
    }

    function restoreViewingModesPanelState() {
        const desktopMode = window.matchMedia('(min-width: 769px)').matches;
        if (!desktopMode) {
            setViewingModesPanelExpanded(true, { persist: false });
            return;
        }

        const saved = localStorage.getItem(VIEWING_MODES_STORAGE_KEY);
        setViewingModesPanelExpanded(saved === '1', { persist: false });
    }

    function toggleViewingModesPanel(event) {
        event?.preventDefault();
        event?.stopPropagation();
        if (!window.matchMedia('(min-width: 769px)').matches) return;

        const panel = document.querySelector('[data-viewing-modes]');
        if (!panel) return;

        setViewingModesPanelExpanded(panel.classList.contains('is-collapsed'));
    }

    function toggleMobileTourSettings(event) {
        event?.preventDefault();
        event?.stopPropagation();
        const panel = document.querySelector('.vr-controls');
        if (!panel) return;
        tourEngine?._showUI?.();
        if (tourEngine) {
            tourEngine._uiManuallyHidden = false;
            tourEngine._syncToggleUIBtn?.(false);
            tourEngine._resetUIIdleTimer?.();
        }
        closeMobileTourMap();
        panel.classList.remove('ui-hidden');
        panel.classList.toggle('mobile-open');
        syncMobileTourSettingsState();
        if (panel.classList.contains('mobile-open')) {
            tourEngine?._hideGazeTooltip?.();
        }
    }

    function syncMobileTourSettingsState() {
        const panel = document.querySelector('.vr-controls');
        const viewer = document.getElementById('tour-viewer');
        const isOpen = !!panel?.classList.contains('mobile-open');
        viewer?.classList.toggle('mobile-settings-open', isOpen);
        if (!isOpen && tourEngine?._tourGuideActive) {
            setTimeout(() => tourEngine._showTourGuideStep(), 80);
        }
    }

    function closeMobileTourSettings() {
        document.querySelector('.vr-controls')?.classList.remove('mobile-open');
        syncMobileTourSettingsState();
    }

    window.toggleMobileTourSettings = toggleMobileTourSettings;
    window.closeMobileTourSettings = closeMobileTourSettings;
    window.toggleMobileTourMap = toggleMobileTourMap;
    window.closeMobileTourMap = closeMobileTourMap;
    window.toggleTourMapPanel = toggleTourMapPanel;
    window.toggleViewingModesPanel = toggleViewingModesPanel;

    document.querySelector('#minimap .minimap-toggle')?.addEventListener('click', toggleTourMapPanel);
    document.querySelector('.vr-controls-header')?.addEventListener('click', toggleViewingModesPanel);
    document.getElementById('nav-scene-name')?.addEventListener('click', toggleMobileTourMap);
    document.getElementById('nav-scene-name')?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        toggleMobileTourMap(event);
    });
    document.getElementById('mobile-settings-btn')?.addEventListener('click', toggleMobileTourSettings);
    syncMobileTourMapTrigger();

    const mobileTourSettingsPanel = document.querySelector('.vr-controls');
    if (mobileTourSettingsPanel) {
        new MutationObserver(syncMobileTourSettingsState).observe(mobileTourSettingsPanel, {
            attributes: true,
            attributeFilter: ['class'],
        });
    }

    document.addEventListener('click', (event) => {
        const panel = document.querySelector('.vr-controls');
        const button = document.getElementById('mobile-settings-btn');
        if (!panel || !button || !panel.classList.contains('mobile-open')) return;
        if (panel.contains(event.target) || button.contains(event.target)) return;
        closeMobileTourSettings();
    });

    document.addEventListener('click', (event) => {
        const panel = document.getElementById('minimap');
        const button = document.getElementById('nav-scene-name');
        if (!panel || !button || !panel.classList.contains('mobile-open')) return;
        if (panel.contains(event.target) || button.contains(event.target)) return;
        closeMobileTourMap();
    });

    document.addEventListener('pointerdown', (event) => {
        if (!window.matchMedia('(max-width: 768px)').matches) return;
        const panel = document.querySelector('.vr-controls');
        const button = document.getElementById('mobile-settings-btn');
        if (!panel || !button || !panel.classList.contains('mobile-open')) return;
        if (panel.contains(event.target) || button.contains(event.target)) return;
        closeMobileTourSettings();
    }, true);

    document.addEventListener('pointerdown', (event) => {
        if (!window.matchMedia('(max-width: 768px)').matches) return;
        const panel = document.getElementById('minimap');
        const button = document.getElementById('nav-scene-name');
        if (!panel || !button || !panel.classList.contains('mobile-open')) return;
        if (panel.contains(event.target) || button.contains(event.target)) return;
        closeMobileTourMap();
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeMobileTourMap();
    });

    window.addEventListener('resize', () => {
        if (!window.matchMedia('(max-width: 768px)').matches) {
            closeMobileTourMap();
        }
        syncMobileTourMapTrigger();
        restoreTourMapPanelState();
        restoreViewingModesPanelState();
    });

    // Elements that must remain visible inside any fullscreen context
    const _fsOverlays = ['room-info-overlay', 'reservation-modal', 'reservation-success-modal'].map(id => document.getElementById(id)).filter(Boolean);
    const _fsOverlayHome = document.getElementById('tour-viewer');

    function _syncOverlaysToFullscreen() {
        closeMobileTourSettings();
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
        closeMobileTourSettings();
        if (!isFs) {
            tourEngine?._showUI?.();
            tourEngine && (tourEngine._uiManuallyHidden = false);
            tourEngine?._syncToggleUIBtn?.(false);
        }
        
        document.getElementById('fs-expand-icon').style.display = isFs ? 'none' : '';
        document.getElementById('fs-compress-icon').style.display = isFs ? '' : 'none';
        document.getElementById('fs-btn-text').textContent = isFs ? 'Exit Fullscreen' : 'Fullscreen';
        _syncOverlaysToFullscreen();
    });

    // Safari / iOS WebKit prefix
    document.addEventListener('webkitfullscreenchange', () => {
        closeMobileTourSettings();
        
        _syncOverlaysToFullscreen();
    });
    async function startWebXRTestMode() {
        if (!tourEngine) return;
        closeMobileTourSettings();
        try {
            await tourEngine.startWebXRTest();
        } catch (error) {
            console.error('WebXR test error:', error);
            tourEngine._showToast(error?.message || 'Could not start WebXR test mode.', 'error');
        }
    }

    function syncGyroButtonState() {
        const btn = document.getElementById('gyro-btn');
        const text = document.getElementById('gyro-btn-text');
        const isActive = tourEngine?.gyroscopePlugin?.isEnabled();

        if (!btn || !text) return;

        btn.classList.toggle('active', !!isActive);
        text.textContent = isActive ? 'Motion ON' : 'Motion Look';
    }

    // Motion Look toggle
    async function toggleGyro() {
        if (!tourEngine) return;
        
        try {
            await tourEngine.toggleGyroscope();
            const isActive = tourEngine.gyroscopePlugin?.isEnabled();
            syncGyroButtonState();

            if (isActive) {
                tourEngine._showToast('Motion Look enabled - tilt your device to look around', 'success');
            }
        } catch (error) {
            console.error('Motion Look error:', error);
            closeMobileTourSettings();
            
            let errorMessage = 'Motion Look is not available on this device';
            
            if (error.message?.includes('denied') || error.message?.includes('permission')) {
                errorMessage = 'Permission denied. Enable in Settings → Safari → Motion & Orientation Access';
            } else if (window.location.protocol === 'http:' && window.location.hostname !== 'localhost') {
                errorMessage = 'Motion Look requires HTTPS connection on mobile devices';
            } else if (error.message?.includes('not initialized')) {
                errorMessage = 'Motion Look is not supported on this device';
            }
            
            tourEngine._showToast(errorMessage, 'error');
        }
    }

    function syncTourReservationDateInputs() {
        const checkIn = document.getElementById('check_in_date');
        const checkOut = document.getElementById('check_out_date');
        if (!checkIn || !checkOut || !checkIn.value) return;

        const [year, month, day] = checkIn.value.split('-').map(Number);
        const minDate = new Date(year, month - 1, day);
        minDate.setDate(minDate.getDate() + 1);
        const minCheckOut = [
            minDate.getFullYear(),
            String(minDate.getMonth() + 1).padStart(2, '0'),
            String(minDate.getDate()).padStart(2, '0'),
        ].join('-');

        checkIn.min = checkIn.min || '{{ date('Y-m-d') }}';
        checkOut.min = minCheckOut;

        if (!checkOut.value || checkOut.value <= checkIn.value) {
            checkOut.value = minCheckOut;
        }
    }

    document.getElementById('check_in_date')?.addEventListener('change', () => {
        syncTourReservationDateInputs();
        if (window.tourEngine) {
            tourEngine._setCheckIn(document.getElementById('check_in_date')?.value);
            tourEngine._setCheckOut(document.getElementById('check_out_date')?.value);
            tourEngine._refreshReservationOccupantLimit?.();
        }
    });
    document.getElementById('check_out_date')?.addEventListener('change', () => {
        if (window.tourEngine) {
            tourEngine._setCheckOut(document.getElementById('check_out_date')?.value);
            tourEngine._refreshReservationOccupantLimit?.();
        }
    });
    document.getElementById('requested_room_count')?.addEventListener('input', () => {
        if (window.tourEngine) {
            tourEngine._refreshReservationOccupantLimit?.();
        }
    });
    syncTourReservationDateInputs();

    // Handle reservation form submission
    async function handleReservationSubmit(event) {
        event.preventDefault();

        const form = event.target;
        if (window.GuestRealtimeValidation && !window.GuestRealtimeValidation.validateForm(form, true)) {
            const firstInvalid = form.querySelector('.guest-field-invalid');
            firstInvalid?.focus({ preventScroll: true });
            firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
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
            requested_room_count: document.getElementById('requested_room_count')?.value || 1,
            check_in_date: document.getElementById('check_in_date').value,
            check_out_date: document.getElementById('check_out_date').value,
            number_of_occupants: document.getElementById('number_of_occupants').value,
            special_requests: document.getElementById('special_requests').value,
            availability_acknowledged: document.getElementById('availability_acknowledged')?.checked ? 1 : 0,
            source: 'virtual_tour'
        };

        if (!formData.preferred_room_type_id) {
            alert('Please open View Details and Request from a room scene before submitting a reservation request.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.originalText || 'Request This Room Type';
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
                submitBtn.textContent = submitBtn.dataset.originalText || 'Request This Room Type';
            }
        }
    }

    // Tour Help guide
    function openTourHelp() {
        tourEngine?.tourGuideLayer?.classList.remove('is-visible');
        document.getElementById('tour-help-modal').style.display = 'flex';
    }

    function closeTourHelp() {
        document.getElementById('tour-help-modal').style.display = 'none';
        if (tourEngine?._tourGuideActive) {
            setTimeout(() => tourEngine._showTourGuideStep(), 100);
        }
    }

    function replayTourGuide() {
        closeTourHelp();
        setTimeout(() => tourEngine?.startTourGuide?.({ force: true }), 160);
    }

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
@endif
@endpush
