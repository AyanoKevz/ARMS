{{--
    Post Training Report — the parts that cannot live inside a table cell.

    The per-training status and actions are rendered in the Training Submissions
    table itself (see post_training_cell). What remains here is the submission
    wizard and the re-upload modals, each in its own partial.

    Expects: $ntcReports, $ptrPending, $ptrSubmitted, $ptrDocumentTypes,
             $instructorRoster
--}}

@php
    // Attachments, participants and the instructor roster are all sent back
    // separately, and any combination can be outstanding at once.
    $ptrDeclined = $ptrSubmitted->filter(fn($r) => $r->needsCorrections());
@endphp

{{-- ══ Submission wizard (one modal, retargeted per training) ══ --}}
<div class="modal fade" id="ptrSubmitModal" tabindex="-1" aria-labelledby="ptrSubmitModalLabel" aria-hidden="true"
     data-photo-url="{{ route('applicant.post_training.participant_photo') }}"
     data-stage-document-url="{{ route('applicant.post_training.stage_document') }}">
    <div class="modal-dialog modal-xl portal-scroll-modal">
        <div class="modal-content ptr-modal-content">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold ptr-heading-navy" id="ptrSubmitModalLabel">
                    Submit Post Training Report
                </h5>

                {{-- Beside the title rather than in the footer: the footer
                     scrolls out of reach on a long step, and a save nobody
                     can see is a save nobody trusts. --}}
                <span class="ptr-save-status" id="ptrSaveStatus" role="status" aria-live="polite">
                    <i class="ptr-save-dot" aria-hidden="true"></i>
                    <span class="ptr-save-text">Your progress is saved as you go</span>
                </span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" id="ptrSubmitForm" enctype="multipart/form-data" novalidate>
                @csrf

                <div class="modal-body">
                    <div id="ptrModalOverdue" class="ptr-notice ptr-notice-danger" hidden>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>This report is overdue.</strong>
                        The submission deadline for this training has already passed.
                    </div>

                    {{-- Which training this report is for. --}}
                    <div class="ptr-notice ptr-notice-info">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="text-uppercase fw-bold ptr-modal-label">NTC Reference</div>
                                <div class="fw-semibold" id="ptrModalNtcRef">—</div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="text-uppercase fw-bold ptr-modal-label">Training Type</div>
                                <div class="fw-semibold" id="ptrModalTrainingType">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-uppercase fw-bold ptr-modal-label">Training Period</div>
                                <div class="fw-semibold" id="ptrModalTrainingPeriod">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-uppercase fw-bold ptr-modal-label">Submission Deadline</div>
                                <div class="fw-semibold" id="ptrModalDeadline">—</div>
                            </div>
                        </div>
                    </div>

                    @include('applicant.partials.post_training_steps')

                </div>

                <div class="modal-footer bg-light ptr-wizard-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>


                    <div class="ptr-wizard-nav">
                        <button type="button" class="btn btn-outline-secondary px-4" id="ptrStepBack" hidden>
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>

                        <button type="button" class="btn ptr-submit-btn px-4" id="ptrStepNext">
                            Next <i class="fas fa-arrow-right ms-1"></i>
                        </button>

                        <button type="submit" class="btn ptr-submit-btn px-4" id="ptrStepSubmit" hidden>
                            <i class="fas fa-paper-plane me-1"></i> Submit Post Training Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('applicant.partials.post_training_reupload_modals')
