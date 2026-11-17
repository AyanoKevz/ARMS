/* ═══════════════════════════════════════════════════════════════════════════
   ACCREDITATION — OSH Practitioner admin pages

     • Busy state for forms that post and reload (Start Evaluation)
     • Application page: hands its config (#prac-eval-config) to window.ARMS
       for public/js/evaluation.js — the FATPro evaluation script, which does
       the auto-saving Approve / Reject, the live PCT stopwatch, the
       "Send Rejection Email" / "Save Approvals" button and the interview's
       Start (opening the meeting link) / 2-hour auto-stop. Load this file
       before evaluation.js, without defer: evaluation.js reads window.ARMS
       on load.
     • Starts a scheduled interview by itself when its time comes
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* ── Evaluation config ──────────────────────────────────────────────── */
    const config = document.getElementById('prac-eval-config');
    if (config) {
        const d = config.dataset;
        let holidays = [];
        try {
            holidays = JSON.parse(d.holidays || '[]');
        } catch (e) {
            holidays = [];
        }

        window.ARMS = window.ARMS || {};
        Object.assign(window.ARMS, {
            csrfToken:         d.csrf,
            evaluateItemUrl:   d.evaluateItemUrl,
            applicationStatus: d.applicationStatus,
            applicationId:     Number(d.applicationId),
            serverTime:        Number(d.serverTime) || Date.now(),
            activeStep:        Number(d.activeStep) || 0,
            pctStatus:         d.pctStatus || '',
            approvalLabel:     d.approvalLabel,
            holidays:          holidays,
            // The applicant books the interview, so no schedule button here.
            isScheduled:          false,
            skipsInterview:       true,
            hasInterviewRecord:   false,
            canUpdateSchedule:    false,
            hasPendingUpdate:     false,
            isApproved:           false,
            isAccredited:         false,
            // Panel interview: evaluation.js stops it at maxInterviewSeconds
            // and opens the meeting link when Start Interview is pressed.
            interviewTimestampMs: Number(d.interviewTimestamp) || null,
            interviewMode:        d.interviewMode || '',
            interviewVenue:       d.interviewLink || '',
            maxInterviewSeconds:  Number(d.maxInterviewSeconds) || 14400
        });

        watchInterviewStart();
    }

    /**
     * Start the interview by itself when its time comes while the page is
     * open, as pressing Start Interview would (evaluation.js opens the
     * meeting link on that submit). The server does the same on page load.
     */
    function watchInterviewStart() {
        const startForm = document.getElementById('start-interview-form');
        const startsAt  = window.ARMS.interviewTimestampMs;
        if (!startForm || !startsAt || window.ARMS.activeStep !== 5 || window.ARMS.pctStatus !== 'paused') return;

        const offset = (window.ARMS.serverTime || Date.now()) - Date.now();
        const timer  = setInterval(function () {
            if (Date.now() + offset < startsAt) return;

            clearInterval(timer);
            if (typeof startForm.requestSubmit === 'function') {
                startForm.requestSubmit();
            } else {
                startForm.submit();
            }
        }, 5000);
    }

    /* ── Approve / Reject buttons → evaluation.js ───────────────────────── */
    document.addEventListener('click', function (e) {
        const button = e.target.closest('.btn-eval[data-eval-status]');
        if (!button || typeof window.evaluateItem !== 'function') return;

        window.evaluateItem(Number(button.dataset.docId), button.dataset.evalStatus, button);
    });

    /* ── Proof of payment: rejection remarks only when Reject is chosen ─── */
    function syncRemarks(form) {
        form.querySelectorAll('[data-prac-remarks-toggle]').forEach(function (radio) {
            const panel = radio.dataset.pracRemarksToggle && document.getElementById(radio.dataset.pracRemarksToggle);
            if (panel) panel.classList.toggle('is-hidden', !radio.checked);
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-prac-remarks-toggle]')) syncRemarks(e.target.form);
    });

    // Clicking the chosen Approve / Reject again clears it (a radio can't be
    // unchecked by itself). Whether it was already chosen is read before the
    // click checks it.
    document.addEventListener('pointerdown', function (e) {
        const label = e.target.closest('label[for]');
        const radio = label ? document.getElementById(label.htmlFor) : e.target.closest('[data-prac-remarks-toggle]');
        if (radio && radio.matches('[data-prac-remarks-toggle]')) radio.dataset.wasChecked = radio.checked ? '1' : '';
    });

    document.addEventListener('click', function (e) {
        const radio = e.target.closest('[data-prac-remarks-toggle]');
        if (!radio) return;

        const wasChecked = radio.dataset.wasChecked === '1';
        radio.dataset.wasChecked = '';
        if (!wasChecked) return;

        radio.checked = false;
        radio.blur();
        syncRemarks(radio.form);
    });

    /* ── Generate Certificate: the PDF opens in a new tab, the prompt closes ─ */
    document.querySelectorAll('form[data-prac-modal-close]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const dismiss = form.closest('.modal')?.querySelector('[data-bs-dismiss="modal"]');
            if (dismiss) setTimeout(function () { dismiss.click(); }, 0);
        });
    });

    /* ── Busy forms ─────────────────────────────────────────────────────── */
    document.querySelectorAll('form[data-prac-busy-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('[data-prac-busy-label]');
            if (btn) {
                // Disable after the submit is under way so the button's own
                // value is not dropped from the request.
                setTimeout(function () {
                    btn.disabled = true;
                    btn.textContent = btn.dataset.pracBusyLabel;
                }, 0);
            }
        });
    });
})();
