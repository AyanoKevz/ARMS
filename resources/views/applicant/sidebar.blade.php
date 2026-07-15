<!-- Applicant Sidebar -->
@php
    $hasOngoingRenewal = \App\Models\Application::where('user_id', auth()->id())
        ->whereIn('application_type', ['renewal', 'reinstatement'])
        ->whereHas('latestStatus', function ($q) {
            $q->whereHas('status', function ($q2) {
                $q2->whereIn('name', [
                    'Submitted',
                    'Under Evaluation',
                    'For Update',
                    'Scheduled for Interview',
                    'Awaiting Payment',
                    'Payment Verification',
                ]);
            });
        })
        ->exists();

    $latestAccreditation = \App\Models\Accreditation::where('user_id', auth()->id())->latest()->first();
    $isRevoked = $latestAccreditation && $latestAccreditation->status === 'revoked';

    // Active state flags
    $dashActive        = request()->routeIs('applicant.dashboard');
    $profileActive     = request()->routeIs('profile.index');
    $ntcActive         = request()->routeIs('applicant.ntc.index') || request()->routeIs('applicant.ntc.show');
    $submissionActive  = $ntcActive; // expand if Report to Changes / Post Training Report get routes
    $renewalActive     = request()->routeIs('applicant.renewal.*');
    $instructorsActive = request()->routeIs('applicant.instructors.*');
@endphp

<li id="tour-step-dashboard" class="{{ $dashActive ? 'current-page active' : '' }}"><a href="{{ route('applicant.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li id="tour-step-profile" class="{{ $profileActive ? 'current-page active' : '' }}"><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>

@if(!$isRevoked && !$hasOngoingRenewal)
    <li id="tour-step-submission" class="{{ $submissionActive ? 'current-page active' : '' }}"><a href="{{ route('applicant.ntc.index') }}"><i class="fas fa-file-invoice"></i> Submission report </a></li>
@elseif($isRevoked)
    <li id="tour-step-submission" class="{{ $submissionActive ? 'current-page active' : '' }}"><a href="javascript:void(0);" onclick="showSidebarNotice('You cannot submit or access the Submission report because your accreditation has been revoked.')" style="opacity: 0.6; cursor: not-allowed;"><i class="fas fa-lock" style="margin-right: 5px;"></i> Submission report </a></li>
@else
    <li id="tour-step-submission" class="{{ $submissionActive ? 'current-page active' : '' }}"><a href="javascript:void(0);" onclick="showSidebarNotice('You cannot submit or access the Submission report while your renewal/reinstatement application is ongoing.')" style="opacity: 0.6; cursor: not-allowed;"><i class="fas fa-lock" style="margin-right: 5px;"></i> Submission report </a></li>
@endif
<li id="tour-step-renewal" class="{{ $renewalActive ? 'current-page active' : '' }}"><a href="{{ route('applicant.renewal.index') }}"><i class="fas fa-sync-alt"></i> Renewal / Reinstatement </a></li>
<li id="tour-step-instructors" class="{{ $instructorsActive ? 'current-page active' : '' }}"><a href="{{ route('applicant.instructors.index') }}"><i class="fas fa-chalkboard-teacher"></i> FATPRO Instructor </a></li>

<!-- ══ Sidebar Notice Modal ══ -->
<div class="modal fade" id="sidebarNoticeModal" tabindex="-1" aria-labelledby="sidebarNoticeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header py-2 px-3" style="background: linear-gradient(135deg, #0D2B55, #1A4A8A); border-bottom: 2px solid var(--portal-gold); display: flex; align-items: center;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-exclamation-triangle" style="color: var(--portal-gold); font-size: 1.1rem; margin-right: 6px;"></i>
                    <h5 class="modal-title fw-semibold text-white mb-0" id="sidebarNoticeModalLabel" style="font-size: 0.95rem; display: inline-block;">Action Restrained</h5>
                </div>
                <button type="button" class="btn-close btn-close-white btn-sm ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <i class="fas fa-lock text-danger" style="font-size: 3rem;"></i>
                </div>
                <p id="sidebarNoticeModalMessage" class="text-secondary mb-0 fw-semibold" style="font-size: 0.95rem;"></p>
            </div>
            <div class="modal-footer border-0 p-3 bg-light d-flex justify-content-end">
                <button type="button" class="btn fw-semibold px-4" data-bs-dismiss="modal" style="background: #0D2B55; color: #fff; border-radius: 6px; font-size: 0.85rem; border: none; padding: 6px 12px; transition: background 0.2s;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function showSidebarNotice(message) {
        const modalEl = document.getElementById('sidebarNoticeModal');
        const msgEl = document.getElementById('sidebarNoticeModalMessage');
        if (modalEl && msgEl) {
            msgEl.textContent = message;
            // Move modal to body to avoid z-index / parent stacking context rendering issues (being behind the backdrop overlay)
            if (modalEl.parentNode !== document.body) {
                document.body.appendChild(modalEl);
            }
            if (window.bootstrap && window.bootstrap.Modal) {
                const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else {
                // Fallback in case bootstrap isn't loaded yet/fully
                alert(message);
            }
        }
    }
</script>