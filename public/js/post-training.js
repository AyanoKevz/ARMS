/* ══════════════════════════════════════════════════════════════
   Post Training Report — applicant portal
   Drop-zone handling for the six required attachments, the submit
   modal, and the re-upload forms for declined documents.
   ══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var MAX_BYTES = 25 * 1024 * 1024; // 25 MB per file, matching the server

    function formatBytes(bytes) {
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function extensionOf(name) {
        var dot = name.lastIndexOf('.');
        return dot === -1 ? '' : name.substring(dot + 1).toLowerCase();
    }

    /**
     * Allowed extensions for a zone, read off data-extensions ("xlsx,xls").
     * Falls back to pdf so a missing attribute can never widen the whitelist.
     */
    function allowedExtensions(zone) {
        var raw = zone.getAttribute('data-extensions') || 'pdf';
        return raw.split(',').map(function (e) { return e.trim().toLowerCase(); }).filter(Boolean);
    }

    function validateFile(zone, file) {
        var allowed = allowedExtensions(zone);
        var ext = extensionOf(file.name);

        if (allowed.indexOf(ext) === -1) {
            window.ARMS && window.ARMS.showToast
                ? window.ARMS.showToast('Invalid file type. Accepted: ' + allowed.join(', ').toUpperCase() + '.', 'danger')
                : alert('Invalid file type. Accepted: ' + allowed.join(', ').toUpperCase() + '.');
            return false;
        }

        if (file.size > MAX_BYTES) {
            window.ARMS && window.ARMS.showToast
                ? window.ARMS.showToast('File is too large. Maximum size allowed is 25 MB.', 'danger')
                : alert('File is too large. Maximum size allowed is 25 MB.');
            return false;
        }

        return true;
    }

    /**
     * Wire one full-size drop zone (used inside the submission modal).
     */
    function setupDropZone(zone) {
        var input = zone.querySelector('.ptr-file-input');
        if (!input) return;

        var stateEmpty = zone.querySelector('.state-empty');
        var stateSelected = zone.querySelector('.state-selected');
        var selectedInfo = zone.querySelector('.selected-file-info');
        var btnClear = zone.querySelector('.btn-clear-file');
        var errorEl = document.getElementById('error_' + input.id);

        function render(file) {
            if (file) {
                if (stateEmpty) stateEmpty.classList.add('d-none');
                if (stateSelected) stateSelected.classList.remove('d-none');
                if (selectedInfo) selectedInfo.textContent = file.name + ' (' + formatBytes(file.size) + ')';
                zone.classList.add('has-file');
                zone.classList.remove('is-invalid-zone');
                if (errorEl) errorEl.classList.add('d-none');
            } else {
                if (stateEmpty) stateEmpty.classList.remove('d-none');
                if (stateSelected) stateSelected.classList.add('d-none');
                zone.classList.remove('has-file');
            }
        }

        function clear() {
            input.value = '';
            render(null);
        }

        function accept(file) {
            if (validateFile(zone, file)) {
                render(file);
            } else {
                clear();
            }
        }

        zone.addEventListener('click', function (e) {
            if (e.target.closest('.no-trigger')) return;
            input.click();
        });

        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) accept(input.files[0]);
        });

        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (!e.dataTransfer || e.dataTransfer.files.length === 0) return;

            var file = e.dataTransfer.files[0];
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            accept(file);
        });

        if (btnClear) {
            btnClear.addEventListener('click', function (e) {
                e.stopPropagation();
                clear();
            });
        }

        zone.ptrClear = clear;
        render(null);
    }

    /**
     * Wire one compact drop zone (used for re-uploading declined documents).
     */
    function setupCompactDropZone(zone) {
        var input = zone.querySelector('.ptr-file-input');
        if (!input) return;

        var fileInfo = zone.querySelector('.file-info');
        var btnClear = zone.querySelector('.btn-clear');
        var defaultHTML = fileInfo ? fileInfo.innerHTML : '';

        function clear() {
            input.value = '';
            if (fileInfo) fileInfo.innerHTML = defaultHTML;
            if (btnClear) btnClear.classList.add('d-none');
            zone.classList.remove('is-invalid-zone');
        }

        function accept(file) {
            if (!validateFile(zone, file)) {
                clear();
                return;
            }
            if (fileInfo) {
                fileInfo.innerHTML =
                    '<i class="fas fa-check-circle text-success"></i> <span class="text-success fw-bold"></span>';
                fileInfo.querySelector('span').textContent = file.name;
            }
            if (btnClear) btnClear.classList.remove('d-none');
            zone.classList.remove('is-invalid-zone');
        }

        zone.addEventListener('click', function (e) {
            if (e.target.closest('.no-trigger')) return;
            input.click();
        });

        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) accept(input.files[0]);
        });

        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (!e.dataTransfer || e.dataTransfer.files.length === 0) return;

            var file = e.dataTransfer.files[0];
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            accept(file);
        });

        if (btnClear) {
            btnClear.addEventListener('click', function (e) {
                e.stopPropagation();
                clear();
            });
        }
    }

    /**
     * The portal lays these modals out deep inside the page, under a sidebar and
     * overlays that claim z-index 1045-1050. Left there the backdrop can end up
     * painted over the dialog — which swallows clicks on the close button — and
     * the percentage heights `modal-dialog-scrollable` relies on resolve against
     * the wrong box, so the body never becomes scrollable and the lower fields
     * are unreachable. Reparenting to <body> puts them in a clean stacking
     * context. The sidebar partial does the same thing for the same reason.
     */
    function relocateModals(modals) {
        modals.forEach(function (m) {
            if (m.parentNode !== document.body) {
                document.body.appendChild(m);
            }
        });
    }

    function isPtrModal(el) {
        return !!el && (el.id === 'ptrSubmitModal' || el.id.indexOf('ptrReuploadModal-') === 0);
    }

    /**
     * Bootstrap's dismiss data-api does not fire reliably on this page — the
     * Report of Changes modal already carries a hand-rolled workaround for the
     * same reason. Delegating from the document covers every close control at
     * once, including ones inside modals that have been reparented, and does
     * not care whether the data-api is listening.
     */
    function wireDismiss() {
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-bs-dismiss="modal"]');
            if (!trigger) return;

            var modalEl = trigger.closest('.modal');
            if (!isPtrModal(modalEl)) return;

            e.preventDefault();
            hideModal(modalEl);
        });

        // Escape should close it too, for the same reason.
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            document.querySelectorAll('.modal.show').forEach(function (m) {
                if (isPtrModal(m)) hideModal(m);
            });
        });
    }

    /**
     * One Bootstrap instance per modal, pinned to the element.
     *
     * The portal theme runs `new bootstrap.Modal(el)` over every .modal on the
     * page and parks the result on `el.modalInstance`. Constructing a Modal
     * REPLACES whatever instance Bootstrap had registered for that element, so
     * re-resolving later can hand back a different object than the one that
     * opened the dialog — and `hide()` starts with `if (!this._isShown) return`,
     * making the close button silently do nothing.
     *
     * Resolving once and caching it means show and hide always address the same
     * instance, whichever of us created it.
     */
    function getModal(modalEl) {
        if (!window.bootstrap || !bootstrap.Modal) return null;

        if (!modalEl.ptrModalInstance) {
            modalEl.ptrModalInstance =
                modalEl.modalInstance || bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        return modalEl.ptrModalInstance;
    }

    /**
     * Strip the dialog and any backdrop by hand. The last resort for when
     * Bootstrap is unavailable, or when its hide() was a no-op because another
     * instance owns the open state.
     */
    function forceClose(modalEl) {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(function (b) {
            if (b.parentNode) b.parentNode.removeChild(b);
        });
    }

    function hideModal(modalEl) {
        var instance = getModal(modalEl);

        if (instance) {
            instance.hide();

            // If a stale instance still owned the open state, hide() was a
            // no-op. Check once the transition would have finished and clear
            // it by hand, so a close control is never a dead end.
            window.setTimeout(function () {
                if (modalEl.classList.contains('show')) forceClose(modalEl);
            }, 400);
            return;
        }

        forceClose(modalEl);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ptr-file-drop-zone').forEach(setupDropZone);
        document.querySelectorAll('.ptr-compact-drop-zone').forEach(setupCompactDropZone);

        // Submission modal plus one re-upload modal per declined report.
        // appendChild keeps the listeners wired above intact.
        var ptrModals = Array.prototype.slice.call(
            document.querySelectorAll('#ptrSubmitModal, [id^="ptrReuploadModal-"]')
        );
        relocateModals(ptrModals);
        wireDismiss();

        // ── Submission modal ─────────────────────────────────────
        var modalEl = document.getElementById('ptrSubmitModal');
        var form = document.getElementById('ptrSubmitForm');
        // Same cached instance the close handler uses — see getModal.
        var modal = modalEl ? getModal(modalEl) : null;

        document.querySelectorAll('.btn-ptr-submit-open').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!form || !modal) return;

                form.action = this.getAttribute('data-action') || '';

                setText('ptrModalNtcRef', this.getAttribute('data-ntc-ref'));
                setText('ptrModalTrainingType', this.getAttribute('data-training-type'));
                setText('ptrModalTrainingPeriod', this.getAttribute('data-training-period'));
                setText('ptrModalDeadline', this.getAttribute('data-deadline'));

                var overdueEl = document.getElementById('ptrModalOverdue');
                if (overdueEl) overdueEl.hidden = this.getAttribute('data-overdue') !== '1';

                // Rebuild the Directory for this training, with its mode pre-filled.
                if (window.ptrDirectory) {
                    window.ptrDirectory.reset(this.getAttribute('data-training-mode'));
                }

                // Tick the instructors this NTC declared.
                if (window.ptrInstructors) {
                    window.ptrInstructors.reset(this.getAttribute('data-instructor-ids'));
                }

                if (window.ptrWizard) window.ptrWizard.reset();

                // The wizard resets its steps but not the values inside them,
                // and the remarks box sits outside it entirely.
                ['ptr_training_video_url', 'ptr_applicant_remarks'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        el.value = '';
                        el.classList.remove('is-invalid');
                    }
                });

                // A fresh dialog every time — never carry a previous pick over.
                modalEl.querySelectorAll('.ptr-file-drop-zone').forEach(function (zone) {
                    if (typeof zone.ptrClear === 'function') zone.ptrClear();
                    zone.classList.remove('is-invalid-zone');
                });
                modalEl.querySelectorAll('.ptr-field-error').forEach(function (el) {
                    el.classList.add('d-none');
                });

                modal.show();
            });
        });

        if (form) {
            form.addEventListener('submit', function (e) {
                // Every step is checked here, because the footer only ever shows
                // the last one and an earlier gap would otherwise reach the server.
                if (window.ptrWizard && !window.ptrWizard.validateAll()) {
                    e.preventDefault();
                    return;
                }

                lockSubmit(document.getElementById('ptrStepSubmit'));
            });
        }

        // ── Re-upload forms ──────────────────────────────────────
    });

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value || '—';
    }

    function lockSubmit(btn) {
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
    }
})();

/**
 * Directory of Participants — the encoding grid inside the submission modal.
 *
 * PHP request limits dictate the shape of this. The whole batch cannot ride in
 * one POST — at up to 5 MB an ID picture, post_max_size runs out well before a
 * large directory does — so each picture uploads on its own the moment it is
 * chosen and the form carries only a token. The rows are serialised into one
 * JSON field instead of ~19 named inputs each, keeping them clear of
 * max_input_vars. Both count limits truncate silently when exceeded, and host
 * defaults are far lower than this stack's, so neither is left to chance.
 */
(function () {
    'use strict';

    var REQUIRED = [
        'certificate_number', 'last_name', 'first_name', 'sex', 'age',
        'company', 'position', 'company_city', 'company_region',
        'industry', 'mobile_no', 'mode_of_training'
    ];

    var MAX_PHOTO_BYTES = 5 * 1024 * 1024;
    var MAX_ROWS = 500;

    var body, template, payload, emptyMsg, errorMsg, countEl, pluralEl, modalEl;
    var defaultMode = '';

    function $(id) { return document.getElementById(id); }

    function photoUrl() {
        return modalEl ? (modalEl.getAttribute('data-photo-url') || '') : '';
    }

    function csrfToken() {
        var input = document.querySelector('#ptrSubmitForm input[name="_token"]');
        if (input) return input.value;
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function rows() {
        return body ? Array.prototype.slice.call(body.querySelectorAll('.ptr-dir-row')) : [];
    }

    function renumber() {
        var all = rows();

        all.forEach(function (row, i) {
            var cell = row.querySelector('.ptr-dir-rowno');
            if (cell) cell.textContent = String(i + 1);
        });

        if (countEl) countEl.textContent = String(all.length);
        if (pluralEl) pluralEl.textContent = all.length === 1 ? '' : 's';
        if (emptyMsg) emptyMsg.classList.toggle('d-none', all.length > 0);
    }

    function field(row, name) {
        return row.querySelector('[data-field="' + name + '"]');
    }

    function valueOf(row, name) {
        var el = field(row, name);
        return el ? el.value.trim() : '';
    }

    // ── Photo upload ──────────────────────────────────────────────────────────

    function uploadPhoto(row, file) {
        var state = row.querySelector('.ptr-dir-photo-state');
        var button = row.querySelector('.ptr-dir-photo-btn');

        if (file.size > MAX_PHOTO_BYTES) {
            setPhotoState(state, 'error', 'Over 5 MB');
            return;
        }

        var data = new FormData();
        data.append('photo', file);
        data.append('_token', csrfToken());

        setPhotoState(state, 'busy', 'Uploading…');
        if (button) button.disabled = true;

        fetch(photoUrl(), {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                return res.json().then(function (json) {
                    if (!res.ok) throw new Error(firstError(json) || 'Upload failed');
                    return json;
                });
            })
            .then(function (json) {
                row.setAttribute('data-photo-token', json.token);
                row.setAttribute('data-photo-name', json.filename || '');
                setPhotoState(state, 'ok', json.filename || 'Uploaded');
            })
            .catch(function (err) {
                row.removeAttribute('data-photo-token');
                setPhotoState(state, 'error', err.message || 'Upload failed');
            })
            .then(function () {
                if (button) button.disabled = false;
            });
    }

    function firstError(json) {
        if (!json) return '';
        if (json.message && !json.errors) return json.message;
        if (json.errors) {
            for (var key in json.errors) {
                if (Object.prototype.hasOwnProperty.call(json.errors, key)) {
                    return json.errors[key][0];
                }
            }
        }
        return '';
    }

    function setPhotoState(el, kind, text) {
        if (!el) return;
        el.className = 'ptr-dir-photo-state is-' + kind;
        el.textContent = text;
        el.title = text;
    }

    // ── Rows ──────────────────────────────────────────────────────────────────

    function addRows(count) {
        if (!body || !template) return;

        var existing = rows().length;
        var room = MAX_ROWS - existing;

        if (room <= 0) return;
        if (count > room) count = room;

        for (var i = 0; i < count; i++) {
            var fragment = template.content.cloneNode(true);
            var row = fragment.querySelector('.ptr-dir-row');

            // Both pre-fill from the training, and both stay editable per row.
            var mode = field(row, 'mode_of_training');
            if (mode) mode.value = defaultMode;

            var batch = field(row, 'batch_no');
            var batchAll = $('ptrDirBatchAll');
            if (batch && batchAll) batch.value = batchAll.value.trim();

            wireRow(row);
            body.appendChild(fragment);
        }

        renumber();
    }

    function wireRow(row) {
        var remove = row.querySelector('.ptr-dir-remove');
        if (remove) {
            remove.addEventListener('click', function () {
                row.parentNode.removeChild(row);
                renumber();
            });
        }

        var button = row.querySelector('.ptr-dir-photo-btn');
        var input = row.querySelector('.ptr-dir-photo-input');

        if (button && input) {
            button.addEventListener('click', function () { input.click(); });
            input.addEventListener('change', function () {
                if (input.files && input.files[0]) uploadPhoto(row, input.files[0]);
            });
        }

        row.querySelectorAll('.ptr-dir-input').forEach(function (el) {
            el.addEventListener('input', function () { el.classList.remove('is-invalid-cell'); });
            el.addEventListener('change', function () { el.classList.remove('is-invalid-cell'); });
        });
    }

    // ── Validation + serialisation ────────────────────────────────────────────

    function validateAndSerialize(silent) {
        if (!payload) return true;

        var all = rows();
        var problems = 0;
        var seen = {};

        if (all.length === 0) {
            if (!silent) showError('Encode at least one participant in the Directory of Participants.');
            return false;
        }

        var data = all.map(function (row) {
            var entry = {};

            row.querySelectorAll('[data-field]').forEach(function (el) {
                entry[el.getAttribute('data-field')] = el.value.trim();
            });

            REQUIRED.forEach(function (name) {
                if (entry[name]) return;
                if (!silent) {
                    var el = field(row, name);
                    if (el) el.classList.add('is-invalid-cell');
                }
                problems++;
            });

            // A certificate number may repeat across trainings, never within one.
            var cert = (entry.certificate_number || '').toLowerCase();
            if (cert) {
                if (seen[cert]) {
                    if (!silent) {
                        var dup = field(row, 'certificate_number');
                        if (dup) dup.classList.add('is-invalid-cell');
                    }
                    problems++;
                } else {
                    seen[cert] = true;
                }
            }

            var token = row.getAttribute('data-photo-token');
            if (!token) {
                if (!silent) {
                    setPhotoState(row.querySelector('.ptr-dir-photo-state'), 'error', 'Required');
                }
                problems++;
            }

            entry.photo_token = token || '';
            entry.photo_name = row.getAttribute('data-photo-name') || '';

            return entry;
        });

        if (problems > 0) {
            if (!silent) {
                showError('Complete every highlighted field. Each participant also needs an ID picture.');
                var firstBad = body.querySelector('.is-invalid-cell, .ptr-dir-photo-state.is-error');
                if (firstBad && firstBad.scrollIntoView) {
                    firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            return false;
        }

        hideError();
        payload.value = JSON.stringify(data);
        return true;
    }

    function showError(text) {
        if (!errorMsg) return;
        errorMsg.textContent = text;
        errorMsg.classList.remove('d-none');
    }

    function hideError() {
        if (errorMsg) errorMsg.classList.add('d-none');
    }

    // ── Public surface, used by the submission modal ──────────────────────────

    function reset(mode) {
        defaultMode = mode || '';

        if (body) body.innerHTML = '';
        if (payload) payload.value = '';

        var batchAll = $('ptrDirBatchAll');
        if (batchAll) batchAll.value = '';

        var addCount = $('ptrDirAddCount');
        if (addCount) addCount.value = '1';

        hideError();
        addRows(1);
    }

    document.addEventListener('DOMContentLoaded', function () {
        modalEl = $('ptrSubmitModal');
        body = $('ptrDirBody');
        template = $('ptrDirRowTemplate');
        payload = $('ptrDirPayload');
        emptyMsg = $('ptrDirEmpty');
        errorMsg = $('error_ptr_directory');
        countEl = $('ptrDirCount');
        pluralEl = $('ptrDirCountPlural');

        if (!body || !template) return;

        var addBtn = $('ptrDirAddRows');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var input = $('ptrDirAddCount');
                var n = parseInt(input ? input.value : '1', 10);
                if (isNaN(n) || n < 1) n = 1;
                if (n > 50) n = 50;
                addRows(n);
            });
        }

        var applyBtn = $('ptrDirApplyBatch');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var batchAll = $('ptrDirBatchAll');
                var value = batchAll ? batchAll.value.trim() : '';

                rows().forEach(function (row) {
                    var cell = field(row, 'batch_no');
                    if (cell) cell.value = value;
                });
            });
        }

        renumber();
    });

    window.ptrDirectory = {
        reset: reset,
        validateAndSerialize: validateAndSerialize
    };
})();

/**
 * Submission wizard — one requirement per step.
 *
 * Next validates the step you are leaving, so a problem surfaces where it was
 * made rather than all at once on submit. The rail is free to click, though:
 * reviewing an earlier step should never be blocked by a later one.
 */
(function () {
    'use strict';

    var wizard, panels, reqItems, backBtn, nextBtn, submitBtn;
    var current = 1;
    var total = 0;

    function panelFor(step) {
        return wizard ? wizard.querySelector('.ptr-step-panel[data-step="' + step + '"]') : null;
    }

    function requirementFor(step) {
        return wizard ? wizard.querySelector('.ptr-req[data-goto="' + step + '"]') : null;
    }

    function goTo(step) {
        if (!wizard || step < 1 || step > total) return;

        current = step;

        panels.forEach(function (panel) {
            var isCurrent = Number(panel.getAttribute('data-step')) === step;
            panel.classList.toggle('is-active', isCurrent);
            panel.hidden = !isCurrent;
        });

        reqItems.forEach(function (item) {
            var isCurrent = Number(item.getAttribute('data-goto')) === step;
            item.classList.toggle('is-current', isCurrent);
            item.setAttribute('aria-current', isCurrent ? 'step' : 'false');
        });

        if (backBtn) backBtn.hidden = step === 1;
        if (nextBtn) nextBtn.hidden = step === total;
        if (submitBtn) submitBtn.hidden = step !== total;

        refreshTicks();

        // A long grid leaves the body scrolled down; the next step should open
        // at its own beginning.
        var body = wizard.closest('.modal-body');
        if (body) body.scrollTop = 0;
    }

    /** True when the step has what it needs. `silent` checks without complaining. */
    function validateStep(step, silent) {
        var panel = panelFor(step);
        if (!panel) return true;

        var kind = panel.getAttribute('data-kind');

        if (kind === 'directory') {
            return window.ptrDirectory
                ? window.ptrDirectory.validateAndSerialize(silent)
                : true;
        }

        if (kind === 'roster') {
            return window.ptrInstructors
                ? window.ptrInstructors.validate(silent)
                : true;
        }

        // The closing Remarks step is optional by design, so it is always
        // satisfied — it exists to be read, not to be filled in.
        if (kind === 'remarks') {
            return true;
        }

        if (kind === 'link') {
            var url = panel.querySelector('input[type="url"]');
            var ok  = !!(url && url.value.trim() !== '' && url.checkValidity());

            if (!silent) {
                var urlError = panel.querySelector('.ptr-field-error');

                if (url) url.classList.toggle('is-invalid', !ok);
                if (urlError) urlError.classList.toggle('d-none', ok);
            }

            return ok;
        }

        var input = panel.querySelector('.ptr-file-input');
        var ok = !!(input && input.files && input.files.length > 0);

        if (!silent) {
            var zone = panel.querySelector('.ptr-file-drop-zone');
            var error = panel.querySelector('.ptr-field-error');

            if (zone) zone.classList.toggle('is-invalid-zone', !ok);
            if (error) error.classList.toggle('d-none', ok);
        }

        return ok;
    }

    /** Tick every requirement that is already satisfied. */
    function refreshTicks() {
        for (var step = 1; step <= total; step++) {
            var item = requirementFor(step);
            if (item) item.classList.toggle('is-done', validateStep(step, true));
        }
    }

    /**
     * Check every step and stop on the first that fails, so the FATPro lands on
     * the problem instead of being told the form is invalid somewhere.
     */
    function validateAll() {
        for (var step = 1; step <= total; step++) {
            if (validateStep(step, true)) continue;

            goTo(step);
            validateStep(step, false);
            return false;
        }

        // Re-run the Directory unsilenced so its payload is serialised for the post.
        for (var i = 1; i <= total; i++) {
            var panel = panelFor(i);
            if (panel && panel.getAttribute('data-kind') === 'directory') {
                if (!validateStep(i, false)) {
                    goTo(i);
                    return false;
                }
            }
        }

        return true;
    }

    function reset() {
        if (!wizard) return;

        reqItems.forEach(function (item) { item.classList.remove('is-done'); });
        goTo(1);
    }

    document.addEventListener('DOMContentLoaded', function () {
        wizard = document.getElementById('ptrWizard');
        if (!wizard) return;

        panels = Array.prototype.slice.call(wizard.querySelectorAll('.ptr-step-panel'));
        reqItems = Array.prototype.slice.call(wizard.querySelectorAll('.ptr-req'));
        total = panels.length;

        backBtn = document.getElementById('ptrStepBack');
        nextBtn = document.getElementById('ptrStepNext');
        submitBtn = document.getElementById('ptrStepSubmit');

        if (backBtn) {
            backBtn.addEventListener('click', function () { goTo(current - 1); });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (validateStep(current, false)) goTo(current + 1);
            });
        }

        reqItems.forEach(function (item) {
            item.addEventListener('click', function () {
                goTo(Number(item.getAttribute('data-goto')));
            });
        });

        // Picking a file satisfies its step straight away.
        wizard.querySelectorAll('.ptr-file-input').forEach(function (input) {
            input.addEventListener('change', refreshTicks);
        });

        goTo(1);
    });

    window.ptrWizard = {
        reset: reset,
        goTo: goTo,
        validateAll: validateAll
    };
})();

/* ══════════════════════════════════════════════════════════════
   Instructors who conducted the training (wizard step 2)

   Requirement 2 is no longer a PDF. The submission dialog is one modal
   retargeted per training, so the whole accredited roster is rendered
   once and this ticks whichever instructors the chosen NTC declared.

   A declared instructor stays selectable whatever has since happened to
   their credentials — they taught the course. Anyone else is a late
   addition and is disabled unless currently eligible, which is the same
   line resolveReportInstructors() draws server-side.
   ══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var root;

    function options() {
        return root
            ? Array.prototype.slice.call(root.querySelectorAll('[data-ptr-instructor-option]'))
            : [];
    }

    function checkboxOf(option) {
        return option.querySelector('input[type="checkbox"]');
    }

    function errorEl() {
        return document.getElementById('error_ptr_instructors');
    }

    window.ptrInstructors = {
        /**
         * Point the picker at one training: tick its declared instructors and
         * lock anyone else who could not be added now.
         */
        reset: function (declaredJson) {
            if (!root) return;

            var declared = [];
            try {
                var parsed = JSON.parse(declaredJson || '[]');
                if (Array.isArray(parsed)) declared = parsed.map(Number);
            } catch (err) {
                declared = [];
            }

            options().forEach(function (option) {
                var input = checkboxOf(option);
                if (!input) return;

                var id         = Number(input.value);
                var wasOnNtc   = declared.indexOf(id) !== -1;
                var blocked    = Boolean(option.getAttribute('data-reason')) && !wasOnNtc;

                input.disabled = blocked;
                input.checked  = wasOnNtc;
                option.classList.toggle('is-ineligible', blocked);
            });

            var error = errorEl();
            if (error) error.classList.add('d-none');
        },

        /** True when at least one instructor is named. */
        validate: function (silent) {
            if (!root) return true;

            var chosen = options().some(function (option) {
                var input = checkboxOf(option);
                return input && input.checked && !input.disabled;
            });

            if (!silent) {
                var error = errorEl();
                if (error) error.classList.toggle('d-none', chosen);
            }

            return chosen;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        root = document.querySelector('[data-ptr-instructors]');
        if (!root) return;

        // Clear the complaint as soon as the gap is filled, rather than making
        // the FATPro press Next again to find out it is satisfied.
        root.addEventListener('change', function () {
            var error = errorEl();
            if (error && !error.classList.contains('d-none')) {
                window.ptrInstructors.validate(false);
            }
        });
    });
})();
