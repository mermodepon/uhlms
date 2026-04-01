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
        var win = window.open('', '_blank');
        win.document.write('<html><head><title>' + title + '</title>');
        win.document.write('<style>body{font-family:Arial,sans-serif;text-align:center;padding:40px;}img{max-width:90%;margin:20px auto;}</style>');
        win.document.write('</head><body>');
        win.document.write('<h1 style="color:#00491E;font-size:20pt;">Central Mindanao University - University Homestay</h1>');
        win.document.write('<h2 style="color:#333;font-size:14pt;">' + title + '</h2>');
        win.document.write('<p style="color:#666;font-size:10pt;">Generated: ' + new Date().toLocaleString() + '</p>');
        win.document.write('<img src="' + dataUrl + '">');
        win.document.write('<scr' + 'ipt>window.onload=function(){window.print();}</' + 'script>');
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

// Ensure charts initialize on first load and after Livewire updates
document.addEventListener('DOMContentLoaded', function() {
    try {
        document.querySelectorAll('.chart-container').forEach(function(el) {
            if (window.CMUCharts && typeof window.CMUCharts.init === 'function') {
                window.CMUCharts.init(el);
            }
        });
    } catch (e) {
        console.error('CMUCharts auto-init error (DOMContentLoaded)', e);
    }
});

// Livewire hook: re-init charts after Livewire updates
if (window.Livewire && window.Livewire.hook) {
    try {
        window.Livewire.hook('message.processed', function() {
            document.querySelectorAll('.chart-container').forEach(function(el) {
                try { window.CMUCharts.init(el); } catch(e) { console.error('CMUCharts init error (Livewire hook)', e); }
            });
        });
    } catch(e) {
        console.error('CMUCharts Livewire hook setup failed', e);
    }
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
