/**
 * ARMS Portal JS
 * Contains: File Viewer Modal + Sidebar Quick Tour logic (Intro.js powered).
 * CSS: See public/css/portal.css  (FILE VIEWER MODAL + SIDEBAR QUICK TOUR sections)
 */

(function () {
    'use strict';

    /* ─────────────────────────────────────────────
       FILE VIEWER MODAL
       Intercepts all <a data-file-modal> clicks and
       opens the linked file inside #fileViewerModal.
    ───────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        var modal   = document.getElementById('fileViewerModal');
        var frame   = document.getElementById('fileViewerFrame');
        var label   = document.getElementById('fileViewerModalLabel');
        var dlLink  = document.getElementById('fileViewerDownload');

        if (!modal || !frame) return;

        // Delegated click — works for dynamically rendered content too
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-file-modal]');
            if (!link) return;

            e.preventDefault();

            var url   = link.getAttribute('href');
            var title = link.getAttribute('data-file-title') || 'File Preview';

            if (label)  label.textContent = title;
            if (dlLink) dlLink.href = url;

            // Set iframe src first, then show modal
            frame.src = url;

            if (window.bootstrap && window.bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });

        // Clear iframe on modal close to stop PDF/media loading
        modal.addEventListener('hidden.bs.modal', function () {
            frame.src = 'about:blank';
        });
    });

    /* ─────────────────────────────────────────────
       TOUR STEP DEFINITIONS
       Each step targets a CSS selector inside the sidebar.
       The element must exist in the DOM when the tour starts.
    ───────────────────────────────────────────── */
    const TOUR_STEPS = {

        applicant: [
            {
                element: '#sidebar-menu',
                title: 'Welcome to ARMS!',
                intro: 'This quick tour will guide you through the <strong>Applicant Portal</strong> sidebar. Use the arrows to navigate each section.',
                position: 'right',
            },
            {
                element: '#tour-step-dashboard',
                title: 'Dashboard',
                intro: 'Your home base. View your application status, recent activity, and key updates all in one place.',
                position: 'right',
            },
            {
                element: '#tour-step-profile',
                title: 'My Profile',
                intro: 'Keep your personal or organization information up to date. You can also change your password here.',
                position: 'right',
            },
            {
                element: '#tour-step-submission',
                title: 'Submission Report',
                intro: 'Submit required documents such as <strong>Notice of Conduct</strong>, Reports to Changes, and Post-Training Reports.',
                position: 'right',
            },
            {
                element: '#tour-step-renewal',
                title: 'Renewal / Reinstatement',
                intro: 'Apply for accreditation renewal or reinstatement when your accreditation is nearing expiry or has lapsed.',
                position: 'right',
            },
            {
                element: '#tour-step-instructors',
                title: 'FATPRO Instructor',
                intro: "Manage and view the list of your organization's registered FATPRO instructors.",
                position: 'right',
            },
        ],

        evaluator: [
            {
                element: '#sidebar-menu',
                title: 'Welcome, Evaluator!',
                intro: 'This tour will walk you through the <strong>HCD Evaluator Portal</strong> sidebar and its key functions.',
                position: 'right',
            },
            {
                element: '#tour-step-dashboard',
                title: 'Dashboard',
                intro: 'Monitor all application metrics, recent submissions, and activity summaries at a glance.',
                position: 'right',
            },
            {
                element: '#tour-step-profile',
                title: 'My Profile',
                intro: 'Update your admin account details, role, and password.',
                position: 'right',
            },
            {
                element: '#tour-step-admin-list',
                title: 'HCD Admin List',
                intro: 'View the directory of all HCD administrators and their assigned roles.',
                position: 'right',
            },
            {
                element: '#tour-step-new-apps',
                title: 'New Applications',
                intro: 'Review and evaluate incoming accreditation applications. Expand to see <strong>Pending</strong> and <strong>Under Review</strong> sub-sections.',
                position: 'right',
            },
            {
                element: '#tour-step-renewal',
                title: 'Renewal / Reinstatement',
                intro: 'Review renewal and reinstatement applications submitted by existing FatPro holders.',
                position: 'right',
            },
            {
                element: '#tour-step-interviews',
                title: 'Schedule Interviews',
                intro: 'Manage applications awaiting interview scheduling and those that already have set interview appointments.',
                position: 'right',
            },
            {
                element: '#tour-step-payment',
                title: 'For Recommendation',
                intro: 'Applications that have passed evaluation and are ready for your formal recommendation.',
                position: 'right',
            },
            {
                element: '#tour-step-active-fatpro',
                title: 'Active FatPro',
                intro: 'Browse the directory of currently accredited FatPro trainers.',
                position: 'right',
            },
            {
                element: '#tour-step-inactive-fatpro',
                title: 'Revoked / Expired',
                intro: 'View FatPro accreditations that have been revoked or have expired.',
                position: 'right',
            },
            {
                element: '#tour-step-archived',
                title: 'Archived Applications',
                intro: 'Access historical records of archived accreditation applications.',
                position: 'right',
            },
        ],

        verifier: [
            {
                element: '#sidebar-menu',
                title: 'Welcome, Verifier!',
                intro: 'This tour will guide you through the <strong>HCD Verifier Portal</strong> sidebar and its key functions.',
                position: 'right',
            },
            {
                element: '#tour-step-dashboard',
                title: 'Dashboard',
                intro: 'Monitor all application metrics and activity summaries at a glance.',
                position: 'right',
            },
            {
                element: '#tour-step-profile',
                title: 'My Profile',
                intro: 'Update your admin account details and password.',
                position: 'right',
            },
            {
                element: '#tour-step-admin-list',
                title: 'HCD Admin List',
                intro: 'View the directory of all HCD administrators.',
                position: 'right',
            },
            {
                element: '#tour-step-payment',
                title: 'Recommendation / Payment',
                intro: 'Verify payment submissions and process official recommendations for approved applications.',
                position: 'right',
            },
            {
                element: '#tour-step-releasing',
                title: 'Releasing',
                intro: 'The accreditation certificate is now ready and available for release to the applicant.',
                position: 'right',
            },
            {
                element: '#tour-step-active-fatpro',
                title: 'Active FatPro',
                intro: 'Browse the directory of currently accredited FatPro trainers.',
                position: 'right',
            },
            {
                element: '#tour-step-inactive-fatpro',
                title: 'Revoked / Expired',
                intro: 'View FatPro accreditations that have been revoked or have expired.',
                position: 'right',
            },
            {
                element: '#tour-step-archived',
                title: 'Archived Applications',
                intro: 'Access historical records of archived accreditation applications.',
                position: 'right',
            },
        ],
    };

    /* ─────────────────────────────────────────────
       STORAGE HELPERS
    ───────────────────────────────────────────── */
    function storageKey(tourType) {
        return 'arms_sidebar_tour_' + tourType;
    }

    function isDismissed(tourType) {
        try {
            return localStorage.getItem(storageKey(tourType)) === 'dismissed';
        } catch (e) {
            return false;
        }
    }

    function dismiss(tourType) {
        try {
            localStorage.setItem(storageKey(tourType), 'dismissed');
        } catch (e) { /* storage unavailable */ }
    }

    function undismiss(tourType) {
        try {
            localStorage.removeItem(storageKey(tourType));
        } catch (e) { /* storage unavailable */ }
    }

    /* ─────────────────────────────────────────────
       "NEVER SHOW AGAIN" CHECKBOX INJECTION
       Intro.js doesn't natively support extra footer
       content, so we inject it after each step render.
    ───────────────────────────────────────────── */
    function injectNeverAgainCheckbox(introInstance, tourType) {
        const buttonsRow = document.querySelector('.introjs-tooltipbuttons');
        if (!buttonsRow || document.querySelector('.tour-nav-row')) return;

        // Grab the existing nav buttons before restructuring
        const prevBtn = buttonsRow.querySelector('.introjs-prevbutton');
        const nextBtn = buttonsRow.querySelector('.introjs-nextbutton') ||
                        buttonsRow.querySelector('.introjs-donebutton');

        // ── Row 1: Back + Next centred ──
        const navRow = document.createElement('div');
        navRow.className = 'tour-nav-row';
        if (prevBtn) navRow.appendChild(prevBtn);
        if (nextBtn) navRow.appendChild(nextBtn);

        // ── Row 2: Single combined skip + dismiss button ──
        const bottomRow = document.createElement('div');
        bottomRow.className = 'tour-bottom-row';

        const skipDismissBtn = document.createElement('button');
        skipDismissBtn.type      = 'button';
        skipDismissBtn.id        = 'tour-skip-btn';
        skipDismissBtn.className = 'tour-skip-link';
        skipDismissBtn.textContent = "Skip & Don't show again";
        skipDismissBtn.addEventListener('click', function () {
            dismiss(tourType);   // permanently save dismissal
            introInstance.exit();
        });

        bottomRow.appendChild(skipDismissBtn);

        // Clear leftovers and rebuild
        buttonsRow.innerHTML = '';
        buttonsRow.appendChild(navRow);
        buttonsRow.appendChild(bottomRow);
    }

    function isNeverChecked() {
        // No longer used — skip button handles dismissal directly.
        return false;
    }

    /* ─────────────────────────────────────────────
       TOUR TRIGGER BUTTON (re-launch after dismiss)
    ───────────────────────────────────────────── */
    function showTriggerButton(tourType) {
        const btn = document.getElementById('arms-tour-trigger');
        if (!btn) return;
        btn.style.display = 'block';
        btn.onclick = function () {
            undismiss(tourType);
            btn.style.display = 'none';
            startTour(tourType);
        };
    }

    /* ─────────────────────────────────────────────
       FILTER STEPS — remove steps whose element
       does not exist in DOM (role-conditional items)
    ───────────────────────────────────────────── */
    function buildSteps(tourType) {
        const rawSteps = TOUR_STEPS[tourType] || [];
        return rawSteps.filter(function (step) {
            // First step always uses #sidebar-menu which always exists
            if (!step.element || step.element === '#sidebar-menu') return true;
            return !!document.querySelector(step.element);
        });
    }

    /* ─────────────────────────────────────────────
       START TOUR
    ───────────────────────────────────────────── */
    function startTour(tourType) {
        if (typeof introJs === 'undefined') {
            console.warn('[ARMS Tour] introJs not loaded yet.');
            return;
        }

        const steps = buildSteps(tourType);
        if (!steps.length) return;

        const intro = introJs();
        intro.setOptions({
            steps: steps,
            showStepNumbers: false,
            showBullets: true,
            showProgress: true,
            exitOnOverlayClick: false,
            disableInteraction: true,
            scrollToElement: true,
            tooltipPosition: 'right',
            nextLabel: 'Next &rarr;',
            prevLabel: '&larr; Back',
            skipLabel: '&times;',
            doneLabel: '🎉 Got it!',
            overlayOpacity: 0.72,
            helperElementPadding: 10,
            positionPrecedence: ['right', 'left', 'bottom', 'top'],
        });

        // Inject "Never show again" after each step change
        intro.onafterchange(function () {
            // Re-inject since tooltip DOM is recreated on each step
            setTimeout(function () {
                injectNeverAgainCheckbox(intro, tourType);
            }, 50);
        });

        // On skip (× header close) or natural completion — just show the trigger button.
        // Note: "Skip & Don't show again" button calls dismiss() itself before exit().
        function handleExit() {
            showTriggerButton(tourType);
        }

        intro.oncomplete(function () { handleExit(); });
        intro.onexit(function ()     { handleExit(); });

        intro.start();

        // Inject on first step (onafterchange fires only from step 2+)
        setTimeout(function () {
            injectNeverAgainCheckbox(intro, tourType);
        }, 100);
    }

    /* ─────────────────────────────────────────────
       BOOTSTRAP — called from sidebar_tour.blade.php
       via window.ARMSTour.init(tourType)
    ───────────────────────────────────────────── */
    window.ARMSTour = {
        init: function (tourType, sessionId) {
            if (!TOUR_STEPS[tourType]) {
                console.warn('[ARMS Tour] Unknown tourType:', tourType);
                return;
            }

            document.addEventListener('DOMContentLoaded', function () {
                if (isDismissed(tourType)) {
                    // Already dismissed permanently — show the re-launch button instead
                    showTriggerButton(tourType);
                    return;
                }

                // Check session storage to ensure it only auto-runs once per login session
                var sessionKey = 'arms_sidebar_tour_shown_' + tourType + (sessionId ? '_' + sessionId : '');
                var alreadyShownThisSession = false;
                try {
                    alreadyShownThisSession = sessionStorage.getItem(sessionKey) === 'true';
                } catch (e) { /* sessionStorage unavailable */ }

                if (alreadyShownThisSession) {
                    // Already shown in this session — show the re-launch button instead of auto-starting
                    showTriggerButton(tourType);
                    return;
                }

                // Mark as shown in this session immediately so subsequent page loads don't auto-start it
                try {
                    sessionStorage.setItem(sessionKey, 'true');
                } catch (e) { /* sessionStorage unavailable */ }

                // Small delay so Gentelella sidebar JS finishes rendering
                setTimeout(function () {
                    startTour(tourType);
                }, 600);
            });
        },
    };

})();
