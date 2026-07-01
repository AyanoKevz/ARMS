@extends('layouts.admin')

@section('title', 'NTC — ' . $ntcReport->reference_number)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/show-application.css') }}?v={{ filemtime(public_path('css/show-application.css')) }}">
<style>
/* ── NTC Detail Overrides ──────────────────── */
.ntc-doc-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid #f0f2f7;
    flex-wrap: wrap;
}
.ntc-doc-row:last-child { border-bottom: none; }

.ntc-doc-name {
    flex: 1;
    min-width: 180px;
    font-weight: 600;
    font-size: .88rem;
    color: #2A3F54;
}
.ntc-doc-meta {
    font-size: .75rem;
    color: #888;
    margin-top: 3px;
    font-weight: 400;
}
.ntc-eval-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.btn-ntc-approve,
.btn-ntc-reject {
    border: none;
    border-radius: 6px;
    padding: 5px 14px;
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-ntc-approve {
    background: #f0fdf4;
    color: #16a34a;
    border: 1.5px solid #bbf7d0;
}
.btn-ntc-approve:hover,
.btn-ntc-approve.active {
    background: #16a34a;
    color: #fff;
    border-color: #16a34a;
}
.btn-ntc-reject {
    background: #fff5f5;
    color: #dc2626;
    border: 1.5px solid #fecaca;
}
.btn-ntc-reject:hover,
.btn-ntc-reject.active {
    background: #dc2626;
    color: #fff;
    border-color: #dc2626;
}
.ntc-reject-panel {
    width: 100%;
    margin-top: 8px;
    padding: 10px;
    background: #fff8f8;
    border: 1px solid #fecaca;
    border-radius: 8px;
}
.ntc-reject-panel textarea {
    width: 100%;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: .83rem;
    resize: vertical;
    background: #fff;
    outline: none;
}
.ntc-reject-panel textarea:focus {
    border-color: #dc2626;
    box-shadow: 0 0 0 2px rgba(220,38,38,.1);
}
.ntc-save-btn {
    background: linear-gradient(135deg, #1A4A8A, #0D2B55);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 22px;
    font-size: .83rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ntc-save-btn:hover { opacity: .88; }
.ntc-save-btn:disabled { opacity: .6; cursor: not-allowed; }

/* Badge variants */
.ntc-badge-pending  { background: #f3f4f6; color: #6b7280; }
.ntc-badge-approved { background: #d1fae5; color: #065f46; }
.ntc-badge-rejected { background: #fee2e2; color: #991b1b; }
.ntc-badge-returned { background: #fef3c7; color: #92400e; }
</style>
@endpush

@section('content')
@php
$isEvaluator = strtolower(auth()->user()?->adminProfile?->adminRole?->name ?? '') === 'evaluator';
$fatproUser  = $ntcReport->accreditation->user ?? null;
$isOrg       = $fatproUser?->profile_type === 'Organization';
$fatproName  = $isOrg
    ? ($fatproUser->organizationProfile->name ?? $fatproUser->name ?? '—')
    : ($fatproUser->name ?? '—');

$allDocuments  = $ntcReport->documents;
$totalDocs     = $allDocuments->count();
$approvedDocs  = $allDocuments->where('status', 'approved')->count();

$ntcStatus = $ntcReport->status;
$isAcknowledged = $ntcStatus === 'acknowledged';
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
        <h3><i class="fas fa-clipboard-list me-2" style="color:var(--portal-gold);"></i> NTC Evaluation</h3>
    </div>
    <a href="{{ route('admin.hcd.reports.ntc.index') }}" class="btn btn-secondary btn-sm mt-3">
        <i class="bi bi-arrow-left me-1"></i> Back to NTC List
    </a>
</div>
<div class="clearfix"></div>

{{-- ── Reference / Status Strip ── --}}
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
     style="background: linear-gradient(135deg,#eef5ff,#dbeafe); border: 1px solid #bfdbfe;">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;letter-spacing:.45px;">
            <i class="bi bi-clipboard-data me-1"></i>NTC Reference Number
        </div>
        <h4 class="m-0 fw-bold" style="color:#1e40af;">{{ $ntcReport->reference_number }}</h4>
        <small style="color:#1d4ed8;">
            <i class="bi bi-person me-1"></i>{{ $fatproName }}
            &nbsp;&bull;&nbsp;
            <i class="bi bi-card-text me-1"></i>{{ $ntcReport->accreditation->accreditation_number ?? '—' }}
        </small>
    </div>
    <div class="d-flex flex-column align-items-end gap-1">
        @if($isAcknowledged)
        <span class="badge fs-6 px-3 py-2 bg-success">
            <i class="bi bi-check-circle-fill me-1"></i> Acknowledged
        </span>
        @elseif($allDocuments->contains('status', 'rejected'))
        <span class="badge fs-6 px-3 py-2 bg-danger">
            <i class="bi bi-x-circle-fill me-1"></i> Documents Rejected
        </span>
        @else
        <span class="badge fs-6 px-3 py-2 bg-warning text-dark">
            <i class="bi bi-hourglass-split me-1"></i> Under Review
        </span>
        @endif
        <small style="color:#1d4ed8; font-size:.78rem;">
            Submitted: {{ $ntcReport->submitted_at?->format('M d, Y h:i A') ?? '—' }}
        </small>
    </div>
</div>

{{-- ── NTC Details Card ── --}}
<div class="ai-card mb-4">
    <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#ntcDetailsBody" aria-expanded="true">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#1A4A8A,#0D2B55);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-info-circle-fill text-white" style="font-size:.95rem;"></i>
        </div>
        <h5 class="mb-0">NTC Submission Details</h5>
        <i class="bi bi-chevron-down ms-auto" id="ntcDetailsChevron"></i>
    </div>
    <div id="ntcDetailsBody" class="collapse show">
        <div class="row mt-2">
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Training Type</div>
                    <div class="val">{{ $ntcReport->trainingType->name ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Training Code</div>
                    <div class="val">{{ $ntcReport->trainingType->code ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Mode</div>
                    <div class="val">{{ $ntcReport->trainingMode->name ?? '—' }}</div>
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
                    <div class="val">{{ $ntcReport->training_start_date?->format('M d, Y') ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Training End</div>
                    <div class="val">{{ $ntcReport->training_end_date?->format('M d, Y') ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Submitted At</div>
                    <div class="val">{{ $ntcReport->submitted_at?->format('M d, Y h:i A') ?? '—' }}</div>
                </div>
            </div>
            @if($isAcknowledged)
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Acknowledged By</div>
                    <div class="val">{{ $ntcReport->acknowledgedByUser?->name ?? '—' }}</div>
                </div>
            </div>
            @endif
        </div>
        @if($ntcReport->remarks)
        <div class="info-pair mt-2">
            <div class="lbl">Admin Remarks</div>
            <div class="val">{{ $ntcReport->remarks }}</div>
        </div>
        @endif
    </div>
</div>

{{-- ── Documents Evaluation Card ── --}}
<div class="ai-card mb-4">
    <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#ntcDocsBody" aria-expanded="true">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#1A4A8A,#0D2B55);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-folder2-open text-white" style="font-size:.95rem;"></i>
        </div>
        <h5 class="mb-0">Submitted Documents</h5>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="badge bg-success" id="ntc-docs-progress" style="font-size:.75rem;">
                {{ $approvedDocs }} / {{ $totalDocs }} Accepted
            </span>
            <i class="bi bi-chevron-down" id="ntcDocsChevron"></i>
        </div>
    </div>

    <div id="ntcDocsBody" class="collapse show">
        <div class="pt-2">
            @if($allDocuments->count() === 0)
            <div class="alert alert-secondary text-center mb-0">No documents found for this NTC submission.</div>
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
                    'approved' => 'Approved',
                    'rejected' => 'Rejected — Awaiting Re-upload',
                    'returned' => 'Awaiting Re-upload',
                    default    => 'Pending',
                };
                $evalStatus = in_array($docStatus, ['approved','rejected','returned']) ? $docStatus : 'pending';
            @endphp
            <div class="ntc-doc-row" id="ntc-doc-row-{{ $doc->id }}">

                {{-- Document name & meta --}}
                <div class="ntc-doc-name">
                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
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
                    <div style="margin-top:5px;font-size:.78rem;color:#991b1b;background:#fff5f5;border-radius:6px;padding:5px 8px;border:1px solid #fecaca;">
                        <i class="bi bi-chat-left-text me-1"></i>
                        <strong>Remarks:</strong> {{ $doc->remarks }}
                    </div>
                    @endif
                </div>

                {{-- Status badge --}}
                <span class="badge {{ $badgeClass }} px-2 py-1" style="font-size:.75rem;border-radius:20px;white-space:nowrap;"
                      id="ntc-badge-{{ $doc->id }}">{{ $badgeLabel }}</span>

                {{-- View button (hidden when file has been deleted after rejection) --}}
                @if($doc->file_path)
                <div>
                    <a href="{{ route('admin.hcd.reports.ntc.document.view', $doc->id) }}"
                       target="_blank"
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

                {{-- Evaluate buttons (only when not acknowledged) --}}
                @if(!$isAcknowledged && !in_array($docStatus, ['rejected', 'returned']))
                <div class="ntc-eval-actions" id="ntc-eval-actions-{{ $doc->id }}">
                    <button type="button"
                            class="btn-ntc-approve {{ $evalStatus === 'approved' ? 'active' : '' }}"
                            id="btn-approve-{{ $doc->id }}"
                            onclick="ntcEvalDoc({{ $doc->id }}, 'approved')">
                        <i class="bi bi-check-circle-fill"></i> Approve
                    </button>
                    <button type="button"
                            class="btn-ntc-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}"
                            id="btn-reject-{{ $doc->id }}"
                            onclick="ntcToggleReject({{ $doc->id }})">
                        <i class="bi bi-x-circle-fill"></i> Reject
                    </button>
                </div>
                @elseif(!$isAcknowledged && in_array($docStatus, ['rejected', 'returned']))
                <div class="ntc-eval-actions" id="ntc-eval-actions-{{ $doc->id }}">
                    <span class="badge bg-secondary" style="font-size:.75rem;">Awaiting re-upload from FATPro</span>
                </div>
                @endif

                {{-- Rejection panel --}}
                @if(!$isAcknowledged && !in_array($docStatus, ['rejected', 'returned']))
                <div class="ntc-reject-panel w-100" id="ntc-reject-panel-{{ $doc->id }}" style="display:none;">
                    <label style="font-size:.8rem;font-weight:600;color:#991b1b;margin-bottom:5px;display:block;">
                        <i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span style="font-weight:400;color:#888;">(optional)</span>
                    </label>
                    <textarea id="ntc-remarks-{{ $doc->id }}"
                              placeholder="Explain why this document was rejected…"
                              rows="2">{{ $doc->remarks }}</textarea>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="ntc-save-btn" onclick="ntcEvalDoc({{ $doc->id }}, 'rejected')" id="ntc-save-btn-{{ $doc->id }}">
                            <i class="bi bi-send-fill"></i> Confirm Rejection
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="ntcToggleReject({{ $doc->id }}, true)">
                            Cancel
                        </button>
                    </div>
                </div>
                @endif

            </div>
            @endforeach
            @endif
        </div>
    </div>
</div>

{{-- ── Already Acknowledged Notice ── --}}
@if($isAcknowledged)
<div class="ai-card mb-4" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;">
    <div class="d-flex align-items-center gap-3 p-1">
        <div style="width:44px;height:44px;background:#16a34a;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-check-all text-white fs-5"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-bold" style="color:#14532d;">NTC Acknowledged</h6>
            <small style="color:#166534;">
                All documents have been approved. This NTC was acknowledged on
                {{ $ntcReport->acknowledged_at?->format('M d, Y h:i A') ?? '—' }}.
                @if($ntcReport->acknowledgedByUser)
                    By {{ $ntcReport->acknowledgedByUser->name }}.
                @endif
            </small>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
const EVALUATE_URL_BASE = "{{ url('admin/hcd/reports/ntc/documents') }}";
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Approve: send immediately.
 * Reject: shown inline via ntcToggleReject, then confirmed via ntcEvalDoc.
 */
function ntcToggleReject(docId, cancel = false) {
    const panel = document.getElementById('ntc-reject-panel-' + docId);
    if (!panel) return;
    if (cancel) {
        panel.style.display = 'none';
        // Reset reject button to non-active
        document.getElementById('btn-reject-' + docId)?.classList.remove('active');
        return;
    }
    const isVisible = panel.style.display !== 'none';
    panel.style.display = isVisible ? 'none' : '';
    document.getElementById('btn-reject-' + docId)?.classList.toggle('active', !isVisible);
}

function ntcEvalDoc(docId, status) {
    const remarks  = document.getElementById('ntc-remarks-' + docId)?.value ?? '';
    const saveBtn  = document.getElementById('ntc-save-btn-' + docId);
    const approveBtn = document.getElementById('btn-approve-' + docId);

    if (saveBtn)  { saveBtn.disabled  = true; saveBtn.innerHTML  = '<i class="bi bi-hourglass-split"></i> Saving…'; }
    if (approveBtn && status === 'approved') { approveBtn.disabled = true; approveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…'; }

    fetch(`${EVALUATE_URL_BASE}/${docId}/evaluate`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ status, remarks }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error('Evaluation failed');
        updateDocRow(docId, status, remarks);
        updateProgress();

        if (data.ntc_acknowledged) {
            setTimeout(() => {
                showAcknowledgedBanner();
            }, 600);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Something went wrong. Please try again.');
        if (saveBtn)    { saveBtn.disabled = false;    saveBtn.innerHTML    = '<i class="bi bi-send-fill"></i> Confirm Rejection'; }
        if (approveBtn) { approveBtn.disabled = false; approveBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Approve'; }
    });
}

function updateDocRow(docId, status, remarks) {
    const badge = document.getElementById('ntc-badge-' + docId);
    const panel = document.getElementById('ntc-reject-panel-' + docId);
    const actions = document.getElementById('ntc-eval-actions-' + docId);

    const badgeMap = {
        approved: { cls: 'ntc-badge-approved', label: 'Approved' },
        rejected: { cls: 'ntc-badge-rejected', label: 'Rejected — Awaiting Re-upload' },
    };
    const b = badgeMap[status] ?? { cls: 'ntc-badge-pending', label: status };

    if (badge) {
        badge.className = `badge ${b.cls} px-2 py-1`;
        badge.style.cssText = 'font-size:.75rem;border-radius:20px;white-space:nowrap;';
        badge.textContent = b.label;
    }

    // Hide evaluation controls
    if (panel)   panel.style.display = 'none';
    if (actions) {
        if (status === 'rejected') {
            actions.innerHTML = '<span class="badge bg-secondary" style="font-size:.75rem;">Awaiting re-upload from FATPro</span>';
        } else {
            actions.style.display = 'none';
        }
    }

    // When rejected: file is deleted server-side, replace View button with "File Removed" badge
    if (status === 'rejected') {
        const row = document.getElementById('ntc-doc-row-' + docId);
        const viewDiv = row?.querySelector('a[href*="/view"]')?.closest('div');
        if (viewDiv) {
            viewDiv.innerHTML = '<span class="badge bg-secondary" style="font-size:.72rem;"><i class="bi bi-trash me-1"></i>File Removed</span>';
        }
    }

    // Show remarks bubble if rejected
    if (status === 'rejected' && remarks) {
        const row = document.getElementById('ntc-doc-row-' + docId);
        const nameEl = row?.querySelector('.ntc-doc-name');
        if (nameEl && !nameEl.querySelector('.ntc-remark-bubble')) {
            const bubble = document.createElement('div');
            bubble.className = 'ntc-remark-bubble';
            bubble.style.cssText = 'margin-top:5px;font-size:.78rem;color:#991b1b;background:#fff5f5;border-radius:6px;padding:5px 8px;border:1px solid #fecaca;';
            bubble.innerHTML = `<i class="bi bi-chat-left-text me-1"></i><strong>Remarks:</strong> ${remarks}`;
            nameEl.appendChild(bubble);
        }
    }
}

function updateProgress() {
    const allBadges  = document.querySelectorAll('[id^="ntc-badge-"]');
    const total      = allBadges.length;
    const approved   = Array.from(allBadges).filter(b => b.classList.contains('ntc-badge-approved')).length;
    const progressEl = document.getElementById('ntc-docs-progress');
    if (progressEl) progressEl.textContent = `${approved} / ${total} Accepted`;
}

function showAcknowledgedBanner() {
    const strip = document.querySelector('.ai-card[style*="bfdbfe"]');
    if (strip) {
        strip.style.background = 'linear-gradient(135deg, #f0fdf4, #dcfce7)';
        strip.style.border = '1px solid #bbf7d0';
        const badge = strip.querySelector('.badge');
        if (badge) {
            badge.className = 'badge fs-6 px-3 py-2 bg-success';
            badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Acknowledged';
        }
    }
    // Show acknowledged footer
    const footer = document.getElementById('ntc-acknowledged-footer');
    if (footer) footer.style.display = '';
}
</script>
@endpush
