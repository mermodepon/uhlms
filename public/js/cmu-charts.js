// Chart helpers for CMUCharts
// Requires Chart.js loaded before this file
window.CMUCharts = {
    init: function(el) {
        try {
            var canvas = el.querySelector('canvas');
            if (!canvas || typeof Chart === 'undefined') return;
            try {
                var existing = Chart.getChart(canvas);
                if (existing) existing.destroy();
            } catch(e) {
                console.warn('CMUCharts: error destroying existing chart', e);
            }

            var configEl = el.querySelector('script[data-chart]');
            if (!configEl) {
                console.warn('CMUCharts: no config script found for chart container', el);
                return;
            }

            function decodeHtmlEntities(str) {
                var txt = document.createElement('textarea');
                txt.innerHTML = str;
                return txt.value;
            }

            var raw = configEl.textContent || configEl.innerHTML || '';
            raw = raw.trim();
            if (!raw) {
                console.warn('CMUCharts: empty chart config');
                return;
            }

            var config = null;
            try {
                config = JSON.parse(raw);
            } catch (jsonErr) {
                try {
                    var decoded = decodeHtmlEntities(raw);
                    config = JSON.parse(decoded);
                } catch (decodedErr) {
                    console.error('CMUCharts: failed to parse chart config JSON', jsonErr, decodedErr, {raw: raw.slice(0,200)});
                    try {
                        var note = document.createElement('div');
                        note.style.color = '#b91c1c';
                        note.style.fontSize = '13px';
                        note.style.marginTop = '8px';
                        note.textContent = 'Chart error: invalid config (see console)';
                        configEl.parentNode.appendChild(note);
                    } catch(e) {}
                    return;
                }
            }

            if (config.options && config.options._yPercent) {
                config.options.scales = config.options.scales || {};
                config.options.scales.y = config.options.scales.y || {};
                config.options.scales.y.ticks = config.options.scales.y.ticks || {};
                config.options.scales.y.ticks.callback = function(v) { return v + '%'; };
                delete config.options._yPercent;
            }

            try {
                new Chart(canvas, config);
            } catch (chartErr) {
                console.error('CMUCharts: Chart creation failed', chartErr, config);
                try {
                    var note2 = document.createElement('div');
                    note2.style.color = '#b91c1c';
                    note2.style.fontSize = '13px';
                    note2.style.marginTop = '8px';
                    note2.textContent = 'Chart render error (see console)';
                    canvas.parentNode.appendChild(note2);
                } catch(e) {}
            }
        } catch (err) {
            console.error('CMUCharts.init unexpected error', err);
        }
    },
    print: function(el) {
        var canvas = el.querySelector('canvas');
        if (!canvas) return;
        var h3 = el.querySelector('h3');
        var title = h3 ? h3.textContent.trim() : 'Chart';
        var dataUrl = canvas.toDataURL('image/png', 1.0);

        // Grab the print header from the page so charts use the same CMU letterhead
        var printHeader = document.querySelector('.print-header');
        var headerHtml = '';
        if (printHeader) {
            var clone = printHeader.cloneNode(true);
            clone.style.display = 'block';
            headerHtml = clone.outerHTML;
        }

        // Collect page stylesheets so the header renders correctly
        var styles = '';
        document.querySelectorAll('link[rel="stylesheet"]').forEach(function(l) {
            styles += '<link rel="stylesheet" href="' + l.href + '">';
        });

        var win = window.open('', '_blank');
        win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + title + '</title>');
        win.document.write(styles);
        win.document.write('<style>');
        win.document.write('body{font-family:Arial,sans-serif;padding:20px 40px;color:#000;background:#fff;}');
        win.document.write('.print-header{display:block!important;margin-bottom:12px;}');
        win.document.write('.chart-title{text-align:center;font-size:14pt;font-weight:bold;color:#00491E;margin:18px 0 8px;}');
        win.document.write('.chart-img{display:block;max-width:90%;margin:10px auto;}');
        win.document.write('@page{size:A4 landscape;margin:1.5cm;}');
        win.document.write('</style>');
        win.document.write('</head><body>');
        win.document.write(headerHtml);
        win.document.write('<div class="chart-title">' + title + '</div>');
        win.document.write('<img class="chart-img" src="' + dataUrl + '">');
        win.document.write('<scr' + 'ipt>window.onload=function(){window.print();window.onafterprint=function(){window.close();};}' + '</' + 'script>');
        win.document.write('</body></html>');
        win.document.close();
    }
};

window.printReport = function() {
    document.querySelectorAll('.chart-container canvas').forEach(function(canvas) {
        var existing = canvas.parentNode.querySelector('.chart-print-img');
        if (existing) existing.remove();
        try {
            var img = document.createElement('img');
            img.src = canvas.toDataURL('image/png', 1.0);
            img.className = 'chart-print-img';
            img.style.width = '100%';
            img.style.height = 'auto';
            canvas.parentNode.appendChild(img);
        } catch(e) {
            console.warn('Could not convert chart:', e);
        }
    });
    setTimeout(function() { window.print(); }, 300);
};

// Print report without charts: open a popup with only the report content to avoid blank first page
window.printReportNoCharts = function() {
    var area = document.getElementById('report-printable-area');
    if (!area) { window.print(); return; }

    // Collect all <style> tags from the current page
    var styles = '';
    document.querySelectorAll('style').forEach(function(s) { styles += s.outerHTML; });
    // Also collect print-relevant link stylesheets (skip non-print media)
    document.querySelectorAll('link[rel="stylesheet"]').forEach(function(l) {
        styles += '<link rel="stylesheet" href="' + l.href + '">';
    });

    // Clone content; remove .no-print elements from the clone
    var clone = area.cloneNode(true);
    clone.querySelectorAll('.no-print').forEach(function(el) { el.remove(); });
    // Make print-header visible in the popup
    var ph = clone.querySelector('.print-header');
    if (ph) { ph.style.display = 'block'; }

    var win = window.open('', '_blank', 'width=1200,height=800');
    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8">');
    win.document.write('<title>Report</title>');
    win.document.write(styles);
    win.document.write('<style>body{background:white;color:black;font-size:10pt;}@page{size:A4 landscape;margin:1.5cm;}.no-print{display:none!important;}.print-header{display:block!important;}.chart-container{display:none!important;}</style>');
    win.document.write('</head><body>');
    win.document.write(clone.outerHTML);
    win.document.write('<scr' + 'ipt>window.onload=function(){window.print();window.onafterprint=function(){window.close();};}' + '</' + 'script>');
    win.document.write('</body></html>');
    win.document.close();
};

function cmuInitAllCharts() {
    try {
        document.querySelectorAll('.chart-container').forEach(function(el) {
            if (window.CMUCharts && typeof window.CMUCharts.init === 'function') {
                window.CMUCharts.init(el);
            }
        });
    } catch (e) {
        console.error('CMUCharts auto-init error', e);
    }
}

function cmuScheduleChartInit() {
    window.requestAnimationFrame(function() {
        window.setTimeout(cmuInitAllCharts, 0);
        window.setTimeout(cmuInitAllCharts, 75);
        window.setTimeout(cmuInitAllCharts, 200);
    });
}

function cmuRegisterLivewireChartHooks() {
    if (!window.Livewire || !window.Livewire.hook || window.__cmuChartsLivewireHooksRegistered) {
        return;
    }

    window.__cmuChartsLivewireHooksRegistered = true;

    try {
        // Livewire v2
        window.Livewire.hook('message.processed', cmuScheduleChartInit);
    } catch(e) {}

    try {
        // Livewire v3 / Filament v3
        window.Livewire.hook('morph.updated', cmuScheduleChartInit);
        window.Livewire.hook('commit', function(payload) {
            if (payload && typeof payload.succeed === 'function') {
                payload.succeed(cmuScheduleChartInit);
            }
        });
    } catch(e) {}
}

// Ensure charts initialize on first load and after Livewire updates.
document.addEventListener('DOMContentLoaded', function() {
    cmuScheduleChartInit();
    cmuRegisterLivewireChartHooks();
});

document.addEventListener('livewire:init', function() {
    cmuRegisterLivewireChartHooks();
    cmuScheduleChartInit();
});

document.addEventListener('livewire:navigated', cmuScheduleChartInit);

try {
    var cmuChartObserver = new MutationObserver(function(mutations) {
        var shouldInit = mutations.some(function(mutation) {
            if (mutation.target && mutation.target.closest && mutation.target.closest('.chart-container')) {
                return true;
            }

            return Array.prototype.some.call(mutation.addedNodes || [], function(node) {
                return node.nodeType === 1 && (
                    node.matches && node.matches('.chart-container') ||
                    node.querySelector && node.querySelector('.chart-container')
                );
            });
        });

        if (shouldInit) {
            cmuScheduleChartInit();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (document.body) {
            cmuChartObserver.observe(document.body, { childList: true, subtree: true });
        }
    });
} catch(e) {
    console.error('CMUCharts MutationObserver setup failed', e);
}

// Refresh / re-render all charts on the page (useful after changing date ranges)
window.CMUCharts.refreshAll = function() {
    try {
        var containers = document.querySelectorAll('.chart-container');
        containers.forEach(function(el) {
            try {
                window.CMUCharts.init(el);
            } catch(e) {
                console.error('CMUCharts.refreshAll init error', e);
            }
        });
        return true;
    } catch(err) {
        console.error('CMUCharts.refreshAll unexpected error', err);
        return false;
    }
};
