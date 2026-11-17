/* ═══════════════════════════════════════════════════════════════════════════
   PRACTITIONER REGISTRATION — only activates on /register

   Everything the OSH Practitioner branch of the registration form needs:

     • Section switching between the Practitioner and FATPro halves of the form
     • The ten repeatable CV sections (clone-a-template, same __IDX__ scheme
       landing.js uses for instructor cards)
     • Derived fields — total workforce size, length of service
     • "Review Details": the AF-PCN-A1 application form with its Page 1 / 2 /
       3 / Full tabs, published to landing.js as
       window.ARMS.renderPractitionerReview

   Loaded from @push('scripts'), which the landing layout stacks ABOVE
   landing.js. Both are deferred, so this file's window.ARMS hooks exist by
   the time landing.js's Review button can call them.

   Also loaded on the tracking page, where the same sections come back filled
   in for an applicant correcting a rejected application form
   (landing/partials/practitioner/correction.blade.php). There is no type to
   switch and nothing to preview there — only the repeaters and the derived
   fields run, on cards the server already rendered.

   And on the practitioner portal's renewal form (applicant/practitioner/
   renewal), the same pre-filled way, plus its Review Details: the AF-PCR-A2
   posted from that page's own form (practitioner-renewal.js calls
   window.ARMS.renderPractitionerReview). Its locked steps (data-locked)
   keep the rows on file: no blank cards are added and nothing is removable.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const pracSections = document.getElementById('practitionerSections');
    if (!pracSections) return; /* neither the register page nor a correction */

    const registerForm = document.getElementById('registerForm');
    const correcting   = !registerForm;
    /* The form Review Details posts: registration's, or the renewal page's. */
    const hostForm     = registerForm || pracSections.closest('form');

    /* OSH Practitioner and OSH Consultant fill in the same AF-PCN-A1 form;
       only the parts marked data-prac-for="<type id>" (the documents to
       upload, the photo background, the notice wording) differ. */
    const PRACTITIONER_FORM_IDS = [1, 2];
    const INDIVIDUAL_IDS        = [1, 2];   /* 1 = Practitioners, 2 = Consultant */

    const selectType   = document.getElementById('accreditation_type');
    const pracNotice   = document.getElementById('practitionerNotice');
    const fatproNotice = document.getElementById('fatproRequirementsNotice');
    const fatproFields = document.getElementById('fatproSections');
    const reviewCard   = document.querySelector('#reviewContent')?.closest('.card');

    if (!correcting && !selectType) return;

    let practitionerActive = correcting;

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
        practitionerActive = PRACTITIONER_FORM_IDS.includes(val);

        setActive(pracSections, practitionerActive);
        if (pracNotice) pracNotice.classList.toggle('d-none', !practitionerActive);

        // After the section itself, so the other type's uploads stay disabled.
        document.querySelectorAll('[data-prac-for]').forEach(function (el) {
            setActive(el, practitionerActive && parseInt(el.dataset.pracFor, 10) === val);
        });

        // FATPro steps belong to the organization types only.
        setActive(fatproFields, !isIndiv);
        if (fatproNotice) fatproNotice.classList.toggle('d-none', isIndiv);

        if (practitionerActive) {
            ensureCards();
            syncIndustryOther();
            syncDeclarationDetails();
            syncWorkforceTotal();

            // The shared collapse logic folds every .doc-type-section but the
            // first, which for a practitioner means their only upload group
            // starts closed. Open it. (expandSection comes from landing.js,
            // which has loaded by the time a change event can fire.)
            const docSection = pracSections.querySelector('[data-prac-for="' + val + '"] .doc-type-section');
            if (docSection && window.ARMS && window.ARMS.expandSection) {
                window.ARMS.expandSection(docSection);
            }
        }

        // The AF-PCN-A1 viewer brings its own frame; drop the summary card's.
        if (reviewCard) reviewCard.classList.toggle('prac-review-card', practitionerActive);
    }

    if (!correcting) selectType.addEventListener('change', syncType);

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
            // A renewal step the AF-PCR-A2 does not ask: the rows on file only.
            locked:    section.dataset.locked === '1',
            // Never reused, even after a removal, so two live cards can never
            // collide on practitioner[key][n][field]. Starts after any cards
            // the server rendered pre-filled.
            nextIndex: parseInt(section.dataset.nextIndex, 10) || 0
        };

        if (repeaters[key].addBtn) {
            repeaters[key].addBtn.addEventListener('click', function () {
                addCard(key);
            });
        }

        repeaters[key].container.querySelectorAll('.prac-card').forEach(function (card) {
            wireRemove(card, key);
        });
        relabel(key);
    });

    function wireRemove(card, key) {
        const removeBtn = card.querySelector('.prac-remove-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                card.remove();
                relabel(key);
            });
        }
    }

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
            if (removeBtn) removeBtn.classList.toggle('d-none', rep.locked || cards.length <= rep.min);
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
        wireRemove(card, key);

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
            if (rep.locked) return;
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

    /**
     * Step 13: open a declaration's "give details" box while one of its
     * questions is answered Yes. A closed box is not required, so it cannot
     * block the Review button; what was typed stays, in case the answer goes
     * back to Yes (the server drops it if it stays No).
     */
    function syncDeclarationDetails() {
        pracSections.querySelectorAll('[data-prac-decl-details]').forEach(function (wrapper) {
            const yes = wrapper.dataset.pracDeclDetails.split(' ').some(function (question) {
                return !!pracSections.querySelector(
                    'input[name="practitioner[declarations][' + question + ']"][value="1"]:checked'
                );
            });

            wrapper.hidden = !yes;
            const field = wrapper.querySelector('textarea');
            if (field) field.required = yes;
        });
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
        if (e.target.matches('input[name^="practitioner[declarations]"]')) syncDeclarationDetails();

        if (e.target.matches('[data-service-from], [data-service-to]')) {
            const card = e.target.closest('.prac-card');
            if (card) syncServiceLength(card);
        }
    });

    /* ───────────────────────────────────────────────────────────────────────
       Review Details → the AF-PCN-A1 application form

       The server renders the form from what has been typed so far, through
       the same Blade partials that produce the PDF attached on submission —
       so what the applicant reviews is exactly what the Accreditation admins
       receive. Files are not sent: the ID photo is dropped into the photo box
       here, and the uploaded documents are listed under the form.
       ─────────────────────────────────────────────────────────────────────── */

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /** Everything in the form except files and passwords. */
    function previewPayload() {
        const data = new FormData();
        new FormData(hostForm).forEach(function (value, key) {
            if (value instanceof File) return;
            if (key === 'password' || key === 'password_confirmation') return;
            data.append(key, value);
        });
        return data;
    }

    function documentsHtml() {
        let items = '';
        pracSections.querySelectorAll('[data-prac-document]').forEach(function (field) {
            const input = field.querySelector('input[type="file"]');
            /* The other type's checklist, or a renewal item not asked for. */
            if (input && input.disabled) return;
            const file  = input && input.files && input.files.length ? input.files[0].name : null;
            /* A renewal's certificate already on file: nothing was uploaded. */
            const onFile = field.dataset.onFile;

            items += '<li>'
                  +    '<span>' + escapeHtml(field.dataset.label || '') + '</span>'
                  +    (file
                        ? '<span class="af-doc-name"><i class="bi bi-paperclip me-1"></i>' + escapeHtml(file) + '</span>'
                        : onFile
                            ? '<span class="af-doc-name"><i class="bi bi-check2-circle me-1"></i>' + escapeHtml(onFile) + '</span>'
                            : '<span class="af-doc-missing">Not uploaded (optional)</span>')
                  +  '</li>';
        });

        return '<div class="af-documents">'
             +    '<h6 class="af-documents-title"><i class="bi bi-folder-fill me-2"></i>Submitted Documentary Requirements</h6>'
             +    '<ul class="af-documents-list">' + items + '</ul>'
             +  '</div>';
    }

    let photoUrl = null;

    /** Put the chosen 1"x1" photo into every page's photo box. */
    function fillPhoto(container) {
        const input = pracSections.querySelector('[data-prac-photo-input]');
        const file  = input && input.files && input.files.length ? input.files[0] : null;
        if (!file) return;

        if (photoUrl) URL.revokeObjectURL(photoUrl);
        photoUrl = URL.createObjectURL(file);

        container.querySelectorAll('img[data-af-photo]').forEach(function (img) {
            img.src = photoUrl;
            img.hidden = false;
        });
        container.querySelectorAll('[data-af-photo-placeholder]').forEach(function (el) {
            el.hidden = true;
        });
    }

    /** Render the form into the review panel. Resolves once it is on screen. */
    function renderPractitionerReview(container) {
        return fetch(pracSections.dataset.previewUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: previewPayload()
        })
            .then(function (res) {
                if (!res.ok) throw new Error('preview ' + res.status);
                return res.json();
            })
            .then(function (data) {
                container.innerHTML = data.html + documentsHtml();
                fillPhoto(container);
            });
    }

    /* Page 1 / 2 / 3 / Full tabs. Delegated: the viewer is re-rendered on
       every review. */
    document.addEventListener('click', function (e) {
        const tab = e.target.closest('[data-af-tab]');
        if (!tab) return;

        const viewer = tab.closest('[data-af-viewer]');
        if (!viewer) return;

        viewer.dataset.afShow = tab.dataset.afTab;
        viewer.querySelectorAll('[data-af-tab]').forEach(function (t) {
            t.classList.toggle('is-active', t === tab);
        });
    });

    window.ARMS = window.ARMS || {};
    window.ARMS.isPractitionerActive = function () { return practitionerActive; };
    window.ARMS.renderPractitionerReview = renderPractitionerReview;

    if (correcting) {
        // Pre-filled: top up any section below its starting card count and
        // bring the conditional and derived fields in line with the answers.
        ensureCards();
        syncIndustryOther();
        syncDeclarationDetails();
        syncWorkforceTotal();
        pracSections.querySelectorAll('[data-service-length]').forEach(function (field) {
            const card = field.closest('.prac-card');
            if (card && !field.value) syncServiceLength(card);
        });

        // The tracking page's own script names only its PDF uploads.
        const photo = pracSections.querySelector('[data-prac-photo-input]');
        if (photo) {
            photo.addEventListener('change', function () {
                const label = photo.closest('.file-upload-wrapper')?.querySelector('.file-name-text');
                if (!label) return;
                const file = photo.files && photo.files.length ? photo.files[0].name : null;
                label.textContent = file || 'No file chosen';
                label.classList.toggle('text-muted', !file);
                label.classList.toggle('text-success', !!file);
            });
        }
        return;
    }

    // The select may already hold a value on a bfcache restore.
    syncType();
})();
