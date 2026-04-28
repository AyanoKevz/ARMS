(function () {
    'use strict';

    /* ─────────────────────────────────────────────────────────
     * Register plugins – guard against missing ScrollSmoother
     * (cdnjs may not carry the club-tier plugin)
     * ───────────────────────────────────────────────────────── */
    const corePlugins = [ScrollTrigger, ScrollToPlugin];

    if (typeof ScrollSmoother !== 'undefined') {
        corePlugins.push(ScrollSmoother);
    }

    gsap.registerPlugin(...corePlugins);

    /* ─────────────────────────────────────────────────────────
     * ScrollSmoother – with full fallback to native scroll
     * ───────────────────────────────────────────────────────── */
    let smoother = null;

    function restoreNativeScroll() {
        /* Undo the CSS-lock so the page can scroll normally */
        document.documentElement.classList.add('no-smoother');
        document.body.style.overflow = '';

        const wrapper = document.getElementById('smooth-wrapper');
        const content = document.getElementById('smooth-content');

        if (wrapper) {
            wrapper.style.cssText =
                'position:relative;overflow:visible;height:auto;width:100%;';
        }
        if (content) {
            content.style.cssText = 'overflow:visible;width:100%;';
        }
    }

    if (typeof ScrollSmoother !== 'undefined') {
        try {
            smoother = ScrollSmoother.create({
                wrapper:     '#smooth-wrapper',
                content:     '#smooth-content',
                smooth:      2,        /* higher = more lag / silkier */
                effects:     true,       /* enables data-speed / data-lag attrs */
                smoothTouch: 0.1,        /* subtle on mobile */
                normalizeScroll: true,   /* prevent browser-native jank */
            });
        } catch (err) {
            console.warn('[ARMS] ScrollSmoother init failed – native scroll restored.', err);
            restoreNativeScroll();
        }
    } else {
        console.warn('[ARMS] ScrollSmoother not loaded – native scroll restored.');
        restoreNativeScroll();
    }

    /* ─────────────────────────────────────────────────────────
     * 1. NAVBAR — transparent → solid on scroll
     * ───────────────────────────────────────────────────────── */
    const navbar = document.querySelector('.navbar-arms');

    ScrollTrigger.create({
        start:       'top -60',
        onEnter:     () => navbar.classList.add('scrolled'),
        onLeaveBack: () => navbar.classList.remove('scrolled'),
    });

    /* ─────────────────────────────────────────────────────────
     * 2. SMOOTH HASH-LINK SCROLLING
     *    Uses smoother.scrollTo() when active, gsap.to() fallback
     * ───────────────────────────────────────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (!target) return;
            e.preventDefault();

            const offset = navbar.offsetHeight;

            if (smoother) {
                smoother.scrollTo(target, true, 'top ' + offset + 'px');
            } else {
                const top = target.getBoundingClientRect().top + window.scrollY - offset;
                gsap.to(window, { scrollTo: { y: top }, duration: 1.1, ease: 'power3.inOut' });
            }
        });
    });

    /* ─────────────────────────────────────────────────────────
     * 3. HERO — entrance animation (page load)
     * ───────────────────────────────────────────────────────── */
    const heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    heroTl
        .from('.hero-eyebrow',  { opacity: 0, y: 28, duration: .7, delay: .2 })
        .from('.hero-title',    { opacity: 0, y: 40, duration: .8 }, '-=.4')
        .from('.hero-lead',     { opacity: 0, y: 28, duration: .7 }, '-=.5')
        .from('.hero-btns > *', { opacity: 0, y: 22, duration: .6, stagger: .15 }, '-=.4');

    /* ─────────────────────────────────────────────────────────
     * 4. STATS band — count-up feel
     * ───────────────────────────────────────────────────────── */
    gsap.from('.stat-item', {
        scrollTrigger: { trigger: '.stats-band', start: 'top 85%' },
        opacity: 0, y: 30, duration: .6, stagger: .1, ease: 'power2.out',
    });

    /* ─────────────────────────────────────────────────────────
     * 5. PORTAL cards — cascade in
     * ───────────────────────────────────────────────────────── */
    gsap.from('.portal-card', {
        scrollTrigger: { trigger: '#portals', start: 'top 80%' },
        opacity: 0, y: 50, duration: .75, stagger: .18, ease: 'power3.out',
    });

    /* ─────────────────────────────────────────────────────────
     * 6. CTA band — slide in from sides
     * ───────────────────────────────────────────────────────── */
    gsap.from('.cta-band .col-lg-7', {
        scrollTrigger: { trigger: '.cta-band', start: 'top 80%' },
        opacity: 0, x: -50, duration: .8, ease: 'power3.out',
    });
    gsap.from('.cta-band .col-lg-5', {
        scrollTrigger: { trigger: '.cta-band', start: 'top 80%' },
        opacity: 0, x: 50, duration: .8, ease: 'power3.out',
    });

})();

/* ═══════════════════════════════════════════════════════════
   LOGIN FORM — only activates on /login
═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return; /* not on login page — bail out */

    /* ── Password toggle ── */
    const toggleBtn  = document.getElementById('toggleLoginPass');
    const toggleIcon = document.getElementById('toggleLoginPassIcon');
    const pwInput    = document.getElementById('login_password');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isText = pwInput.type === 'text';
            pwInput.type          = isText ? 'password' : 'text';
            toggleIcon.className  = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    /* ── Bootstrap validation ── */
    loginForm.addEventListener('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('was-validated');
            const first = this.querySelector(':invalid');
            if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); }
            return;
        }

        this.classList.add('was-validated');
        // Let normal submission proceed
    });

})();

/* ═══════════════════════════════════════════════════════════
    REGISTER FORM — only activates on /register
═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return; /* not on register page — bail out */

    /* Accreditation type IDs that map to Individual */
    const INDIVIDUAL_IDS = [1, 2]; /* 1 = Practitioners, 2 = Consultant */

    const selectType   = document.getElementById('accreditation_type');
    const hiddenPType  = document.getElementById('profile_type');
    const badge        = document.getElementById('profileTypeBadge');
    const formSections = document.getElementById('formSections');
    const indFields    = document.getElementById('individualFields');
    const orgFields    = document.getElementById('organizationFields');

    const IND_REQUIRED = ['first_name', 'last_name', 'sex', 'birthday', 'region_ind', 'city_ind', 'address_ind'];
    const ORG_REQUIRED = ['org_name', 'org_address', 'head_name', 'designation', 'org_email',
                          'rep_name', 'rep_position', 'rep_contact', 'rep_email'];

    function setRequired(ids, state) {
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.required = state;
        });
    }

    /* ── Accreditation type change → show right section ── */
    selectType.addEventListener('change', function () {
        const val          = parseInt(this.value);
        const isIndividual = INDIVIDUAL_IDS.includes(val);

        hiddenPType.value = isIndividual ? 'Individual' : 'Organization';

        /* badge was removed from the UI — guard against null */
        if (badge) {
            badge.innerHTML = isIndividual
                ? '<span class="profile-type-badge badge-individual"><i class="bi bi-person me-1"></i>Individual</span>'
                : '<span class="profile-type-badge badge-organization"><i class="bi bi-building me-1"></i>Organization</span>';
        }

        formSections.classList.remove('d-none');

        if (isIndividual) {
            indFields.classList.remove('d-none');
            orgFields.classList.add('d-none');
            setRequired(IND_REQUIRED, true);
            setRequired(ORG_REQUIRED, false);
        } else {
            orgFields.classList.remove('d-none');
            indFields.classList.add('d-none');
            setRequired(ORG_REQUIRED, true);
            setRequired(IND_REQUIRED, false);
        }
    });

    /* ── Register password toggle ── */
    const regToggle     = document.getElementById('toggleRegPass');
    const regToggleIcon = document.getElementById('toggleRegPassIcon');
    const regPwInput    = document.getElementById('password');

    if (regToggle) {
        regToggle.addEventListener('click', function () {
            const isText       = regPwInput.type === 'text';
            regPwInput.type    = isText ? 'password' : 'text';
            regToggleIcon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    /* ── Real-time password strength ── */
    if (regPwInput) {
        regPwInput.addEventListener('input', function () {
            const val = this.value;
            const hasLen = val.length >= 8;
            const hasLetter = /[A-Za-z]/.test(val);
            const hasNumber = /\d/.test(val);
            
            const rLen = document.getElementById('rule-length');
            const rLet = document.getElementById('rule-letter');
            const rNum = document.getElementById('rule-number');
            
            if (rLen) {
                rLen.className = hasLen ? 'text-success' : 'text-secondary';
                rLen.querySelector('i').className = hasLen ? 'bi bi-check-circle-fill me-2' : 'bi bi-circle me-2';
            }
            if (rLet) {
                rLet.className = hasLetter ? 'text-success' : 'text-secondary';
                rLet.querySelector('i').className = hasLetter ? 'bi bi-check-circle-fill me-2' : 'bi bi-circle me-2';
            }
            if (rNum) {
                rNum.className = hasNumber ? 'text-success' : 'text-secondary';
                rNum.querySelector('i').className = hasNumber ? 'bi bi-check-circle-fill me-2' : 'bi bi-circle me-2';
            }
            
            this.setCustomValidity((hasLen && hasLetter && hasNumber) ? '' : 'Password must contain at least 8 characters, including letters and numbers.');
            
            // Re-trigger confirm match
            const mainConf = document.getElementById('password_confirmation');
            if (mainConf && mainConf.value) {
                mainConf.setCustomValidity(mainConf.value !== val ? 'Passwords do not match.' : '');
            }
        });
    }

    /* ── Register confirm password toggle ── */
    const regConfirmToggle     = document.getElementById('toggleRegPassConfirm');
    const regConfirmToggleIcon = document.getElementById('toggleRegPassConfirmIcon');
    const regPwConfirmInput    = document.getElementById('password_confirmation');

    if (regConfirmToggle) {
        regConfirmToggle.addEventListener('click', function () {
            const isText       = regPwConfirmInput.type === 'text';
            regPwConfirmInput.type    = isText ? 'password' : 'text';
            regConfirmToggleIcon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    /* ── Real-time confirm-password match ── */
    const pwConfirm = document.getElementById('password_confirmation');
    if (pwConfirm) {
        pwConfirm.addEventListener('input', function () {
            const main = document.getElementById('password');
            this.setCustomValidity(this.value !== main.value ? 'Passwords do not match.' : '');
        });
    }

    // ── Fetch submission & Review Step ──
    const submitBtn     = document.getElementById('submitBtn');
    const submitText    = document.getElementById('submitBtnText');
    const submitSpinner = document.getElementById('submitBtnSpinner');
    const emailPanel    = document.getElementById('emailSentPanel');
    const sentToEmail   = document.getElementById('sentToEmail');
    const tryAgainLink  = document.getElementById('tryAgainLink');

    const reviewBtn     = document.getElementById('reviewBtn');
    const backBtn       = document.getElementById('backBtn');
    const reviewSec     = document.getElementById('reviewSection');
    const reviewCont    = document.getElementById('reviewContent');

    function toggleReviewMode(isReview) {
        const allSteps = document.getElementById('allFormSteps');
        if (allSteps) {
            allSteps.classList.toggle('d-none', isReview);
        }
        
        if (reviewSec) reviewSec.classList.toggle('d-none', !isReview);
        if (reviewBtn) reviewBtn.classList.toggle('d-none', isReview);
        if (backBtn) backBtn.classList.toggle('d-none', !isReview);
        if (submitBtn) {
            if(isReview) submitBtn.classList.remove('d-none');
            else submitBtn.classList.add('d-none');
        }
        
        // Scroll to top
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    if (reviewBtn) {
        reviewBtn.addEventListener('click', function() {
            const privacyCheck = document.getElementById('data_privacy_agreement');
            if (privacyCheck) privacyCheck.required = false;

            if (!registerForm.checkValidity()) {
                registerForm.classList.add('was-validated');
                if (privacyCheck) privacyCheck.required = true;
                
                // Focus first invalid element
                const firstInvalid = registerForm.querySelector(':invalid');
                if(firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
                return;
            }

            if (privacyCheck) privacyCheck.required = true;
            
            // Build summary HTML
            let summaryHtml = '<ul class="list-group list-group-flush">';
            const pType = hiddenPType.value;
            const typeText = selectType.options[selectType.selectedIndex].text;
            summaryHtml += `<li class="list-group-item px-0"><strong>Accreditation Type:</strong> ${typeText}</li>`;
            summaryHtml += `<li class="list-group-item px-0"><strong>Email:</strong> ${document.getElementById('email').value} (Email Account)</li>`;
            
            if (pType === 'Organization') {
                summaryHtml += `<li class="list-group-item px-0"><strong>FatPro Name:</strong> ${document.getElementById('org_name').value}</li>`;
                summaryHtml += `<li class="list-group-item px-0"><strong>Address:</strong> ${document.getElementById('org_address').value}</li>`;
                summaryHtml += `<li class="list-group-item px-0"><strong>Head / Director:</strong> ${document.getElementById('head_name').value} (${document.getElementById('designation').value})</li>`;
                summaryHtml += `<li class="list-group-item px-0"><strong>Representative Name and Contact:</strong> ${document.getElementById('rep_name').value} (${document.getElementById('rep_contact').value})</li>`;
            } else {
                summaryHtml += `<li class="list-group-item px-0"><strong>Name:</strong> ${document.getElementById('first_name').value} ${document.getElementById('middle_name').value} ${document.getElementById('last_name').value}</li>`;
                summaryHtml += `<li class="list-group-item px-0"><strong>Sex:</strong> ${document.getElementById('sex').value}</li>`;
                summaryHtml += `<li class="list-group-item px-0"><strong>Address:</strong> ${document.getElementById('address_ind').value}, ${document.getElementById('city_ind').value}, ${document.getElementById('region_ind').value}</li>`;
            }
            summaryHtml += '</ul><p class="mt-3 text-muted" style="font-size:0.8rem;">Note: The uploaded PDF documents will be included in your final submission.</p>';
            
            if (reviewCont) reviewCont.innerHTML = summaryHtml;
            toggleReviewMode(true);
        });
    }

    if (backBtn) {
        backBtn.addEventListener('click', function() {
            toggleReviewMode(false);
        });
    }

    if (tryAgainLink) {
        tryAgainLink.addEventListener('click', function(e) {
            e.preventDefault();
            emailPanel.classList.add('d-none');
            registerForm.classList.remove('d-none');
            toggleReviewMode(false);
            submitBtn.disabled = false;
        });
    }

    function showTopAlert(message, type) {
        const alertEl  = document.getElementById('dynamicAlert');
        const alertMsg = document.getElementById('dynamicAlertMessage');
        if (!alertEl || !alertMsg) return;

        alertEl.className = `alert alert-${type} alert-dismissible fade show shadow`;
        alertMsg.textContent = message;
        alertEl.classList.remove('d-none');

        // Scroll to alert — use GSAP to avoid conflict with ScrollSmoother
        try {
            const top = alertEl.getBoundingClientRect().top + window.scrollY - 80;
            if (typeof gsap !== 'undefined') {
                gsap.to(window, { scrollTo: { y: top }, duration: 0.6, ease: 'power2.out' });
            } else {
                alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } catch (scrollErr) { /* silent – scroll is non-critical */ }

        // Auto hide after 6 seconds
        setTimeout(() => { alertEl.classList.add('d-none'); }, 6000);
    }

    const fieldMap = {
        accreditation_type_id: 'accreditation_type',
        email: 'email',
        password: 'password',
        password_confirmation: 'password_confirmation',
        org_name: 'org_name',
        org_address: 'org_address',
        head_name: 'head_name',
        designation: 'designation',
        telephone: 'telephone',
        fax: 'fax',
        org_email: 'org_email',
        rep_full_name: 'rep_name',
        rep_position: 'rep_position',
        rep_contact_number: 'rep_contact',
        rep_email: 'rep_email',
    };

    function renderFieldErrors(errors) {
        Object.entries(errors).forEach(([field, messages]) => {
            const inputId = fieldMap[field] || field;
            const input   = document.getElementById(inputId);
            if (input) {
                input.classList.add('is-invalid');
                const fb = input.closest('.col-12, .col-md-4, .col-md-6, .input-group')?.querySelector('.invalid-feedback') || 
                           input.parentElement?.querySelector('.invalid-feedback');
                if (fb) fb.textContent = messages[0];
            } else {
                showTopAlert(messages[0], 'danger');
            }
        });
    }

    function clearAllFieldErrors() {
        registerForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    /* ── File validation (PDF & 10MB) ── */
    function validateFile(input) {
        const files = input.files;
        if (!files || files.length === 0) return true;

        const file = files[0];
        const maxSize = 10 * 1024 * 1024; // 10 MB
        const allowedExt = ['pdf'];
        const fileName = file.name;
        const fileExt = fileName.split('.').pop().toLowerCase();

        input.classList.remove('is-invalid');
        const fb = input.closest('.col-12, .col-md-4, .col-md-6, .input-group')?.querySelector('.invalid-feedback') || 
                    input.parentElement?.querySelector('.invalid-feedback');

        // Robust way to find the label text regardless of nesting
        const titleLabel = input.closest('.col-12, .col-md-4, .col-md-6')?.querySelector('label.form-label');
        const fieldName = titleLabel ? titleLabel.textContent.replace('*', '').trim() : 'File';

        if (!allowedExt.includes(fileExt)) {
            input.classList.add('is-invalid');
            if (fb) fb.textContent = 'Invalid file format. Please upload PDF only.';
            showTopAlert(`Field "${fieldName}": Only PDF files are allowed.`, 'warning');
            return false;
        }

        if (file.size > maxSize) {
            input.classList.add('is-invalid');
            if (fb) fb.textContent = 'File is too large. Maximum size is 10 MB.';
            showTopAlert(`Field "${fieldName}": File exceeds 10 MB limit.`, 'warning');
            return false;
        }

        return true;
    }

    // Attach immediate validation to all file inputs
    registerForm.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function () {
            // Update file name display for custom file input
            const nameSpan = this.parentElement.querySelector('.file-name-text');
            const fileBtn = this.parentElement.querySelector('.custom-file-btn');
            
            if (nameSpan && fileBtn) {
                if (this.files && this.files.length > 0) {
                    nameSpan.textContent = this.files[0].name;
                    nameSpan.classList.remove('text-muted');
                    nameSpan.classList.add('text-primary', 'fw-semibold');
                    fileBtn.classList.remove('btn-outline-primary');
                    fileBtn.classList.add('btn-primary', 'text-white');
                } else {
                    nameSpan.textContent = 'No file chosen';
                    nameSpan.classList.add('text-muted');
                    nameSpan.classList.remove('text-primary', 'fw-semibold');
                    fileBtn.classList.add('btn-outline-primary');
                    fileBtn.classList.remove('btn-primary', 'text-white');
                }
            }
            validateFile(this);
        });
    });

    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // ── Client-side Bootstrap validation ──
        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            
            const firstInvalid = this.querySelector(':invalid');
            if (firstInvalid && firstInvalid.id === 'data_privacy_agreement') {
                showTopAlert('Please agree to the Data Privacy Act by checking the box below.', 'warning');
            } else if (firstInvalid) {
                showTopAlert('Some fields are missing or invalid. Please click "Edit Details" and check your inputs.', 'danger');
            }
            return;
        }

        // ── Client-side File validation ──
        let filesValid = true;
        const fileInputs = this.querySelectorAll('input[type="file"]');
        for (const input of fileInputs) {
            if (!validateFile(input)) {
                filesValid = false;
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                input.focus();
                break;
            }
        }
        if (!filesValid) return;

        const alertEl = document.getElementById('dynamicAlert');
        if (alertEl) alertEl.classList.add('d-none');
        clearAllFieldErrors();

        // Use outer-scope variables (declared at lines ~298-303)
        if (submitBtn) submitBtn.disabled = true;
        if (submitText) submitText.classList.add('d-none');
        if (submitSpinner) submitSpinner.classList.remove('d-none');

        try {
            const formData = new FormData(this);
            const response = await fetch(this.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: formData,
            });

            // ── Parse JSON separately ──────────────────────────────────────────────
            // If the server returns an HTML error page (PHP exception, timeout, etc.)
            // response.json() will throw. We catch it here so the outer catch block
            // (which shows "Connection error") is reserved for real network failures.
            let data;
            try {
                data = await response.json();
            } catch (jsonErr) {
                console.error('[ARMS] Server returned non-JSON. HTTP', response.status, response.statusText);
                showTopAlert(`Server error (${response.status}). Please try again or contact support.`, 'danger');
                return; // finally still runs to re-enable the button
            }

            if (response.ok && data.status === 'pending') {
                // ── SUCCESS: hide form, show email-sent panel ──────────────────────
                if (sentToEmail) sentToEmail.textContent = data.email;
                this.classList.add('d-none');
                if (emailPanel) emailPanel.classList.remove('d-none');
                showTopAlert('Submitted! A verification link has been sent to your email.', 'success');

            } else if (response.status === 422 && data.errors) {
                renderFieldErrors(data.errors);
                this.classList.add('was-validated');
                showTopAlert('Please correct the highlighted errors.', 'warning');
            } else {
                showTopAlert(data.message || 'An unexpected error occurred. Please try again.', 'danger');
            }

        } catch (err) {
            // Only reaches here on a true network failure (no internet, server down, CORS, etc.)
            console.error('[ARMS] Fetch/network error:', err);
            showTopAlert('Connection error. Please check your internet and try again.', 'danger');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (submitText) submitText.classList.remove('d-none');
            if (submitSpinner) submitSpinner.classList.add('d-none');
        }
    });

})();

/* ═══════════════════════════════════════════════════════════
   INSTRUCTOR CARD MANAGER — only activates on /register
   (requires #instructorCardsContainer to be present)
═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const container = document.getElementById('instructorCardsContainer');
    if (!container) return; /* not on register page — bail out */

    const template = document.getElementById('instructorTemplate');
    const addBtn   = document.getElementById('addInstructorBtn');
    let   cardCount = 0;

    /**
     * Replace every __IDX__ placeholder in an element's
     * name, id, and for attributes with the real numeric index.
     */
    function reindexElement(el, idx) {
        ['name', 'id', 'for'].forEach(attr => {
            if (el.hasAttribute(attr)) {
                el.setAttribute(attr, el.getAttribute(attr).replace(/__IDX__/g, idx));
            }
        });
        el.querySelectorAll('[name],[id],[for]').forEach(child => {
            ['name', 'id', 'for'].forEach(attr => {
                if (child.hasAttribute(attr)) {
                    child.setAttribute(attr, child.getAttribute(attr).replace(/__IDX__/g, idx));
                }
            });
        });
    }

    /** Relabel all visible cards (Instructor #1, #2, …) and toggle Remove btn */
    function relabelCards() {
        const cards = container.querySelectorAll('.instructor-card');
        cards.forEach((card, i) => {
            card.querySelector('.instructor-label').textContent = 'Instructor #' + (i + 1);
            const removeBtn = card.querySelector('.remove-instructor-btn');
            if (cards.length > 1) {
                removeBtn.classList.remove('d-none');
            } else {
                removeBtn.classList.add('d-none');
            }
        });
    }

    /** Wire file inputs inside a card to show the chosen filename */
    function bindFileInputs(card) {
        card.querySelectorAll('.real-file-input').forEach(input => {
            input.addEventListener('change', function () {
                const wrapper  = this.closest('.file-upload-wrapper');
                if (!wrapper) return;
                const nameSpan = wrapper.querySelector('.file-name-text');
                const fileBtn  = wrapper.querySelector('.custom-file-btn');

                if (this.files && this.files.length > 0) {
                    if (nameSpan) {
                        nameSpan.textContent = this.files[0].name;
                        nameSpan.classList.remove('text-muted');
                        nameSpan.classList.add('text-primary', 'fw-semibold');
                    }
                    if (fileBtn) {
                        fileBtn.classList.remove('btn-outline-primary');
                        fileBtn.classList.add('btn-primary', 'text-white');
                    }
                } else {
                    if (nameSpan) {
                        nameSpan.textContent = 'No file chosen';
                        nameSpan.classList.add('text-muted');
                        nameSpan.classList.remove('text-primary', 'fw-semibold');
                    }
                    if (fileBtn) {
                        fileBtn.classList.add('btn-outline-primary');
                        fileBtn.classList.remove('btn-primary', 'text-white');
                    }
                }
            });
        });
    }

    /** Clone the hidden template and append a new instructor card */
    function addCard() {
        const idx   = cardCount++;
        const clone = template.querySelector('.instructor-card').cloneNode(true);

        reindexElement(clone, idx);

        clone.querySelector('.remove-instructor-btn').addEventListener('click', function () {
            clone.remove();
            relabelCards();
        });

        bindFileInputs(clone);

        // Animate in
        clone.style.opacity    = '0';
        clone.style.transform  = 'translateY(-8px)';
        clone.style.transition = 'opacity .25s ease, transform .25s ease';
        container.appendChild(clone);
        requestAnimationFrame(() => {
            clone.style.opacity   = '1';
            clone.style.transform = 'translateY(0)';
        });

        relabelCards();
    }

    // Initialise with one card and wire the Add button
    addCard();
    addBtn.addEventListener('click', addCard);
})();
