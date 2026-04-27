import { Viewer } from '@photo-sphere-viewer/core';
import '@photo-sphere-viewer/core/index.css';

function initTourPreview(el) {
    const panorama = el.dataset.panorama;

    if (!panorama) return;

    const yaw = Number.parseFloat(el.dataset.yaw ?? '0');
    const pitch = Number.parseFloat(el.dataset.pitch ?? '0');
    const zoom = Number.parseInt(el.dataset.zoom ?? '50', 10);

    el.innerHTML = '';

    new Viewer({
        container: el,
        panorama,
        navbar: false,
        defaultYaw: `${Number.isFinite(yaw) ? yaw : 0}deg`,
        defaultPitch: `${Number.isFinite(pitch) ? pitch : 0}deg`,
        defaultZoomLvl: Number.isFinite(zoom) ? zoom : 50,
        mousewheel: false,
        mousemove: false,
        touchmoveTwoFingers: false,
        moveInertia: false,
        keyboard: false,
        loadingImg: null,
        touchmove: false,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tour-preview]').forEach((el) => {
        initTourPreview(el);
    });
});
