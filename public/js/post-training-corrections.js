/* ══════════════════════════════════════════════════════════════
   Post Training Report — corrections dialog

   A report can be sent back in three ways at once: a wiped
   attachment, rejected participant rows, a refused instructor
   list. The dialog shows whichever are outstanding and posts them
   as one submission.

   The attachments look after themselves — ptr-compact-drop-zone is
   already wired by post-training.js. What is left for this file is
   the participant cards, which have to be folded back into the
   `participants` JSON the server already understands, and the
   replacement pictures, which stage one at a time exactly as they
   do on a first submission.
   ══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // The same shapes the encoding grid applies, so a field corrected here is
    // held to what it would have had to be on a first filing.
    var FORMATS = {
        mobile_no:        function (v) { return !window.PhFields || window.PhFields.isMobile(v); },
        company_landline: function (v) { return !window.PhFields || window.PhFields.isLandline(v); },
        company_email:    function (v) { return EMAIL.test(v); },
        personal_email:   function (v) { return EMAIL.test(v); }
    };

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setState(el, kind, text) {
        if (!el) return;

        el.textContent = text;
        el.classList.remove('is-error', 'is-ok');

        if (kind) el.classList.add('is-' + kind);
    }

    /**
     * Stage one replacement picture.
     *
     * Uploaded on pick rather than with the form, for the same reason a first
     * submission does it: several 5 MB pictures in one request runs into
     * post_max_size long before a large correction is filed.
     */
    function stagePhoto(card, input, url) {
        var file  = input.files && input.files[0];
        var state = card.querySelector('[data-correction-photo-state]');

        if (!file) return;

        if (file.size > MAX_PHOTO_BYTES) {
            setState(state, 'error', 'Too large — 5 MB maximum');
            input.value = '';
            return;
        }

        setState(state, null, 'Uploading…');

        var body = new FormData();
        body.append('photo', file);
        body.append('_token', csrfToken());

        fetch(url, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
            .then(function (data) {
                if (!data || !data.token) return Promise.reject(data);

                card.setAttribute('data-photo-token', data.token);
                card.setAttribute('data-photo-name', file.name);
                setState(state, 'ok', 'New picture ready: ' + file.name);
            })
            .catch(function () {
                card.removeAttribute('data-photo-token');
                input.value = '';
                setState(state, 'error', 'Upload failed — try again');
            });
    }

    // Which complaint serialiseParticipants earned, so submit can say something
    // more useful than "complete every field" when a value is merely misshapen.
    var lastWasMalformed = false;

    /**
     * Fold the cards back into the payload the server reads.
     *
     * Returns false and marks the offending fields when something required is
     * blank; the id travels with each row so applyParticipantCorrections can
     * match it to the participant it belongs to.
     */
    function serialiseParticipants(form) {
        var wrap = form.querySelector('[data-correction-participants]');
        var payload = form.querySelector('[data-correction-payload]');

        if (!wrap || !payload) return true;

        var cards = Array.prototype.slice.call(wrap.querySelectorAll('[data-participant-id]'));
        var problems = 0;
        var malformed = 0;

        var rows = cards.map(function (card) {
            var row = { id: Number(card.getAttribute('data-participant-id')) };

            card.querySelectorAll('[data-field]').forEach(function (el) {
                var name = el.getAttribute('data-field');

                // The address pickers are filled from a fetched asset, so what
                // was chosen lives on data-value until it arrives.
                var value = el.hasAttribute('data-ph-region') || el.hasAttribute('data-ph-city')
                    ? (el.getAttribute('data-value') || '').trim()
                    : (el.value || '').trim();

                row[name] = value;

                var required = el.hasAttribute('data-required');
                var check    = FORMATS[name];
                var bad      = (required && !value) || (value !== '' && check && !check(value));

                el.classList.toggle('is-invalid', bad);

                if (bad) {
                    problems++;
                    if (value !== '') malformed++;
                }
            });

            row.photo_token = card.getAttribute('data-photo-token') || '';
            row.photo_name  = card.getAttribute('data-photo-name') || '';

            return row;
        });

        payload.value = JSON.stringify(rows);
        lastWasMalformed = malformed > 0;

        return problems === 0;
    }

    /**
     * Every declined attachment needs its replacement.
     *
     * Submitting partway would re-open the report for review while still
     * missing what the evaluator asked for.
     */
    function filesChosen(form) {
        var complete = true;

        form.querySelectorAll(".ptr-compact-drop-zone").forEach(function (zone) {
            var input = zone.querySelector(".ptr-file-input");
            var ok    = input && input.files && input.files.length > 0;

            zone.classList.toggle("is-invalid-zone", !ok);

            if (!ok) complete = false;
        });

        return complete;
    }

    /** At least one instructor must remain named. */
    function instructorsChosen(form) {
        var wrap = form.querySelector('[data-correction-instructors]');
        if (!wrap) return true;

        return Array.prototype.slice
            .call(wrap.querySelectorAll('input[type="checkbox"]'))
            .some(function (input) { return input.checked && !input.disabled; });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ptr-corrections-form').forEach(function (form) {
            var photoUrl = form.getAttribute('data-photo-url') || '';

            // Address pickers and contact formats, exactly as the grid wires them.
            if (window.PhFields) {
                form.querySelectorAll('[data-participant-id]').forEach(function (card) {
                    window.PhFields.wireAddress(
                        card.querySelector('[data-ph-region]'),
                        card.querySelector('[data-ph-city]')
                    );

                    window.PhFields.wireContact(card.querySelector('[data-ph-mobile]'), 'mobile');
                    window.PhFields.wireContact(card.querySelector('[data-ph-landline]'), 'landline');
                });
            }

            // Replacement pictures.
            form.querySelectorAll('[data-correction-photo-btn]').forEach(function (button) {
                var card  = button.closest('[data-participant-id]');
                var input = card ? card.querySelector('[data-correction-photo]') : null;

                if (!input) return;

                button.addEventListener('click', function () { input.click(); });
                input.addEventListener('change', function () { stagePhoto(card, input, photoUrl); });
            });

            form.addEventListener('submit', function (e) {
                var error = form.querySelector('[data-correction-error]');
                var message = null;

                if (!filesChosen(form)) {
                    message = 'Choose a replacement file for every declined document.';
                } else if (!serialiseParticipants(form)) {
                    message = lastWasMalformed
                        ? 'Check the highlighted participant fields. Mobile numbers look like 09171234567, landlines are ten digits including the area code, and e-mail addresses must be complete.'
                        : 'Complete every highlighted participant field.';
                } else if (!instructorsChosen(form)) {
                    message = 'Name at least one instructor who conducted this training.';
                }

                if (error) {
                    error.textContent = message || '';
                    error.classList.toggle('d-none', !message);
                }

                if (message) {
                    e.preventDefault();
                    return;
                }

                var submit = form.querySelector('button[type="submit"]');

                if (submit) {
                    submit.disabled = true;
                    submit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting…';
                }
            });
        });
    });
})();
