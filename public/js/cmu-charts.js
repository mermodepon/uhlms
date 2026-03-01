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

// Print report without charts: hide chart containers during print
window.printReportNoCharts = function() {
    var els = document.querySelectorAll('.chart-container');
    els.forEach(function(el) { el.classList.add('no-print'); });
    // give the DOM a moment to apply the class before printing
    setTimeout(function() {
        try { window.print(); } catch(e) { console.error('print failed', e); }
        // restore after a short delay (print dialog may block, but we attempt cleanup)
        setTimeout(function() { els.forEach(function(el) { el.classList.remove('no-print'); }); }, 500);
    }, 150);
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
