/* ══════════════════════════════════════════════════════════════
   Post Training Report — drafts

   Seven requirements is more than one sitting, so everything the
   FATPro types, picks or uploads is kept as they go and restored
   when they come back.

   Two halves:

     • Values — participants, instructors, the video link, the
       remarks and which step they reached — are posted as JSON to
       the draft endpoint, debounced so typing does not hammer it.

     • Files cannot travel in JSON, so each PDF is uploaded to the
       staging area the moment it is picked and only its token goes
       in the draft. Submitting claims the staged file; abandoning
       leaves it for the prune.

   The footer status says plainly which state the draft is in,
   because a save nobody can see is a save nobody trusts.
   ══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var SAVE_DEBOUNCE_MS = 1200;

    var modalEl, statusEl, saveUrl, stageUrl;

    // The trigger that opened the dialog. data-draft on it is rendered once,
    // at page load, so every successful save writes the new payload back onto
    // it — otherwise closing and reopening without a refresh would restore
    // whatever the page happened to be rendered with.
    var openedFrom = null;
    var timer = null;
    var inFlight = false;
    var dirtySinceSave = false;
    var lastSavedAt = null;
    var clockTimer = null;

    /** Tokens for files already staged, keyed by document type id. */
    var stagedDocuments = {};

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function parseJson(raw, fallback) {
        try {
            var parsed = JSON.parse(raw || '');
            return parsed && typeof parsed === 'object' ? parsed : fallback;
        } catch (err) {
            return fallback;
        }
    }

    /* ─── Status line ──────────────────────────────────────── */

    /** "just now", "4 minutes ago", "2 hours ago", else a date. */
    function describeAge(date) {
        var seconds = Math.floor((Date.now() - date.getTime()) / 1000);

        if (seconds < 45) return 'just now';
        if (seconds < 90) return 'a minute ago';

        var minutes = Math.round(seconds / 60);
        if (minutes < 60) return minutes + ' minutes ago';

        var hours = Math.round(minutes / 60);
        if (hours < 24) return hours === 1 ? 'an hour ago' : hours + ' hours ago';

        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function setStatus(state, text) {
        if (!statusEl) return;

        statusEl.setAttribute('data-state', state);
        var label = statusEl.querySelector('.ptr-save-text');
        if (label) label.textContent = text;
    }

    /** Re-render "saved N minutes ago" so it does not go stale on screen. */
    function refreshClock() {
        if (inFlight || dirtySinceSave || !lastSavedAt) return;

        setStatus('saved', 'Saved ' + describeAge(lastSavedAt));
    }

    /* ─── Collecting what to keep ──────────────────────────── */

    function value(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function collect() {
        var payload = {
            training_video_url: value('ptr_training_video_url'),
            applicant_remarks: value('ptr_applicant_remarks'),
            instructor_ids: [],
            participants: [],
            documents: stagedDocuments,
            step: window.ptrWizard ? window.ptrWizard.currentStep() : 1
        };

        document.querySelectorAll('[data-ptr-instructors] input[type="checkbox"]').forEach(function (input) {
            if (input.checked) payload.instructor_ids.push(Number(input.value));
        });

        // Serialised silently: a draft is saved mid-edit, so an incomplete row
        // is expected and must not raise errors at the FATPro.
        if (window.ptrDirectory && window.ptrDirectory.rowsForDraft) {
            payload.participants = window.ptrDirectory.rowsForDraft();
        }

        return payload;
    }

    /* ─── Saving ───────────────────────────────────────────── */

    function save() {
        if (!saveUrl) return;

        var payload = collect();

        inFlight = true;
        dirtySinceSave = false;
        setStatus('saving', 'Saving…');

        fetch(saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ payload: payload })
        })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
            .then(function (data) {
                inFlight = false;

                if (!data || !data.ok) return Promise.reject(data);

                lastSavedAt = data.saved_at ? new Date(data.saved_at) : new Date();

                rememberOnTrigger(payload, lastSavedAt);

                // Something changed while the request was in the air, so the
                // draft on the server is already behind — go again.
                if (dirtySinceSave) {
                    queue();
                    return;
                }

                setStatus('saved', 'Saved ' + describeAge(lastSavedAt));
            })
            .catch(function () {
                inFlight = false;
                dirtySinceSave = true;
                setStatus('error', 'Could not save — retrying');

                // The work is still in the browser, so a failure is a delay
                // rather than a loss. Try again on a longer leash.
                window.setTimeout(queue, 15000);
            });
    }

    /**
     * Save now rather than on the debounce.
     *
     * Closing the dialog within a second of typing used to lose that edit
     * outright: the timer had not fired, and opening another training cleared
     * it. Anything still pending is written before the dialog goes away.
     */
    function flush() {
        window.clearTimeout(timer);

        if (dirtySinceSave && !inFlight) save();
    }

    function queue() {
        dirtySinceSave = true;

        if (!inFlight) setStatus('pending', 'Unsaved changes');

        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            if (!inFlight) save();
        }, SAVE_DEBOUNCE_MS);
    }

    /**
     * Write the saved state back onto the trigger button.
     *
     * The dialog is one modal reused for every training, so when it closes
     * nothing of what was typed survives in the DOM. The button is what the
     * next open reads from, so it has to carry the current draft, not the one
     * the page was rendered with.
     */
    function rememberOnTrigger(payload, savedAt) {
        if (!openedFrom) return;

        try {
            openedFrom.setAttribute('data-draft', JSON.stringify(payload));
            openedFrom.setAttribute('data-draft-saved-at', savedAt.toISOString());
        } catch (err) {
            // A payload that will not stringify cannot be remembered, but the
            // server already has it — a refresh will still bring it back.
        }
    }

    /* ─── Staging a PDF on pick ────────────────────────────── */

    function stageDocument(input) {
        var file = input.files && input.files[0];
        var panel = input.closest('.ptr-step-panel');
        var typeId = panel ? panel.getAttribute('data-doc-type-id') : null;

        if (!file || !typeId || !stageUrl) return;

        setStatus('saving', 'Uploading ' + file.name + '…');

        var body = new FormData();
        body.append('document', file);
        body.append('ptr_document_type_id', typeId);
        body.append('_token', csrfToken());

        fetch(stageUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
            .then(function (data) {
                if (!data || !data.ok) return Promise.reject(data);

                stagedDocuments[typeId] = { token: data.token, name: data.name, size: data.size };
                queue();
            })
            .catch(function () {
                setStatus('error', 'Upload failed — the file is still attached for this submission');
            });
    }

    /* ─── Restoring ────────────────────────────────────────── */

    function restore(payload) {
        stagedDocuments = payload.documents && typeof payload.documents === 'object'
            ? payload.documents
            : {};

        var video = document.getElementById('ptr_training_video_url');
        if (video) video.value = payload.training_video_url || '';

        var remarks = document.getElementById('ptr_applicant_remarks');
        if (remarks) remarks.value = payload.applicant_remarks || '';

        var chosen = Array.isArray(payload.instructor_ids) ? payload.instructor_ids.map(Number) : [];

        document.querySelectorAll('[data-ptr-instructors] input[type="checkbox"]').forEach(function (input) {
            if (!input.disabled) input.checked = chosen.indexOf(Number(input.value)) !== -1;
        });

        if (window.ptrDirectory && window.ptrDirectory.loadRows && Array.isArray(payload.participants)) {
            window.ptrDirectory.loadRows(payload.participants);
        }

        // A staged file is already on the server, so the drop zone says so
        // rather than looking empty and inviting a pointless re-upload.
        Object.keys(stagedDocuments).forEach(function (typeId) {
            var panel = document.querySelector('.ptr-step-panel[data-doc-type-id="' + typeId + '"]');
            if (!panel) return;

            var entry = stagedDocuments[typeId];
            var zone  = panel.querySelector('.ptr-file-drop-zone');

            if (entry && zone && typeof zone.ptrMarkStaged === 'function') {
                zone.ptrMarkStaged(entry.name);
            }
        });

        if (window.ptrWizard && payload.step) window.ptrWizard.goTo(Number(payload.step));
    }

    /* ─── Wiring ───────────────────────────────────────────── */

    window.ptrDraft = {
        /** Called by the wizard and the directory whenever anything changes. */
        touch: queue,

        /** True when this document type already has a file on the server. */
        hasStaged: function (typeId) {
            var entry = stagedDocuments[typeId];
            return Boolean(entry && entry.token);
        },

        /** Write anything outstanding immediately. */
        flush: flush,

        /** Submitting or discarding ends the draft's life in this page. */
        stop: function () {
            window.clearTimeout(timer);
            window.clearInterval(clockTimer);
            dirtySinceSave = false;
        },

        /** Point the draft at one training and put its contents back on screen. */
        open: function (button) {
            // Whatever the last training still owed, before the dialog is
            // repointed and its contents belong to someone else.
            flush();

            // Cleared FIRST. If restoring then fails, the next training must
            // not inherit these tokens and tick steps nobody filled in.
            stagedDocuments = {};

            openedFrom = button;
            saveUrl = button.getAttribute('data-draft-url') || '';
            lastSavedAt = null;
            dirtySinceSave = false;
            window.clearTimeout(timer);

            var payload = parseJson(button.getAttribute('data-draft'), {});
            var savedAt = button.getAttribute('data-draft-saved-at');

            if (Object.keys(payload).length) {
                // A draft that cannot be read back is a nuisance; a draft that
                // stops the dialog opening is a wall. Whatever goes wrong here,
                // the FATPro still gets a usable form — and the console still
                // gets the reason.
                try {
                    restore(payload);

                    if (savedAt) {
                        lastSavedAt = new Date(savedAt);
                        setStatus('saved', 'Saved ' + describeAge(lastSavedAt));
                    } else {
                        setStatus('saved', 'Restored from your last visit');
                    }
                } catch (err) {
                    if (window.console) console.error('Post training draft restore failed:', err);

                    stagedDocuments = {};
                    setStatus('error', 'Could not restore your saved work');
                }
            } else {
                setStatus('idle', 'Your progress is saved as you go');
            }

            // Drawn before the restore, so they describe an empty dialog.
            if (window.ptrWizard && window.ptrWizard.refreshTicks) {
                window.ptrWizard.refreshTicks();
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        modalEl = document.getElementById('ptrSubmitModal');
        statusEl = document.getElementById('ptrSaveStatus');

        if (!modalEl) return;

        stageUrl = modalEl.getAttribute('data-stage-document-url') || '';

        // Any edit anywhere in the dialog counts as a change worth keeping.
        modalEl.addEventListener('input', function (e) {
            if (e.target && e.target.type !== 'file') queue();
        });

        modalEl.addEventListener('change', function (e) {
            if (!e.target) return;

            if (e.target.classList && e.target.classList.contains('ptr-file-input')) {
                stageDocument(e.target);
                return;
            }

            if (e.target.type !== 'file') queue();
        });

        // A dialog on its way out still owes whatever was typed into it.
        modalEl.addEventListener('hide.bs.modal', flush);

        // Keep "saved 3 minutes ago" honest while the dialog sits open.
        clockTimer = window.setInterval(refreshClock, 30000);

        // Leaving with something unsaved should not be silent.
        window.addEventListener('beforeunload', function (e) {
            if (!dirtySinceSave) return;

            e.preventDefault();
            e.returnValue = '';
        });
    });
})();
