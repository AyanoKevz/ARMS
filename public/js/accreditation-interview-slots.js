/* ═══════════════════════════════════════════════════════════════════════════
   ACCREDITATION — Manage Interview Slots
   (resources/views/admin/accreditation/division/interview_slots)

     • Mode: Face-to-Face pre-fills the default venue; Online asks for a link
     • Dates: pick one, press Add (or Enter) → a chip holding the date and
       the mode and venue chosen at that moment (dates[i][date|mode|venue]),
       so one batch can mix face-to-face and online days
     • Custom times: add / remove start–end rows from the <template>
     • A live "N slots on D dates" summary; Open Slots stays disabled until
       there is at least one date and one time
     • Remove: the confirmation modal is filled from the row's button
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const removeModal = document.getElementById('slotRemoveModal');
    if (!removeModal) return;

    removeModal.addEventListener('show.bs.modal', function (e) {
        const button = e.relatedTarget;
        if (!button) return;

        removeModal.querySelector('[data-slot-remove-form]').action = button.dataset.slotRemoveAction;
        ['date', 'time', 'mode'].forEach(function (key) {
            const field = removeModal.querySelector('[data-slot-remove-field="' + key + '"]');
            if (field) field.textContent = button.dataset['slotRemove' + key.charAt(0).toUpperCase() + key.slice(1)] || '';
        });
    });
})();

(function () {
    'use strict';

    const form = document.querySelector('[data-slot-form]');
    if (!form) return;

    const dateInput  = form.querySelector('[data-slot-date-input]');
    const dateAdd    = form.querySelector('[data-slot-date-add]');
    const chips      = form.querySelector('[data-slot-dates]');
    const chipsEmpty = form.querySelector('[data-slot-dates-empty]');
    const custom     = form.querySelector('[data-slot-custom]');
    const customAdd  = form.querySelector('[data-slot-custom-add]');
    const template   = form.querySelector('[data-slot-custom-template]');
    const venue      = form.querySelector('[data-slot-venue]');
    const summary    = form.querySelector('[data-slot-summary]');
    const submit     = form.querySelector('[data-slot-submit]');

    const DAY_FMT = new Intl.DateTimeFormat('en-US', { weekday: 'short', month: 'short', day: '2-digit', year: 'numeric' });

    const MODE_LABELS = { f2f: 'Face-to-Face', online: 'Online' };
    const MODE_ICONS  = { f2f: 'fa-users', online: 'fa-video' };

    function chosenDates() {
        return Array.from(chips.querySelectorAll('input[name$="[date]"]')).map(function (i) { return i.value; });
    }

    function currentModeValue() {
        const checked = form.querySelector('[data-slot-mode]:checked');
        return checked ? checked.value : 'f2f';
    }

    // The venue must suit the mode before a date can take it — the same
    // rules the server applies.
    function venueIsValid(mode) {
        const value = venue.value.trim();
        let message = '';

        if (!value) {
            message = mode === 'online' ? 'Enter the meeting link first.' : 'Enter the venue address first.';
        } else if (mode === 'online' && !/^https?:\/\/\S+$/i.test(value)) {
            message = 'Enter the full meeting link, starting with https://.';
        } else if (mode !== 'online' && /^(https?:\/\/|www\.)/i.test(value)) {
            message = 'A face-to-face interview needs the venue address, not a meeting link.';
        }

        venue.setCustomValidity(message);
        if (message) venue.reportValidity();

        return !message;
    }

    function hiddenInput(index, key, value) {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'dates[' + index + '][' + key + ']';
        input.value = value;
        return input;
    }

    function chosenTimeCount() {
        const presets = form.querySelectorAll('input[name="presets[]"]:checked').length;
        const customs = Array.from(custom.querySelectorAll('[data-slot-custom-row]')).filter(function (row) {
            const [start, end] = row.querySelectorAll('input[type="time"]');
            return start.value && end.value && end.value > start.value;
        }).length;

        return presets + customs;
    }

    function refresh() {
        const dates = chosenDates().length;
        const times = chosenTimeCount();

        chipsEmpty.hidden = dates > 0;
        submit.disabled = dates === 0 || times === 0;

        if (dates === 0 || times === 0) {
            summary.textContent = 'Add a date and choose at least one time.';
        } else {
            const total = dates * times;
            summary.textContent = total + ' slot' + (total === 1 ? '' : 's') + ' on ' + dates + ' date' + (dates === 1 ? '' : 's') + ' will be opened.';
        }
    }

    /* ── Dates ──────────────────────────────────────────────────────────── */
    function addDate() {
        const value = dateInput.value;
        if (!value) {
            dateInput.focus();
            return false;
        }

        if (dateInput.min && value < dateInput.min) {
            dateInput.setCustomValidity('Interview dates cannot be in the past.');
            dateInput.reportValidity();
            return false;
        }
        dateInput.setCustomValidity('');

        if (chosenDates().indexOf(value) !== -1) {
            dateInput.setCustomValidity('This date is already added. Remove it first to change its mode or venue.');
            dateInput.reportValidity();
            return false;
        }

        const mode = currentModeValue();
        if (!venueIsValid(mode)) return false;

        const index = Number(chips.dataset.nextIndex || 0);
        chips.dataset.nextIndex = String(index + 1);

        const [y, m, d] = value.split('-').map(Number);
        const chip = document.createElement('div');
        chip.className = 'slot-chip' + (mode === 'online' ? ' is-online' : '');
        chip.setAttribute('data-slot-chip', '');

        const body = document.createElement('div');
        body.className = 'slot-chip-body';

        const day = document.createElement('span');
        day.className = 'slot-chip-date';
        day.textContent = DAY_FMT.format(new Date(y, m - 1, d));

        const where = document.createElement('span');
        where.className = 'slot-chip-venue';
        const icon = document.createElement('i');
        icon.className = 'fas ' + MODE_ICONS[mode];
        where.append(icon, ' ' + MODE_LABELS[mode] + ' · ' + venue.value.trim());

        body.append(day, where);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'slot-chip-remove';
        remove.setAttribute('data-slot-chip-remove', '');
        remove.setAttribute('aria-label', 'Remove date');
        remove.textContent = '×';

        chip.append(
            body,
            hiddenInput(index, 'date', value),
            hiddenInput(index, 'mode', mode),
            hiddenInput(index, 'venue', venue.value.trim()),
            remove
        );

        // Keep the chips in date order.
        const after = Array.from(chips.querySelectorAll('[data-slot-chip]')).find(function (c) {
            return c.querySelector('input[name$="[date]"]').value > value;
        });
        chips.insertBefore(chip, after || chipsEmpty);

        dateInput.value = '';
        refresh();
        return true;
    }

    dateAdd.addEventListener('click', addDate);
    dateInput.addEventListener('input', function () { dateInput.setCustomValidity(''); });
    venue.addEventListener('input', function () { venue.setCustomValidity(''); });
    dateInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addDate();
        }
    });

    chips.addEventListener('click', function (e) {
        const remove = e.target.closest('[data-slot-chip-remove]');
        if (!remove) return;
        remove.closest('[data-slot-chip]').remove();
        refresh();
    });

    /* ── Custom times ───────────────────────────────────────────────────── */
    customAdd.addEventListener('click', function () {
        const index = Number(custom.dataset.nextIndex || 0);
        custom.dataset.nextIndex = String(index + 1);

        const html = template.innerHTML.replace(/__i__/g, String(index));
        custom.insertAdjacentHTML('beforeend', html);
        custom.lastElementChild.querySelector('input').focus();
        refresh();
    });

    custom.addEventListener('click', function (e) {
        const remove = e.target.closest('[data-slot-custom-remove]');
        if (!remove) return;
        remove.closest('[data-slot-custom-row]').remove();
        refresh();
    });

    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-slot-time]')) refresh();
    });
    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-slot-time]')) refresh();
    });

    /* ── Mode & venue ───────────────────────────────────────────────────── */
    // One box serves both modes, so each mode keeps its own entry: switching
    // to Online and back must not leave the meeting link as the venue.
    const checkedMode = form.querySelector('[data-slot-mode]:checked');
    let currentMode   = checkedMode ? checkedMode.value : 'f2f';
    const venueByMode = { f2f: venue.dataset.defaultVenue || '', online: '' };
    venueByMode[currentMode] = venue.value;

    form.querySelectorAll('[data-slot-mode]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            venueByMode[currentMode] = venue.value;
            currentMode = radio.value;
            venue.setCustomValidity('');

            venue.value       = venueByMode[currentMode];
            venue.type        = currentMode === 'online' ? 'url' : 'text';
            venue.placeholder = currentMode === 'online'
                ? 'Meeting link (e.g. https://zoom.us/j/…)'
                : 'Venue address';
        });
    });

    /* ── Submit ─────────────────────────────────────────────────────────── */
    form.addEventListener('submit', function (e) {
        // A date picked but never added is almost certainly meant to be —
        // and if its venue is not right yet, stay on the form.
        if (dateInput.value && !addDate()) {
            e.preventDefault();
            return;
        }

        if (chosenDates().length === 0 || chosenTimeCount() === 0) {
            e.preventDefault();
            refresh();
            return;
        }

        setTimeout(function () {
            submit.disabled = true;
            submit.textContent = 'Opening…';
        }, 0);
    });

    refresh();
})();
