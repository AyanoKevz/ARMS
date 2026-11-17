/* ═══════════════════════════════════════════════════════════════════════════
   PRACTITIONER PORTAL — Renewal of Accreditation
   (resources/views/applicant/practitioner/renewal.blade.php)

     • The chosen file's name on each upload, checked against its type
     • Items 6 – 7 of the checklist (Certificate of Employment, Duties and
       Responsibilities): shown, enabled and required only while "change of
       employer or position" is answered Yes
     • Review Details: the form checked, then the AF-PCR-A2 rendered from it
       by practitioner-form.js (window.ARMS.renderPractitionerReview)

   The form cannot be submitted yet; its Submit button stays disabled.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const form = document.getElementById('practitionerRenewalForm');
    if (!form) return;

    const review     = document.getElementById('renewalReview');
    const reviewBody = review ? review.querySelector('[data-renewal-review-body]') : null;

    /* ── Uploads ─────────────────────────────────────────────────────────── */
    function syncFile(input) {
        const wrapper = input.closest('.file-upload-wrapper');
        const label   = wrapper ? wrapper.querySelector('.file-name-text') : null;
        const file    = input.files && input.files.length ? input.files[0] : null;

        const allowed = (input.dataset.allowedExt || '').split(',').filter(Boolean);
        const ext     = file ? file.name.split('.').pop().toLowerCase() : '';
        input.setCustomValidity(file && allowed.length && !allowed.includes(ext) ? 'Wrong file type' : '');

        if (!label) return;
        label.textContent = file ? file.name : 'No file chosen';
        label.classList.toggle('text-muted', !file);
        label.classList.toggle('text-success', !!file && input.validity.valid);
    }

    /* ── Change of employer or position ─────────────────────────────────── */
    function syncEmployerDocs() {
        const box = form.querySelector('[data-renewal-employer-docs]');
        if (!box) return;

        const yes = !!form.querySelector('[data-employer-changed][value="1"]:checked');
        box.hidden = !yes;
        // Disabled while hidden: not required, and left out of the form.
        box.querySelectorAll('input').forEach(function (input) {
            input.disabled = !yes;
        });
    }

    form.addEventListener('change', function (e) {
        if (e.target.matches('.real-file-input')) syncFile(e.target);
        if (e.target.matches('[data-employer-changed]')) syncEmployerDocs();
    });

    /* ── Review Details ─────────────────────────────────────────────────── */
    function firstInvalid() {
        return Array.prototype.find.call(form.elements, function (el) {
            return el.willValidate && !el.checkValidity();
        });
    }

    function showReview() {
        form.classList.add('was-validated');

        const invalid = firstInvalid();
        if (invalid) {
            const target = invalid.closest('.prac-field') || invalid;
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (invalid.type !== 'file') invalid.focus({ preventScroll: true });
            return;
        }

        if (!review || !reviewBody || !window.ARMS || !window.ARMS.renderPractitionerReview) return;

        review.hidden = false;
        reviewBody.innerHTML = '<div class="pr-review-state"><span class="spinner-border spinner-border-sm me-2"></span>Preparing your renewal application form…</div>';
        review.scrollIntoView({ behavior: 'smooth', block: 'start' });

        window.ARMS.renderPractitionerReview(reviewBody).catch(function () {
            reviewBody.innerHTML = '<div class="pr-review-state text-danger">The form could not be prepared. Please try again.</div>';
        });
    }

    const reviewBtn = form.querySelector('[data-renewal-review]');
    if (reviewBtn) reviewBtn.addEventListener('click', showReview);

    const backBtn = review ? review.querySelector('[data-renewal-back]') : null;
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            review.hidden = true;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // A restored page (back/forward cache) may already hold a Yes.
    syncEmployerDocs();
})();
