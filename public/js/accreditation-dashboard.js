/* ═══════════════════════════════════════════════════════════════════════════
   ACCREDITATION DASHBOARD (resources/views/admin/accreditation/dashboard)

     • Monthly applications chart — data comes from the canvas's data-*
       attributes, so the Blade view holds no script
     • Year picker submits on change
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) select.form.submit();
        });
    });

    const canvas = document.getElementById('barChart');
    if (!canvas || typeof Chart === 'undefined') return;

    function series(name) {
        try {
            return JSON.parse(canvas.dataset[name] || '[]');
        } catch (e) {
            return [];
        }
    }

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: series('months'),
            datasets: [
                { label: 'New',        data: series('new'),        backgroundColor: 'rgba(26,111,189,.75)', borderRadius: 4 },
                { label: 'Renewal',    data: series('renewal'),    backgroundColor: 'rgba(230,126,34,.75)', borderRadius: 4 },
                { label: 'Accredited', data: series('accredited'), backgroundColor: 'rgba(39,174,96,.75)',  borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 } } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,.05)' } }
            }
        }
    });
})();
