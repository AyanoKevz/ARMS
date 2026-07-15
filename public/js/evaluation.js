/**
 * evaluation.js  —  Document Evaluation & Interview Scheduling
 *
 * BUTTON STATES (single centred button — #btn-open-schedule):
 *  1. pending > 0          → disabled · grey   · "Pending Documents (X remaining)"
 *  2. no pending, rejected > 0 → enabled · red · "Send Rejection Email (X rejected)" → POST finalize
 *  3. all approved         → enabled · green   · "Schedule Interview" → opens modal
 */

(function () {
    'use strict';

    const isScheduled = window.ARMS?.isScheduled ?? false;
    const hasInterviewRecord = window.ARMS?.hasInterviewRecord ?? false;
    const allApproved = window.ARMS?.allApproved ?? false;

    let activeSavesCount = 0;

    /* ─── Helpers ─────────────────────────────────────────── */
    function getStatusInputs() {
        return Array.from(document.querySelectorAll('input[id^="status-input-"]'));
    }

    function updateActiveSavesIndicator() {
        if (activeSavesCount <= 0) {
            refreshState();
        }
    }

    function updateDocStatusUI(docId, status) {
        const statusInput = document.getElementById(`status-input-${docId}`);
        const badge       = document.getElementById(`badge-${docId}`);
        const approveBtn  = document.querySelector(`.btn-approve[data-doc-id="${docId}"]`);
        const rejectBtn   = document.querySelector(`.btn-reject[data-doc-id="${docId}"]`);
        const rejectPanel = document.getElementById(`reject-panel-${docId}`);

        if (!statusInput) return;

        statusInput.value = status;

        if (badge) {
            badge.classList.remove('doc-badge-approved','doc-badge-rejected','doc-badge-pending','doc-badge-for_revision');
            badge.classList.add(`doc-badge-${status}`);
            
            let labelText = 'Pending';
            if (status === 'approved') labelText = 'Approved';
            else if (status === 'rejected') labelText = 'Rejected';
            else if (status === 'returned') labelText = 'Awaiting Re-upload';
            else if (status === 'for_revision') labelText = 'For Revision';
            badge.textContent = labelText;
        }

        if (approveBtn) approveBtn.classList.toggle('active', status === 'approved');
        if (rejectBtn) rejectBtn.classList.toggle('active',  status === 'rejected');

        if (rejectPanel) {
            rejectPanel.style.display = status === 'rejected' ? 'block' : 'none';
        }
    }

    /* ─── setDocStatus ────────────────────────────────────── */
    window.setDocStatus = function (docId, status) {
        const statusInput = document.getElementById(`status-input-${docId}`);
        if (!statusInput) return;

        const oldStatus = statusInput.value;
        if (oldStatus === status) return;

        updateDocStatusUI(docId, status);
        if (status === 'rejected') {
            const ta = document.getElementById(`remarks-${docId}`);
            if (ta) ta.focus();
        }

        activeSavesCount++;
        updateActiveSavesIndicator();

        let itemType = 'document';
        let itemId = docId;

        if (typeof docId === 'string') {
            if (docId.startsWith('cred-')) {
                itemType = 'credential';
                itemId = parseInt(docId.replace('cred-', ''), 10);
            } else if (docId.startsWith('inst-')) {
                itemType = 'instructor';
                itemId = parseInt(docId.replace('inst-', ''), 10);
            } else {
                itemId = parseInt(docId, 10);
            }
        } else {
            itemId = parseInt(docId, 10);
        }

        if (window.ARMS && window.ARMS.evaluateItemUrl) {
            const formData = new FormData();
            formData.append('_token', window.ARMS.csrfToken);
            formData.append('item_type', itemType);
            formData.append('item_id', itemId);
            formData.append('status', status);

            const remarksInput = document.getElementById(`remarks-${docId}`);
            if (remarksInput) {
                formData.append('remarks', remarksInput.value);
            }

            fetch(window.ARMS.evaluateItemUrl, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            }).then(async (res) => {
                const data = await res.json();
                if (data.success) {
                    showToast('Evaluation auto-saved.', 'success');
                } else {
                    console.error('Auto-save failed:', data.message);
                    showToast('Failed to auto-save evaluation. Reverting...', 'danger');
                    updateDocStatusUI(docId, oldStatus);
                    refreshState();
                }
            }).catch(err => {
                console.error('Auto-save network error:', err);
                showToast('Network error during auto-save. Reverting...', 'danger');
                updateDocStatusUI(docId, oldStatus);
                refreshState();
            }).finally(() => {
                activeSavesCount--;
                updateActiveSavesIndicator();
            });
        } else {
            activeSavesCount--;
            updateActiveSavesIndicator();
        }

        refreshState();
    };

    /* ─── refreshState ────────────────────────────────────── */
    function refreshState() {
        const inputs   = getStatusInputs();
        const total    = inputs.length;

        const applicationStatus = window.ARMS?.applicationStatus ?? 'Under Evaluation';
        const isForUpdate = (applicationStatus === 'For Update');

        const approved = inputs.filter(i => i.value === 'approved').length;

        const awaitingUpdate = isForUpdate
            ? inputs.filter(i => i.getAttribute('data-db-status') === 'rejected' || i.getAttribute('data-db-status') === 'returned').length
            : 0;

        const rejected = isForUpdate
            ? 0
            : inputs.filter(i => i.value === 'rejected').length;

        const pending  = total - approved - rejected - awaitingUpdate;

        // Progress label inside Documents card header (old progress element if it exists)
        const progressEl = document.getElementById('eval-progress-label');
        if (progressEl) {
            let labelText = `${approved} approved`;
            if (rejected > 0) labelText += ` · ${rejected} rejected`;
            if (awaitingUpdate > 0) labelText += ` · ${awaitingUpdate} awaiting re-upload`;
            if (pending > 0) labelText += ` · ${pending} pending`;
            progressEl.textContent = labelText;
        }

        // Submitted Documents (Main Card Progress Badge)
        const docInputs = inputs.filter(i => !i.id.includes('cred-') && !i.id.includes('inst-'));
        const docTotal = docInputs.length;
        const docApproved = docInputs.filter(i => i.value === 'approved').length;
        const docProgressEl = document.getElementById('submitted-docs-progress');
        if (docProgressEl) {
            docProgressEl.textContent = `${docApproved} / ${docTotal} Accepted`;
        }

        // Folder Sub-sections Progress Badges
        const folderSections = document.querySelectorAll('[id^="folder-section-"]');
        folderSections.forEach(section => {
            const typeId = section.id.replace('folder-section-', '');
            const folderInputs = Array.from(section.querySelectorAll('input[id^="status-input-"]'));
            const folderTotal = folderInputs.length;
            const folderApproved = folderInputs.filter(i => i.value === 'approved').length;
            const badgeEl = document.getElementById(`folder-progress-${typeId}`);
            if (badgeEl) {
                badgeEl.textContent = `${folderApproved} / ${folderTotal} Accepted`;
            }
        });

        // Instructor Credentials (Main Card Progress Badge)
        const credInputs = inputs.filter(i => i.id.includes('cred-') || i.id.includes('inst-'));
        const credTotal = credInputs.length;
        const credApproved = credInputs.filter(i => i.value === 'approved').length;
        const credProgressEl = document.getElementById('instructor-creds-progress');
        if (credProgressEl) {
            credProgressEl.textContent = `${credApproved} / ${credTotal} Accepted`;
        }

        // Instructor Sub-sections Progress Badges
        const instructorSections = document.querySelectorAll('[id^="instructor-section-"]');
        instructorSections.forEach(section => {
            const instructorId = section.id.replace('instructor-section-', '');
            const instInputs = Array.from(section.querySelectorAll('input[id^="status-input-"]'));
            const instTotal = instInputs.length;
            const instApproved = instInputs.filter(i => i.value === 'approved').length;
            const badgeEl = document.getElementById(`instructor-progress-${instructorId}`);
            if (badgeEl) {
                badgeEl.textContent = `${instApproved} / ${instTotal} Accepted`;
            }
        });

        const btn      = document.getElementById('btn-open-schedule');
        const btnText  = document.getElementById('btn-schedule-text');
        if (!btn) return;

        // If the application is already scheduled in the database,
        // we do not want to manage the save-approvals states, as the button is used for scheduling.
        if (isScheduled && !window.ARMS?.hasPendingUpdate && window.ARMS?.canUpdateSchedule) {
            return;
        }

        if (rejected > 0) {
            // ── State 2: Some rejected → Send Rejection Email ──
            btn.disabled = false;
            btn.className = 'btn btn-danger btn-sm fw-semibold px-4';
            btn.style.cssText = 'border-radius:6px;';
            btn.setAttribute('data-bs-toggle', 'modal');
            btn.setAttribute('data-bs-target', '#rejectionConfirmModal');
            btn.onclick = null;
            if (btnText) btnText.textContent = `Send Rejection Email (${rejected} rejected)`;

        } else if (pending > 0 || awaitingUpdate > 0) {
            // ── State 1: Still has unevaluated docs or awaiting re-upload ──
            btn.disabled = true;
            btn.className = 'btn btn-outline-secondary btn-sm fw-semibold px-4';
            btn.style.cssText = 'border-radius:6px;';
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.onclick = null;
            if (btnText) {
                if (awaitingUpdate > 0 && pending === 0) {
                    btnText.textContent = `Awaiting Applicant Re-upload (${awaitingUpdate} remaining)`;
                } else {
                    btnText.textContent = `Pending Documents (${pending + awaitingUpdate} remaining)`;
                }
            }

        } else {
            // ── State 3: All approved → Save Approvals & Schedule Interview ──
            btn.disabled = false;
            btn.className = 'btn btn-success btn-sm fw-semibold px-4';
            btn.style.cssText = 'border-radius:6px;';
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.onclick = submitAllApproved;
            if (btnText) {
                const isAccepted = window.ARMS?.isApproved || window.ARMS?.isAccredited;
                btnText.textContent = isAccepted ? 'Save Approvals' : 'Save Approvals and Schedule Interview';
            }
        }
    }

    /* ─── submitAllApproved (called when all approved on UI) ─ */
    async function submitAllApproved() {
        const form    = document.getElementById('evaluation-form');
        const mainBtn = document.getElementById('btn-open-schedule');
        if (!form) return;

        mainBtn.disabled = true;
        mainBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';

        try {
            const res  = await fetch(form.dataset.url, {
                method:  'POST',
                body:    new FormData(form),
                headers: { 'Accept': 'application/json' },
            });

            const data = await res.json();

            if (!data.success) {
                alert(data.message || 'Submission failed. Please try again.');
                mainBtn.disabled = false;
                mainBtn.innerHTML = 'Save Approvals and Schedule Interview';
                return;
            }

            // Update status badge
            const statusBadge = document.getElementById('app-status-badge');
            if (statusBadge && data.new_status) {
                statusBadge.textContent = data.new_status;
                statusBadge.className   = 'badge fs-6 px-3 py-2 bg-primary';
            }

            showToast(data.message || 'Approvals saved!', 'success');
            
            // Lock eval buttons — evaluation complete
            document.querySelectorAll('.doc-eval-actions').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.reject-panel').forEach(el => el.style.display = 'none');

            const isAccepted = window.ARMS?.isApproved || window.ARMS?.isAccredited;

            if (isAccepted || data.action === 'update_accepted') {
                // Instructor credential update accepted — just reload, no interview needed
                mainBtn.textContent = 'Approvals Saved';
                setTimeout(() => window.location.reload(), 1500);
            } else {
                mainBtn.textContent = 'Approvals Saved';
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }

        } catch (err) {
            console.error('Approval submission error:', err);
            alert('A network error occurred. Please try again.');
            mainBtn.disabled = false;
            const isAccepted = window.ARMS?.isApproved || window.ARMS?.isAccredited;
            mainBtn.textContent = isAccepted ? 'Save Approvals' : 'Save Approvals and Schedule Interview';
        }
    }

    /* ─── Rejection Modal Population ──────────────────────── */
    const rejectionModalEl = document.getElementById('rejectionConfirmModal');
    if (rejectionModalEl) {
        rejectionModalEl.addEventListener('show.bs.modal', function (e) {
            // Build the list of rejected docs to show
            const list = document.getElementById('rejection-doc-list');
            if (list) {
                list.innerHTML = '';
                document.querySelectorAll('input[id^="status-input-"]').forEach(input => {
                    if (input.value !== 'rejected') return;

                    const docId   = input.id.replace('status-input-', '');
                    const nameEl  = document.querySelector(`#doc-row-${docId} .doc-field-name`);
                    const remarks = document.getElementById(`remarks-${docId}`)?.value?.trim() ?? '';

                    const docName = nameEl ? nameEl.textContent.trim() : `Document #${docId}`;

                    const item = document.createElement('div');
                    item.style.cssText = 'background:#fff;border-radius:8px;border:1px solid #f5c6cb;padding:10px 14px;';
                    item.innerHTML = `
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-file-earmark-x text-danger"></i>
                            <span class="fw-semibold" style="font-size:.88rem;color:#2A3F54;">${docName}</span>
                        </div>
                        ${remarks
                            ? `<div class="text-muted" style="font-size:.8rem;padding-left:22px;">
                                   <i class="bi bi-chat-left-text me-1"></i>${remarks}
                               </div>`
                            : `<div class="text-muted fst-italic" style="font-size:.78rem;padding-left:22px;">No remarks provided</div>`
                        }`;
                    list.appendChild(item);
                });
            }
        });
    }

    /* ─── submitRejection (called by Confirm button in modal) ─ */
    async function submitRejection() {
        const form    = document.getElementById('evaluation-form');
        const mainBtn = document.getElementById('btn-open-schedule');
        const confBtn = document.getElementById('btn-confirm-rejection');
        if (!form) return;

        // Spinner on confirm button
        if (confBtn) {
            confBtn.disabled = true;
            confBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
        }

        try {
            const res  = await fetch(form.dataset.url, {
                method:  'POST',
                body:    new FormData(form),
                headers: { 'Accept': 'application/json' },
            });

            const data = await res.json();

            // Close modal safely
            const modalEl = document.getElementById('rejectionConfirmModal');
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getInstance(modalEl)?.hide();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('hide');
            }

            if (!data.success) {
                alert(data.message || 'Submission failed. Please try again.');
                if (confBtn) { confBtn.disabled = false; confBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Confirm and Send Email'; }
                return;
            }

            // Update status badge
            const statusBadge = document.getElementById('app-status-badge');
            if (statusBadge && data.new_status) {
                statusBadge.textContent = data.new_status;
                statusBadge.className   = 'badge fs-6 px-3 py-2 bg-warning text-dark';
            }

            showToast(data.message || 'Rejection email sent!', 'success');
            setTimeout(() => window.location.reload(), 1800);

        } catch (err) {
            console.error('Rejection submission error:', err);
            const modalEl = document.getElementById('rejectionConfirmModal');
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getInstance(modalEl)?.hide();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('hide');
            }
            
            alert('A network error occurred. Please try again.');
            if (confBtn) { confBtn.disabled = false; confBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Confirm and Send Email'; }
        }
    }

    /* ─── showToast ───────────────────────────────────────── */
    function showToast(message, type = 'success') {
        const colour = type === 'success' ? '#198754' : '#dc3545';
        const toast  = document.createElement('div');
        toast.style.cssText = `
            position:fixed; bottom:28px; right:28px; z-index:9999;
            background:${colour}; color:#fff;
            padding:12px 22px; border-radius:8px;
            font-size:.9rem; font-weight:600;
            box-shadow:0 4px 12px rgba(0,0,0,.18);
            transition: opacity .4s;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }, 2500);
    }

    /* ─── Interview mode → venue toggle (inside modal) ────── */
    function syncVenueState() {
        const modeSelect = document.getElementById('interview-mode');
        const venueInput = document.getElementById('interview-venue');
        const venueNote  = document.getElementById('venue-note');
        const onlineNotice = document.getElementById('online-notice');

        if (!modeSelect) return;

        const isOnline = modeSelect.value === 'online';
        const isF2F = modeSelect.value === 'f2f';
        const noMode = modeSelect.value === '';

        if (venueInput) {
            if (noMode) {
                venueInput.disabled = true;
                venueInput.placeholder = 'Select a mode first';
                venueInput.value = '';
            } else {
                venueInput.disabled = false;
                if (isF2F) {
                    venueInput.placeholder = 'Enter venue address';
                    if (!venueInput.value) {
                        venueInput.value = 'Occupational Safety And Health Center';
                    }
                } else if (isOnline) {
                    venueInput.placeholder = 'Enter meeting link (e.g. Zoom, Google Meet)';
                    if (venueInput.value === 'Occupational Safety And Health Center') {
                        venueInput.value = '';
                    }
                } else {
                    venueInput.placeholder = 'Venue / meeting link';
                }
            }
        }

        if (venueNote) {
            if (isOnline) {
                venueNote.textContent = '(Online Link)';
            } else if (isF2F) {
                venueNote.textContent = '(F2F Venue)';
            } else {
                venueNote.textContent = '';
            }
        }

        if (onlineNotice) {
            if (isOnline) {
                onlineNotice.classList.remove('d-none');
            } else {
                onlineNotice.classList.add('d-none');
            }
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'interview-mode') syncVenueState();
    });

    const modal = document.getElementById('scheduleInterviewModal');
    if (modal) modal.addEventListener('shown.bs.modal', syncVenueState);

    // Wire the modal confirm button
    const confirmBtn = document.getElementById('btn-confirm-rejection');
    if (confirmBtn) confirmBtn.addEventListener('click', submitRejection);

    // Wire the schedule interview form submit spinner
    const scheduleForm = document.getElementById('schedule-interview-form');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function () {
            const btn = document.getElementById('submit-schedule-btn');
            const text = document.getElementById('submit-schedule-text');
            const spinner = document.getElementById('submit-schedule-spinner');
            if (btn) btn.disabled = true;
            if (text) text.classList.add('d-none');
            if (spinner) spinner.classList.remove('d-none');
        });
    }

    // Debounce timer for remarks auto-save
    let remarksDebounceTimer = null;

    // AbortController map: tracks in-flight fetch per textarea id so we can
    // cancel a previous request when a newer one starts for the same field.
    const remarksAbortControllers = {};

    function saveRemarks(ta) {
        const isNtc = ta.id.startsWith('ntc-remarks-');
        const docId = isNtc ? ta.id.replace('ntc-remarks-', '') : ta.id.replace('remarks-', '');

        if (isNtc) {
            if (!window.ARMS || !window.ARMS.ntcEvaluateUrlBase) return;

            const statusInput = document.getElementById(`ntc-status-input-${docId}`);
            const status = statusInput ? statusInput.value : 'rejected';

            // Only save when the document has been explicitly evaluated
            if (!status || status === 'pending') return;

            // Cancel any previous in-flight request for this textarea
            if (remarksAbortControllers[ta.id]) {
                remarksAbortControllers[ta.id].abort();
                // activeSavesCount was already decremented in the finally of the aborted request
            }
            const controller = new AbortController();
            remarksAbortControllers[ta.id] = controller;

            activeSavesCount++;
            updateActiveSavesIndicator();

            const url = `${window.ARMS.ntcEvaluateUrlBase}/${docId}/evaluate`;
            const formData = new FormData();
            formData.append('_token', window.ARMS.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content);
            formData.append('status', status);
            formData.append('remarks', ta.value);

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
                signal: controller.signal
            }).then(async (res) => {
                const data = await res.json();
                if (!data.success) {
                    showToast('Failed to auto-save remarks.', 'danger');
                }
            }).catch(err => {
                // AbortError is expected when a newer request cancels this one — don't show toast
                if (err.name !== 'AbortError') {
                    console.error('Auto-save remarks error:', err);
                    showToast('Network error during remarks auto-save.', 'danger');
                }
            }).finally(() => {
                delete remarksAbortControllers[ta.id];
                activeSavesCount--;
                updateActiveSavesIndicator();
            });
        } else {
            let itemType = 'document';
            let itemId = docId;

            if (docId.startsWith('cred-')) {
                itemType = 'credential';
                itemId = parseInt(docId.replace('cred-', ''), 10);
            } else if (docId.startsWith('inst-')) {
                itemType = 'instructor';
                itemId = parseInt(docId.replace('inst-', ''), 10);
            } else {
                itemId = parseInt(docId, 10);
            }

            const statusInput = document.getElementById(`status-input-${docId}`);
            const status = statusInput ? statusInput.value : 'rejected';

            if (!window.ARMS || !window.ARMS.evaluateItemUrl) return;

            // Only save when the document has been explicitly evaluated
            if (!status || status === 'pending') return;

            // Cancel any previous in-flight request for this textarea
            if (remarksAbortControllers[ta.id]) {
                remarksAbortControllers[ta.id].abort();
            }
            const controller = new AbortController();
            remarksAbortControllers[ta.id] = controller;

            activeSavesCount++;
            updateActiveSavesIndicator();

            const formData = new FormData();
            formData.append('_token', window.ARMS.csrfToken);
            formData.append('item_type', itemType);
            formData.append('item_id', itemId);
            formData.append('status', status);
            formData.append('remarks', ta.value);

            fetch(window.ARMS.evaluateItemUrl, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
                signal: controller.signal
            }).then(async (res) => {
                const data = await res.json();
                if (!data.success) {
                    showToast('Failed to auto-save remarks.', 'danger');
                }
            }).catch(err => {
                // AbortError is expected when a newer request cancels this one — don't show toast
                if (err.name !== 'AbortError') {
                    console.error('Auto-save remarks error:', err);
                    showToast('Network error during remarks auto-save.', 'danger');
                }
            }).finally(() => {
                delete remarksAbortControllers[ta.id];
                activeSavesCount--;
                updateActiveSavesIndicator();
            });
        }
    }

    // Save remarks on input (debounced to 1500ms) and immediately on blur.
    // The longer debounce reduces the number of in-flight requests while typing.
    document.addEventListener('input', function (e) {
        if (e.target && e.target.classList.contains('reject-remarks-input')) {
            clearTimeout(remarksDebounceTimer);
            remarksDebounceTimer = setTimeout(() => saveRemarks(e.target), 1500);
        }
    });
    document.addEventListener('blur', function (e) {
        if (e.target && e.target.classList.contains('reject-remarks-input')) {
            clearTimeout(remarksDebounceTimer);
            saveRemarks(e.target);
        }
    }, true);

    window.addEventListener('beforeunload', function (e) {
        if (activeSavesCount > 0) {
            e.preventDefault();
            e.returnValue = 'Evaluation updates are still saving in the background. Are you sure you want to leave?';
            return e.returnValue;
        }
    });

    /* ─── Init ────────────────────────────────────────────── */
    if (isScheduled && !window.ARMS?.hasPendingUpdate && window.ARMS?.canUpdateSchedule) {
        // Already scheduled — enable button directly
        const btn     = document.getElementById('btn-open-schedule');
        const btnText = document.getElementById('btn-schedule-text');
        if (btn) {
            btn.disabled = false;
            btn.className = 'btn btn-outline-primary btn-sm fw-semibold px-4';
            btn.style.cssText = 'border-radius:6px;';
            btn.setAttribute('data-bs-toggle', 'modal');
            btn.setAttribute('data-bs-target', '#scheduleInterviewModal');
            btn.onclick = null;
        }
        if (btnText) btnText.textContent = hasInterviewRecord ? 'Update Schedule' : 'Set Schedule';

        // Hide eval buttons (belt-and-suspenders alongside blade guard)
        document.querySelectorAll('.doc-eval-actions').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.reject-panel').forEach(el => el.style.display = 'none');
    } // end if (isScheduled)

    /* ─── Chevrons / Panel Collapses ──────────────────────── */
    const pctBody = document.getElementById('pctTimelineBody');
    const pctChevron = document.getElementById('pctChevron');
    if (pctBody && pctChevron) {
        pctBody.addEventListener('show.bs.collapse', () => pctChevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
        pctBody.addEventListener('hide.bs.collapse', () => pctChevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
    }

    const docsBody = document.getElementById('submittedDocumentsBody');
    const docsChevron = document.getElementById('docsChevron');
    if (docsBody && docsChevron) {
        docsBody.addEventListener('show.bs.collapse', () => docsChevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
        docsBody.addEventListener('hide.bs.collapse', () => docsChevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
    }

    const credsBody = document.getElementById('instructorCredentialsBody');
    const credsChevron = document.getElementById('credsChevron');
    if (credsBody && credsChevron) {
        credsBody.addEventListener('show.bs.collapse', () => credsChevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
        credsBody.addEventListener('hide.bs.collapse', () => credsChevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
    }

    // Dynamic chevrons for subfolders
    document.querySelectorAll('[id^="folder-body-"]').forEach(body => {
        const typeId = body.id.replace('folder-body-', '');
        const chevron = document.getElementById(`folder-chevron-${typeId}`);
        if (chevron) {
            body.addEventListener('show.bs.collapse', () => chevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
            body.addEventListener('hide.bs.collapse', () => chevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
        }
    });

    const historyBody = document.getElementById('accreditationHistoryBody');
    const historyChevron = document.getElementById('historyChevron');
    if (historyBody && historyChevron) {
        historyBody.addEventListener('show.bs.collapse', () => historyChevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
        historyBody.addEventListener('hide.bs.collapse', () => historyChevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
    }

    /* ─── Certificate Generation ──────────────────────────── */
    let certBaseUrl = '';
    window.setCertUrl = function(url) {
        certBaseUrl = url;
        const nameInput = document.getElementById('cert-director-name');
        if (nameInput) {
            nameInput.value = 'JOSE MARIA S. BATINO';
            nameInput.classList.remove('is-invalid');
        }
    };

    window.generateCert = function() {
        const nameInput = document.getElementById('cert-director-name');
        const name = nameInput ? nameInput.value.trim() : '';
        if (!name) {
            if (nameInput) nameInput.classList.add('is-invalid');
            return;
        }
        const url = certBaseUrl + '?executive_director=' + encodeURIComponent(name);
        window.open(url, '_blank');
        
        // Close modal programmatically by clicking the modal's close/cancel button
        const modalEl = document.getElementById('certDirectorModal');
        if (modalEl) {
            const cancelBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');
            if (cancelBtn) {
                cancelBtn.click();
            }
        }
    };

    const certDirNameInput = document.getElementById('cert-director-name');
    if (certDirNameInput) {
        certDirNameInput.addEventListener('input', function () {
            this.classList.remove('is-invalid');
        });
    }

    /* ─── Interview Slot Conflict Checker ─────────────────── */
    const dateInput = document.getElementById('interview-date');
    const timeInput = document.getElementById('interview-time');
    const warningBox = document.getElementById('slot-conflict-warning');
    const warningMsg = document.getElementById('slot-conflict-msg');
    const submitBtn = document.querySelector('#schedule-interview-form button[type="submit"], button[form="schedule-interview-form"]');

    if (dateInput && timeInput) {
        let checkTimeout = null;

        const checkSlot = function() {
            const date = dateInput.value;
            const time = timeInput.value;

            // Only check when both fields are filled
            if (!date || !time) {
                hideWarning();
                return;
            }

            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(async function() {
                try {
                    const checkSlotUrl = window.ARMS?.checkSlotUrl;
                    const appId = window.ARMS?.applicationId;
                    if (!checkSlotUrl || !appId) return;

                    const url = new URL(checkSlotUrl, window.location.origin);
                    url.searchParams.set('date', date);
                    url.searchParams.set('time', time);
                    url.searchParams.set('application_id', appId);

                    const res = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();

                    if (!data.available) {
                        showWarning(data.message);
                    } else {
                        hideWarning();
                    }
                } catch (err) {
                    console.error('Slot check failed:', err);
                    hideWarning();
                }
            }, 350); // debounce 350ms
        };

        const showWarning = function(msg) {
            if (warningBox) {
                warningMsg.textContent = msg;
                warningBox.classList.remove('d-none');
                warningBox.classList.add('d-flex');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        };

        const hideWarning = function() {
            if (warningBox) {
                warningBox.classList.add('d-none');
                warningBox.classList.remove('d-flex');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        };

        dateInput.addEventListener('change', checkSlot);
        timeInput.addEventListener('change', checkSlot);
    }

    /* ─── Request Update Reasons Modal & Submission Loaders ─ */
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('req-chk')) {
            const targetId = e.target.dataset.target;
            const box = document.getElementById(targetId);
            if (box) {
                box.style.display = e.target.checked ? 'block' : 'none';
                const input = box.querySelector('input, textarea');
                if (input) input.required = e.target.checked;
            }
        }
    });

    const forms = ['confirm-approval-form', 'confirm-reject-form', 'evaluate-payment-form', 'upload-scanned-certificate-form', 'start-interview-form'];
    forms.forEach(function(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function() {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.style.opacity = '0.85';
                    btn.style.cursor = 'not-allowed';
                    const textSpan = btn.querySelector('.btn-text');
                    const spinnerSpan = btn.querySelector('.btn-spinner');
                    if (textSpan) textSpan.classList.add('d-none');
                    if (spinnerSpan) spinnerSpan.classList.remove('d-none');
                }
            });
        }
    });

    // Dynamic chevrons for instructors
    document.querySelectorAll('[id^="instructor-body-"]').forEach(body => {
        const instructorId = body.id.replace('instructor-body-', '');
        const chevron = document.getElementById(`instructor-chevron-${instructorId}`);
        if (chevron) {
            body.addEventListener('show.bs.collapse', () => chevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
            body.addEventListener('hide.bs.collapse', () => chevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
        }
    });

    // Live Ticker for Active Step
    const liveCounter = document.getElementById('livePctCounter');
    if (liveCounter) {
        let isSubmittingInterview = false;
        const secondsOnLoad = parseInt(liveCounter.getAttribute('data-seconds'), 10) || 0;
        const targetDays = liveCounter.getAttribute('data-target');
        const holidaysList = window.ARMS?.holidays || [];
        const serverTimeOnLoad = window.ARMS?.serverTime || Date.now();
        const timeOffset = serverTimeOnLoad - Date.now();

        // Helper to get components in Asia/Manila (UTC+8)
        const getManilaDateComponents = (timestampMs) => {
            const d = new Date(timestampMs + 8 * 3600 * 1000);
            return {
                year: d.getUTCFullYear(),
                month: d.getUTCMonth() + 1,
                date: d.getUTCDate(),
                day: d.getUTCDay(), // 0=Sunday, 6=Saturday
                hours: d.getUTCHours(),
                minutes: d.getUTCMinutes(),
                seconds: d.getUTCSeconds(),
                timeOfDaySeconds: d.getUTCHours() * 3600 + d.getUTCMinutes() * 60 + d.getUTCSeconds()
            };
        };

        // Helper to calculate working seconds between two timestamps in Manila timezone
        const calculateWorkingSecondsJS = (startMs, endMs) => {
            if (startMs >= endMs) {
                return 0;
            }

            let totalSeconds = 0;
            const startComp = getManilaDateComponents(startMs);
            const endComp = getManilaDateComponents(endMs);

            let currentDayStartMs = Date.UTC(startComp.year, startComp.month - 1, startComp.date) - 8 * 3600 * 1000;
            const endDayStartMs = Date.UTC(endComp.year, endComp.month - 1, endComp.date) - 8 * 3600 * 1000;
            const oneDayMs = 24 * 3600 * 1000;

            while (currentDayStartMs <= endDayStartMs) {
                const comp = getManilaDateComponents(currentDayStartMs);
                const isWeekend = comp.day === 0 || comp.day === 6;
                const yyyy = comp.year;
                const mm = String(comp.month).padStart(2, '0');
                const dd = String(comp.date).padStart(2, '0');
                const dateStr = `${yyyy}-${mm}-${dd}`;
                const isHoliday = holidaysList.includes(dateStr);

                if (!isWeekend && !isHoliday) {
                    const workStartMs = currentDayStartMs + 8 * 3600 * 1000;
                    const workEndMs   = currentDayStartMs + 17 * 3600 * 1000;

                    const effectiveStartMs = Math.max(startMs, workStartMs);
                    const effectiveEndMs   = Math.min(endMs, workEndMs);

                    if (effectiveStartMs < effectiveEndMs) {
                        totalSeconds += Math.floor((effectiveEndMs - effectiveStartMs) / 1000);
                    }
                }
                currentDayStartMs += oneDayMs;
            }

            return totalSeconds;
        };

        const updateLiveTicker = () => {
            const serverNow = Date.now() + timeOffset;

            // Auto start the interview if scheduled time is met/passed
            if (window.ARMS?.activeStep === 5 && 
                window.ARMS?.pctStatus === 'paused' && 
                window.ARMS?.interviewTimestampMs && 
                serverNow >= window.ARMS.interviewTimestampMs && 
                !isSubmittingInterview) {
                
                isSubmittingInterview = true;
                const startForm = document.getElementById('start-interview-form');
                if (startForm) {
                    const btnText = startForm.querySelector('.btn-text');
                    const btnSpinner = startForm.querySelector('.btn-spinner');
                    if (btnText && btnSpinner) {
                        btnText.classList.add('d-none');
                        btnSpinner.classList.remove('d-none');
                    }
                    startForm.submit();
                } else {
                    window.location.reload();
                }
            }
            
            // Calculate current components for checking if working hours/days
            const currentComp = getManilaDateComponents(serverNow);
            const isWeekend = currentComp.day === 0 || currentComp.day === 6;
            const yyyy = currentComp.year;
            const mm = String(currentComp.month).padStart(2, '0');
            const dd = String(currentComp.date).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;
            const isHoliday = holidaysList.includes(dateStr);

            let isWorking = true;
            let jsPausedReason = '';

            if (isWeekend) {
                isWorking = false;
                jsPausedReason = 'Weekend';
            } else if (isHoliday) {
                isWorking = false;
                jsPausedReason = 'Holiday';
            } else if (currentComp.timeOfDaySeconds < 8 * 3600 || currentComp.timeOfDaySeconds >= 17 * 3600) {
                isWorking = false;
                jsPausedReason = 'Past Working Hours';
            }

            // Calculate the working seconds since load and total seconds
            const workingSecondsSinceLoad = calculateWorkingSecondsJS(serverTimeOnLoad, serverNow);
            const seconds = secondsOnLoad + workingSecondsSinceLoad;

            // Format days/hours/minutes/seconds
            const days = (seconds / 32400).toFixed(1);
            const hrs = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            const liveTime = `${hrs}h ${mins}m ${secs}s`;

            liveCounter.innerHTML = `<i class="bi bi-stopwatch me-1"></i>(${liveTime}) &nbsp;&nbsp;${days} days / ${targetDays} days`;

            if (!isWorking) {
                const stepEl = liveCounter.closest('.pct-step');
                if (stepEl && stepEl.classList.contains('pct-step-active')) {
                    stepEl.classList.remove('pct-step-active');
                    stepEl.classList.add('pct-step-paused');
                    const iconEl = stepEl.querySelector('.pct-step-icon i');
                    if (iconEl) {
                        iconEl.classList.remove('bi-play-circle-fill');
                        iconEl.classList.add('bi-pause-circle-fill');
                    }
                }
                const reasonEl = document.getElementById('pct-paused-reason-live');
                if (reasonEl) {
                    reasonEl.textContent = ` — ${jsPausedReason}`;
                    reasonEl.style.display = 'inline';
                }
            } else {
                const stepEl = liveCounter.closest('.pct-step');
                if (stepEl && stepEl.classList.contains('pct-step-paused')) {
                    stepEl.classList.remove('pct-step-paused');
                    stepEl.classList.add('pct-step-active');
                    const iconEl = stepEl.querySelector('.pct-step-icon i');
                    if (iconEl) {
                        iconEl.classList.remove('bi-pause-circle-fill');
                        iconEl.classList.add('bi-play-circle-fill');
                    }
                }
                const reasonEl = document.getElementById('pct-paused-reason-live');
                if (reasonEl) {
                    reasonEl.textContent = '';
                    reasonEl.style.display = 'none';
                }
            }

            // Dynamically show/hide elements that are only allowed during working hours (e.g. Approve/Reject buttons)
            const workingOnlyEls = document.querySelectorAll('.pct-working-only');
            workingOnlyEls.forEach(el => {
                if (isWorking) {
                    el.style.setProperty('display', '', 'important');
                } else {
                    el.style.setProperty('display', 'none', 'important');
                }
            });

            // Dynamically toggle readonly state for rejection remarks textareas
            const rejectRemarksInputs = document.querySelectorAll('.reject-remarks-input');
            rejectRemarksInputs.forEach(input => {
                const defaultReadonly = input.getAttribute('data-default-readonly') === 'true';
                if (!isWorking || defaultReadonly) {
                    input.readOnly = true;
                } else {
                    input.readOnly = false;
                }
            });
        };

        // Run immediately on load and then every 1000ms
        updateLiveTicker();
        setInterval(updateLiveTicker, 1000);
    }

    /* ─── NTC Document Evaluation (Single Button Workflow) ─── */
    window.setNtcDocStatus = function (docId, status) {
        const input = document.getElementById('ntc-status-input-' + docId);
        if (!input) return;

        const oldStatus = input.value;
        if (oldStatus === status) return;

        input.value = status;
        const currentVal = status;

        // Update Button Classes
        const btnApprove = document.getElementById('btn-approve-' + docId);
        const btnReject = document.getElementById('btn-reject-' + docId);

        if (btnApprove) btnApprove.classList.toggle('active', currentVal === 'approved');
        if (btnReject) btnReject.classList.toggle('active', currentVal === 'rejected');

        // Update badge
        const badge = document.getElementById('ntc-badge-' + docId);
        if (badge) {
            badge.classList.remove('ntc-badge-approved', 'ntc-badge-rejected', 'ntc-badge-returned', 'ntc-badge-pending');
            
            let label = 'Pending';
            let cls = 'ntc-badge-pending';

            if (currentVal === 'approved') {
                label = 'Approved';
                cls = 'ntc-badge-approved';
            } else if (currentVal === 'rejected') {
                label = 'Rejected';
                cls = 'ntc-badge-rejected';
            }

            badge.className = `badge ${cls} px-2 py-1`;
            badge.style.cssText = 'font-size:.75rem;border-radius:20px;white-space:nowrap;';
            badge.textContent = label;
        }

        // Toggle Remarks Panel
        const panel = document.getElementById('ntc-reject-panel-' + docId);
        if (panel) {
            panel.style.display = currentVal === 'rejected' ? 'block' : 'none';
        }

        if (status === 'rejected') {
            const ta = document.getElementById(`ntc-remarks-${docId}`);
            if (ta) ta.focus();
        }

        // Auto-save via AJAX
        if (window.ARMS && window.ARMS.ntcEvaluateUrlBase) {
            activeSavesCount++;
            updateActiveSavesIndicator();

            const url = `${window.ARMS.ntcEvaluateUrlBase}/${docId}/evaluate`;
            const formData = new FormData();
            formData.append('_token', window.ARMS.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content);
            formData.append('status', status);

            const remarksInput = document.getElementById(`ntc-remarks-${docId}`);
            if (remarksInput) {
                formData.append('remarks', remarksInput.value);
            }

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            }).then(async (res) => {
                const data = await res.json();
                if (data.success) {
                    showToast('Evaluation auto-saved.', 'success');
                } else {
                    console.error('NTC Auto-save failed:', data.message);
                    showToast('Failed to auto-save evaluation. Reverting...', 'danger');
                    // Revert UI
                    input.value = oldStatus;
                    if (btnApprove) btnApprove.classList.toggle('active', oldStatus === 'approved');
                    if (btnReject) btnReject.classList.toggle('active', oldStatus === 'rejected');
                    if (badge) {
                        badge.classList.remove('ntc-badge-approved', 'ntc-badge-rejected', 'ntc-badge-returned', 'ntc-badge-pending');
                        let label = 'Pending';
                        let cls = 'ntc-badge-pending';
                        if (oldStatus === 'approved') { label = 'Approved'; cls = 'ntc-badge-approved'; }
                        else if (oldStatus === 'rejected') { label = 'Rejected'; cls = 'ntc-badge-rejected'; }
                        badge.className = `badge ${cls} px-2 py-1`;
                        badge.style.cssText = 'font-size:.75rem;border-radius:20px;white-space:nowrap;';
                        badge.textContent = label;
                    }
                    if (panel) panel.style.display = oldStatus === 'rejected' ? 'block' : 'none';
                    refreshNtcState();
                }
            }).catch(err => {
                console.error('NTC Auto-save network error:', err);
                showToast('Network error during auto-save. Reverting...', 'danger');
                // Revert UI
                input.value = oldStatus;
                if (btnApprove) btnApprove.classList.toggle('active', oldStatus === 'approved');
                if (btnReject) btnReject.classList.toggle('active', oldStatus === 'rejected');
                if (badge) {
                    badge.classList.remove('ntc-badge-approved', 'ntc-badge-rejected', 'ntc-badge-returned', 'ntc-badge-pending');
                    let label = 'Pending';
                    let cls = 'ntc-badge-pending';
                    if (oldStatus === 'approved') { label = 'Approved'; cls = 'ntc-badge-approved'; }
                    else if (oldStatus === 'rejected') { label = 'Rejected'; cls = 'ntc-badge-rejected'; }
                    badge.className = `badge ${cls} px-2 py-1`;
                    badge.style.cssText = 'font-size:.75rem;border-radius:20px;white-space:nowrap;';
                    badge.textContent = label;
                }
                if (panel) panel.style.display = oldStatus === 'rejected' ? 'block' : 'none';
                refreshNtcState();
            }).finally(() => {
                activeSavesCount--;
                updateActiveSavesIndicator();
            });
        }

        refreshNtcState();
    };

    window.refreshNtcState = function () {
        const inputs = Array.from(document.querySelectorAll('input[id^="ntc-status-input-"]'));
        const total = inputs.length;

        const approved = inputs.filter(i => i.value === 'approved').length;
        const awaitingUpdate = inputs.filter(i => {
            const hasFile = i.getAttribute('data-has-file');
            if (hasFile !== null) {
                return hasFile === 'false';
            }
            return i.getAttribute('data-db-status') === 'rejected' || i.getAttribute('data-db-status') === 'returned';
        }).length;

        const rejected = inputs.filter(i => {
            const hasFile = i.getAttribute('data-has-file');
            if (hasFile !== null) {
                return hasFile === 'true' && i.value === 'rejected';
            }
            return i.value === 'rejected';
        }).length;

        const pending = inputs.filter(i => {
            const hasFile = i.getAttribute('data-has-file');
            if (hasFile !== null) {
                return hasFile === 'true' && i.value !== 'approved' && i.value !== 'rejected';
            }
            return i.value !== 'approved' && i.value !== 'rejected' && i.getAttribute('data-db-status') !== 'rejected' && i.getAttribute('data-db-status') !== 'returned';
        }).length;

        const progressEl = document.getElementById('ntc-docs-progress');
        if (progressEl) {
            progressEl.textContent = `${approved} / ${total} Accepted`;
        }

        const btn = document.getElementById('btn-ntc-submit');
        const btnText = document.getElementById('btn-ntc-text');
        if (!btn) return;

        if (rejected > 0) {
            btn.disabled = false;
            btn.className = 'btn btn-danger btn-sm fw-semibold px-4';
            btn.style.cssText = 'border-radius:6px;';
            btn.setAttribute('data-bs-toggle', 'modal');
            btn.setAttribute('data-bs-target', '#ntcRejectionConfirmModal');
            btn.onclick = null;
            if (btnText) btnText.textContent = `Send Rejection Email (${rejected} rejected)`;
        } else if (pending > 0 || awaitingUpdate > 0) {
            btn.disabled = true;
            btn.className = 'btn btn-outline-secondary btn-sm fw-semibold px-4';
            btn.style.cssText = 'border-radius:6px;';
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.onclick = null;
            if (btnText) {
                if (awaitingUpdate > 0 && pending === 0) {
                    btnText.textContent = `Awaiting Applicant Re-upload (${awaitingUpdate} remaining)`;
                } else {
                    btnText.textContent = `Pending Documents (${pending + awaitingUpdate} remaining)`;
                }
            }
        } else {
            btn.disabled = false;
            btn.className = 'btn btn-success btn-sm fw-semibold px-4';
            btn.style.cssText = 'border-radius:6px;';
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.onclick = submitNtcApproved;
            if (btnText) btnText.textContent = 'Acknowledge Notice to Conduct';
        }
    };

    window.submitNtcApproved = function () {
        const btn = document.getElementById('btn-ntc-submit');
        const btnText = document.getElementById('btn-ntc-text');
        if (btn) {
            btn.disabled = true;
            if (btnText) btnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing…';
        }

        submitNtcForm();
    };

    window.submitNtcRejection = function () {
        const modalEl = document.getElementById('ntcRejectionConfirmModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        const btn = document.getElementById('btn-ntc-submit');
        const btnText = document.getElementById('btn-ntc-text');
        if (btn) {
            btn.disabled = true;
            if (btnText) btnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending Email…';
        }

        submitNtcForm();
    };

    function submitNtcForm() {
        const form = document.getElementById('ntc-evaluation-form');
        if (!form) return;

        const url = form.getAttribute('data-url');

        // Map evaluations array into expected JSON structure
        const json = { evaluations: [] };
        const inputs = form.querySelectorAll('input[id^="ntc-status-input-"]');
        inputs.forEach(input => {
            const id = input.id.replace('ntc-status-input-', '');
            const status = input.value;
            const remarks = document.getElementById('ntc-remarks-' + id)?.value ?? '';
            json.evaluations.push({ id, status, remarks });
        });

        const csrfToken = window.ARMS?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(json),
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Evaluation failed');

            if (data.has_rejections) {
                showToast(data.message || 'Rejection email sent!', 'success');
            } else {
                showToast(data.message || 'Evaluation saved successfully.', 'success');
            }

            setTimeout(() => {
                window.location.reload();
            }, 1000);
        })
        .catch(err => {
            console.error(err);
            showToast(err.message || 'Something went wrong. Please try again.', 'danger');
            refreshNtcState();
        });
    }

    // Modal populate listener for NTC Rejection
    const ntcRejectionModalEl = document.getElementById('ntcRejectionConfirmModal');
    if (ntcRejectionModalEl) {
        ntcRejectionModalEl.addEventListener('show.bs.modal', function () {
            const list = document.getElementById('ntc-rejection-doc-list');
            if (list) {
                list.innerHTML = '';
                document.querySelectorAll('input[id^="ntc-status-input-"]').forEach(input => {
                    if (input.value !== 'rejected') return;

                    const docId = input.id.replace('ntc-status-input-', '');
                    const row = document.getElementById('ntc-doc-row-' + docId);
                    const nameEl = row?.querySelector('.ntc-doc-name');
                    const remarks = document.getElementById('ntc-remarks-' + docId)?.value?.trim() ?? '';

                    // Get document type name, stripping any metadata details
                    let docName = 'Document #' + docId;
                    if (nameEl) {
                        const clone = nameEl.cloneNode(true);
                        const meta = clone.querySelector('.ntc-doc-meta');
                        if (meta) clone.removeChild(meta);
                        const bubble = clone.querySelector('div');
                        if (bubble) clone.removeChild(bubble);
                        docName = clone.textContent.replace(/[\n\r]/g, '').trim();
                    }

                    const item = document.createElement('div');
                    item.style.cssText = 'background:#fff;border-radius:8px;border:1px solid #f5c6cb;padding:10px 14px;';
                    item.innerHTML = `
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-file-earmark-x text-danger"></i>
                            <span class="fw-semibold" style="font-size:.88rem;color:#2A3F54;">${docName}</span>
                        </div>
                        ${remarks
                            ? `<div class="text-muted" style="font-size:.8rem;padding-left:22px;">
                                   <i class="bi bi-chat-left-text me-1"></i>${remarks}
                               </div>`
                            : `<div class="text-muted fst-italic" style="font-size:.78rem;padding-left:22px;">No remarks provided</div>`
                        }
                    `;
                    list.appendChild(item);
                });
            }
        });
    }

    // Always run refreshState on page load so the evaluation button
    // is correctly initialised regardless of approval/schedule state.
    refreshState();
    if (document.getElementById('btn-ntc-submit')) {
        refreshNtcState();
    }

})();
