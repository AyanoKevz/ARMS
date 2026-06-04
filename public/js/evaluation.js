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
        const btn = document.getElementById('btn-open-schedule');
        const indicator = document.getElementById('eval-saving-indicator');

        if (activeSavesCount > 0) {
            if (!indicator) {
                const container = document.getElementById('btn-open-schedule')?.parentNode || document.body;
                const indEl = document.createElement('div');
                indEl.id = 'eval-saving-indicator';
                indEl.className = 'text-warning fw-semibold d-flex align-items-center gap-2 mt-2 justify-content-center';
                indEl.style.fontSize = '.9rem';
                indEl.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving evaluation in background... Please do not refresh.';
                if (container === document.body) {
                    indEl.style.cssText = 'position:fixed; bottom:80px; right:28px; z-index:9998; background:rgba(0,0,0,0.8); color:#ffc107; padding:10px 18px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15);';
                }
                container.appendChild(indEl);
            } else {
                indicator.style.display = 'flex';
            }

            if (btn) {
                btn.disabled = true;
                const btnText = document.getElementById('btn-schedule-text');
                if (btnText) {
                    btnText.textContent = 'Saving Evaluation...';
                }
            }
        } else {
            if (indicator) {
                indicator.style.display = 'none';
            }
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
        
        if (venueInput) {
            venueInput.disabled = false; // Always enabled now
            if (isF2F) {
                venueInput.placeholder = 'Enter venue address';
                venueInput.value = 'Occupational Safety And Health Center';
            } else if (isOnline) {
                venueInput.placeholder = 'Enter meeting link (e.g. Zoom, Google Meet)';
                if (venueInput.value === 'Occupational Safety And Health Center') {
                    venueInput.value = '';
                }
            } else {
                venueInput.placeholder = 'Venue / meeting link';
            }
        }

        if (venueNote) {
            if (isOnline) {
                venueNote.textContent = '(Meeting Link)';
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

    // Wire auto-save for rejection remarks on change / blur
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('reject-remarks-input')) {
            const ta = e.target;
            const docId = ta.id.replace('remarks-', '');
            
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

            activeSavesCount++;
            updateActiveSavesIndicator();

            if (window.ARMS && window.ARMS.evaluateItemUrl) {
                const formData = new FormData();
                formData.append('_token', window.ARMS.csrfToken);
                formData.append('item_type', itemType);
                formData.append('item_id', itemId);
                formData.append('status', status);
                formData.append('remarks', ta.value);

                fetch(window.ARMS.evaluateItemUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                }).then(async (res) => {
                    const data = await res.json();
                    if (data.success) {
                        showToast('Rejection remarks auto-saved.', 'success');
                    } else {
                        showToast('Failed to auto-save remarks.', 'danger');
                    }
                }).catch(err => {
                    console.error('Auto-save remarks error:', err);
                    showToast('Network error during remarks auto-save.', 'danger');
                }).finally(() => {
                    activeSavesCount--;
                    updateActiveSavesIndicator();
                });
            } else {
                activeSavesCount--;
                updateActiveSavesIndicator();
            }
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (activeSavesCount > 0) {
            e.preventDefault();
            e.returnValue = 'Evaluation updates are still saving in the background. Are you sure you want to leave?';
            return e.returnValue;
        }
    });

    /* ─── Init ────────────────────────────────────────────── */
    if ((allApproved || isScheduled) && !window.ARMS?.hasPendingUpdate && window.ARMS?.canUpdateSchedule) {
        // Already all approved or scheduled — enable button directly
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
    } else {
        refreshState();
    }

})();
