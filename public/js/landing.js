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
        e.preventDefault();
        e.stopPropagation();

        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            const first = this.querySelector(':invalid');
            if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); }
            return;
        }

        this.classList.add('was-validated');
        /* TODO: replace with real fetch/form submit when backend ready */
        console.log('[ARMS] Login submitted');
        alert('Login submitted! (Backend integration pending)');
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

    // ── Fetch submission ──
    const submitBtn     = document.getElementById('submitBtn');
    const submitText    = document.getElementById('submitBtnText');
    const submitSpinner = document.getElementById('submitBtnSpinner');
    const emailPanel    = document.getElementById('emailSentPanel');
    const sentToEmail   = document.getElementById('sentToEmail');
    const tryAgainLink  = document.getElementById('tryAgainLink');

    if (tryAgainLink) {
        tryAgainLink.addEventListener('click', function(e) {
            e.preventDefault();
            emailPanel.classList.add('d-none');
            registerForm.classList.remove('d-none');
        });
    }

    function showTopAlert(message, type) {
        const alertEl  = document.getElementById('dynamicAlert');
        const alertMsg = document.getElementById('dynamicAlertMessage');
        if (!alertEl || !alertMsg) return;
        
        alertEl.className = `alert alert-${type} alert-dismissible fade show shadow`;
        alertMsg.textContent = message;
        alertEl.classList.remove('d-none');
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            alertEl.classList.add('d-none');
        }, 5000);
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

    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // ── Client-side Bootstrap validation ──
        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            return;
        }

        const alertEl = document.getElementById('dynamicAlert');
        if(alertEl) { alertEl.classList.add('d-none'); }
        clearAllFieldErrors();

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

            const data = await response.json();

            if (response.ok && data.status === 'pending') {
                if (sentToEmail) sentToEmail.textContent = data.email;
                this.classList.add('d-none');
                if (emailPanel) emailPanel.classList.remove('d-none');
                showTopAlert('Registration submitted successfully! Please check your email.', 'success');
            } else if (response.status === 422 && data.errors) {
                renderFieldErrors(data.errors);
                this.classList.add('was-validated');
                showTopAlert('Please correct the highlighted errors.', 'warning');
            } else {
                showTopAlert(data.message || 'An unexpected error occurred. Please try again.', 'danger');
            }

        } catch (err) {
            console.error(err);
            showTopAlert('System error. Please check your connection and try again.', 'danger');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (submitText) submitText.classList.remove('d-none');
            if (submitSpinner) submitSpinner.classList.add('d-none');
        }
    });

})();
