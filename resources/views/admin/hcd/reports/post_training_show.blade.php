@extends('layouts.admin')

@section('title', 'Post Training Report — ' . $postTrainingReport->reference_number)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/show-application.css') }}?v={{ filemtime(public_path('css/show-application.css')) }}">
<link rel="stylesheet" href="{{ asset('css/post-training.css') }}?v={{ filemtime(public_path('css/post-training.css')) }}">
@endpush

@section('content')
@php
// $fatproUser, $isOrg, $org, $ind, $reps, $accreditation come from the controller
$fatproName = $isOrg
    ? ($org->name ?? $fatproUser?->name ?? '—')
    : ($fatproUser?->name ?? '—');

$ntc           = $postTrainingReport->ntcReport;
$allDocuments  = $postTrainingReport->documents->sortBy(fn($d) => $d->documentType->sort_order ?? 0);
$totalDocs     = $allDocuments->count();
$approvedDocs  = $allDocuments->where('status', 'approved')->count();

$isAccepted = $postTrainingReport->isAccepted();
$isLate     = $postTrainingReport->wasSubmittedLate();
@endphp

{{-- Flash messages --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Page header --}}
<div class="page-title d-flex justify-content-between align-items-center">
    <div class="title_left">
        <h3><i class="fas fa-flag-checkered me-2" style="color:var(--portal-gold);"></i> Post Training Report Evaluation</h3>
    </div>
    <a href="{{ route('admin.hcd.reports.post_training.index') }}" class="btn btn-secondary btn-sm mt-3">
        Back to Post Training Report List
    </a>
</div>
<div class="clearfix"></div>

{{-- ── Reference / Status Strip ── --}}
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
     style="background: linear-gradient(135deg,#eef5ff,#dbeafe); border: 1px solid #bfdbfe;">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;letter-spacing:.45px;">
            <i class="bi bi-clipboard-data me-1"></i>Reference Number
        </div>
        <h4 class="m-0 fw-bold" style="color:#1e40af;">{{ $postTrainingReport->reference_number }}</h4>
        <small style="color:#1d4ed8;">Related NTC: {{ $ntc->reference_number ?? '—' }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-1">
        @if($isAccepted)
        <span class="badge fs-6 px-3 py-2 bg-success">
            <i class="bi bi-check-circle-fill me-1"></i> Accepted
        </span>
        @elseif($allDocuments->contains(fn($d) => $d->status === 'rejected' && !$d->file_path))
        <span class="badge fs-6 px-3 py-2 bg-danger">
            <i class="bi bi-x-circle-fill me-1"></i> Documents Declined
        </span>
        @else
        <span class="badge fs-6 px-3 py-2 bg-warning text-dark">
            <i class="bi bi-hourglass-split me-1"></i> Under Review
        </span>
        @endif
        <small style="color:#1d4ed8; font-size:.78rem;">
            Submitted: {{ $postTrainingReport->submitted_at?->format('M d, Y h:i A') ?? '—' }}
        </small>
    </div>
</div>

{{-- ── Deadline Compliance Card ── --}}
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 {{ $isLate ? 'ptr-deadline-card-late' : 'ptr-deadline-card-ontime' }}">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:{{ $isLate ? '#991b1b' : '#166534' }};letter-spacing:.45px;">
            <i class="bi bi-calendar-check me-1"></i>Submission Deadline
        </div>
        <h4 class="m-0 fw-bold" style="color:{{ $isLate ? '#991b1b' : '#166534' }};">
            {{ $postTrainingReport->due_date?->format('F d, Y') ?? 'N/A' }}
        </h4>
        <small style="color:{{ $isLate ? '#991b1b' : '#166534' }};">
            {{ $ntc?->postTrainingDaysAllowed() ?? '—' }} working
            {{ \Illuminate\Support\Str::plural('day', $ntc?->postTrainingDaysAllowed() ?? 1) }}
            after the last training day ({{ $ntc?->trainingType->name ?? 'N/A' }})
        </small>
    </div>
    <div>
        <span class="badge fs-6 px-3 py-2 {{ $isLate ? 'bg-danger' : 'bg-success' }}">
            {{ $isLate ? 'Late Submission' : 'Submitted On Time' }}
        </span>
    </div>
</div>

{{-- ── Accreditation Number Card ── --}}
@php
    $accStatus = $accreditation->status ?? 'unknown';
    $accBgStyle = match($accStatus) {
        'active'  => 'background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #bbf7d0;',
        'expired' => 'background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1px solid #fde68a;',
        'revoked' => 'background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #fecaca;',
        default   => 'background: #f8f9fa; border: 1px solid #dee2e6;',
    };
    $accTextColor = match($accStatus) {
        'active'  => '#166534',
        'expired' => '#92400e',
        'revoked' => '#991b1b',
        default   => '#2A3F54',
    };
    $accBadgeClass = match($accStatus) {
        'active'  => 'bg-success',
        'expired' => 'bg-warning text-dark',
        'revoked' => 'bg-danger',
        default   => 'bg-secondary',
    };
    $accIcon = match($accStatus) {
        'active'  => 'bi-patch-check-fill',
        'expired' => 'bi-clock-history',
        'revoked' => 'bi-shield-x',
        default   => 'bi-award',
    };
@endphp
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3" style="{{ $accBgStyle }}">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:{{ $accTextColor }};letter-spacing:.45px;">
            <i class="bi {{ $accIcon }} me-1"></i>Accreditation Number
        </div>
        <h4 class="m-0 fw-bold" style="color:{{ $accTextColor }}; {{ $accStatus === 'revoked' ? 'text-decoration: line-through;' : '' }}">
            {{ $accreditation->accreditation_number ?? 'N/A' }}
        </h4>
        @if($accreditation->date_of_accreditation)
            <small style="color:{{ $accTextColor }}; display:block;">
                <i class="bi bi-calendar-check me-1"></i>
                Date Accredited: {{ \Carbon\Carbon::parse($accreditation->date_of_accreditation)->format('F d, Y') }}
            </small>
        @endif
        @if($accreditation->validity_date)
            <small style="color:{{ $accTextColor }}; display:block;">
                <i class="bi bi-calendar-event me-1"></i>
                Valid Until: {{ \Carbon\Carbon::parse($accreditation->validity_date)->format('F d, Y') }}
            </small>
        @endif
    </div>
    <div class="d-flex flex-column align-items-end gap-1">
        <span class="badge fs-6 px-3 py-2 {{ $accBadgeClass }}">{{ ucfirst($accStatus) }}</span>
        <small style="color:{{ $accTextColor }};">{{ $accreditation->accreditationType->name ?? 'N/A' }}</small>
    </div>
</div>

{{-- ══ Org / Reps Card ══ --}}
<div class="ai-card mb-4">
    <div class="ai-card-header">
        <i class="bi bi-building fs-5 text-dark"></i>
        <h5>Organization Information</h5>
    </div>
    @if($isOrg && $org)
    <div class="row">
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Organization Name</div>
                <div class="val">{{ $org->name }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Head of Organization</div>
                <div class="val">{{ $org->head_name ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Designation</div>
                <div class="val">{{ $org->designation ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Organization Email</div>
                <div class="val">{{ $org->email ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Telephone</div>
                <div class="val">{{ $org->telephone ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Fax</div>
                <div class="val">{{ $org->fax ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-6 col-12">
            <div class="info-pair mb-0">
                <div class="lbl">Address</div>
                <div class="val">{{ $org->address ?? '—' }}</div>
            </div>
        </div>
    </div>
    @elseif(!$isOrg && $ind)
    <div class="row">
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Full Name</div>
                <div class="val">{{ trim($ind->first_name . ' ' . $ind->last_name) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Email</div>
                <div class="val">{{ $fatproUser->email ?? '—' }}</div>
            </div>
        </div>
    </div>
    @else
    <p class="text-muted small mb-0">Organization profile not found.</p>
    @endif

    <hr style="border:0; border-top:1px dashed #dde3ef; margin:16px -20px;">

    <div class="ai-card-header">
        <i class="bi bi-person-badge fs-5 text-dark"></i>
        <h5>Authorized Representative(s)</h5>
    </div>
    @forelse($reps as $rep)
    <div class="rep-block">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Full Name</div>
                    <div class="val">{{ $rep->full_name }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Position</div>
                    <div class="val">{{ $rep->position ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair mb-0">
                    <div class="lbl">Contact Number</div>
                    <div class="val">{{ $rep->contact_number ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair mb-0">
                    <div class="lbl">Email</div>
                    <div class="val">{{ $rep->email ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <p class="text-muted small mb-0">No authorized representatives found.</p>
    @endforelse
</div>

{{-- ── Training Details Card ── --}}
<div class="ai-card mb-4">
    <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#ptrDetailsBody" aria-expanded="true">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#1A4A8A,#0D2B55);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-info-circle-fill text-white" style="font-size:.95rem;"></i>
        </div>
        <h5 class="mb-0">Training Details</h5>
        <i class="bi bi-chevron-down ms-auto"></i>
    </div>
    <div id="ptrDetailsBody" class="collapse show">
        <div class="row mt-2">
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Training Type</div>
                    <div class="val">{{ $ntc->trainingType->name ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Training Code</div>
                    <div class="val">{{ $ntc->trainingType->code ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Mode</div>
                    <div class="val">{{ $ntc->trainingMode->name ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">{{ (optional($ntc?->trainingMode)->code === 'BLENDED' || str_contains(strtolower($ntc->trainingMode->name ?? ''), 'blended')) ? 'Zoom Link / Meeting Link' : 'Venue' }}</div>
                    <div class="val">
                        @if($ntc && $ntc->venue)
                            @if(optional($ntc->trainingMode)->code === 'BLENDED' || str_contains(strtolower($ntc->trainingMode->name ?? ''), 'blended'))
                                <a href="{{ $ntc->venue }}" target="_blank" class="text-primary text-decoration-none"><i class="bi bi-camera-video me-1"></i>{{ $ntc->venue }}</a>
                            @else
                                <i class="bi bi-geo-alt me-1 text-danger"></i>{{ $ntc->venue }}
                            @endif
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">FATPro</div>
                    <div class="val">{{ $fatproName }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Training Start</div>
                    <div class="val">{{ $ntc?->training_start_date?->format('M d, Y') ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Training End</div>
                    <div class="val">{{ $ntc?->training_end_date?->format('M d, Y') ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Report Submitted At</div>
                    <div class="val">{{ $postTrainingReport->submitted_at?->format('M d, Y h:i A') ?? '—' }}</div>
                </div>
            </div>
            @if($isAccepted)
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Accepted By</div>
                    <div class="val">{{ $postTrainingReport->acceptedByUser?->name ?? '—' }}</div>
                </div>
            </div>
            @endif
        </div>
        @if($postTrainingReport->remarks)
        <div class="info-pair mt-2">
            <div class="lbl">Admin Remarks</div>
            <div class="val">{{ $postTrainingReport->remarks }}</div>
        </div>
        @endif
    </div>
</div>

{{-- ── Documents Evaluation Card ── --}}
<div class="ai-card mb-4">
    <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#ptrDocsBody" aria-expanded="true">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#1A4A8A,#0D2B55);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-folder2-open text-white" style="font-size:.95rem;"></i>
        </div>
        <h5 class="mb-0">Submitted Documents</h5>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="badge bg-success" id="ntc-docs-progress" style="font-size:.75rem;">
                {{ $approvedDocs }} / {{ $totalDocs }} Accepted
            </span>
            <i class="bi bi-chevron-down"></i>
        </div>
    </div>

    <div id="ptrDocsBody" class="collapse show">
        {{-- The evaluation script is shared with the NTC page, so this form keeps
             the same element ids and posts to the post training endpoints. --}}
        <form id="ntc-evaluation-form"
              data-url="{{ route('admin.hcd.reports.post_training.finalize_evaluation', $postTrainingReport->id) }}"
              method="POST">
            @csrf
            <div class="pt-2">
                @if($totalDocs === 0)
                <div class="alert alert-secondary text-center mb-0">No documents found for this post training report.</div>
                @else
                @foreach($allDocuments as $doc)
                @php
                    $docStatus  = $doc->status ?? 'pending';
                    $badgeClass = match($docStatus) {
                        'approved' => 'ntc-badge-approved',
                        'rejected' => 'ntc-badge-rejected',
                        'returned' => 'ntc-badge-returned',
                        default    => 'ntc-badge-pending',
                    };
                    $badgeLabel = match($docStatus) {
                        'approved' => 'Accepted',
                        'rejected' => 'Declined — Awaiting Re-upload',
                        'returned' => 'Re-uploaded — Awaiting Review',
                        default    => 'Pending',
                    };
                    $evalStatus = in_array($docStatus, ['approved','rejected','returned']) ? $docStatus : 'pending';
                @endphp
                <div class="ntc-doc-row" id="ntc-doc-row-{{ $doc->id }}">
                    <input type="hidden" name="evaluations[{{ $doc->id }}][id]" value="{{ $doc->id }}">
                    <input type="hidden" name="evaluations[{{ $doc->id }}][status]"
                           id="ntc-status-input-{{ $doc->id }}"
                           value="{{ $evalStatus }}"
                           data-db-status="{{ $doc->status }}"
                           data-has-file="{{ $doc->file_path ? 'true' : 'false' }}">

                    <div class="ntc-doc-name">
                        <i class="bi bi-file-earmark-text text-primary me-1"></i>
                        {{ $doc->documentType->name ?? 'Document' }}
                        <div class="ntc-doc-meta">
                            {{ $doc->original_filename }}
                            &bull; {{ number_format($doc->file_size / 1024, 1) }} KB
                            &bull; Uploaded {{ $doc->uploaded_at?->format('M d, Y h:i A') ?? '—' }}
                            @if($doc->evaluatedByUser)
                                &bull; Evaluated by {{ $doc->evaluatedByUser->name }} on {{ $doc->evaluated_at?->format('M d, Y') }}
                            @endif
                        </div>
                        @if($docStatus === 'rejected' && $doc->remarks)
                        <div class="ntc-doc-remark-box">
                            <i class="bi bi-chat-left-text me-1"></i>
                            <strong>Remarks:</strong> {{ $doc->remarks }}
                        </div>
                        @endif
                    </div>

                    <span class="badge {{ $badgeClass }} px-2 py-1"
                          style="font-size:.75rem;border-radius:20px;white-space:nowrap;"
                          id="ntc-badge-{{ $doc->id }}">{{ $badgeLabel }}</span>

                    @if($doc->file_path)
                    <div>
                        <a href="{{ route('admin.hcd.reports.post_training.document.view', $doc->id) }}"
                           data-file-modal
                           data-file-title="{{ $doc->documentType->name ?? 'Document' }}"
                           class="btn btn-outline-primary btn-xs px-2 py-0"
                           style="font-size:.78rem;">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </div>
                    @else
                    <div>
                        <span class="badge bg-secondary" style="font-size:.72rem;">
                            <i class="bi bi-trash me-1"></i>File Removed
                        </span>
                    </div>
                    @endif

                    @if(!$isAccepted && $doc->file_path)
                    <div class="doc-eval-actions" id="ntc-eval-actions-{{ $doc->id }}">
                        <button type="button"
                                class="btn-eval btn-approve {{ $evalStatus === 'approved' ? 'active' : '' }}"
                                id="btn-approve-{{ $doc->id }}"
                                onclick="setNtcDocStatus({{ $doc->id }}, 'approved')">
                            <i class="bi bi-check-circle-fill"></i> Accept
                        </button>
                        <button type="button"
                                class="btn-eval btn-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}"
                                id="btn-reject-{{ $doc->id }}"
                                onclick="setNtcDocStatus({{ $doc->id }}, 'rejected')">
                            <i class="bi bi-x-circle-fill"></i> Decline
                        </button>
                    </div>
                    @elseif(!$isAccepted && !$doc->file_path)
                    <div class="doc-eval-actions" id="ntc-eval-actions-{{ $doc->id }}">
                        <span class="badge bg-secondary" style="font-size:.75rem;">Awaiting re-upload from FATPro</span>
                    </div>
                    @endif

                    @if(!$isAccepted)
                    <div class="reject-panel w-100" id="ntc-reject-panel-{{ $doc->id }}"
                         style="{{ $evalStatus === 'rejected' ? '' : 'display:none;' }}">
                        <label class="reject-remarks-label">
                            <i class="bi bi-pencil-square me-1"></i>Decline Remarks <span class="text-muted">(optional)</span>
                        </label>
                        <textarea class="reject-remarks-input w-100"
                                  name="evaluations[{{ $doc->id }}][remarks]"
                                  id="ntc-remarks-{{ $doc->id }}"
                                  placeholder="Explain why this document was declined…"
                                  rows="2"
                                  {{ !$doc->file_path ? 'readonly' : '' }}>{{ $doc->remarks }}</textarea>
                    </div>
                    @endif
                </div>
                @endforeach
                @endif
            </div>
        </form>
    </div>
</div>

@if(!$isAccepted)
<div class="mt-4 mb-4 text-center">
    <div class="ntc-eval-saving-indicator mb-2 text-center" style="display: none;">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-light border text-primary small fw-semibold shadow-sm">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Saving evaluation changes...</span>
        </div>
    </div>
    <button type="button"
        id="btn-ntc-submit"
        class="btn btn-outline-secondary btn-sm fw-semibold px-4"
        disabled
        style="border-radius:6px;">
        <span id="btn-ntc-text">Pending Documents</span>
    </button>
</div>

{{-- ══ Decline Confirmation Modal ══ --}}
<div class="modal fade" id="ntcRejectionConfirmModal" tabindex="-1"
    aria-labelledby="ntcRejectionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-0 d-flex align-items-center justify-content-between p-4"
                style="background: linear-gradient(135deg, #d32f2f, #b71c1c); color: #fff;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-envelope-exclamation-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="ntcRejectionConfirmModalLabel">
                            Send Post Training Report Decline Email
                        </h5>
                        <small class="text-white-50">Accreditation #{{ $accreditation->accreditation_number ?? 'N/A' }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" style="background:#fafafa;">
                <div class="d-flex align-items-center gap-3 mb-3 p-3"
                    style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
                    <i class="bi bi-person-circle fs-2 text-danger"></i>
                    <div>
                        <div class="fw-bold" style="color:#2A3F54;font-size:.95rem;">{{ $fatproName }}</div>
                        <small class="text-muted">{{ $fatproUser->email ?? 'N/A' }}</small>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-2 mb-3 p-3"
                    style="background:#fff3cd;border-radius:8px;border-left:4px solid #ffc107;">
                    <i class="bi bi-exclamation-triangle-fill text-warning mt-1"></i>
                    <small class="text-dark">
                        An email will be sent to the FATPro listing the declined documents and remarks.
                        They will be able to re-upload corrected documents.
                    </small>
                </div>

                <h6 class="fw-bold mb-2" style="color:#922b21; font-size:.85rem; text-transform:uppercase; letter-spacing:.4px;">
                    <i class="bi bi-x-circle-fill me-1"></i>Declined Documents
                </h6>
                <div id="ntc-rejection-doc-list" class="d-flex flex-column gap-2">
                    {{-- Filled dynamically by evaluation.js --}}
                </div>
            </div>

            <div class="modal-footer border-0 pt-2" style="background:#fafafa; padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Go Back</button>
                <button type="button" id="btn-ntc-confirm-rejection"
                    class="btn btn-danger fw-bold px-5"
                    style="border-radius:8px;"
                    onclick="submitNtcRejection()">
                    <i class="bi bi-send-fill me-2"></i>Confirm &amp; Send Email
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Already Accepted Notice ── --}}
@if($isAccepted)
<div class="ai-card mb-4" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;">
    <div class="d-flex align-items-center gap-3 p-1">
        <div style="width:44px;height:44px;background:#16a34a;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-check-all text-white fs-5"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-bold" style="color:#14532d;">Post Training Report Accepted</h6>
            <small style="color:#166534;">
                All documents have been approved. This report was accepted on
                {{ $postTrainingReport->accepted_at?->format('M d, Y h:i A') ?? '—' }}.
                @if($postTrainingReport->acceptedByUser)
                    By {{ $postTrainingReport->acceptedByUser->name }}.
                @endif
            </small>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    window.ARMS = window.ARMS || {};
    window.ARMS.csrfToken = '{{ csrf_token() }}';
    window.ARMS.ntcEvaluateUrlBase = '{{ url("admin/hcd/reports/post-training/documents") }}';
    window.ARMS.ntcApproveLabel = 'Accept Post Training Report';
    window.ARMS.ntcRejectLabel = 'Send Decline Email';
</script>
<script src="{{ asset('js/evaluation.js') }}?v={{ filemtime(public_path('js/evaluation.js')) }}"></script>
@endpush
