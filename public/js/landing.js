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

    if (navbar) {
        ScrollTrigger.create({
            start:       'top -60',
            onEnter:     () => navbar.classList.add('scrolled'),
            onLeaveBack: () => navbar.classList.remove('scrolled'),
        });
    }

    /* ─────────────────────────────────────────────────────────
     * 2. SMOOTH HASH-LINK SCROLLING
     *    Uses smoother.scrollTo() when active, gsap.to() fallback
     * ───────────────────────────────────────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (!target) return;
            e.preventDefault();

            const offset = navbar ? navbar.offsetHeight : 0;

            if (smoother) {
                smoother.scrollTo(target, true, 'top ' + offset + 'px');
            } else {
                const top = target.getBoundingClientRect().top + window.scrollY - offset;
                gsap.to(window, { scrollTo: { y: top }, duration: 1.1, ease: 'power3.inOut' });
            }
        });
    });

    /* ─────────────────────────────────────────────────────────
     * 3. HERO — entrance animation (home page only)
     * ───────────────────────────────────────────────────────── */
    if (document.querySelector('.hero-eyebrow')) {
        const heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
        heroTl
            .from('.hero-eyebrow',  { opacity: 0, y: 28, duration: .7, delay: .2 })
            .from('.hero-title',    { opacity: 0, y: 40, duration: .8 }, '-=.4')
            .from('.hero-lead',     { opacity: 0, y: 28, duration: .7 }, '-=.5')
            .from('.hero-btns > *', { opacity: 0, y: 22, duration: .6, stagger: .15 }, '-=.4');
    }

    /* ─────────────────────────────────────────────────────────
     * 4. STATS band — count-up feel (home page only)
     * ───────────────────────────────────────────────────────── */
    if (document.querySelector('.stats-band')) {
        gsap.from('.stat-item', {
            scrollTrigger: { trigger: '.stats-band', start: 'top 85%' },
            opacity: 0, y: 30, duration: .6, stagger: .1, ease: 'power2.out',
        });
    }

    /* ─────────────────────────────────────────────────────────
     * 5. PORTAL cards — cascade in (home page only)
     * ───────────────────────────────────────────────────────── */
    if (document.querySelector('#portals')) {
        gsap.from('.portal-card', {
            scrollTrigger: { trigger: '#portals', start: 'top 80%' },
            opacity: 0, y: 50, duration: .75, stagger: .18, ease: 'power3.out',
        });
    }

    /* ─────────────────────────────────────────────────────────
     * 6. CTA band — slide in from sides (home page only)
     * ───────────────────────────────────────────────────────── */
    if (document.querySelector('.cta-band')) {
        gsap.from('.cta-band .col-lg-7', {
            scrollTrigger: { trigger: '.cta-band', start: 'top 80%' },
            opacity: 0, x: -50, duration: .8, ease: 'power3.out',
        });
        gsap.from('.cta-band .col-lg-5', {
            scrollTrigger: { trigger: '.cta-band', start: 'top 80%' },
            opacity: 0, x: 50, duration: .8, ease: 'power3.out',
        });
    }

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
        const submitBtn = document.getElementById('loginSubmitBtn');
        const submitText = document.getElementById('loginSubmitText');
        const submitSpinner = document.getElementById('loginSubmitSpinner');
        if (submitBtn) submitBtn.disabled = true;
        if (submitText) submitText.classList.add('d-none');
        if (submitSpinner) submitSpinner.classList.remove('d-none');
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
                const matches = mainConf.value === val;
                mainConf.setCustomValidity(matches ? '' : 'Passwords do not match.');
                if (matches) {
                    mainConf.classList.remove('is-invalid');
                    mainConf.classList.add('is-valid');
                } else {
                    mainConf.classList.remove('is-valid');
                    mainConf.classList.add('is-invalid');
                }
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
            if (this.value) {
                const matches = this.value === main.value;
                this.setCustomValidity(matches ? '' : 'Passwords do not match.');
                if (matches) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    }

    /* ── Live Validation for Telephone, Fax, and Rep Contact ── */
    const telInput = document.getElementById('telephone');
    const faxInput = document.getElementById('fax');
    const repContactInput = document.getElementById('rep_contact');

    function validateLandline(input, typeName) {
        let val = input.value.replace(/[^0-9]/g, '');
        input.value = val;
        
        if (val.length === 10) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            input.setCustomValidity('');
        } else if (val.length === 0) {
            input.classList.remove('is-invalid', 'is-valid');
            input.setCustomValidity('');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            input.setCustomValidity(`Enter a valid 10-digit ${typeName} number`);
        }
    }

    function validateRepContact(input) {
        let val = input.value.replace(/[^\d+]/g, '');
        if (val.startsWith('+')) {
            val = '+' + val.slice(1).replace(/\+/g, '');
        } else {
            val = val.replace(/\+/g, '');
        }
        input.value = val;

        const pattern = /^(09|\+639)\d{9}$/;
        if (val.length === 0) {
            if (input.hasAttribute('required')) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                input.setCustomValidity('Contact number is required.');
            } else {
                input.classList.remove('is-invalid', 'is-valid');
                input.setCustomValidity('');
            }
        } else {
            if (pattern.test(val)) {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                input.setCustomValidity('');
            } else {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                input.setCustomValidity('Enter a valid PH mobile number (e.g. 09171234567).');
            }
        }
    }

    if (telInput) {
        telInput.addEventListener('input', function() {
            validateLandline(this, 'telephone');
        });
    }

    if (faxInput) {
        faxInput.addEventListener('input', function() {
            validateLandline(this, 'facsimile');
        });
    }

    if (repContactInput) {
        repContactInput.addEventListener('input', function() {
            validateRepContact(this);
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
            summaryHtml += '</ul>';

            // Gather instructors from the DOM
            const instructorCardsContainer = document.getElementById('instructorCardsContainer');
            if (instructorCardsContainer) {
                const instructorCards = instructorCardsContainer.querySelectorAll('.instructor-card');
                if (instructorCards.length > 0) {
                    summaryHtml += `<div class="mt-4 pt-3 border-top">`;
                    summaryHtml += `<h5 class="fw-bold text-primary mb-3" style="font-size: 1.1rem;"><i class="bi bi-people-fill me-2"></i>Instructors &amp; Credentials Summary</h5>`;
                    
                    instructorCards.forEach((card, i) => {
                        const firstName = card.querySelector('input[name$="[first_name]"]')?.value || '';
                        const middleName = card.querySelector('input[name$="[middle_name]"]')?.value || '';
                        const lastName = card.querySelector('input[name$="[last_name]"]')?.value || '';
                        const fullName = `${firstName} ${middleName} ${lastName}`.trim().replace(/\s+/g, ' ');
                        
                        // Service Agreement
                        const saInput = card.querySelector('input[name$="[service_agreement]"]');
                        const saFileName = saInput && saInput.files && saInput.files.length > 0 ? saInput.files[0].name : '';
                        
                        summaryHtml += `<div class="instructor-summary-card mb-3 p-3 border rounded bg-white shadow-sm" style="border-left: 4px solid #0b3d91 !important;">`;
                        summaryHtml += `<div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">`;
                        summaryHtml += `<span class="fw-bold text-dark" style="font-size: 0.95rem;"><i class="bi bi-person-badge-fill text-primary me-2"></i>Instructor #${i + 1}: ${fullName}</span>`;
                        
                        if (saFileName) {
                            summaryHtml += `<span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 0.75rem;"><i class="bi bi-file-earmark-check-fill me-1"></i>Agreement Attached</span>`;
                        } else {
                            summaryHtml += `<span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2" style="font-size: 0.75rem;"><i class="bi bi-file-earmark-x-fill me-1"></i>No Agreement</span>`;
                        }
                        summaryHtml += `</div>`;
                        
                        if (saFileName) {
                            summaryHtml += `<div class="mb-3 small text-muted"><i class="bi bi-paperclip me-1"></i>Service Agreement PDF: <strong>${saFileName}</strong></div>`;
                        }

                        // Grid for credentials
                        summaryHtml += `<div class="row g-2">`;
                        let hasCreds = false;
                        
                        ['EMS', 'TM1', 'NTTC', 'BOSH'].forEach(type => {
                            const number = card.querySelector(`input[name$="[credentials][${type}][number]"]`)?.value || '';
                            const issuedDate = card.querySelector(`input[name$="[credentials][${type}][issued_date]"]`)?.value || '';
                            const validityDate = card.querySelector(`input[name$="[credentials][${type}][validity_date]"]`)?.value || '';
                            const trainingDates = card.querySelector(`input[name$="[credentials][${type}][training_dates]"]`)?.value || '';
                            const pdfInput = card.querySelector(`input[name$="[credentials][${type}][pdf]`);
                            const pdfName = pdfInput && pdfInput.files && pdfInput.files.length > 0 ? pdfInput.files[0].name : '';

                            if (number || issuedDate || validityDate || trainingDates || pdfName) {
                                hasCreds = true;
                                summaryHtml += `<div class="col-md-6">`;
                                summaryHtml += `<div class="card h-100 border-0 shadow-none bg-light p-2 rounded-2" style="border-left: 3px solid #6c757d !important;">`;
                                summaryHtml += `<div class="fw-bold mb-1 text-dark" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em;">${type === 'EMS' ? 'TESDA EMS NC II/III' : type === 'TM1' ? 'TESDA TM1' : type === 'NTTC' ? 'TESDA NTTC' : 'BOSH SO1/SO2'}</div>`;
                                
                                summaryHtml += `<table class="table table-sm table-borderless mb-0" style="font-size: 0.78rem; line-height: 1.3; background: transparent;">`;
                                if (number) {
                                    summaryHtml += `<tr><td class="text-muted p-0 py-0.5" style="width: 40%; background: transparent;">Number:</td><td class="fw-semibold text-dark p-0 py-0.5" style="background: transparent;">${number}</td></tr>`;
                                }
                                if (type === 'BOSH' && trainingDates) {
                                    summaryHtml += `<tr><td class="text-muted p-0 py-0.5" style="width: 40%; background: transparent;">Training:</td><td class="text-dark p-0 py-0.5" style="background: transparent;">${trainingDates}</td></tr>`;
                                } else if (issuedDate) {
                                    summaryHtml += `<tr><td class="text-muted p-0 py-0.5" style="width: 40%; background: transparent;">Issued:</td><td class="text-dark p-0 py-0.5" style="background: transparent;">${issuedDate}</td></tr>`;
                                }
                                if (validityDate) {
                                    summaryHtml += `<tr><td class="text-muted p-0 py-0.5" style="width: 40%; background: transparent;">Validity:</td><td class="text-dark p-0 py-0.5" style="background: transparent;">${validityDate}</td></tr>`;
                                }
                                if (pdfName) {
                                    summaryHtml += `<tr><td class="text-muted p-0 py-0.5" style="width: 40%; background: transparent;">PDF:</td><td class="text-primary fw-semibold p-0 py-0.5 text-truncate" style="max-width: 150px; background: transparent;" title="${pdfName}"><i class="bi bi-file-pdf me-1"></i>${pdfName}</td></tr>`;
                                }
                                summaryHtml += `</table>`;
                                summaryHtml += `</div>`;
                                summaryHtml += `</div>`;
                            }
                        });
                        
                        if (!hasCreds) {
                            summaryHtml += `<div class="col-12"><div class="text-muted small italic p-2 bg-light rounded border text-center">No credential details added.</div></div>`;
                        }
                        
                        summaryHtml += `</div>`; // end row g-2
                        summaryHtml += `</div>`; // end instructor-summary-card
                    });
                    summaryHtml += `</div>`;
                }
            }

            summaryHtml += '<p class="mt-3 text-muted" style="font-size:0.8rem;">Note: The uploaded PDF documents will be included in your final submission.</p>';
            
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
        alertMsg.innerHTML = message;
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
        first_name: 'first_name',
        middle_name: 'middle_name',
        last_name: 'last_name',
        sex: 'sex',
        birthday: 'birthday',
        region: 'region_ind',
        city: 'city_ind',
        address: 'address_ind',
    };

    function renderFieldErrors(errors) {
        let errorMessages = [];
        
        Object.entries(errors).forEach(([field, messages]) => {
            const inputId = fieldMap[field] || field;
            const input   = document.getElementById(inputId);
            
            if (input) {
                input.classList.add('is-invalid');
                const fb = input.closest('.col-12, .col-md-4, .col-md-6, .input-group')?.querySelector('.invalid-feedback') || 
                           input.parentElement?.querySelector('.invalid-feedback');
                if (fb) fb.textContent = messages[0];
                
                // Get human readable field label
                const labelEl = input.closest('.col-12, .col-md-4, .col-md-6')?.querySelector('label.form-label');
                const labelText = labelEl ? labelEl.textContent.replace('*', '').trim() : field;
                errorMessages.push(`<strong>${labelText}</strong>: ${messages[0]}`);
            } else {
                // Handle nested validation keys like instructors.0.first_name
                let niceField = field;
                const instMatch = field.match(/^instructors\.(\d+)\.(.+)$/);
                if (instMatch) {
                    const index = parseInt(instMatch[1]) + 1;
                    let subField = instMatch[2].replace(/\./g, ' ').replace(/_/g, ' ');
                    // Capitalize subfield words
                    subField = subField.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                    niceField = `Instructor #${index} - ${subField}`;
                } else {
                    niceField = field.replace(/_/g, ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                }
                errorMessages.push(`<strong>${niceField}</strong>: ${messages[0]}`);
            }
        });

        if (errorMessages.length > 0) {
            let alertHtml = '<div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct the following errors:</div>';
            alertHtml += '<ul class="mb-0 ps-3 mt-1 text-start">';
            errorMessages.forEach(msg => {
                alertHtml += `<li>${msg}</li>`;
            });
            alertHtml += '</ul>';
            showTopAlert(alertHtml, 'danger');
        }
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
                toggleReviewMode(false);
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
        const sourceNode = template.content ? template.content : template;
        const clone = sourceNode.querySelector('.instructor-card').cloneNode(true);

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
