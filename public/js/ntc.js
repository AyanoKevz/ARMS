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

    // ── Training day and instructor pickers ───────────────
    // Shared with the admin NTC page, so they live in their own file.
    const Picker = window.NtcTrainingPicker;

    const formatDisplayDate     = Picker.formatDisplayDate;
    const safeJsonArray         = Picker.safeJsonArray;
    const safeJsonMap           = Picker.safeJsonMap;
    const wireTrainingDays      = Picker.wireTrainingDays;
    const wireInstructorPicker  = Picker.wireInstructorPicker;
    const linkDaysToInstructors = Picker.link;

    const mainDays        = wireTrainingDays('', 'ntc_training_type_id');
    const mainInstructors = wireInstructorPicker('');
    linkDaysToInstructors(mainDays, mainInstructors);

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

            if (mainDays && !mainDays.validate()) isValid = false;
            if (mainInstructors && !mainInstructors.validate()) isValid = false;

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
            const trainingDates = this.getAttribute('data-training-dates');
            const instructorIds = this.getAttribute('data-instructor-ids');

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
                
                // Reload the submission's own days. The type is already set
                // above, so the right number of rows is built before they are
                // filled; the end date follows from the days as usual.
                if (modalDays) {
                    modalDays.setDates(safeJsonMap(trainingDates));
                }

                // Applied after the days, so anyone whose credentials do not
                // reach the last training day is already disabled and cannot
                // be re-checked here.
                if (modalInstructors) {
                    modalInstructors.setSelected(safeJsonArray(instructorIds));
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

    // ── Instructors dialog (read-only, from the table) ────
    // One dialog for every row, filled from the button that opened it.
    const instructorsModalEl = document.getElementById('ntcInstructorsModal');
    const instructorsModal   = instructorsModalEl ? new bootstrap.Modal(instructorsModalEl) : null;
    const instructorsList    = document.getElementById('ntcInstructorsModalList');
    const instructorsRef     = document.getElementById('ntcInstructorsModalRef');

    if (instructorsModalEl && instructorsModal) {
        instructorsModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', () => instructorsModal.hide());
        });
    }

    document.querySelectorAll('.btn-view-instructors').forEach(button => {
        button.addEventListener('click', function () {
            if (!instructorsModal || !instructorsList) return;

            if (instructorsRef) {
                instructorsRef.textContent = this.getAttribute('data-ntc-ref') || '';
            }

            // Built as nodes rather than markup: the names are user data and
            // have no business being parsed as HTML.
            instructorsList.replaceChildren();

            safeJsonArray(this.getAttribute('data-instructors')).forEach(entry => {
                const item = document.createElement('li');
                item.className = 'ntc-readout-item';

                const name = document.createElement('span');
                name.className = 'ntc-readout-name';
                name.textContent = entry.name || '';
                item.appendChild(name);

                const credentials = Array.isArray(entry.credentials) ? entry.credentials : [];

                if (credentials.length) {
                    const tags = document.createElement('span');
                    tags.className = 'ntc-readout-tags';

                    credentials.forEach(text => {
                        const tag = document.createElement('span');
                        tag.className = 'ntc-readout-tag';
                        tag.textContent = text;
                        tags.appendChild(tag);
                    });

                    item.appendChild(tags);
                } else {
                    const none = document.createElement('span');
                    none.className = 'ntc-readout-empty';
                    none.textContent = 'No credentials on file';
                    item.appendChild(none);
                }

                instructorsList.appendChild(item);
            });

            instructorsModal.show();
        });
    });

    // ── Training period dialog (read-only, from the table) ─
    const periodModalEl = document.getElementById('ntcTrainingDaysModal');
    const periodModal   = periodModalEl ? new bootstrap.Modal(periodModalEl) : null;
    const periodList    = document.getElementById('ntcTrainingDaysModalList');
    const periodRef     = document.getElementById('ntcTrainingDaysModalRef');
    const periodStart   = document.getElementById('ntcTrainingDaysModalStart');
    const periodEnd     = document.getElementById('ntcTrainingDaysModalEnd');

    if (periodModalEl && periodModal) {
        periodModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', () => periodModal.hide());
        });
    }

    document.querySelectorAll('.btn-view-training-days').forEach(button => {
        button.addEventListener('click', function () {
            if (!periodModal || !periodList) return;

            if (periodRef)   periodRef.textContent   = this.getAttribute('data-ntc-ref') || '';
            if (periodStart) periodStart.textContent = this.getAttribute('data-start') || '—';
            if (periodEnd)   periodEnd.textContent   = this.getAttribute('data-end') || '—';

            // Built as nodes rather than markup, for the same reason the
            // instructors dialog is: this is server data, not a template.
            periodList.replaceChildren();

            safeJsonArray(this.getAttribute('data-days')).forEach(entry => {
                const item = document.createElement('li');
                item.className = 'ntc-readout-item';

                const heading = document.createElement('span');
                heading.className = 'ntc-readout-name';
                heading.textContent = 'Day ' + entry.day;
                item.appendChild(heading);

                const dates = Array.isArray(entry.dates) ? entry.dates : [];

                if (dates.length) {
                    const tags = document.createElement('span');
                    tags.className = 'ntc-readout-tags';

                    dates.forEach(text => {
                        const tag = document.createElement('span');
                        tag.className = 'ntc-readout-tag';
                        tag.textContent = text;
                        tags.appendChild(tag);
                    });

                    item.appendChild(tags);
                } else {
                    const none = document.createElement('span');
                    none.className = 'ntc-readout-empty';
                    none.textContent = 'No date recorded';
                    item.appendChild(none);
                }

                periodList.appendChild(item);
            });

            periodModal.show();
        });
    });

    // ── Modal training days and instructors ───────────────
    const modalDays        = wireTrainingDays('modal_', 'modal_ntc_training_type_id');
    const modalInstructors = wireInstructorPicker('modal_');
    linkDaysToInstructors(modalDays, modalInstructors);

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

    /* ─── Notice of Cancellation ──────────────────────────────────────────
       One dialog for the whole table, retargeted by whichever button opened
       it. Cancelling is terminal and nobody reviews it afterwards, so the
       submit stays disabled until a reason has actually been written. */

    const cancelModalEl  = document.getElementById('ntcCancelModal');
    const cancelForm     = document.getElementById('ntcCancelForm');
    const cancelReason   = document.getElementById('ntcCancelReason');
    const cancelSubmit   = document.getElementById('ntcCancelSubmit');
    const cancelCount    = document.getElementById('ntcCancelCount');
    const cancelError    = document.getElementById('ntcCancelError');
    const cancelModal    = cancelModalEl && window.bootstrap
        ? new bootstrap.Modal(cancelModalEl)
        : null;

    const MIN_REASON = 10;

    function cancelReasonOk() {
        return cancelReason && cancelReason.value.trim().length >= MIN_REASON;
    }

    function refreshCancelState() {
        if (cancelSubmit) cancelSubmit.disabled = !cancelReasonOk();
        if (cancelCount && cancelReason) cancelCount.textContent = String(cancelReason.value.length);
        if (cancelError && cancelReasonOk()) cancelError.classList.add('d-none');
    }

    if (cancelReason) {
        cancelReason.addEventListener('input', refreshCancelState);
    }

    document.querySelectorAll('.btn-cancel-ntc').forEach(button => {
        button.addEventListener('click', function () {
            if (!cancelModal || !cancelForm) return;

            cancelForm.setAttribute('action', this.getAttribute('data-url') || '');

            const ref    = document.getElementById('ntcCancelRef');
            const type   = document.getElementById('ntcCancelType');
            const period = document.getElementById('ntcCancelPeriod');

            // textContent throughout: the venue and period are the FATPro's
            // own text and are not to be parsed as markup.
            if (ref)    ref.textContent    = this.getAttribute('data-ref') || 'this training';
            if (type)   type.textContent   = this.getAttribute('data-type') || '—';
            if (period) period.textContent = this.getAttribute('data-period') || '—';

            // Last training's reason must not carry over into this one.
            if (cancelReason) cancelReason.value = '';
            if (cancelError) cancelError.classList.add('d-none');
            refreshCancelState();

            cancelModal.show();
        });
    });

    if (cancelForm) {
        cancelForm.addEventListener('submit', function (e) {
            if (!cancelReasonOk()) {
                e.preventDefault();
                if (cancelError) cancelError.classList.remove('d-none');
                if (cancelReason) cancelReason.focus();
                return;
            }

            if (cancelSubmit) {
                cancelSubmit.disabled = true;
                cancelSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Cancelling...';
            }
        });
    }

});