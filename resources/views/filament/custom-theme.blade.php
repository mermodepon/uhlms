<style>
/*
 * CMU Color Theme
 * Yellow: #FFC600
 * Green: #003615
 * Alt Green 1: #02681E
 * Alt Green 2: #919F02
 */

/* ===== Typography ===== */
:root {
    --admin-font-body: "Segoe UI", Arial, sans-serif;
    --admin-font-display: "Georgia", "Times New Roman", serif;
}

body,
.fi-body,
.filament-main,
.fi-ta-text,
.fi-input,
.fi-select-input,
.fi-textarea,
button,
input,
select,
textarea {
    font-family: var(--admin-font-body) !important;
}

.fi-header-heading,
.fi-section-header-heading,
.fi-page-subheading,
.fi-ta-header-cell-label,
.fi-sidebar-group-label,
.fi-modal-heading,
.fi-modal-description,
h1,
h2,
h3,
h4,
h5,
h6 {
    font-family: var(--admin-font-body) !important;
}

.filament-brand-text,
.fi-logo {
    font-family: var(--admin-font-display) !important;
}

/* ===== Sidebar ===== */
.fi-sidebar {
    background-color: #003615 !important;
    border-right: 1px solid #02681E !important;
}

.fi-sidebar-open {
    ring-color: transparent !important;
}

.fi-sidebar-header {
    background-color: #003615 !important;
    border-bottom: 2px solid rgba(255, 198, 0, 0.4) !important;
    min-height: 4.5rem !important;
    padding: 0.85rem 1.25rem !important;
}

.uh-brand-logo {
    padding: 0.15rem 0.25rem !important;
}

/* Sidebar brand name */
.fi-sidebar-header a,
.fi-sidebar-header span,
.fi-sidebar-header div {
    color: #FFFFFF !important;
    font-weight: 700;
}

.fi-sidebar-header .uh-brand-title,
.fi-topbar .uh-brand-title {
    color: #FFC600 !important;
}

.fi-sidebar-header .uh-brand-subtitle,
.fi-topbar .uh-brand-subtitle {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Sidebar nav */
.fi-sidebar-nav {
    background-color: #003615 !important;
}

.fi-sidebar-nav-groups {
    background-color: transparent !important;
}

/* Sidebar group header */
.fi-sidebar-group-button {
    color: rgba(255, 255, 255, 0.5) !important;
    padding-inline: 1rem !important;
    margin-top: 0.5rem !important;
}

.fi-sidebar-group-label {
    color: rgba(255, 198, 0, 0.7) !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    font-size: 0.65rem !important;
    letter-spacing: 0.1em !important;
}

.fi-sidebar-group-icon {
    color: rgba(255, 198, 0, 0.55) !important;
}

.fi-sidebar-group-collapse-button {
    color: rgba(255, 255, 255, 0.35) !important;
}

/* Divider between groups */
.fi-sidebar-group + .fi-sidebar-group {
    border-top: 1px solid rgba(255, 255, 255, 0.07) !important;
    padding-top: 0.25rem !important;
}

/* Sidebar nav item link */
.fi-sidebar-item-button {
    color: rgba(255, 255, 255, 0.9) !important;
    border-radius: 0.5rem !important;
    padding-left: 0.875rem !important;
    margin-inline: 0.5rem !important;
    position: relative !important;
    transition: background-color 0.15s ease, border-color 0.15s ease !important;
    border-left: 3px solid transparent !important;
}

.fi-sidebar-item-label {
    color: rgba(255, 255, 255, 0.85) !important;
    font-size: 0.875rem !important;
    font-weight: 400 !important;
    letter-spacing: 0.01em !important;
}

.fi-sidebar-item-icon {
    color: rgba(255, 255, 255, 0.5) !important;
}

/* Hide the old dot/vertical-line connector — replaced by left-border accent */
.fi-sidebar-item-grouped-border {
    display: none !important;
}

/* Sidebar item hover */
.fi-sidebar-item-button:hover {
    background-color: rgba(255, 255, 255, 0.07) !important;
}

.fi-sidebar-item-button:hover .fi-sidebar-item-label {
    color: #FFC600 !important;
}

.fi-sidebar-item-button:hover .fi-sidebar-item-icon {
    color: #FFC600 !important;
}

/* Sidebar active item */
.fi-sidebar-item.fi-active .fi-sidebar-item-button {
    background-color: rgba(255, 198, 0, 0.12) !important;
}

.fi-sidebar-item.fi-active .fi-sidebar-item-label {
    color: #FFC600 !important;
    font-weight: 600 !important;
}

.fi-sidebar-item.fi-active .fi-sidebar-item-icon {
    color: #FFC600 !important;
}

/* Sidebar group active state when collapsed (icon-only dropdown triggers) */
.fi-sidebar-group.fi-active .fi-dropdown-trigger button {
    background-color: rgba(255, 198, 0, 0.15) !important;
    border-radius: 0.5rem !important;
}

.fi-sidebar-group.fi-active .fi-dropdown-trigger button svg {
    color: #FFC600 !important;
}

.fi-sidebar-group .fi-dropdown-trigger button:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}

.fi-sidebar-group .fi-dropdown-trigger button:hover svg {
    color: #FFC600 !important;
}

/* Sidebar collapse/toggle buttons */
.fi-sidebar .fi-icon-btn {
    color: rgba(255, 255, 255, 0.7) !important;
}

.fi-sidebar .fi-icon-btn:hover {
    color: #FFC600 !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
}

/* Keep the expanded sidebar scrollable while flyout menus stay above dense dashboard widgets. */
.fi-sidebar {
    overflow: visible !important;
    z-index: 80 !important;
}

.fi-sidebar-nav {
    max-height: calc(100vh - 5rem) !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    scrollbar-gutter: stable !important;
}

.fi-sidebar-nav-groups,
.fi-sidebar-group,
.fi-sidebar-group-items {
    min-width: 0 !important;
}

.fi-sidebar .fi-dropdown-panel,
.fi-sidebar [class*="fi-dropdown-panel"],
.fi-sidebar [role="menu"] {
    z-index: 9999 !important;
    width: max-content !important;
    min-width: 18rem !important;
    max-width: 24rem !important;
}

.fi-sidebar .fi-dropdown-panel {
    inline-size: max-content !important;
}

.fi-sidebar .fi-dropdown-list,
.fi-sidebar .fi-dropdown-list-item,
.fi-sidebar .fi-dropdown-list-item-label {
    min-width: 0 !important;
}

.fi-sidebar .fi-dropdown-list-item-label {
    overflow: visible !important;
    text-overflow: clip !important;
    white-space: nowrap !important;
}

.fi-sidebar .fi-dropdown-list-item {
    position: relative !important;
    z-index: 1 !important;
}

/* ===== Topbar ===== */
.fi-topbar > nav {
    background-color: #003615 !important;
    border-bottom: 3px solid #FFC600 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15) !important;
    min-height: 4.5rem !important;
}

/* Remove default ring/shadow on topbar nav */
.fi-topbar > nav > div {
    background-color: transparent !important;
    min-height: 4.5rem !important;
    padding-block: 0.85rem !important;
}

/* Topbar buttons (hamburger, close sidebar) */
.fi-topbar .fi-icon-btn,
.fi-topbar-open-sidebar-btn,
.fi-topbar-close-sidebar-btn {
    color: #FFFFFF !important;
}

.fi-topbar .fi-icon-btn:hover,
.fi-topbar-open-sidebar-btn:hover,
.fi-topbar-close-sidebar-btn:hover {
    color: #FFC600 !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
}

/* Topbar brand text */
.fi-topbar a[href] {
    color: #FFFFFF !important;
}

/* Topbar user menu */
.fi-topbar .fi-avatar {
    border-color: rgba(255, 198, 0, 0.5) !important;
}

/* Topbar item (when using top navigation) */
.fi-topbar-item-button {
    color: rgba(255, 255, 255, 0.85) !important;
}

.fi-topbar-item-label {
    color: rgba(255, 255, 255, 0.85) !important;
}

.fi-topbar-item-icon {
    color: rgba(255, 255, 255, 0.6) !important;
}

.fi-topbar-item.fi-active .fi-topbar-item-button {
    background-color: rgba(255, 198, 0, 0.15) !important;
}

.fi-topbar-item.fi-active .fi-topbar-item-label {
    color: #FFC600 !important;
}

.fi-topbar-item.fi-active .fi-topbar-item-icon {
    color: #FFC600 !important;
}

/* ===== Login / Auth Pages ===== */
.fi-simple-layout {
    background:
        radial-gradient(circle at 50% 8%, rgba(255, 198, 0, 0.16) 0, rgba(255, 198, 0, 0) 26rem),
        linear-gradient(145deg, #003615 0%, #02681E 58%, #002f13 100%) !important;
    min-height: 100vh !important;
}

.fi-simple-layout::before {
    content: '' !important;
    position: fixed !important;
    inset: 0 !important;
    pointer-events: none !important;
    background:
        linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px) !important;
    background-size: 4rem 4rem !important;
    mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.65), transparent 70%) !important;
}

.fi-simple-main {
    border-top: 4px solid #FFC600 !important;
    border-radius: 0.875rem !important;
    background: rgba(255, 255, 255, 0.98) !important;
    box-shadow:
        0 1.5rem 4rem rgba(0, 0, 0, 0.32),
        0 0.5rem 1.5rem rgba(0, 54, 21, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
    outline: 1px solid rgba(255, 255, 255, 0.65) !important;
    overflow: hidden !important;
    position: relative !important;
}

.fi-simple-main::before {
    content: '' !important;
    position: absolute !important;
    inset: 0 0 auto !important;
    height: 5rem !important;
    pointer-events: none !important;
    background: linear-gradient(180deg, rgba(255, 198, 0, 0.08), transparent) !important;
}

/* Auth page heading */
.fi-simple-layout .fi-logo {
    color: #003615 !important;
    margin-bottom: 2rem !important;
}

.fi-simple-layout .uh-brand-logo {
    justify-content: center !important;
    gap: 1rem !important;
    margin-bottom: 0 !important;
}

.fi-simple-layout .uh-brand-logo img {
    border-radius: 0.55rem !important;
    box-shadow:
        0 0.6rem 1.4rem rgba(0, 54, 21, 0.16),
        0 0 0 1px rgba(0, 54, 21, 0.08) !important;
}

.fi-simple-layout .uh-brand-title {
    color: #E7A900 !important;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) !important;
}

.fi-simple-layout .uh-brand-subtitle {
    color: rgba(0, 54, 21, 0.72) !important;
    font-family: var(--admin-font-body) !important;
    font-weight: 600 !important;
    letter-spacing: 0.04em !important;
    margin-top: 0.2rem !important;
    text-transform: uppercase !important;
}

.fi-simple-main .fi-input-wrp {
    background-color: #F8FBF8 !important;
    border-color: rgba(0, 54, 21, 0.42) !important;
    box-shadow:
        0 0.25rem 0.75rem rgba(0, 54, 21, 0.08),
        inset 0 0 0 1px rgba(0, 54, 21, 0.1) !important;
    transition: border-color 0.16s ease, box-shadow 0.16s ease !important;
}

.fi-simple-main .fi-input {
    background-color: transparent !important;
}

.fi-simple-main .fi-input-wrp:focus-within {
    border-color: rgba(0, 73, 30, 0.55) !important;
    box-shadow:
        0 0 0 3px rgba(255, 198, 0, 0.24),
        0 0.45rem 1rem rgba(0, 54, 21, 0.08) !important;
}

.fi-simple-main .fi-btn-primary {
    background: linear-gradient(180deg, #006326 0%, #00491E 100%) !important;
    box-shadow: 0 0.7rem 1.3rem rgba(0, 73, 30, 0.24) !important;
    transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease !important;
}

.fi-simple-main .fi-btn-primary:hover {
    filter: brightness(1.04) !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 0.9rem 1.5rem rgba(0, 73, 30, 0.3) !important;
}

/* ===== Global accent overrides ===== */

/* Primary buttons */
.fi-btn-primary {
    color: #FFFFFF !important;
}

/* Dashboard heading */
.fi-header-heading {
    color: #003615 !important;
}

/* Table header cells */
.fi-ta-header-cell-label {
    color: #003615 !important;
    font-weight: 600;
}

/* Breadcrumbs */
.fi-breadcrumbs a {
    color: #003615 !important;
}

.fi-breadcrumbs a:hover {
    color: #02681E !important;
}

/* ===== Modal usability ===== */
.fi-modal-header {
    position: relative !important;
    z-index: 20 !important;
}

.fi-modal-header > .absolute {
    z-index: 30 !important;
    pointer-events: auto !important;
}

.fi-modal-close-btn {
    position: relative !important;
    z-index: 40 !important;
    pointer-events: auto !important;
    opacity: 1 !important;
    color: #374151 !important;
    background-color: rgba(0, 0, 0, 0.06) !important;
    border-radius: 0.5rem !important;
}

.fi-modal-close-btn svg,
.fi-modal-close-btn .fi-icon-btn-icon {
    opacity: 1 !important;
    color: #374151 !important;
    stroke: #374151 !important;
}

.fi-modal-close-btn:hover {
    color: #111827 !important;
    background-color: rgba(0, 0, 0, 0.12) !important;
}

.dark .fi-modal-close-btn {
    color: #d1d5db !important;
    background-color: rgba(255, 255, 255, 0.1) !important;
}

.dark .fi-modal-close-btn svg,
.dark .fi-modal-close-btn .fi-icon-btn-icon {
    color: #d1d5db !important;
    stroke: #d1d5db !important;
}

.dark .fi-modal-close-btn:hover {
    color: #f9fafb !important;
    background-color: rgba(255, 255, 255, 0.16) !important;
}

/* ===== Dark Mode Overrides ===== */
.dark .fi-sidebar {
    background-color: #001a0b !important;
}

.dark .fi-sidebar-header {
    background-color: #001a0b !important;
}

.dark .fi-sidebar-nav {
    background-color: #001a0b !important;
}

.dark .fi-topbar > nav {
    background-color: #001a0b !important;
}

.dark .fi-simple-layout {
    background: linear-gradient(145deg, #001a0b 0%, #003615 60%, #001a0b 100%) !important;
}

.dark .fi-simple-main {
    background-color: #18181b !important;
    border-color: #FFC600 !important;
    box-shadow:
        0 1.5rem 4rem rgba(0, 0, 0, 0.42),
        0 0.5rem 1.5rem rgba(0, 0, 0, 0.28),
        inset 0 1px 0 rgba(255, 255, 255, 0.08) !important;
    outline-color: rgba(255, 255, 255, 0.18) !important;
}

.dark .fi-simple-layout .uh-brand-title {
    color: #FFD43B !important;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.45) !important;
}

.dark .fi-simple-layout .uh-brand-subtitle {
    color: rgba(255, 255, 255, 0.72) !important;
}

.dark .fi-header-heading {
    color: #FFC600 !important;
}

.dark .fi-ta-header-cell-label {
    color: #FFC600 !important;
}

/* Ensure brand text remains visible when sidebar is collapsed */
.fi-topbar .fi-logo {
    overflow: visible !important;
}
.fi-topbar .filament-brand-text {
    display: inline-block !important;
    opacity: 1 !important;
    max-width: none !important;
    white-space: nowrap !important;
}

/* Handle possible collapsed sidebar state classes used by Filament */
.fi-sidebar--collapsed .fi-topbar .filament-brand-text,
.fi-sidebar-collapsed .fi-topbar .filament-brand-text,
.fi-app--sidebar-collapsed .fi-topbar .filament-brand-text {
    display: inline-block !important;
}

/* Greeting beside avatar styling */
.filament-greeting-wrap {
    color: #FFFFFF !important;
    display: none !important;
    align-items: center !important;
    gap: .25rem !important;
}

.filament-greeting,
.filament-name {
    color: #FFFFFF !important;
    font-weight: 700 !important;
    font-size: .95rem !important;
}

@media (min-width: 768px) {
    .filament-greeting-wrap {
        display: inline-flex !important;
    }
}

/* ===== Loading Animations ===== */

/* Enhanced Livewire loading overlay */
[wire\:loading] {
    opacity: 1 !important;
}

[wire\:loading\.delay] {
    transition: opacity 0.3s ease-in-out !important;
}

/* Custom loading spinner for action buttons */
.fi-btn[wire\:loading] {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.fi-btn[wire\:loading]::after {
    content: '';
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1rem;
    height: 1rem;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: button-spinner 0.6s linear infinite;
}

@keyframes button-spinner {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

/* Loading overlay for table refreshes */
.fi-ta[wire\:loading] {
    position: relative;
    pointer-events: none;
}

.fi-ta[wire\:loading]::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(2px);
    z-index: 50;
    transition: opacity 0.2s ease-in-out;
}

.dark .fi-ta[wire\:loading]::before {
    background-color: rgba(0, 0, 0, 0.5);
}

.fi-ta[wire\:loading]::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 3rem;
    height: 3rem;
    border: 4px solid rgba(0, 54, 21, 0.2);
    border-top-color: #003615;
    border-radius: 50%;
    animation: table-spinner 0.8s linear infinite;
    z-index: 51;
}

.dark .fi-ta[wire\:loading]::after {
    border-color: rgba(255, 198, 0, 0.2);
    border-top-color: #FFC600;
}

@keyframes table-spinner {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Loading skeleton styles for deferred tables */
.fi-ta-skeleton {
    animation: skeleton-pulse 1.5s ease-in-out infinite;
}

@keyframes skeleton-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Action group loading state */
.fi-dropdown-trigger[wire\:loading] {
    opacity: 0.6;
    cursor: wait;
}

/* Modal loading overlay */
.fi-modal[wire\:loading] .fi-modal-window {
    pointer-events: none;
}

.fi-modal[wire\:loading] .fi-modal-window::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(1px);
    z-index: 100;
    border-radius: inherit;
}

.dark .fi-modal[wire\:loading] .fi-modal-window::before {
    background-color: rgba(0, 0, 0, 0.7);
}

.fi-modal[wire\:loading] .fi-modal-window::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 2.5rem;
    height: 2.5rem;
    border: 3px solid rgba(0, 54, 21, 0.2);
    border-top-color: #003615;
    border-radius: 50%;
    animation: modal-spinner 0.7s linear infinite;
    z-index: 101;
}

.dark .fi-modal[wire\:loading] .fi-modal-window::after {
    border-color: rgba(255, 198, 0, 0.2);
    border-top-color: #FFC600;
}

@keyframes modal-spinner {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Smooth transitions for loading states */
.fi-btn,
.fi-ta,
.fi-modal-window,
.fi-dropdown-trigger {
    transition: opacity 0.2s ease-in-out;
}

/* Prevent multiple spinners when nested loading states */
[wire\:loading] [wire\:loading]::after {
    display: none;
}

/* Loading state for pagination */
.fi-ta-pagination[wire\:loading] {
    opacity: 0.6;
    pointer-events: none;
}

/* ===== Notification Panel — dismiss (X) button ===== */
/* Hide the square background by default; show only on hover */
.fi-no-notification .fi-icon-btn {
    background-color: transparent !important;
    box-shadow: none !important;
}

.fi-no-notification .fi-icon-btn:hover {
    background-color: rgba(0, 0, 0, 0.08) !important;
}

.dark .fi-no-notification .fi-icon-btn:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}

/* ===== Notification Slide-over — panel close (X) button ===== */
/* Override the global fi-modal-close-btn background for the slide-over only */
.fi-modal-slide-over-window .fi-modal-close-btn {
    background-color: transparent !important;
    box-shadow: none !important;
}

.fi-modal-slide-over-window .fi-modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.08) !important;
}

.dark .fi-modal-slide-over-window .fi-modal-close-btn:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}
</style>


{{-- Chart.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

{{-- Chart Helpers (moved to external file) --}}
<script src="{{ asset('js/cmu-charts.js') }}?v={{ filemtime(public_path('js/cmu-charts.js')) }}"></script>

@auth
<script @if(request()->attributes->get('csp_nonce')) nonce="{{ request()->attributes->get('csp_nonce') }}" @endif>
    (function() {
        const username = @json(auth()->user()->name ?? '');

        function injectUsername() {
            const avatar = document.querySelector('.fi-topbar .fi-avatar');
            if (!avatar) return;
            // Avoid inserting multiple times
            if (document.querySelector('.filament-greeting-wrap')) return;

            const wrap = document.createElement('span');
            wrap.className = 'filament-greeting-wrap';

            const greet = document.createElement('span');
            greet.className = 'filament-greeting';
            greet.textContent = 'Hello,';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'filament-name';
            nameSpan.textContent = username;

            wrap.appendChild(greet);
            wrap.appendChild(nameSpan);

            // Prefer to insert into a clickable wrapper if available so behavior stays intact
            const wrapper = avatar.closest('button, a, .fi-topbar-item, .fi-topbar-user, .fi-topbar-item-button') || avatar.parentElement;
            if (!wrapper) return;

            // Ensure wrapper lays out items horizontally
            wrapper.style.display = 'inline-flex';
            wrapper.style.alignItems = 'center';
            wrapper.style.gap = '0.5rem';

            // Insert the greeting before the avatar so it appears on the left
            wrapper.insertBefore(wrap, avatar);
        }

        // Try immediately and also observe DOM changes (Filament may re-render parts)
        document.addEventListener('DOMContentLoaded', injectUsername);
        injectUsername();

        const observer = new MutationObserver(() => injectUsername());
        observer.observe(document.documentElement, { childList: true, subtree: true });
    })();

    // Enhanced loading indicators for Livewire
    document.addEventListener('livewire:init', () => {
        // Show loading state on request start
        Livewire.hook('request', ({ uri, options, payload, respond, succeed, fail }) => {
            // Add a subtle loading indicator to the document
            document.body.style.cursor = 'wait';
            
            succeed(({ status, json }) => {
                // Remove loading cursor when request succeeds
                document.body.style.cursor = '';
            });
            
            fail(({ status, content, preventDefault }) => {
                // Remove loading cursor when request fails
                document.body.style.cursor = '';
            });
        });
        
        // Smooth scroll to top on page change (for long tables)
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            succeed(({ snapshot, effect }) => {
                // If pagination changed, scroll to table top smoothly
                if (snapshot.data && snapshot.data.page !== undefined) {
                    const table = document.querySelector('.fi-ta');
                    if (table) {
                        const tableTop = table.getBoundingClientRect().top + window.pageYOffset - 100;
                        window.scrollTo({ top: tableTop, behavior: 'smooth' });
                    }
                }
            });
        });
    });
</script>
@endauth
