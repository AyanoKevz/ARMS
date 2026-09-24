/* ══════════════════════════════════════════════════════════════
   NTC training-day and instructor pickers

   Shared by the FATPro's own NTC form (ntc.js) and the Training
   Evaluator's details editor on the admin NTC page, which present
   the same two controls against the same blade partials.

   Everything here is driven by data attributes and id prefixes, so
   one page can carry several independent copies.
   ══════════════════════════════════════════════════════════════ */
window.NtcTrainingPicker = (function () {
    'use strict';

    // ── Training Day Selection ────────────────────────────
    // The training type fixes how MANY days a course runs, not which dates.
    // Those are picked one at a time, may fall on a weekend, and need not be
    // consecutive; a single day of the course may also take more than one
    // date. The start and end dates are outputs — the earliest and latest of
    // whatever is picked — and are re-derived server-side either way.

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** A Y-m-d string as a local-midnight Date, or null if it is not one. */
    function parseDateInput(iso) {
        const parts = String(iso || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) return null;

        // Built from parts rather than new Date(iso), which parses as UTC and
        // lands on the previous day west of Greenwich.
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    /** A Date back as the Y-m-d a date input expects. */
    function toDateInputValue(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    /** The day after a Y-m-d string, or '' if it is not one. */
    function dayAfter(iso) {
        const date = parseDateInput(iso);
        if (!date) return '';

        date.setDate(date.getDate() + 1);
        return toDateInputValue(date);
    }

    /** "2027-03-04" → "Mar 04, 2027". Left as-is if it is not a plain date. */
    function formatDisplayDate(iso) {
        const parts = String(iso || '').split('-');
        if (parts.length !== 3) return iso || '';
        return MONTHS[Number(parts[1]) - 1] + ' ' + parts[2] + ', ' + parts[0];
    }

    /**
     * Parse a JSON array out of a data attribute, or [] if it is missing or
     * malformed. Server-rendered, but a blank attribute is ordinary — a row
     * with no instructors yet carries one — so it must not throw.
     */
    function safeJsonArray(raw) {
        try {
            const parsed = JSON.parse(raw || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (err) {
            return [];
        }
    }

    /**
     * Parse a JSON object out of a data attribute, or {} if it is missing or
     * malformed. The day groups arrive keyed by day number, which safeJsonArray
     * would flatten away.
     */
    function safeJsonMap(raw) {
        try {
            const parsed = JSON.parse(raw || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (err) {
            return {};
        }
    }

    /**
     * Wire one training-day block: one group per day of the course, and the two
     * read-only dates that follow from them.
     *
     * A day of the course is a GROUP rather than a single field, because one
     * day may be delivered over more than one date — an 8-hour day split across
     * two mornings is still Day 1. Every date is posted as
     * training_dates[dayNo][]; the start and end dates are outputs, the
     * earliest and latest of whatever has been picked, and are re-derived
     * server-side rather than trusted from here.
     *
     * Shared by the new-NTC form and the Report of Changes modal, which differ
     * only by the id prefix on their fields.
     */
    function wireTrainingDays(prefix, typeSelectId) {
        const root       = document.querySelector('[data-training-days][data-prefix="' + prefix + '"]');
        const typeSelect = document.getElementById(typeSelectId);
        if (!root || !typeSelect) return null;

        const startInput = document.getElementById(prefix + 'training_start_date');
        const endInput   = document.getElementById(prefix + 'training_end_date');
        const daysWrap   = root.querySelector('[data-extra-days]');
        const lockedNote = root.querySelector('[data-days-locked]');
        const groupsWrap = root.querySelector('[data-day-groups]');
        const daysHint   = root.querySelector('[data-extra-days-hint]');
        const errorEl    = root.querySelector('[data-days-error]');
        const minDate    = root.getAttribute('data-min-date') || '';
        if (!startInput || !endInput || !groupsWrap) return null;

        const listeners = [];

        function requiredDays() {
            const option = typeSelect.options[typeSelect.selectedIndex];
            const days   = option ? parseInt(option.getAttribute('data-duration'), 10) : NaN;
            return Number.isNaN(days) ? 0 : days;
        }

        function groups() {
            return Array.from(groupsWrap.querySelectorAll('[data-day-no]'));
        }

        /** Every date input, in day order and then calendar order. */
        function dateInputs() {
            return Array.from(groupsWrap.querySelectorAll('input[type="date"]'));
        }

        /** Everything picked so far, blanks dropped, in calendar order. */
        function pickedDates() {
            return dateInputs().map(i => i.value).filter(Boolean).sort();
        }

        /** What each group currently holds, keyed by day number. */
        function currentValues() {
            const map = {};

            groups().forEach(group => {
                map[Number(group.getAttribute('data-day-no'))] =
                    Array.from(group.querySelectorAll('input[type="date"]')).map(i => i.value);
            });

            return map;
        }

        function addDateRow(group, dayNo, value) {
            const rows = group.querySelector('[data-day-rows]');

            const row = document.createElement('div');
            row.className = 'ntc-day-row';

            const input = document.createElement('input');
            input.type      = 'date';
            input.name      = 'training_dates[' + dayNo + '][]';
            input.className = 'form-control';
            input.value     = value || '';
            if (minDate) input.min = minDate;
            input.addEventListener('change', refresh);
            row.appendChild(input);

            const remove = document.createElement('button');
            remove.type      = 'button';
            remove.className = 'btn btn-sm btn-outline-danger ntc-remove-date';
            remove.title     = 'Remove this date';
            remove.setAttribute('aria-label', 'Remove this date');
            remove.innerHTML = '<i class="fas fa-times"></i>';
            remove.addEventListener('click', function () {
                row.remove();
                refresh();
            });
            row.appendChild(remove);

            rows.appendChild(row);

            return input;
        }

        function buildGroup(dayNo, values) {
            const group = document.createElement('div');
            group.className = 'ntc-day-group';
            group.setAttribute('data-day-no', String(dayNo));

            const head = document.createElement('div');
            head.className = 'ntc-day-group-head';

            const badge = document.createElement('span');
            badge.className = 'ntc-day-badge';
            badge.textContent = 'Day ' + dayNo;
            head.appendChild(badge);

            const add = document.createElement('button');
            add.type      = 'button';
            add.className = 'btn btn-sm ntc-add-date';
            add.innerHTML = '<i class="fas fa-plus me-1"></i>Add date';
            add.addEventListener('click', function () {
                const input = addDateRow(group, dayNo, '');
                refresh();
                input.focus();
            });
            head.appendChild(add);

            group.appendChild(head);

            const rows = document.createElement('div');
            rows.className = 'ntc-day-rows';
            rows.setAttribute('data-day-rows', '');
            group.appendChild(rows);

            values.forEach(value => addDateRow(group, dayNo, value));

            return group;
        }

        /**
         * Every day must keep one date, so only the rows beyond the first get a
         * remove button — which is what stops a day being emptied outright.
         */
        function refreshRowControls() {
            groups().forEach(group => {
                Array.from(group.querySelectorAll('.ntc-day-row')).forEach((row, index) => {
                    const input     = row.querySelector('input[type="date"]');
                    const remove    = row.querySelector('.ntc-remove-date');
                    const mandatory = index === 0;

                    if (input)  input.required = mandatory;
                    if (remove) remove.hidden  = mandatory;
                });
            });
        }

        // Rebuilt rather than added to, so switching SFA → OFA drops the surplus
        // groups instead of leaving stale dates posted with the form. Values are
        // carried across by day so a type change is not destructive.
        function renderDays(values) {
            const needed  = requiredDays();
            const carried = values || currentValues();

            groupsWrap.replaceChildren();

            for (let dayNo = 1; dayNo <= needed; dayNo++) {
                const dates = (carried[dayNo] || []).slice();

                // Every day needs somewhere to put its first date.
                if (dates.length === 0) dates.push('');

                groupsWrap.appendChild(buildGroup(dayNo, dates));
            }

            if (daysWrap)   daysWrap.hidden   = needed === 0;
            if (lockedNote) lockedNote.hidden = needed > 0;

            if (daysHint) {
                daysHint.textContent = needed === 0
                    ? ''
                    : 'This training runs for ' + needed + (needed === 1 ? ' day' : ' days')
                      + '. Dates may fall on a weekend and need not be consecutive, and a day may'
                      + ' run over more than one date — use Add date for that.';
            }

            refreshRowControls();
        }

        /**
         * Walk every row in order, giving each a floor of the day after
         * whatever precedes it.
         *
         * Two dates cannot coincide and the days run in sequence, so a single
         * ascending chain enforces both — across groups as well as within one.
         * A value a moved floor has overtaken is cleared, because leaving it
         * would post a date the input itself calls invalid.
         */
        function applyMinimums() {
            let floor = '';

            dateInputs().forEach(input => {
                const earliest = floor ? dayAfter(floor) : minDate;

                if (earliest) input.min = earliest;

                if (input.value && earliest && input.value < earliest) {
                    input.value = '';
                }

                floor = input.value || floor;
            });
        }

        /** Does every day of the course have at least one date? */
        function everyDayFilled() {
            const all = groups();

            return all.length > 0 && all.every(group =>
                Array.from(group.querySelectorAll('input[type="date"]'))
                    .some(input => Boolean(input.value)));
        }

        function refresh() {
            applyMinimums();
            refreshRowControls();

            const dates = pickedDates();
            const ready = everyDayFilled();

            // Only meaningful once every day is in: a partial set would name a
            // start and end the training does not actually have.
            startInput.value = ready ? dates[0] : '';
            endInput.value   = ready ? dates[dates.length - 1] : '';

            listeners.forEach(fn => fn(endInput.value));
        }

        typeSelect.addEventListener('change', function () {
            renderDays();
            refresh();
        });

        // Repopulates the groups after a failed submit bounces back with old().
        renderDays(safeJsonMap(root.getAttribute('data-preset')));
        refresh();

        return {
            onChange(fn) { listeners.push(fn); fn(endInput.value); },

            /** Load an existing submission's dates back into the groups. */
            setDates(datesByDay) {
                const source  = datesByDay && typeof datesByDay === 'object' ? datesByDay : {};
                const carried = {};

                Object.keys(source).forEach(key => {
                    carried[Number(key)] = (source[key] || []).slice();
                });

                renderDays(carried);
                refresh();
            },

            validate() {
                let message = null;

                if (requiredDays() === 0) {
                    message = 'Select a type of training first.';
                } else {
                    const short = groups().find(group =>
                        !Array.from(group.querySelectorAll('input[type="date"]'))
                            .some(input => Boolean(input.value)));

                    if (short) {
                        message = 'Day ' + short.getAttribute('data-day-no')
                            + ' needs at least one date.';
                    }
                }

                if (!message) {
                    const filled = pickedDates();

                    if (new Set(filled).size !== filled.length) {
                        message = 'Each training date must be a different day.';
                    }
                }

                if (errorEl) {
                    errorEl.textContent = message || '';
                    errorEl.classList.toggle('d-none', !message);
                }

                return message === null;
            }
        };
    }
    /**
     * Wire one instructor picker.
     *
     * Whether an instructor cleared evaluation is settled server-side and sits
     * in data-reason. Whether their credentials outlast the training cannot be:
     * the last training day is not known until it is picked, so data-expires is
     * compared against it here and the row greyed out on the spot rather than
     * letting the FATPro submit and be refused.
     */
    function wireInstructorPicker(prefix) {
        const root = document.querySelector('[data-instructor-picker][data-prefix="' + prefix + '"]');
        if (!root) return null;

        const options = Array.from(root.querySelectorAll('[data-instructor-option]'));
        const errorEl = root.querySelector('[data-instructor-error]');

        function checkbox(option) {
            return option.querySelector('input[type="checkbox"]');
        }

        return {
            applyLastDay(lastDay) {
                options.forEach(option => {
                    // Already ineligible for a reason no date will change.
                    if (option.getAttribute('data-reason')) return;

                    const expires = option.getAttribute('data-expires');
                    const meta    = option.querySelector('[data-instructor-meta]');
                    const input   = checkbox(option);
                    const lapsed  = Boolean(lastDay && expires && expires < lastDay);

                    option.classList.toggle('is-ineligible', lapsed);
                    if (input) {
                        input.disabled = lapsed;
                        if (lapsed) input.checked = false;
                    }

                    if (meta) {
                        meta.textContent = lapsed
                            ? 'Credentials expire ' + formatDisplayDate(expires)
                                + ', before your last training day.'
                            : '';
                        meta.classList.toggle('d-none', !lapsed);
                    }
                });
            },

            setSelected(ids) {
                const wanted = new Set((ids || []).map(Number));
                options.forEach(option => {
                    const input = checkbox(option);
                    if (input) input.checked = !input.disabled && wanted.has(Number(input.value));
                });
            },

            validate() {
                const chosen = options.some(option => {
                    const input = checkbox(option);
                    return input && input.checked && !input.disabled;
                });

                if (errorEl) errorEl.classList.toggle('d-none', chosen);

                return chosen;
            }
        };
    }

    /** Keep a picker in step with the days chosen beside it. */
    function linkDaysToInstructors(days, instructors) {
        if (days && instructors) {
            days.onChange(lastDay => instructors.applyLastDay(lastDay));
        }
    }

    return {
        parseDateInput,
        toDateInputValue,
        dayAfter,
        formatDisplayDate,
        safeJsonArray,
        safeJsonMap,
        wireTrainingDays,
        wireInstructorPicker,
        link: linkDaysToInstructors,
    };
})();
