/* ═══════════════════════════════════════════════════════════════════════════
   TRACKING PAGE — choose an interview slot
   (resources/views/landing/partials/interview_slot_picker)

     • Draws a month calendar: dates with an open slot are Available (green),
       dates whose slots are all taken are Fully Booked (red)
     • Clicking a date shows that date's times; taken ones are disabled
     • Shows the chosen schedule and enables Confirm once a slot is picked
     • Asks for confirmation in a modal (the choice is final online), then locks the
       button so a double click cannot book twice
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const form = document.querySelector('[data-ti-form]');
    if (!form) return;

    const chosen      = form.querySelector('[data-ti-chosen]');
    const submit      = form.querySelector('[data-ti-submit]');
    const calendar    = form.querySelector('[data-ti-calendar]');
    const days        = JSON.parse(form.dataset.tiDays || '{}');
    const MONTHS      = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                         'August', 'September', 'October', 'November', 'December'];
    const WEEKDAYS    = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    // "2026-11-05" → { y: 2026, m: 10 } (month index, like Date)
    function monthOf(ymd) {
        const parts = ymd.split('-');
        return { y: Number(parts[0]), m: Number(parts[1]) - 1 };
    }

    function ymd(y, m, d) {
        return y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }

    function monthIndex(month) {
        return month.y * 12 + month.m;
    }

    const dates      = Object.keys(days).sort();
    const firstMonth = monthOf(form.dataset.tiToday);
    const lastMonth  = dates.length ? monthOf(dates[dates.length - 1]) : firstMonth;
    const minIndex   = monthIndex(firstMonth);
    const maxIndex   = Math.max(minIndex, monthIndex(lastMonth));

    let selectedDate = form.dataset.tiInitial || '';
    let shown        = selectedDate ? monthOf(selectedDate) : firstMonth;

    // ── Calendar ────────────────────────────────────────────────────────
    function navButton(label, title, delta, disabled) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ti-cal-nav';
        button.textContent = label;
        button.title = title;
        button.setAttribute('aria-label', title);
        button.disabled = disabled;
        button.addEventListener('click', function () {
            const index = monthIndex(shown) + delta;
            shown = { y: Math.floor(index / 12), m: index % 12 };
            render();
        });
        return button;
    }

    function dayCell(date, dayNumber, outside) {
        const state = outside ? null : days[date];

        if (!state) {
            const span = document.createElement('span');
            span.className = 'ti-cal-day' + (outside ? ' is-outside' : '');
            span.textContent = dayNumber;
            return span;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ti-cal-day is-' + state + (date === selectedDate ? ' is-selected' : '');
        button.textContent = dayNumber;
        button.dataset.date = date;
        button.setAttribute('aria-pressed', date === selectedDate ? 'true' : 'false');
        button.setAttribute('aria-label', date + (state === 'open' ? ' — available' : ' — fully booked'));
        return button;
    }

    function render() {
        const index = monthIndex(shown);

        const head = document.createElement('div');
        head.className = 'ti-cal-head';
        const title = document.createElement('span');
        title.className = 'ti-cal-month';
        title.textContent = MONTHS[shown.m] + ' ' + shown.y;
        head.append(
            navButton('«', 'Previous month', -1, index <= minIndex),
            title,
            navButton('»', 'Next month', 1, index >= maxIndex)
        );

        const grid = document.createElement('div');
        grid.className = 'ti-cal-grid';
        WEEKDAYS.forEach(function (name) {
            const cell = document.createElement('span');
            cell.className = 'ti-cal-dow';
            cell.textContent = name;
            grid.appendChild(cell);
        });

        // Always six weeks, starting on the Sunday on or before the 1st.
        const start = new Date(shown.y, shown.m, 1);
        start.setDate(1 - start.getDay());
        for (let i = 0; i < 42; i++) {
            const day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            const outside = day.getMonth() !== shown.m;
            grid.appendChild(dayCell(ymd(day.getFullYear(), day.getMonth(), day.getDate()), day.getDate(), outside));
        }

        calendar.replaceChildren(head, grid);
    }

    function showTimes(date) {
        selectedDate = date;
        form.querySelectorAll('.ti-slots').forEach(function (fieldset) {
            fieldset.classList.toggle('is-active', fieldset.dataset.tiDate === date);
        });
        form.classList.toggle('has-date', !!date);
        render();
    }

    calendar.addEventListener('click', function (e) {
        const day = e.target.closest('.ti-cal-day[data-date]');
        if (day) showTimes(day.dataset.date);
    });

    form.classList.add('ti-js');
    showTimes(selectedDate);

    // ── Selection & confirm ─────────────────────────────────────────────
    form.addEventListener('change', function (e) {
        if (!e.target.matches('[data-ti-slot]')) return;

        chosen.textContent = 'Selected: ';
        const strong = document.createElement('strong');
        strong.textContent = e.target.dataset.tiLabel;
        chosen.appendChild(strong);
        submit.disabled = false;
    });

    // The first submit opens the confirmation modal; its Confirm button sends
    // the form for real.
    const modalEl   = document.getElementById('tiConfirmModal');
    // Out of the page's cards: inside them the modal would sit under its own backdrop.
    if (modalEl) document.body.appendChild(modalEl);
    const modal     = modalEl && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const confirmOk = modalEl ? modalEl.querySelector('[data-ti-confirm-ok]') : null;
    let confirmed   = false;

    form.addEventListener('submit', function (e) {
        const slot = form.querySelector('[data-ti-slot]:checked');
        if (!slot) {
            e.preventDefault();
            return;
        }

        if (!confirmed && modal) {
            e.preventDefault();
            ['date', 'time', 'mode'].forEach(function (key) {
                const field = modalEl.querySelector('[data-ti-confirm="' + key + '"]');
                if (field) field.textContent = slot.dataset['ti' + key.charAt(0).toUpperCase() + key.slice(1) + 'Label'] || '';
            });
            modal.show();
            return;
        }

        setTimeout(function () {
            submit.disabled = true;
            submit.textContent = 'Confirming…';
        }, 0);
    });

    if (confirmOk) {
        confirmOk.addEventListener('click', function () {
            confirmed = true;
            confirmOk.disabled = true;
            confirmOk.textContent = 'Confirming…';
            form.requestSubmit(submit);
        });
    }
})();
