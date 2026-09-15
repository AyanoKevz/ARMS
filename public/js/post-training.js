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
                var valid = true;

                form.querySelectorAll('.ptr-file-drop-zone').forEach(function (zone) {
                    var input = zone.querySelector('.ptr-file-input');
                    if (!input || input.files.length > 0) return;

                    zone.classList.add('is-invalid-zone');
                    var errorEl = document.getElementById('error_' + input.id);
                    if (errorEl) errorEl.classList.remove('d-none');
                    valid = false;
                });

                if (!valid) {
                    e.preventDefault();
                    var firstBad = form.querySelector('.ptr-file-drop-zone.is-invalid-zone');
                    if (firstBad) firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                lockSubmit(form.querySelector('button[type="submit"]'));
            });
        }

        // ── Re-upload forms ──────────────────────────────────────
        document.querySelectorAll('.ptr-reupload-form').forEach(function (reuploadForm) {
            reuploadForm.addEventListener('submit', function (e) {
                var valid = true;

                reuploadForm.querySelectorAll('.ptr-compact-drop-zone').forEach(function (zone) {
                    var input = zone.querySelector('.ptr-file-input');
                    if (!input || input.files.length > 0) return;
                    zone.classList.add('is-invalid-zone');
                    valid = false;
                });

                if (!valid) {
                    e.preventDefault();
                    return;
                }

                lockSubmit(reuploadForm.querySelector('button[type="submit"]'));
            });
        });
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
