/* ═══════════════════════════════════════════════════════════════════════════
   PRACTITIONER REGISTRATION — only activates on /register

   Everything the OSH Practitioner branch of the registration form needs:

     • Section switching between the Practitioner and FATPro halves of the form
     • The ten repeatable CV sections (clone-a-template, same __IDX__ scheme
       landing.js uses for instructor cards)
     • Derived fields — total workforce size, length of service
     • The Step 7 review summary, published to landing.js as
       window.ARMS.buildPractitionerSummary
     • A submit guard: the form renders and reviews, but does not submit yet

   Loaded from @push('scripts'), which the landing layout stacks ABOVE
   landing.js. Both are deferred, so this file's listeners are registered first
   and the submit guard runs before landing.js's submit handler.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return; /* not on the register page — bail out */

    const PRACTITIONER_ID = 1;
    const INDIVIDUAL_IDS  = [1, 2];   /* 1 = Practitioners, 2 = Consultant */

    const selectType   = document.getElementById('accreditation_type');
    const pracNotice   = document.getElementById('practitionerNotice');
    const pracSections = document.getElementById('practitionerSections');
    const fatproNotice = document.getElementById('fatproRequirementsNotice');
    const fatproFields = document.getElementById('fatproSections');
    const submitBtn    = document.getElementById('submitBtn');

    if (!selectType || !pracSections) return;

    let practitionerActive = false;

    /* ───────────────────────────────────────────────────────────────────────
       Section activation

       Hiding a branch is not enough. A `required` input inside a display:none
       container still fails checkValidity(), and the browser cannot focus it to
       explain why — the Review button would simply do nothing. Disabling the
       controls takes them out of constraint validation AND out of the submitted
       FormData, so the inactive branch is genuinely inert.
       ─────────────────────────────────────────────────────────────────────── */
    function setActive(root, active) {
        if (!root) return;

        root.classList.toggle('d-none', !active);

        // querySelectorAll does not descend into <template> content, so the
        // repeater templates keep their pristine __IDX__ markup either way.
        root.querySelectorAll('input, select, textarea, button').forEach(function (el) {
            if (active) {
                // Only re-enable what this function disabled; anything disabled
                // for its own reasons (a conditional field) stays that way.
                if (el.dataset.pracDisabled === '1') {
                    el.disabled = false;
                    delete el.dataset.pracDisabled;
                }
            } else if (!el.disabled) {
                el.disabled = true;
                el.dataset.pracDisabled = '1';
            }
        });
    }

    function syncType() {
        const val        = parseInt(selectType.value, 10);
        const isIndiv    = INDIVIDUAL_IDS.includes(val);
        practitionerActive = val === PRACTITIONER_ID;

        setActive(pracSections, practitionerActive);
        if (pracNotice) pracNotice.classList.toggle('d-none', !practitionerActive);

        // FATPro steps belong to the organization types only.
        setActive(fatproFields, !isIndiv);
        if (fatproNotice) fatproNotice.classList.toggle('d-none', isIndiv);

        if (practitionerActive) {
            ensureCards();
            syncIndustryOther();
            syncWorkforceTotal();

            // The shared collapse logic folds every .doc-type-section but the
            // first, which for a practitioner means their only upload group
            // starts closed. Open it. (expandSection comes from landing.js,
            // which has loaded by the time a change event can fire.)
            const docSection = pracSections.querySelector('.doc-type-section');
            if (docSection && window.ARMS && window.ARMS.expandSection) {
                window.ARMS.expandSection(docSection);
            }
        }

        // Submission stays closed for practitioners until the controller can
        // actually store the CV sections.
        if (submitBtn) {
            submitBtn.disabled = practitionerActive;
            submitBtn.title    = practitionerActive
                ? 'Online submission for Practitioner accreditation is not open yet.'
                : '';
        }
    }

    selectType.addEventListener('change', syncType);

    /* ───────────────────────────────────────────────────────────────────────
       Repeatable CV sections
       ─────────────────────────────────────────────────────────────────────── */
    const repeaters = {};

    pracSections.querySelectorAll('[data-repeater]').forEach(function (section) {
        const key = section.dataset.repeater;
        repeaters[key] = {
            section:   section,
            template:  pracSections.querySelector('[data-repeater-template="' + key + '"]'),
            container: section.querySelector('[data-repeater-cards="' + key + '"]'),
            addBtn:    section.querySelector('[data-repeater-add="' + key + '"]'),
            itemLabel: section.dataset.itemLabel || 'Entry',
            initial:   parseInt(section.dataset.initial, 10) || 1,
            min:       parseInt(section.dataset.min, 10) || 1,
            // Never reused, even after a removal, so two live cards can never
            // collide on practitioner[key][n][field].
            nextIndex: 0
        };

        if (repeaters[key].addBtn) {
            repeaters[key].addBtn.addEventListener('click', function () {
                addCard(key);
            });
        }
    });

    /** Rewrite __IDX__ in name/id/for on a cloned card and its descendants. */
    function reindex(el, idx) {
        const attrs = ['name', 'id', 'for'];
        const apply = function (node) {
            attrs.forEach(function (attr) {
                if (node.hasAttribute && node.hasAttribute(attr)) {
                    node.setAttribute(attr, node.getAttribute(attr).replace(/__IDX__/g, idx));
                }
            });
        };
        apply(el);
        el.querySelectorAll('[name],[id],[for]').forEach(apply);
    }

    /** Renumber the visible cards and show Remove only above the minimum. */
    function relabel(key) {
        const rep   = repeaters[key];
        const cards = rep.container.querySelectorAll('.prac-card');
        cards.forEach(function (card, i) {
            const label = card.querySelector('.prac-card-label');
            if (label) label.textContent = rep.itemLabel + ' #' + (i + 1);

            const removeBtn = card.querySelector('.prac-remove-btn');
            if (removeBtn) removeBtn.classList.toggle('d-none', cards.length <= rep.min);
        });
    }

    function addCard(key) {
        const rep = repeaters[key];
        if (!rep || !rep.template) return null;

        const source = rep.template.content
            ? rep.template.content.querySelector('.prac-card')
            : rep.template.querySelector('.prac-card');
        if (!source) return null;

        const card = source.cloneNode(true);
        reindex(card, rep.nextIndex++);

        const removeBtn = card.querySelector('.prac-remove-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                card.remove();
                relabel(key);
            });
        }

        rep.container.appendChild(card);
        relabel(key);

        // Cloned controls arrive after syncType() ran, so an inactive branch
        // would otherwise gain live inputs.
        if (!practitionerActive) {
            card.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                if (!el.disabled) {
                    el.disabled = true;
                    el.dataset.pracDisabled = '1';
                }
            });
        }

        return card;
    }

    /** Bring every repeater up to its configured starting card count. */
    function ensureCards() {
        Object.keys(repeaters).forEach(function (key) {
            const rep = repeaters[key];
            while (rep.container.querySelectorAll('.prac-card').length < rep.initial) {
                addCard(key);
            }
        });
    }

    /* ───────────────────────────────────────────────────────────────────────
       Derived and conditional fields
       ─────────────────────────────────────────────────────────────────────── */

    /** Total Workforce Size = male + female, kept read-only. */
    function syncWorkforceTotal() {
        const total = pracSections.querySelector('[data-workforce-total]');
        if (!total) return;

        const counts = pracSections.querySelectorAll('[data-workforce-count]');
        let sum = 0;
        let anyEntered = false;

        counts.forEach(function (input) {
            if (input.value === '') return;
            const n = parseInt(input.value, 10);
            if (!isNaN(n)) {
                sum += n;
                anyEntered = true;
            }
        });

        total.value = anyEntered ? String(sum) : '';
    }

    /**
     * "3 yrs, 2 mos" from an inclusive date range. An open-ended range is
     * measured to today and flagged, so a current post does not read as if the
     * applicant left this month.
     */
    function lengthOfService(fromVal, toVal) {
        if (!fromVal) return '';

        const from = new Date(fromVal);
        const to   = toVal ? new Date(toVal) : new Date();
        if (isNaN(from.getTime()) || isNaN(to.getTime()) || to < from) return '';

        let months = (to.getFullYear() - from.getFullYear()) * 12 + (to.getMonth() - from.getMonth());
        if (to.getDate() < from.getDate()) months--;
        if (months < 0) months = 0;

        const years = Math.floor(months / 12);
        const rem   = months % 12;
        const parts = [];

        if (years) parts.push(years + (years === 1 ? ' yr' : ' yrs'));
        if (rem)   parts.push(rem + (rem === 1 ? ' mo' : ' mos'));
        if (!parts.length) parts.push('Less than a month');

        return parts.join(', ') + (toVal ? '' : ' (present)');
    }

    function syncServiceLength(card) {
        const from   = card.querySelector('[data-service-from]');
        const to     = card.querySelector('[data-service-to]');
        const target = card.querySelector('[data-service-length]');
        if (!from || !target) return;

        target.value = lengthOfService(from.value, to ? to.value : '');
    }

    /** Reveal the free-text industry field only for the "Other" choice. */
    function syncIndustryOther() {
        const select  = pracSections.querySelector('[data-industry-select]');
        const wrapper = document.getElementById('pracIndustryOtherWrap');
        if (!select || !wrapper) return;

        const isOther = select.value === 'Other';
        const input   = wrapper.querySelector('input');

        wrapper.hidden = !isOther;
        if (!input) return;

        input.required = isOther;
        if (!isOther) input.value = '';
    }

    pracSections.addEventListener('input', function (e) {
        if (e.target.matches('[data-workforce-count]')) syncWorkforceTotal();

        if (e.target.matches('[data-service-from], [data-service-to]')) {
            const card = e.target.closest('.prac-card');
            if (card) syncServiceLength(card);
        }
    });

    pracSections.addEventListener('change', function (e) {
        if (e.target.matches('[data-industry-select]')) syncIndustryOther();

        if (e.target.matches('[data-service-from], [data-service-to]')) {
            const card = e.target.closest('.prac-card');
            if (card) syncServiceLength(card);
        }
    });

    /* ───────────────────────────────────────────────────────────────────────
       Review summary

       landing.js owns the review panel and calls this when the selected type is
       an individual one. Reading the rendered DOM rather than a parallel data
       model means a field added to the blade shows up in the review with no
       change here — the only contract is .prac-field[data-label].
       ─────────────────────────────────────────────────────────────────────── */

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /** The displayable value of one .prac-field, or "N/A" when left blank. */
    function fieldValue(field) {
        const file = field.querySelector('input[type="file"]');
        if (file) {
            return (file.files && file.files.length) ? file.files[0].name : null;
        }

        const radios = field.querySelectorAll('input[type="radio"]');
        if (radios.length) {
            const checked = field.querySelector('input[type="radio"]:checked');
            return checked ? (checked.value === '1' ? 'Yes' : 'No') : null;
        }

        const input = field.querySelector('input, select, textarea');
        if (!input) return null;

        const value = (input.value || '').trim();
        return value === '' ? null : value;
    }

    function fieldRows(scope) {
        let rows = '';
        scope.querySelectorAll('.prac-field').forEach(function (field) {
            const label = field.dataset.label || '';
            const value = fieldValue(field);
            const isFile = !!field.querySelector('input[type="file"]');

            const rendered = value === null
                ? '<span class="text-muted fst-italic">N/A</span>'
                : (isFile
                    ? '<i class="bi bi-paperclip me-1"></i>' + escapeHtml(value)
                    : escapeHtml(value));

            rows += '<tr>'
                 +  '<td class="text-muted ps-0 py-1" style="width:42%;background:transparent;">' + escapeHtml(label) + '</td>'
                 +  '<td class="fw-semibold text-dark py-1" style="background:transparent;">' + rendered + '</td>'
                 +  '</tr>';
        });
        return rows;
    }

    function groupCard(title, innerHtml) {
        if (!innerHtml) return '';
        return '<div class="prac-summary-group mb-3">'
             +    '<div class="prac-summary-title">' + escapeHtml(title) + '</div>'
             +    innerHtml
             +  '</div>';
    }

    function tableFor(scope) {
        const rows = fieldRows(scope);
        if (!rows) return '';
        return '<table class="table table-sm table-borderless mb-0 prac-summary-table">'
             +    '<tbody>' + rows + '</tbody>'
             +  '</table>';
    }

    function buildPractitionerSummary() {
        if (!practitionerActive) return '';

        let html = '<div class="mt-4 pt-3 border-top">'
                 + '<h5 class="fw-bold text-primary mb-3" style="font-size:1.1rem;">'
                 + '<i class="bi bi-person-vcard-fill me-2"></i>OSH Practitioner Details</h5>';

        // Submission is blocked, so say it here too — the review panel is the
        // last thing the applicant reads before looking for a Submit button.
        html += '<div class="prac-blocked-notice mb-3">'
             +    '<i class="bi bi-cone-striped me-2"></i>'
             +    '<span><strong>Online submission is not yet open for this accreditation type.</strong> '
             +    'Your entries are shown here for review only and are not saved.</span>'
             +  '</div>';

        let heading = 'Details';

        Array.prototype.forEach.call(pracSections.children, function (node) {
            if (node.id === 'pracSubmissionNotice') return;

            if (node.matches('p.form-section-title, p.prac-subheading')) {
                heading = (node.textContent || '').trim();
                return;
            }

            // A repeatable section: one table per card.
            if (node.matches('[data-repeater]')) {
                const rep   = repeaters[node.dataset.repeater];
                const cards = node.querySelectorAll('.prac-card');
                let   inner = '';

                cards.forEach(function (card, i) {
                    const table = tableFor(card);
                    if (!table) return;
                    inner += '<div class="prac-summary-card">'
                          +    '<div class="prac-summary-card-label">'
                          +      escapeHtml((rep ? rep.itemLabel : 'Entry') + ' #' + (i + 1))
                          +    '</div>'
                          +    table
                          +  '</div>';
                });

                html += groupCard(node.dataset.sectionTitle || heading, inner);
                return;
            }

            // Declarations and the documentary checklist are plain blocks.
            const title = node.dataset ? (node.dataset.pracSummaryGroup || heading) : heading;
            html += groupCard(title, tableFor(node));
        });

        return html + '</div>';
    }

    window.ARMS = window.ARMS || {};
    window.ARMS.buildPractitionerSummary = buildPractitionerSummary;

    /* ───────────────────────────────────────────────────────────────────────
       Submit guard

       The practitioner branch is display-only: RegistrationController has no
       branch for it, so letting a submission through would create a user with
       an empty profile and silently drop the whole CV.
       ─────────────────────────────────────────────────────────────────────── */
    registerForm.addEventListener('submit', function (e) {
        if (!practitionerActive) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        const message = 'Online submission for <strong>OSH Practitioner</strong> accreditation is not open yet. '
                      + 'You can review your entries, but they cannot be submitted.';

        if (window.ARMS && window.ARMS.showToast) {
            window.ARMS.showToast(message, 'warning', 6000);
        }

        const alertEl  = document.getElementById('dynamicAlert');
        const alertMsg = document.getElementById('dynamicAlertMessage');
        if (alertEl && alertMsg) {
            alertEl.className = 'alert alert-warning alert-dismissible fade show shadow';
            alertMsg.innerHTML = message;
            alertEl.classList.remove('d-none');
        }
    });

    // The select may already hold a value on a bfcache restore.
    syncType();
})();
