/* ══════════════════════════════════════════════════════════════
   Notice to Conduct — applicant submission page

   Extracted from an inline <script> block in ntc.blade.php so the
   view carries markup only.
   ══════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {

    // ── Drop Zone Controller Helper ──────────────────────────
    function setupFileDropZone(zoneId) {
        const zone = document.getElementById(zoneId);
        if (!zone) return null;

        const input = zone.querySelector('.ntc-file-input');
        const stateEmpty = zone.querySelector('.state-empty');
        const stateSelected = zone.querySelector('.state-selected');
        const stateExisting = zone.querySelector('.state-existing');
        const stateReplacement = zone.querySelector('.state-replacement');

        const selectedInfo = zone.querySelector('.selected-file-info');
        const existingName = zone.querySelector('.existing-file-name');
        const btnView = zone.querySelector('.btn-view-file');
        const replacementInfo = zone.querySelector('.replacement-file-info');

        const btnClear = zone.querySelector('.btn-clear-file');
        const btnUndo = zone.querySelector('.btn-undo-replacement');

        let currentFileState = {
            hasExisting: false,
            existingName: '',
            existingUrl: '',
            newFile: null
        };

        const MAX_MB = 100;
        const MAX_BYTES = MAX_MB * 1024 * 1024;
        const validExts = ['.pdf', '.doc', '.docx'];

        function formatBytes(bytes) {
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function updateUIState() {
            // Hide all states first
            stateEmpty.classList.add('d-none');
            if (stateSelected) stateSelected.classList.add('d-none');
            if (stateExisting) stateExisting.classList.add('d-none');
            if (stateReplacement) stateReplacement.classList.add('d-none');

            // Hide validation error if file is present
            const errorEl = document.getElementById('error_' + input.id);
            if (currentFileState.newFile) {
                if (errorEl) errorEl.classList.add('d-none');
                zone.classList.remove('is-invalid-zone');
            }

            if (currentFileState.newFile) {
                // New file selected
                if (currentFileState.hasExisting) {
                    if (stateReplacement) {
                        stateReplacement.classList.remove('d-none');
                        replacementInfo.textContent = currentFileState.newFile.name + ' (' + formatBytes(currentFileState.newFile.size) + ')';
                    }
                } else {
                    if (stateSelected) {
                        stateSelected.classList.remove('d-none');
                        selectedInfo.textContent = currentFileState.newFile.name + ' (' + formatBytes(currentFileState.newFile.size) + ')';
                    }
                }
                zone.classList.add('has-file');
            } else if (currentFileState.hasExisting) {
                // Existing file
                if (stateExisting) {
                    stateExisting.classList.remove('d-none');
                    existingName.textContent = currentFileState.existingName;
                    if (btnView && currentFileState.existingUrl) {
                        btnView.href = currentFileState.existingUrl;
                    }
                }
                zone.classList.remove('has-file');
            } else {
                // Empty state
                stateEmpty.classList.remove('d-none');
                zone.classList.remove('has-file');
            }
        }

        function validateFile(file) {
            if (!file) return false;
            const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            if (!validExts.includes(ext)) {
                alert('Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                return false;
            }
            if (file.size > MAX_BYTES) {
                alert('File is too large. Maximum size allowed is 100 MB.');
                return false;
            }
            return true;
        }

        function selectFile(file) {
            if (validateFile(file)) {
                currentFileState.newFile = file;
                updateUIState();
            } else {
                clearSelection();
            }
        }

        function clearSelection() {
            input.value = '';
            currentFileState.newFile = null;
            updateUIState();
        }

        function setExistingFile(name, url) {
            if (name && url) {
                currentFileState.hasExisting = true;
                currentFileState.existingName = name;
                currentFileState.existingUrl = url;
            } else {
                currentFileState.hasExisting = false;
                currentFileState.existingName = '';
                currentFileState.existingUrl = '';
            }
            clearSelection();
        }

        zone.addEventListener('click', function(e) {
            if (e.target.closest('.no-trigger')) {
                return;
            }
            input.click();
        });

        input.addEventListener('change', function() {
            if (input.files && input.files.length > 0) {
                selectFile(input.files[0]);
            }
        });

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function() {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                selectFile(file);
            }
        });

        if (btnClear) {
            btnClear.addEventListener('click', function(e) {
                e.stopPropagation();
                clearSelection();
            });
        }

        if (btnUndo) {
            btnUndo.addEventListener('click', function(e) {
                e.stopPropagation();
                clearSelection();
            });
        }

        // Initial UI update
        updateUIState();

        return {
            clear: clearSelection,
            setExisting: setExistingFile,
            getCurrentState: () => currentFileState
        };
    }

    // ── Setup Compact Drop Zones (for individual rejected docs) ───────
    function setupCompactDropZone(zoneId) {
        const zone = document.getElementById(zoneId);
        if (!zone) return;

        const input = zone.querySelector('.ntc-file-input');
        const fileInfo = zone.querySelector('.file-info');
        const btnClear = zone.querySelector('.btn-clear');

        const defaultHTML = fileInfo.innerHTML;
        const MAX_MB = 100;
        const MAX_BYTES = MAX_MB * 1024 * 1024;
        const validExts = ['.pdf', '.doc', '.docx'];

        function selectFile(file) {
            const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            if (!validExts.includes(ext)) {
                alert('Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                input.value = '';
                return;
            }
            if (file.size > MAX_BYTES) {
                alert('File is too large. Maximum size allowed is 100 MB.');
                input.value = '';
                return;
            }

            fileInfo.innerHTML = `<i class="fas fa-check-circle text-success fs-6"></i> <span class="text-success fw-bold">${file.name}</span>`;
            btnClear.classList.remove('d-none');
            zone.classList.remove('is-invalid-zone');
        }

        function clearSelection() {
            input.value = '';
            fileInfo.innerHTML = defaultHTML;
            btnClear.classList.add('d-none');
            zone.classList.remove('is-invalid-zone');
        }

        zone.addEventListener('click', function(e) {
            if (e.target.closest('.no-trigger')) {
                return;
            }
            input.click();
        });

        input.addEventListener('change', function() {
            if (input.files && input.files.length > 0) {
                selectFile(input.files[0]);
            }
        });

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function() {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                selectFile(file);
            }
        });

        if (btnClear) {
            btnClear.addEventListener('click', function(e) {
                e.stopPropagation();
                clearSelection();
            });
        }
    }

    // Initialize Submit Form drop zones
    const mainRtcmanCtrl = setupFileDropZone('dropZoneRtcman');
    const mainProgCtrl = setupFileDropZone('dropZoneProg');

    // Initialize Modal drop zones
    const modalRtcmanCtrl = setupFileDropZone('modalDropZoneRtcman');
    const modalProgCtrl = setupFileDropZone('modalDropZoneProg');

    // Initialize all existing compact drop zones
    document.querySelectorAll('.ntc-compact-drop-zone').forEach(zone => {
        setupCompactDropZone(zone.id);
    });

    // ── Derived Training End Date ─────────────────────────
    // The training type fixes the duration, so the end date is shown read-only
    // and filled in here. The server recomputes it on submit — this only keeps
    // the applicant from having to guess what it will be.

    /** Advance a date by N working days, skipping Saturday and Sunday. */
    function addWorkingDays(date, days) {
        const out = new Date(date.getTime());
        let counted = 0;

        while (counted < days) {
            out.setDate(out.getDate() + 1);
            const day = out.getDay();
            if (day !== 0 && day !== 6) {
                counted++;
            }
        }

        return out;
    }

    function toDateInputValue(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    /**
     * Keep one end-date field in step with its type select and start date.
     * Used by both the new-NTC form and the Report of Changes modal.
     */
    function wireDerivedEndDate(typeSelectId, startInputId, endInputId) {
        const typeSelect = document.getElementById(typeSelectId);
        const startInput = document.getElementById(startInputId);
        const endInput   = document.getElementById(endInputId);

        if (!typeSelect || !startInput || !endInput) return null;

        const recalculate = () => {
            const option   = typeSelect.options[typeSelect.selectedIndex];
            const duration = option ? parseInt(option.getAttribute('data-duration'), 10) : NaN;

            if (!startInput.value || !duration || Number.isNaN(duration)) {
                endInput.value = '';
                return;
            }

            // Parse as local midnight; `new Date('YYYY-MM-DD')` is UTC and can
            // land on the previous day west of Greenwich.
            const parts = startInput.value.split('-').map(Number);
            const start = new Date(parts[0], parts[1] - 1, parts[2]);

            endInput.value = toDateInputValue(addWorkingDays(start, duration));
        };

        typeSelect.addEventListener('change', recalculate);
        startInput.addEventListener('change', recalculate);
        recalculate();

        return recalculate;
    }

    wireDerivedEndDate('ntc_training_type_id', 'training_start_date', 'training_end_date');

    // ── Venue Label & Placeholder Sync ─────────────────────
    function syncVenueLabels() {
        const mainModeSelect = document.getElementById('ntc_training_mode_id');
        const mainVenueLabel = document.getElementById('ntc_venue_label');
        const mainVenueInput = document.getElementById('ntc_venue');
        if (mainModeSelect && mainVenueLabel && mainVenueInput) {
            const selectedOpt = mainModeSelect.options[mainModeSelect.selectedIndex];
            const code = selectedOpt ? (selectedOpt.getAttribute('data-code') || selectedOpt.text) : '';
            if (code.toUpperCase().includes('BLENDED')) {
                mainVenueLabel.innerHTML = 'Zoom Link / Meeting Link <span class="text-danger">*</span>';
                mainVenueInput.placeholder = 'e.g. https://zoom.us/j/123456789';
            } else {
                mainVenueLabel.innerHTML = 'Venue <span class="text-danger">*</span>';
                mainVenueInput.placeholder = 'e.g. OSHC Main Auditorium, Quezon City';
            }
        }

        const modalModeSelect = document.getElementById('modal_ntc_training_mode_id');
        const modalVenueLabel = document.getElementById('modal_ntc_venue_label');
        const modalVenueInput = document.getElementById('modal_ntc_venue');
        if (modalModeSelect && modalVenueLabel && modalVenueInput) {
            const selectedOpt = modalModeSelect.options[modalModeSelect.selectedIndex];
            const code = selectedOpt ? (selectedOpt.getAttribute('data-code') || selectedOpt.text) : '';
            if (code.toUpperCase().includes('BLENDED')) {
                modalVenueLabel.innerHTML = 'Zoom Link / Meeting Link <span class="text-danger">*</span>';
                modalVenueInput.placeholder = 'e.g. https://zoom.us/j/123456789';
            } else {
                modalVenueLabel.innerHTML = 'Venue <span class="text-danger">*</span>';
                modalVenueInput.placeholder = 'e.g. OSHC Main Auditorium, Quezon City';
            }
        }
    }

    const mainModeSelect = document.getElementById('ntc_training_mode_id');
    const modalModeSelect = document.getElementById('modal_ntc_training_mode_id');
    if (mainModeSelect) mainModeSelect.addEventListener('change', syncVenueLabels);
    if (modalModeSelect) modalModeSelect.addEventListener('change', syncVenueLabels);
    syncVenueLabels();

    // ── Submit spinner guard ──────────────────────────────
    const form       = document.getElementById('ntcSubmitForm');
    const submitBtn  = document.getElementById('ntcSubmitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            let isValid = true;

            // Check RTCMan file
            const fileRtcman = document.getElementById('file_rtcman');
            const errorRtcman = document.getElementById('error_file_rtcman');
            const zoneRtcman = document.getElementById('dropZoneRtcman');
            if (fileRtcman && (!fileRtcman.files || fileRtcman.files.length === 0)) {
                if (errorRtcman) errorRtcman.classList.remove('d-none');
                if (zoneRtcman) zoneRtcman.classList.add('is-invalid-zone');
                isValid = false;
            }

            // Check PROG file
            const fileProg = document.getElementById('file_prog');
            const errorProg = document.getElementById('error_file_prog');
            const zoneProg = document.getElementById('dropZoneProg');
            if (fileProg && (!fileProg.files || fileProg.files.length === 0)) {
                if (errorProg) errorProg.classList.remove('d-none');
                if (zoneProg) zoneProg.classList.add('is-invalid-zone');
                isValid = false;
            }

            if (!form.checkValidity() || !isValid) {
                e.preventDefault();
                form.reportValidity();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
        });
    }

    // ── Report of Changes Modal Populating ────────────────
    const reportChangesModalEl = document.getElementById('reportChangesModal');
    const reportChangesModal = reportChangesModalEl ? new bootstrap.Modal(reportChangesModalEl) : null;
    const reportChangesForm = document.getElementById('reportChangesForm');

    if (reportChangesModalEl && reportChangesModal) {
        reportChangesModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', function () {
                reportChangesModal.hide();
            });
        });
    }

    document.querySelectorAll('.btn-report-changes').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const trainingType = this.getAttribute('data-training-type');
            const trainingMode = this.getAttribute('data-training-mode');
            const startDate = this.getAttribute('data-start-date');
            const endDate = this.getAttribute('data-end-date');

            const venue = this.getAttribute('data-venue');
            const rtcmanName = this.getAttribute('data-rtcman-file-name');
            const rtcmanUrl = this.getAttribute('data-rtcman-file-url');
            const progName = this.getAttribute('data-prog-file-name');
            const progUrl = this.getAttribute('data-prog-file-url');

            if (reportChangesForm) {
                reportChangesForm.action = `/applicant/ntc/${id}/report-of-changes`;

                const typeSelect = document.getElementById('modal_ntc_training_type_id');
                if (typeSelect) {
                    typeSelect.value = trainingType;
                }

                const modeSelect = document.getElementById('modal_ntc_training_mode_id');
                if (modeSelect) {
                    modeSelect.value = trainingMode;
                }

                const venueInputModal = document.getElementById('modal_ntc_venue');
                if (venueInputModal) {
                    venueInputModal.value = venue || '';
                }
                syncVenueLabels();
                
                const startInputModal = document.getElementById('modal_training_start_date');

                if (startInputModal) {
                    startInputModal.value = startDate;
                }

                // The end date is derived, not carried over from the row — the
                // type or start date may be changed in this very dialog.
                if (typeof recalculateModalEndDate === 'function') {
                    recalculateModalEndDate();
                }
            }

            // Update Current File Links
            const rtcmanCurrentContainer = document.getElementById('modal_rtcman_current_container');
            const rtcmanCurrentLink = document.getElementById('modal_rtcman_current_link');
            if (rtcmanCurrentContainer && rtcmanCurrentLink) {
                if (rtcmanName && rtcmanUrl) {
                    rtcmanCurrentLink.textContent = rtcmanName;
                    rtcmanCurrentLink.href = rtcmanUrl;
                    rtcmanCurrentContainer.style.display = 'block';
                } else {
                    rtcmanCurrentContainer.style.display = 'none';
                }
            }

            const progCurrentContainer = document.getElementById('modal_prog_current_container');
            const progCurrentLink = document.getElementById('modal_prog_current_link');
            if (progCurrentContainer && progCurrentLink) {
                if (progName && progUrl) {
                    progCurrentLink.textContent = progName;
                    progCurrentLink.href = progUrl;
                    progCurrentContainer.style.display = 'block';
                } else {
                    progCurrentContainer.style.display = 'none';
                }
            }

            if (modalRtcmanCtrl) {
                modalRtcmanCtrl.clear();
            }
            if (modalProgCtrl) {
                modalProgCtrl.clear();
            }

            if (reportChangesModal) {
                reportChangesModal.show();
            }
        });
    });

    // ── Modal derived Training End Date ───────────────────
    const recalculateModalEndDate = wireDerivedEndDate(
        'modal_ntc_training_type_id',
        'modal_training_start_date',
        'modal_training_end_date'
    );

    // ── Modal Submit Spinner Guard ────────────────────────
    const modalSubmitBtn = document.getElementById('modalSubmitBtn');
    if (reportChangesForm && modalSubmitBtn) {
        reportChangesForm.addEventListener('submit', function (e) {
            let isValid = true;

            // Check RTCMan file in modal
            const fileRtcman = document.getElementById('modal_file_rtcman');
            const errorRtcman = document.getElementById('error_modal_file_rtcman');
            const zoneRtcman = document.getElementById('modalDropZoneRtcman');
            if (fileRtcman && (!fileRtcman.files || fileRtcman.files.length === 0)) {
                if (errorRtcman) errorRtcman.classList.remove('d-none');
                if (zoneRtcman) zoneRtcman.classList.add('is-invalid-zone');
                isValid = false;
            }

            // Check PROG file in modal
            const fileProg = document.getElementById('modal_file_prog');
            const errorProg = document.getElementById('error_modal_file_prog');
            const zoneProg = document.getElementById('modalDropZoneProg');
            if (fileProg && (!fileProg.files || fileProg.files.length === 0)) {
                if (errorProg) errorProg.classList.remove('d-none');
                if (zoneProg) zoneProg.classList.add('is-invalid-zone');
                isValid = false;
            }

            if (!reportChangesForm.checkValidity() || !isValid) {
                e.preventDefault();
                reportChangesForm.reportValidity();
                return;
            }
            modalSubmitBtn.disabled = true;
            modalSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
        });
    }

    // ── Re-upload Batch Submit Spinner Guard ───────────────────
    document.querySelectorAll('.ntc-reupload-form').forEach(reuploadForm => {
        reuploadForm.addEventListener('submit', function (e) {
            let isValid = true;
            
            // Check all file inputs in this form
            reuploadForm.querySelectorAll('.ntc-file-input').forEach(input => {
                const zone = input.closest('.ntc-compact-drop-zone');
                if (!input.files || input.files.length === 0) {
                    if (zone) {
                        zone.classList.add('is-invalid-zone');
                    }
                    isValid = false;
                }
            });

            if (!reuploadForm.checkValidity() || !isValid) {
                e.preventDefault();
                reuploadForm.reportValidity();
                return;
            }
            const reuploadSubmitBtn = reuploadForm.querySelector('button[type="submit"]');
            if (reuploadSubmitBtn) {
                reuploadSubmitBtn.disabled = true;
                reuploadSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
            }
        });
    });

});
