{{--
    Recommendation / Payment — FATPro's panels (admin/hcd/show_application_info),
    minus the inline styles:

      For Recommendation    Recommendation Form → generate the PDF (FATPro's
                            template); upload its scanned, signed copy
      Awaiting Payment      waiting for the applicant's proof of payment
      Payment Verification  Approve / Reject the proof of payment

    Expects $application, $status, $payment.
--}}
@php
    $defaults        = config('accreditation.recommendation_defaults', []);
    $hasSignedLetter = (bool) $payment?->signed_recommendation_letter;
    $proofStatus     = $payment?->proof_of_payment ? $payment->proof_of_payment_status : null;
    $industry        = $application->user?->individualProfile?->type_of_industry === 'Others'
        ? $application->user?->individualProfile?->type_of_industry_other
        : $application->user?->individualProfile?->type_of_industry;
@endphp

{{-- ══ Recommendation Form ══ --}}
@if($status === 'For Recommendation')
<div class="ai-card mb-4">
    <div class="ai-card-header">
        <i class="bi bi-file-earmark-pdf fs-5 text-dark"></i>
        <h5>Recommendation Form</h5>
    </div>
    <div class="x_content p-3 mt-2">
        <p class="text-muted small">This application has passed the interview stage. Fill out the recommendation form details and generate the PDF to print.</p>

        <form action="{{ route('admin.accreditation.practitioner.generate_recommendation', $application->id) }}" method="POST" target="_blank" class="mb-3">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small" for="rec-date">Form Date</label>
                    <input type="date" name="date" id="rec-date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small" for="rec-from">From Division</label>
                    <input type="text" name="from" id="rec-from" class="form-control form-control-sm" value="{{ $defaults['from'] ?? '' }}" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold small" for="rec-to">To Office</label>
                    <input type="text" name="to" id="rec-to" class="form-control form-control-sm" value="{{ $defaults['to'] ?? '' }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small" for="rec-spec">Specialization/Industry</label>
                    <input type="text" name="specialization" id="rec-spec" class="form-control form-control-sm" value="{{ $industry }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small" for="rec-evaluator">Evaluator Name</label>
                    <input type="text" name="evaluator" id="rec-evaluator" class="form-control form-control-sm" value="{{ $application->assignedEvaluator?->name ?? auth()->user()->name }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-semibold small" for="rec-interviewers">
                        Interviewers (One per line) <span class="text-muted fw-normal prac-hint-inline">— Press Enter after each name to add each interviewer on a new line.</span>
                    </label>
                    <textarea name="interviewers" id="rec-interviewers" class="form-control form-control-sm" rows="3" placeholder="Enter interviewer names, one per line"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small" for="rec-recommended">Recommended By</label>
                    <input type="text" name="recommended_by" id="rec-recommended" class="form-control form-control-sm" value="{{ $defaults['recommended_by'] ?? '' }}" placeholder="Division Chief" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small" for="rec-approved">Approved By</label>
                    <input type="text" name="approved_by" id="rec-approved" class="form-control form-control-sm" value="{{ $defaults['approved_by'] ?? '' }}" required>
                </div>
            </div>

            <div class="mt-4 text-center">
                <button type="submit" class="btn btn-primary fw-semibold text-white px-5 py-2 prac-pill-btn">
                    <i class="fas fa-file-pdf me-2"></i>Generate Recommendation Form
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ══ Signed Recommendation & Payment Verification ══ --}}
<div class="ai-card mb-4 prac-verify-card">
    <div class="ai-card-header">
        <i class="bi bi-shield-check fs-5 text-dark"></i>
        <h5 class="fw-bold text-dark mb-0">Signed Recommendation & Payment Verification</h5>
    </div>
    <div class="x_content p-3 mt-2">
        <p class="text-muted small">
            Upload the recommendation form once it is signed (offline by the Division Chief and the OED) — the applicant is then
            emailed the payment instructions ({{ $application->paymentAmount() }} Pesos) — and validate the proof of payment they upload.
        </p>

        <div class="row g-3">
            {{-- Signed recommendation form --}}
            <div class="col-md-6">
                <div class="prac-verify-box is-primary">
                    <h6 class="fw-bold prac-verify-title"><i class="fas fa-file-signature me-1"></i> Signed Recommendation Form (PDF) <span class="text-danger">*</span></h6>
                    @if($hasSignedLetter)
                        <div class="mt-3">
                            <div class="text-success fw-semibold small mb-1"><i class="fas fa-check-circle me-1"></i>Already uploaded</div>
                            <div class="text-muted small mb-2 prac-file-name">{{ basename($payment->signed_recommendation_letter) }}</div>
                            <a href="{{ route('admin.accreditation.payments.view', ['payment' => $payment->id, 'fileType' => 'signed_recommendation_letter']) }}?v={{ $payment->updated_at?->timestamp ?? time() }}"
                               data-file-modal data-file-title="Signed Recommendation Form"
                               class="btn btn-outline-success btn-sm fw-semibold px-3">
                                <i class="fas fa-eye me-1"></i> View Signed Form
                            </a>
                        </div>
                    @else
                        <form action="{{ route('admin.accreditation.practitioner.upload_recommendation', $application->id) }}" method="POST" enctype="multipart/form-data"
                              class="mt-2" data-prac-busy-form>
                            @csrf
                            <input type="file" name="signed_recommendation_letter" class="form-control" accept=".pdf" required>
                            <div class="mt-2 text-danger small"><i class="fas fa-exclamation-circle"></i> Missing: upload the scanned signed recommendation form to send the payment instructions.</div>
                            <button type="submit" class="btn btn-primary fw-semibold px-4 mt-3" data-prac-busy-label="Uploading…">
                                <i class="fas fa-upload me-1"></i> Upload &amp; Send Payment Instructions
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Proof of payment --}}
            <div class="col-md-6">
                <div class="prac-verify-box">
                    <h6 class="fw-bold text-dark mb-2">Proof of Payment</h6>
                    <div class="mt-2 mb-2 text-center">
                        @if($proofStatus === 'rejected')
                            <div class="alert alert-warning mt-3 mb-0 small text-start">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Rejected.</strong> Waiting for applicant to resubmit.
                                @if($payment->proof_of_payment_remarks)<br><span class="text-muted">{{ $payment->proof_of_payment_remarks }}</span>@endif
                                @if($application->compliance_due_date)
                                    <br><strong>Deadline:</strong> {{ $application->compliance_due_date->format('F d, Y') }} (5 working days) — archived automatically after it.
                                @endif
                            </div>
                        @elseif($proofStatus)
                            <a href="{{ route('admin.accreditation.payments.view', ['payment' => $payment->id, 'fileType' => 'proof_of_payment']) }}?v={{ $payment->updated_at?->timestamp ?? time() }}"
                               data-file-modal data-file-title="Proof of Payment" class="btn btn-outline-primary btn-xs mt-2 px-3 py-1 fw-semibold">
                                <i class="fas fa-eye me-1"></i> View Proof of Payment
                            </a>
                            <div class="text-muted small mt-2 prac-file-name">{{ basename($payment->proof_of_payment) }}</div>
                        @else
                            <span class="badge bg-secondary mt-3">Not Uploaded Yet</span>
                        @endif
                    </div>

                    @if($proofStatus === 'approved')
                        <div class="alert alert-success mt-3 mb-0 small text-center fw-semibold">
                            <i class="fas fa-check-circle me-1"></i> Payment validated. Certificate issuance is the next stage.
                        </div>
                    @elseif($proofStatus === 'pending' && $status === 'Payment Verification')
                        <form id="evaluate-payment-form" action="{{ route('admin.accreditation.practitioner.evaluate_payment', $application->id) }}" method="POST" class="mt-3 pt-3 border-top">
                            @csrf
                            <label class="form-label small fw-semibold d-block mb-2">Status</label>
                            <div class="d-flex gap-2">
                                <input type="radio" class="btn-check" name="proof_of_payment_status" id="pop_app" value="approved" required autocomplete="off" data-prac-remarks-toggle="">
                                <label class="btn btn-outline-success fw-bold flex-fill py-2" for="pop_app"><i class="bi bi-check-circle-fill me-1"></i> Approve</label>

                                <input type="radio" class="btn-check" name="proof_of_payment_status" id="pop_rej" value="rejected" required autocomplete="off" data-prac-remarks-toggle="pop-remarks">
                                <label class="btn btn-outline-danger fw-bold flex-fill py-2" for="pop_rej"><i class="bi bi-x-circle-fill me-1"></i> Reject</label>
                            </div>

                            <div class="mt-2 is-hidden" id="pop-remarks">
                                <label class="form-label small text-muted" for="pop-remarks-input">Rejection Remarks</label>
                                <textarea name="proof_of_payment_remarks" id="pop-remarks-input" class="form-control form-control-sm" rows="2" placeholder="State reason for rejection..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary fw-semibold px-4 mt-3">
                                <span class="btn-text"><i class="fas fa-save me-1"></i> Submit Evaluation</span>
                                <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm me-2" role="status"></span> Submitting...</span>
                            </button>
                        </form>
                    @elseif($hasSignedLetter)
                        <div class="alert alert-info py-2 px-3 mt-3 mb-0 small">
                            <i class="fas fa-info-circle me-1"></i> Waiting for the applicant to upload or resubmit Proof of Payment.
                        </div>
                    @else
                        <div class="text-muted small mt-3 text-center">The applicant is asked to pay once the signed recommendation form is uploaded.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
            <button type="button" class="btn btn-danger fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#archivePaymentModal">
                <i class="fas fa-archive me-1"></i> Archive Application
            </button>
        </div>
    </div>
</div>

{{-- ══ Archive Application — FATPro's confirmation ══ --}}
<div class="modal fade" id="archivePaymentModal" tabindex="-1" aria-labelledby="archivePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header is-danger">
                <h5 class="modal-title text-white fw-bold mb-0" id="archivePaymentModalLabel"><i class="fas fa-exclamation-triangle me-1"></i> Confirm Archive Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <p>Are you sure you want to archive/reject this application? This action is <strong>permanent</strong> and will notify the applicant that they did not pass the requirements.</p>
                <ul class="mb-0">
                    <li><strong>Tracking Number:</strong> {{ $application->tracking_number }}</li>
                    <li><strong>Practitioner Name:</strong> {{ $application->user?->name ?? 'N/A' }}</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.accreditation.practitioner.archive', $application->id) }}" method="POST" class="d-inline" data-prac-busy-form>
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold" data-prac-busy-label="Archiving…">Archive Application</button>
                </form>
            </div>
        </div>
    </div>
</div>
