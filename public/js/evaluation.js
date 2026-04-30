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
    const allApproved = window.ARMS?.allApproved ?? false;

    /* ─── Helpers ─────────────────────────────────────────── */
    function getStatusInputs() {
        return Array.from(document.querySelectorAll('input[id^="status-input-"]'));
    }

    /* ─── setDocStatus ────────────────────────────────────── */
    window.setDocStatus = function (docId, status) {
        const statusInput = document.getElementById(`status-input-${docId}`);
        const badge       = document.getElementById(`badge-${docId}`);
        const approveBtn  = document.querySelector(`.btn-approve[data-doc-id="${docId}"]`);
        const rejectBtn   = document.querySelector(`.btn-reject[data-doc-id="${docId}"]`);
        const rejectPanel = document.getElementById(`reject-panel-${docId}`);

        if (!statusInput) return;

        statusInput.value = status;

        // Badge
        badge.classList.remove('doc-badge-approved','doc-badge-rejected','doc-badge-pending','doc-badge-for_revision');
        badge.classList.add(`doc-badge-${status}`);
        badge.textContent = status === 'approved' ? 'Approved' : 'Rejected';

        // Button active states
        approveBtn.classList.toggle('active', status === 'approved');
        rejectBtn.classList.toggle('active',  status === 'rejected');

        // Reject remarks panel
        if (rejectPanel) {
            rejectPanel.style.display = status === 'rejected' ? 'block' : 'none';
            if (status === 'rejected') {
                const ta = document.getElementById(`remarks-${docId}`);
                if (ta) ta.focus();
            }
        }

        refreshState();
    };

    /* ─── refreshState ────────────────────────────────────── */
    function refreshState() {
        const inputs   = getStatusInputs();
        const total    = inputs.length;
        const approved = inputs.filter(i => i.value === 'approved').length;
        const rejected = inputs.filter(i => i.value === 'rejected').length;
        const pending  = total - approved - rejected;

        // Progress label inside Documents card header
        const progressEl = document.getElementById('eval-progress-label');
        if (progressEl) {
            progressEl.textContent = `${approved} approved · ${rejected} rejected · ${pending} pending`;
        }

        const btn      = document.getElementById('btn-open-schedule');
        const btnIcon  = document.getElementById('btn-schedule-icon');
        const btnText  = document.getElementById('btn-schedule-text');
        if (!btn) return;

        if (pending > 0) {
            // ── State 1: Still has unevaluated docs ──
            btn.disabled = true;
            btn.className = 'btn btn-secondary btn-lg fw-bold px-5 py-3 shadow-sm';
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.onclick = null;
            if (btnIcon) btnIcon.className = 'bi bi-hourglass-split me-2 fs-5';
            if (btnText) btnText.textContent = `Pending Documents (${pending} remaining)`;

        } else if (rejected > 0) {
            // ── State 2: Some rejected, none pending → Send Rejection Email ──
            btn.disabled = false;
            btn.className = 'btn btn-danger btn-lg fw-bold px-5 py-3 shadow';
            btn.setAttribute('data-bs-toggle', 'modal');
            btn.setAttribute('data-bs-target', '#rejectionConfirmModal');
            btn.onclick = null;
            if (btnIcon) btnIcon.className = 'bi bi-envelope-fill me-2 fs-5';
            if (btnText) btnText.textContent = `Send Rejection Email (${rejected} rejected)`;

        } else {
            // ── State 3: All approved → Save Approvals & Schedule Interview ──
            btn.disabled = false;
            btn.className = 'btn btn-success btn-lg fw-bold px-5 py-3 shadow';
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.onclick = submitAllApproved;
            if (btnIcon) btnIcon.className = 'bi bi-save me-2 fs-5';
            if (btnText) btnText.textContent = 'Save Approvals and Schedule Interview';
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
                mainBtn.innerHTML = '<i class="bi bi-save me-2 fs-5"></i>Save Approvals and Schedule Interview';
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

            // Set up button to open modal now
            mainBtn.innerHTML = '<i class="bi bi-calendar-check-fill me-2 fs-5"></i>Schedule Interview';
            mainBtn.disabled = false;
            mainBtn.onclick = null;
            mainBtn.setAttribute('data-bs-toggle', 'modal');
            mainBtn.setAttribute('data-bs-target', '#scheduleInterviewModal');
            
            // Auto-open modal
            const modalEl = document.getElementById('scheduleInterviewModal');
            if (window.bootstrap && window.bootstrap.Modal) {
                new window.bootstrap.Modal(modalEl).show();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('show');
            }

        } catch (err) {
            console.error('Approval submission error:', err);
            alert('A network error occurred. Please try again.');
            mainBtn.disabled = false;
            mainBtn.innerHTML = '<i class="bi bi-save me-2 fs-5"></i>Save Approvals and Schedule Interview';
        }
    }

    /* ─── Rejection Modal Population ──────────────────────── */
    const rejectionModalEl = document.getElementById('rejectionConfirmModal');
    if (rejectionModalEl) {
        rejectionModalEl.addEventListener('show.bs.modal', function (e) {
            // Guard: ensure no pending docs
            const pending = getStatusInputs().filter(i => !['approved','rejected'].includes(i.value));
            if (pending.length > 0) {
                e.preventDefault(); // Stop modal from opening
                alert('Please approve or reject every document before submitting.');
                return;
            }

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
        
        if (venueInput) {
            venueInput.disabled    = isOnline;
            venueInput.placeholder = isOnline ? 'N/A – online interview' : 'Enter venue address';
        }

        if (venueNote) {
            venueNote.textContent = isOnline ? '(not required)' : '(F2F only)';
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

    /* ─── Init ────────────────────────────────────────────── */
    if (allApproved || isScheduled) {
        // Already all approved or scheduled — go straight to green button
        const btn     = document.getElementById('btn-open-schedule');
        const btnIcon = document.getElementById('btn-schedule-icon');
        const btnText = document.getElementById('btn-schedule-text');
        if (btn) {
            btn.disabled = false;
            btn.className = 'btn btn-success btn-lg fw-bold px-5 py-3 shadow';
            btn.setAttribute('data-bs-toggle', 'modal');
            btn.setAttribute('data-bs-target', '#scheduleInterviewModal');
            btn.onclick = null;
        }
        if (btnIcon) btnIcon.className = 'bi bi-calendar-check-fill me-2 fs-5';
        if (btnText) btnText.textContent = isScheduled ? 'Update Interview Schedule' : 'Schedule Interview';

        // Hide eval buttons (belt-and-suspenders alongside blade guard)
        document.querySelectorAll('.doc-eval-actions').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.reject-panel').forEach(el => el.style.display = 'none');
    } else {
        refreshState();
    }

})();
