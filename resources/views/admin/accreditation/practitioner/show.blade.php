@extends('layouts.admin')

@section('title', 'Application – ' . $application->tracking_number)

@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/show-application.css') }}">
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/accreditation-practitioner.css') }}">
@endpush

@section('content')
{{--
    OSH Practitioner application — the HCD portal's FATPro evaluation page
    (admin/hcd/show_application_info) for the Accreditation division:
    the same PCT timeline, document rows, Approve / Reject buttons and
    "Send Rejection Email" / "Save Approvals" button, driven by the same
    public/js/evaluation.js. The AF-PCN-A1 form is the first document row.
--}}
@php
    $user        = $application->user;
    $profile     = $user?->individualProfile;
    $prac        = $application->practitionerProfile;
    $isForUpdate = $status === 'For Update';
    $isPending   = $status === 'Submitted';
    $formCode    = \App\Services\PractitionerApplicationService::applicationFormCode($application->accreditation_type_id);
    // OSH Consultant runs through this same page; its lists are its own.
    $listRoute   = $application->isConsultant() ? 'admin.accreditation.consultant' : 'admin.accreditation.practitioner';
    $formPath    = $prac?->application_form_path;

    $totalDocs    = $documents->count();
    $approvedDocs = $documents->where('status', 'approved')->count();

    $forInterview = $status === 'Scheduled for Interview';
    $slot         = $application->interviewSlot;
    $isArchived   = $status === 'Rejected';

    $statusColor = match ($status) {
        'For Update'              => 'bg-warning text-dark',
        'Rejected'                => 'bg-danger',
        'Submitted'               => 'bg-secondary',
        'Scheduled for Interview' => 'bg-primary',
        'For Recommendation'      => 'bg-success',
        'Awaiting Payment'        => 'bg-warning text-dark',
        'Payment Verification'    => 'bg-success',
        'Approved'                => 'bg-success',
        default                   => 'bg-info',
    };

    $backUrl = match (true) {
        in_array($status, ['Under Evaluation', 'For Update'], true) => route($listRoute . '.new.under_review'),
        $forInterview && $slot !== null                            => route('admin.accreditation.division.interviews.scheduled'),
        $forInterview                                              => route('admin.accreditation.division.interviews.slots'),
        in_array($status, ['For Recommendation', 'Awaiting Payment', 'Payment Verification'], true) => route('admin.accreditation.applications.awaiting_payment'),
        $status === 'Approved' && ! $application->accreditation?->released_at => route('admin.accreditation.applications.releasing'),
        $status === 'Approved'                                     => route('admin.accreditation.division.accreditations.active'),
        $isArchived                                                => route('admin.accreditation.division.applications.archived'),
        default                                                    => route($listRoute . '.new.pending'),
    };
@endphp

{{-- Read by accreditation-practitioner.js into window.ARMS for evaluation.js. --}}
<div id="prac-eval-config" hidden
     data-csrf="{{ csrf_token() }}"
     data-evaluate-item-url="{{ route('admin.accreditation.practitioner.evaluate_item', $application->id) }}"
     data-application-status="{{ $status }}"
     data-application-id="{{ $application->id }}"
     data-server-time="{{ now()->timestamp * 1000 }}"
     data-active-step="{{ $activeStep }}"
     data-pct-status="{{ $pctStatus }}"
     data-approval-label="Save Approvals and Proceed to Interview"
     data-holidays="{{ json_encode($holidays) }}"
     data-interview-timestamp="{{ $interviewState === 'waiting' && $interviewStartsAt ? $interviewStartsAt->timestamp * 1000 : '' }}"
     data-interview-mode="{{ $interview?->mode }}"
     data-interview-link="{{ $interviewLink }}"
     data-max-interview-seconds="{{ $maxInterviewSeconds }}"></div>

{{-- ── Flash Messages ── --}}
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@include('admin.accreditation.practitioner._flash')

{{-- ── Page Header ── --}}
<div class="page-title d-flex justify-content-between align-items-center">
    <div class="title_left">
        <h3>Application Details</h3>
    </div>
    <a href="{{ $backUrl }}" class="btn btn-secondary btn-sm mt-3">Back</a>
</div>
<div class="clearfix"></div>

{{-- ── Tracking Strip ── --}}
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <div class="prac-strip-label">Tracking Number</div>
        <h4 class="prac-strip-number">{{ $application->tracking_number }}</h4>
        <small class="text-muted d-block"><i class="bi bi-calendar3 me-1"></i>Submitted: {{ ($application->submitted_at ?? $application->created_at)->format('F d, Y h:i A') }}</small>
        <small class="text-muted d-block"><i class="bi bi-person-check me-1"></i>In-Charge: {{ $application->assignedEvaluator?->name ?? 'Evaluation not started' }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        <div class="d-flex align-items-center gap-2">
            <span id="app-status-badge" class="badge fs-6 px-3 py-2 {{ $statusColor }}">{{ $status }}</span>
            @if($isPending)
            <button type="button" class="btn btn-primary btn-sm m-0 fw-bold prac-strip-btn" data-bs-toggle="modal" data-bs-target="#startEvalModal{{ $application->id }}">
                <i class="bi bi-play-fill me-1"></i> Start Evaluation
            </button>
            @endif
        </div>
        <small class="text-muted">New Application &mdash; {{ $application->accreditationTitle() }}</small>
    </div>
</div>

{{-- ── Accreditation strip (FATPro's), once the payment is validated ── --}}
@if($application->accreditation)
@php
    $acc        = $application->accreditation;
    $accArchived = $acc->status === 'archived';
@endphp
<div class="ai-card prac-acc-strip {{ $accArchived ? 'is-archived' : '' }} d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <div class="prac-acc-label"><i class="bi bi-patch-check-fill me-1"></i>Accreditation Number</div>
        <h4 class="m-0 fw-bold prac-acc-number">{{ $acc->accreditation_number }}</h4>
        <small class="prac-acc-meta"><i class="bi bi-calendar-check me-1"></i>Valid Until: {{ $acc->validity_date->format('F d, Y') }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge fs-6 px-3 py-2 {{ $accArchived ? 'bg-secondary' : ($acc->status === 'expired' ? 'bg-warning text-dark' : 'bg-success') }} text-white">
                <i class="bi {{ $accArchived ? 'bi-archive-fill' : 'bi-check-circle-fill' }} me-1"></i> {{ ucfirst($acc->status) }}
            </span>
            <button type="button" class="btn btn-success btn-sm m-0 fw-semibold prac-acc-btn {{ ! $acc->scanned_certificate ? 'btn-pulse-highlight' : '' }}"
                    data-bs-toggle="modal" data-bs-target="#certDirectorModal">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> View Certificate PDF
            </button>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($accArchived)
                <button type="button" class="btn btn-success btn-sm m-0 fw-bold prac-acc-small-btn" data-bs-toggle="modal" data-bs-target="#unarchiveAccreditationModal">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Unarchive
                </button>
            @else
                <button type="button" class="btn btn-outline-secondary btn-sm m-0 fw-bold prac-acc-small-btn" data-bs-toggle="modal" data-bs-target="#archiveAccreditationModal">
                    <i class="bi bi-archive me-1"></i> Move to Archived
                </button>
            @endif
        </div>
        <small class="prac-acc-meta">{{ $application->accreditationTitle() }}</small>
    </div>
</div>

{{-- ══ Move to Archived / Unarchive — FATPro's confirmations ══ --}}
@php
    $accModal = $accArchived
        ? ['id' => 'unarchiveAccreditationModal', 'title' => 'Unarchive Accreditation', 'tone' => 'is-success', 'icon' => 'bi-arrow-counterclockwise',
           'route' => 'admin.accreditation.practitioner.accreditation.unarchive', 'button' => 'Unarchive', 'buttonClass' => 'btn-success',
           'text' => 'The accreditation will be restored to ' . ($acc->validity_date?->isPast() ? 'Expired' : 'Active') . ' and the practitioner will be able to log in to their portal again.']
        : ['id' => 'archiveAccreditationModal', 'title' => 'Move to Archived List', 'tone' => 'is-danger', 'icon' => 'bi-archive-fill',
           'route' => 'admin.accreditation.practitioner.accreditation.archive', 'button' => 'Move to Archived', 'buttonClass' => 'btn-danger',
           'text' => 'The accreditation will be moved to the Archived Applications list, and the practitioner will no longer be able to log in to their portal. You can unarchive it later.'];
@endphp
<div class="modal fade" id="{{ $accModal['id'] }}" tabindex="-1" aria-labelledby="{{ $accModal['id'] }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header {{ $accModal['tone'] }}">
                <div class="d-flex align-items-center gap-3">
                    <span class="prac-modal-icon"><i class="bi {{ $accModal['icon'] }}"></i></span>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="{{ $accModal['id'] }}Label">{{ $accModal['title'] }}</h5>
                        <small class="text-white-50">{{ $acc->accreditation_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-0">{{ $accModal['text'] }}</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route($accModal['route'], $application->id) }}" data-prac-busy-form>
                    @csrf
                    <button type="submit" class="btn {{ $accModal['buttonClass'] }} fw-bold px-4" data-prac-busy-label="Saving…">{{ $accModal['button'] }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Process Cycle Time ── --}}
@include('admin.accreditation.practitioner._pct_timeline')

{{-- ── Archived ── --}}
@if($isArchived && $application->archived_at)
<div class="ai-card mb-4 prac-archived-card">
    <div class="ai-card-header">
        <span class="prac-card-icon is-danger"><i class="bi bi-archive-fill"></i></span>
        <h5 class="mb-0">Archived</h5>
    </div>
    <div class="prac-notice is-danger">
        <i class="bi bi-x-octagon-fill"></i>
        <div>
            <strong>{{ $archiveReason }}</strong><br>
            Archived on {{ $application->archived_at->format('F d, Y h:i A') }}.
            @if($application->reapply_after)
                The applicant may apply again on or after <strong>{{ $application->reapply_after->format('F d, Y') }}</strong>,
                when this record and its files are deleted automatically.
            @else
                The record is kept in the Archived list.
            @endif
        </div>
    </div>
</div>
@endif

{{-- ── Waiting for the applicant to book an interview slot ── --}}
@if($forInterview && ! $interview)
<div class="ai-card mb-4">
    <div class="ai-card-header">
        <span class="prac-card-icon"><i class="bi bi-calendar2-check"></i></span>
        <h5 class="mb-0">Interview</h5>
    </div>
    <div class="prac-notice">
        <i class="bi bi-hourglass-split"></i>
        All documents approved. The applicant has been emailed to choose an interview schedule from the open
        <a href="{{ route('admin.accreditation.division.interviews.slots') }}">interview slots</a> on the tracking page.
    </div>
</div>
@endif

{{-- ── Applicant ── --}}
<div class="ai-card mb-4">
    <div class="ai-card-header">
        <i class="bi bi-person-vcard fs-5 text-dark"></i>
        <h5>Applicant Information</h5>
    </div>
    <div class="prac-applicant-row">
        @if($prac?->photo_path)
            <img src="{{ route('admin.accreditation.practitioner.photo', $application->id) }}" alt="1x1 ID photo" class="prac-photo">
        @endif
        <div class="row flex-grow-1">
            <div class="col-md-4 col-6">
                <div class="info-pair"><div class="lbl">Full Name</div><div class="val">{{ $user?->name ?? 'N/A' }}</div></div>
            </div>
            <div class="col-md-4 col-6">
                <div class="info-pair"><div class="lbl">Email</div><div class="val">{{ $user?->email ?? 'N/A' }}</div></div>
            </div>
            <div class="col-md-4 col-6">
                <div class="info-pair"><div class="lbl">Contact Number</div><div class="val">{{ $profile?->contact_number ?: 'N/A' }}</div></div>
            </div>
            <div class="col-md-4 col-6">
                <div class="info-pair"><div class="lbl">Sex</div><div class="val">{{ $profile?->sex ?: 'N/A' }}</div></div>
            </div>
            <div class="col-md-4 col-6">
                <div class="info-pair"><div class="lbl">Address</div><div class="val">{{ collect([$profile?->address, $profile?->city, $profile?->region])->filter()->implode(', ') ?: 'N/A' }}</div></div>
            </div>
            <div class="col-md-4 col-6">
                <div class="info-pair"><div class="lbl">OSH Experience</div><div class="val">{{ $prac?->total_osh_experience_years ?? 0 }} year(s)</div></div>
            </div>
        </div>
    </div>
</div>

{{-- ══ Evaluation Form ══ --}}
<form id="evaluation-form" data-url="{{ route('admin.accreditation.practitioner.finalize_evaluation', $application->id) }}">
    @csrf

    <div class="ai-card mb-4">
        <div class="ai-card-header prac-card-toggle" data-bs-toggle="collapse" data-bs-target="#submittedDocumentsBody" aria-expanded="true">
            <span class="prac-card-icon"><i class="bi bi-folder2-open"></i></span>
            <h5 class="mb-0">Application Form &amp; Submitted Documents</h5>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="badge bg-success prac-pct-total-badge" id="submitted-docs-progress">{{ $approvedDocs }} / {{ $totalDocs }} Accepted</span>
                <i class="bi bi-chevron-down" id="docsChevron"></i>
            </div>
        </div>

        <div id="submittedDocumentsBody" class="collapse show">
            <div class="pt-2">
                @if($isPending)
                    <div class="prac-notice mb-3">
                        <i class="bi bi-info-circle-fill"></i>
                        Start the evaluation to approve or reject the application form and documents.
                    </div>
                @elseif($isForUpdate)
                    <div class="prac-notice is-warning mb-3">
                        <i class="bi bi-hourglass-split"></i>
                        <div>
                            Waiting for the applicant to re-upload the rejected items. The PCT clock is paused.
                            @if($application->compliance_due_date)
                                <br>Re-upload deadline: <strong>{{ $application->compliance_due_date->format('l, F d, Y') }}</strong>
                                (5 working days). If nothing is received by then, the application is archived and the applicant may apply again after 1 month.
                            @endif
                        </div>
                    </div>
                @endif

                @if($totalDocs > 0)
                <div class="doc-section" id="folder-section-prac">
                    <div class="doc-section-header d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-folder-fill me-1"></i> {{ $application->accreditationTitle() }} Requirements</div>
                        <span class="badge bg-secondary" id="folder-progress-prac">{{ $approvedDocs }} / {{ $totalDocs }} Accepted</span>
                    </div>

                    @foreach($documents as $doc)
                    @php
                        $field      = $doc->documentField;
                        $filePath   = $doc->userDocument?->file_path;
                        $isForm     = $field?->code === $formCode;
                        $evalStatus = in_array($doc->status, ['approved', 'rejected', 'returned'], true) ? $doc->status : 'pending';

                        $badgeClass = match ($doc->status) {
                            'approved'             => 'doc-badge-approved',
                            'rejected', 'returned' => 'doc-badge-for_revision',
                            default                => 'doc-badge-pending',
                        };
                        $badgeLabel = match ($doc->status) {
                            'approved' => 'Approved',
                            'rejected' => $isForUpdate ? 'Awaiting Re-upload' : 'Rejected',
                            'returned' => 'Awaiting Re-upload',
                            default    => 'Pending',
                        };

                        $viewUrl = $isForm
                            ? route('admin.accreditation.practitioner.application_form', $application->id)
                            : route('admin.accreditation.documents.view', $doc->id);
                    @endphp
                    <div class="doc-row" id="doc-row-{{ $doc->id }}">
                        <input type="hidden" name="evaluations[{{ $doc->id }}][id]" value="{{ $doc->id }}">
                        <input type="hidden" name="evaluations[{{ $doc->id }}][status]"
                               id="status-input-{{ $doc->id }}" value="{{ $evalStatus }}" data-db-status="{{ $doc->status }}">

                        <div class="doc-field-name">
                            <i class="bi {{ $isForm ? 'bi-file-earmark-text text-primary' : 'bi-file-earmark-pdf text-danger' }} me-1"></i>
                            {{ $field?->name ?? 'Unknown Field' }}
                        </div>

                        <div class="doc-value prac-doc-file">
                            {{ $isForm && $filePath === $formPath ? 'Generated from the online application' : basename($filePath) }}
                        </div>

                        <span class="doc-badge {{ $badgeClass }}" id="badge-{{ $doc->id }}">{{ $badgeLabel }}</span>

                        <div class="doc-actions">
                            <a href="{{ $viewUrl }}?v={{ $doc->updated_at?->timestamp ?? time() }}"
                               data-file-modal data-file-title="{{ $field?->name ?? 'Document' }}"
                               class="btn btn-outline-primary btn-xs px-2 py-0 prac-doc-view">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </div>

                        @if($canEvaluate || $isForUpdate)
                            @if($canEvaluate)
                            <div class="doc-eval-actions">
                                <button type="button" class="btn-eval btn-approve {{ $evalStatus === 'approved' ? 'active' : '' }}"
                                        data-doc-id="{{ $doc->id }}" data-eval-status="approved">
                                    <i class="bi bi-check-circle-fill"></i> Approve
                                </button>
                                <button type="button" class="btn-eval btn-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}"
                                        data-doc-id="{{ $doc->id }}" data-eval-status="rejected">
                                    <i class="bi bi-x-circle-fill"></i> Reject
                                </button>
                            </div>
                            @endif
                            <div class="reject-panel {{ in_array($evalStatus, ['rejected', 'returned'], true) ? '' : 'is-hidden' }}" id="reject-panel-{{ $doc->id }}">
                                <label class="reject-remarks-label" for="remarks-{{ $doc->id }}">
                                    <i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span class="text-muted">(optional)</span>
                                </label>
                                <textarea class="reject-remarks-input"
                                          name="evaluations[{{ $doc->id }}][remarks]"
                                          id="remarks-{{ $doc->id }}"
                                          placeholder="Explain why this document was rejected…"
                                          rows="2"
                                          data-default-readonly="{{ $isForUpdate ? 'true' : 'false' }}"
                                          {{ $isForUpdate ? 'readonly' : '' }}>{{ $doc->remarks }}</textarea>
                            </div>
                        @elseif($doc->remarks)
                            <div class="prac-doc-remarks"><i class="bi bi-chat-left-text me-1"></i>{{ $doc->remarks }}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @else
                <div class="alert alert-secondary text-center mb-0">
                    No documents have been uploaded for this application.
                </div>
                @endif
            </div>
        </div>
    </div>
</form>

{{-- The single evaluation button; evaluation.js switches it between
     "Pending Documents", "Send Rejection Email" and "Save Approvals". --}}
@if($canEvaluate || $isForUpdate)
<div class="mt-4 mb-4 text-center">
    <button type="button" id="btn-open-schedule" class="btn btn-outline-primary btn-sm fw-semibold px-4" disabled>
        <span id="btn-schedule-icon"></span>
        <span id="btn-schedule-text">Save Approvals</span>
    </button>
</div>
@endif

{{-- ── Interview Schedule Details — FATPro's interview card ── --}}
@if($interview && $status !== 'Under Evaluation' && $status !== 'For Update')
    @include('admin.accreditation.practitioner._interview_card')
@endif

{{-- ── Recommendation form, signed scan and payment — FATPro's panels ── --}}
@if(in_array($status, ['For Recommendation', 'Awaiting Payment', 'Payment Verification'], true))
    @include('admin.accreditation.practitioner._recommendation_payment')
@endif

{{-- ── Certificate Issuance: signed certificate, ID card, release ── --}}
@if($status === 'Approved' && $application->accreditation)
    @include('admin.accreditation.practitioner._certificate', ['accreditation' => $application->accreditation])
@endif

@if($isPending)
    @include('admin.accreditation.practitioner._start_modal', ['application' => $application])
@endif

{{-- ══ Generate Certificate — FATPro's Executive Director prompt ══ --}}
@if($application->accreditation)
<div class="modal fade" id="certDirectorModal" tabindex="-1" aria-labelledby="certDirectorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content prac-modal">
            <div class="modal-header py-3 px-4 prac-modal-header is-success">
                <h6 class="modal-title text-white fw-bold mb-0" id="certDirectorModalLabel">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Generate Certificate
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET" action="{{ route('admin.accreditation.practitioner.certificate', $application->id) }}" target="_blank" data-prac-modal-close>
                <div class="modal-body px-4 py-4">
                    <small class="text-muted d-block mb-2">Name of signatory on the certificate.</small>
                    <input type="text" class="form-control" id="cert-director-name" name="executive_director"
                           value="JOSE MARIA S. BATINO" placeholder="Enter Executive Director name" required>
                    <label for="cert-director-name" class="form-label fw-semibold mt-2 mb-0 d-block text-center small">Executive Director Name</label>

                    {{-- The back page's CERTIFICATION signatory (OIC-Deputy Executive Director):
                         starts blank — the officer changes, so it is typed each time. --}}
                    <small class="text-muted d-block mt-4 mb-2">Signatory of the certification on the back.</small>
                    <input type="text" class="form-control" id="cert-back-signatory" name="back_signatory"
                           value="" placeholder="Enter OIC-Deputy Executive Director name" autocomplete="off" required>
                    <label for="cert-back-signatory" class="form-label fw-semibold mt-2 mb-0 d-block text-center small">OIC-Deputy Executive Director Name</label>
                    <input type="text" class="form-control mt-3" id="cert-back-signatory-title" name="back_signatory_title"
                           value="{{ config('accreditation.certificate_back_signatory.title') }}" placeholder="Enter position" required>
                    <label for="cert-back-signatory-title" class="form-label fw-semibold mt-2 mb-0 d-block text-center small">Position</label>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-semibold px-3"><i class="bi bi-file-earmark-arrow-down me-1"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($interviewState === 'running' || $interviewState === 'result')
    @include('admin.accreditation.practitioner._interview_modals', ['application' => $application])
@endif

{{-- ══ Rejection Confirmation Modal ══ --}}
<div class="modal fade" id="rejectionConfirmModal" tabindex="-1" aria-labelledby="rejectionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 pb-2 prac-modal-header is-danger">
                <div class="d-flex align-items-center gap-3">
                    <span class="prac-modal-icon"><i class="bi bi-envelope-exclamation-fill"></i></span>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="rejectionConfirmModalLabel">Send Rejection Email</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4 prac-modal-body">
                <div class="d-flex align-items-center gap-3 mb-3 p-3 prac-modal-applicant">
                    <i class="bi bi-person-circle fs-2 text-danger"></i>
                    <div>
                        <div class="fw-bold prac-modal-name">{{ $user?->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $user?->email }}</small>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-2 mb-3 p-3 prac-modal-warning">
                    <i class="bi bi-exclamation-triangle-fill text-warning mt-1"></i>
                    <small class="text-dark">
                        An email will be sent to the applicant listing the rejected documents and remarks. The application status will change to <strong>For Update</strong>.
                    </small>
                </div>

                <h6 class="fw-bold mb-2 prac-modal-subtitle"><i class="bi bi-x-circle-fill me-1"></i>Rejected Documents</h6>
                <div id="rejection-doc-list" class="d-flex flex-column gap-2"></div>
            </div>

            <div class="modal-footer border-0 pt-2 prac-modal-body">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Go Back</button>
                <button type="button" id="btn-confirm-rejection" class="btn btn-danger fw-bold px-5">
                    <i class="bi bi-send-fill me-2"></i>Confirm &amp; Send Email
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Config first: evaluation.js reads window.ARMS as it loads. --}}
<script src="{{ \App\Support\AssetVersion::url('js/accreditation-practitioner.js') }}"></script>
<script src="{{ \App\Support\AssetVersion::url('js/evaluation.js') }}"></script>
@endpush
