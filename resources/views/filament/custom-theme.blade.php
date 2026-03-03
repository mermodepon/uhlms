<style>
/*
 * CMU Color Theme
 * Yellow: #FFC600
 * Green: #00491E
 * Alt Green 1: #02681E
 * Alt Green 2: #919F02
 */

/* ===== Sidebar ===== */
.fi-sidebar {
    background-color: #00491E !important;
    border-right: 1px solid #02681E !important;
}

.fi-sidebar-open {
    ring-color: transparent !important;
}

.fi-sidebar-header {
    background-color: #00491E !important;
    border-bottom: 2px solid rgba(255, 198, 0, 0.4) !important;
}

/* Sidebar brand name */
.fi-sidebar-header a,
.fi-sidebar-header span,
.fi-sidebar-header div {
    color: #FFFFFF !important;
    font-weight: 700;
}

/* Sidebar nav */
.fi-sidebar-nav {
    background-color: #00491E !important;
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
    border-left-color: rgba(255, 198, 0, 0.5) !important;
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
    border-left-color: #FFC600 !important;
}

.fi-sidebar-item.fi-active .fi-sidebar-item-label {
    color: #FFC600 !important;
    font-weight: 600 !important;
}

.fi-sidebar-item.fi-active .fi-sidebar-item-icon {
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

/* ===== Topbar ===== */
.fi-topbar > nav {
    background-color: #00491E !important;
    border-bottom: 3px solid #FFC600 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15) !important;
}

/* Remove default ring/shadow on topbar nav */
.fi-topbar > nav > div {
    background-color: transparent !important;
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
    background: linear-gradient(145deg, #00491E 0%, #02681E 60%, #003315 100%) !important;
}

.fi-simple-main {
    border-top: 4px solid #FFC600 !important;
}

/* Auth page heading */
.fi-simple-layout .fi-logo {
    color: #00491E !important;
}

/* ===== Global accent overrides ===== */

/* Primary buttons */
.fi-btn-primary {
    color: #FFFFFF !important;
}

/* Dashboard heading */
.fi-header-heading {
    color: #00491E !important;
}

/* Table header cells */
.fi-ta-header-cell-label {
    color: #00491E !important;
    font-weight: 600;
}

/* Breadcrumbs */
.fi-breadcrumbs a {
    color: #00491E !important;
}

.fi-breadcrumbs a:hover {
    color: #02681E !important;
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
    background: linear-gradient(145deg, #001a0b 0%, #00491E 60%, #001a0b 100%) !important;
}

.dark .fi-simple-main {
    background-color: #18181b !important;
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
</style>

{{-- Chart.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

{{-- Chart Helpers (moved to external file) --}}
<script src="{{ asset('js/cmu-charts.js') }}"></script>

@auth
<script>
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
</script>
@endauth
