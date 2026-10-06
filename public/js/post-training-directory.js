/**
 * Directory of Participants — evaluator controls.
 *
 * Participants are judged individually, but the Directory has one verdict and
 * one set of remarks. This script keeps the section's hidden status input in
 * step with the rows so the shared evaluation.js gating (which counts those
 * inputs) treats the Directory like any other item, and mirrors each click to
 * the server the way document verdicts already are.
 */
(function () {
    'use strict';

    var evaluateBase = '';

    function csrfToken() {
        return (window.ARMS && window.ARMS.csrfToken)
            || (document.querySelector('meta[name="csrf-token"]') || {}).content
            || '';
    }

    function statusInput(id) {
        return document.getElementById('ptr-participant-status-' + id);
    }

    function allStatusInputs() {
        return Array.prototype.slice.call(
            document.querySelectorAll('input[id^="ptr-participant-status-"]')
        );
    }

    /**
     * Give the Directory's section row the verdict its participants imply.
     * The server derives this again on submit — this is only so the evaluator
     * sees the consequence of a click immediately.
     */
    function syncSection() {
        var rows = allStatusInputs();
        if (rows.length === 0) return;

        var values = rows.map(function (input) { return input.value; });
        var anyRejected = values.indexOf('rejected') !== -1;
        var allApproved = values.every(function (v) { return v === 'approved'; });

        var status = anyRejected ? 'rejected' : (allApproved ? 'approved' : 'pending');

        var section = document.querySelector('input[data-ptr-directory]');

        if (section) {
            section.value = status;
            // A declined Directory is waiting on the FATPro, exactly like a
            // declined attachment whose file has been cleared.
            section.setAttribute('data-has-file', status === 'rejected' ? 'false' : 'true');

            var badge = document.getElementById('ntc-badge-' + section.getAttribute('data-doc-id'));
            if (badge) {
                var label = 'Pending';
                var cls = 'ntc-badge-pending';

                if (status === 'approved') { label = 'Accepted'; cls = 'ntc-badge-approved'; }
                else if (status === 'rejected') { label = 'Declined — Awaiting Correction'; cls = 'ntc-badge-rejected'; }

                badge.className = 'badge ' + cls + ' px-2 py-1';
                badge.style.cssText = 'font-size:.75rem;border-radius:20px;white-space:nowrap;';
                badge.textContent = label;
            }
        }

        var panel = document.getElementById('ptr-directory-remarks-panel');
        if (panel) panel.style.display = anyRejected ? 'block' : 'none';

        updateCounts(values);

        if (typeof window.refreshNtcState === 'function') window.refreshNtcState();

    }

    function updateCounts(values) {
        var counts = { approved: 0, rejected: 0, pending: 0 };

        values.forEach(function (v) {
            if (v === 'approved') counts.approved++;
            else if (v === 'rejected') counts.rejected++;
            else counts.pending++;
        });

        setCount('ptrDirApprovedCount', counts.approved);
        setCount('ptrDirRejectedCount', counts.rejected);
        setCount('ptrDirPendingCount', counts.pending);
    }

    function setCount(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(value);
    }

    function setVerdict(id, next) {
        var input = statusInput(id);
        if (!input) return;

        var previous = input.value;
        // Clicking the active verdict again clears it, so a mis-click is undoable.
        var status = previous === next ? 'pending' : next;

        input.value = status;
        paint(id, status);
        syncSection();
        persist(id, status, previous);
    }

    function paint(id, status) {
        var row = document.getElementById('ptr-participant-row-' + id);
        if (row) {
            row.classList.toggle('is-approved', status === 'approved');
            row.classList.toggle('is-rejected', status === 'rejected');
        }

        var wrap = document.querySelector('.ptr-dir-verdict[data-participant="' + id + '"]');
        if (!wrap) return;

        var approve = wrap.querySelector('.ptr-dir-verdict-approve');
        var reject = wrap.querySelector('.ptr-dir-verdict-reject');

        if (approve) approve.classList.toggle('is-active-approve', status === 'approved');
        if (reject) reject.classList.toggle('is-active-reject', status === 'rejected');
    }

    function persist(id, status, previous) {
        if (!evaluateBase) return;

        var body = new FormData();
        body.append('status', status);
        body.append('_token', csrfToken());

        fetch(evaluateBase + '/' + id + '/evaluate', {
            method: 'POST',
            body: body,
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Could not save that verdict.');
                return res.json();
            })
            .catch(function () {
                // Put the row back rather than leaving the screen lying about
                // what the server holds.
                var input = statusInput(id);
                if (input) input.value = previous;
                paint(id, previous);
                syncSection();

                if (typeof window.showToast === 'function') {
                    window.showToast('Could not save that verdict. Please try again.', 'error');
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('input[data-ptr-directory]');
        if (!root) return;

        evaluateBase = root.getAttribute('data-evaluate-base') || '';

        // Delegated, because the table is paged: rows off the current page are
        // detached from the document and would miss a direct binding.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.ptr-dir-verdict button');
            if (!btn) return;

            var id = btn.closest('.ptr-dir-verdict').getAttribute('data-participant');

            if (btn.classList.contains('ptr-dir-verdict-approve')) setVerdict(id, 'approved');
            else if (btn.classList.contains('ptr-dir-verdict-reject')) setVerdict(id, 'rejected');
        });

        syncSection();
    });
})();
