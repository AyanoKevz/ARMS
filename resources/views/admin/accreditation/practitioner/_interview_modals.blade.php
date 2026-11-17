{{--
    Panel interview modals — FATPro's Stop Interview / Passed / Not Passed
    confirmations (admin/hcd/show_application_info) for the practitioner.
    The form ids are the ones evaluation.js gives a busy state, and
    #stop-interview-form is the one it submits when the 2 hours are up.

    Expects $application.
--}}

{{-- ══ Stop Interview ══ --}}
<div class="modal fade" id="stopInterviewModal" tabindex="-1" aria-labelledby="stopInterviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header is-danger">
                <div class="d-flex align-items-center gap-3">
                    <span class="prac-modal-icon"><i class="bi bi-stop-circle-fill"></i></span>
                    <h5 class="modal-title text-white mb-0 fw-bold" id="stopInterviewModalLabel">Stop Interview</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="mb-0 fw-semibold">Are you sure you want to stop the interview?</p>
                <small class="text-muted">The PCT timer for the interview will be completed, and you will be able to record the result.</small>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="stop-interview-form" method="POST" action="{{ route('admin.accreditation.practitioner.stop_interview', $application->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-danger fw-bold">
                        <span class="btn-text"><i class="bi bi-stop-fill me-1"></i> Confirm Stop</span>
                        <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm me-1" role="status"></span> Stopping...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ══ Passed ══ --}}
<div class="modal fade" id="passedConfirmModal" tabindex="-1" aria-labelledby="passedConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header is-success">
                <div class="d-flex align-items-center gap-3">
                    <span class="prac-modal-icon"><i class="bi bi-patch-check-fill"></i></span>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="passedConfirmModalLabel">Confirm: Interview Passed</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 prac-modal-body">
                <div class="d-flex align-items-center gap-3 mb-3 p-3 prac-modal-applicant">
                    <i class="bi bi-person-circle fs-2 text-success"></i>
                    <div>
                        <div class="fw-bold prac-modal-name">{{ $application->user?->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $application->user?->email }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 prac-modal-note is-success">
                    <i class="bi bi-check-circle-fill text-success mt-1"></i>
                    <small class="text-dark">
                        The application will be marked as having <strong>Passed the Interview</strong>. The status will be
                        updated to <strong>For Recommendation</strong>, allowing the recommendation form to be generated, and the
                        applicant will be notified by email.
                    </small>
                </div>
            </div>
            <div class="modal-footer border-0 prac-modal-body">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <form id="confirm-approval-form" method="POST" action="{{ route('admin.accreditation.practitioner.interview_result', $application->id) }}">
                    @csrf
                    <input type="hidden" name="result" value="passed">
                    <button type="submit" class="btn btn-success fw-bold px-5">
                        <span class="btn-text"><i class="bi bi-check-circle-fill me-2"></i>Confirm Passed</span>
                        <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm me-2" role="status"></span> Updating...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ══ Not Passed ══ --}}
<div class="modal fade" id="notPassedConfirmModal" tabindex="-1" aria-labelledby="notPassedConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header is-danger">
                <div class="d-flex align-items-center gap-3">
                    <span class="prac-modal-icon"><i class="bi bi-x-octagon-fill"></i></span>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="notPassedConfirmModalLabel">Confirm: Did Not Pass</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 prac-modal-body">
                <div class="d-flex align-items-center gap-3 mb-3 p-3 prac-modal-applicant">
                    <i class="bi bi-person-circle fs-2 text-danger"></i>
                    <div>
                        <div class="fw-bold prac-modal-name">{{ $application->user?->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $application->user?->email }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 prac-modal-note is-danger">
                    <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
                    <small class="text-dark">
                        <strong>Warning — this action is permanent and cannot be undone.</strong><br>
                        The status will be changed to <strong>Rejected — Did Not Pass</strong> and the application moved to
                        <strong>Archived Applications</strong>. The applicant will be notified by email and may apply again after
                        <strong>6 months</strong>, when the archived record is deleted automatically.
                    </small>
                </div>
            </div>
            <div class="modal-footer border-0 prac-modal-body">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <form id="confirm-reject-form" method="POST" action="{{ route('admin.accreditation.practitioner.interview_result', $application->id) }}">
                    @csrf
                    <input type="hidden" name="result" value="not_passed">
                    <button type="submit" class="btn btn-danger fw-bold px-5">
                        <span class="btn-text"><i class="bi bi-archive-fill me-2"></i>Confirm &amp; Archive</span>
                        <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm me-2" role="status"></span> Archiving...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
