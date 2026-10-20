{{--
    First-aider certificate — evaluator review on the application page.

    Shows the certificate on file (PREM_07 / PREM_DATE) and, when the FATPro has
    uploaded a renewal during the accreditation, the renewal beside it with
    Approve / Reject. Only the evaluator assigned to this application decides
    (any Evaluator when nobody is assigned); the Team Lead sees it read-only.

    Expects: $accreditation (active, with a first-aider certificate)
--}}
@php
    $faDocs     = $accreditation->firstAiderDocuments();
    $faValidity = $accreditation->firstAiderCertificateValidity();
    $faState    = $accreditation->firstAiderCertificateState($faValidity);
    $faPending  = $accreditation->pendingFirstAiderCertificate;
    $faHistory  = $accreditation->firstAiderCertificateSubmissions()->whereIn('status', ['approved', 'rejected'])->with('reviewedByUser')->take(5)->get();

    $faViewer      = auth()->user();
    $faRole        = strtolower($faViewer?->adminProfile?->adminRole?->name ?? '');
    $faAssignedId  = $accreditation->application?->assigned_evaluator_id;
    $faCanDecide   = $faRole === 'evaluator' && (!$faAssignedId || $faAssignedId === $faViewer->id);

    [$faTone, $faLabel] = match ($faState) {
        'expired'  => ['danger',  'Expired'],
        'expiring' => ['warning', 'Expiring Soon'],
        default    => ['success', 'Valid'],
    };
@endphp

<div class="ai-card mb-3" style="border:1px solid {{ $faPending ? '#93c5fd' : '#e5e7eb' }};">
    <div class="ai-card-header">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#0ea5e9,#0369a1);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-heart-pulse-fill text-white" style="font-size:.9rem;"></i>
        </div>
        <h5 class="mb-0">First-Aider Certificate</h5>
        <div class="ms-auto d-flex align-items-center gap-2">
            @if($faPending)
                <span class="badge bg-info text-white" style="font-size:.75rem;"><i class="bi bi-hourglass-split me-1"></i>Renewal awaiting review</span>
            @endif
            <span class="badge bg-{{ $faTone }} {{ $faTone === 'warning' ? 'text-dark' : '' }}" style="font-size:.75rem;">{{ $faLabel }}</span>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="p-3 rounded h-100" style="background:#f8fafc;border:1px solid #e2e8f0;">
                <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">On file</div>
                <div class="fw-semibold mt-1">Valid until {{ $faValidity?->format('F d, Y') ?? 'N/A' }}</div>
                @if($faDocs['file']?->userDocument?->file_path)
                    <a href="{{ route('admin.hcd.documents.view', $faDocs['file']->id) }}?v={{ $faDocs['file']->updated_at?->timestamp ?? time() }}"
                       data-file-modal data-file-title="First-Aider Certificate (on file)"
                       class="btn btn-outline-primary btn-xs px-2 py-0 mt-2" style="font-size:.78rem;">
                        <i class="bi bi-eye me-1"></i>View certificate
                    </a>
                @endif
            </div>
        </div>

        @if($faPending)
        <div class="col-md-6">
            <div class="p-3 rounded h-100" style="background:#eff6ff;border:1px solid #bfdbfe;">
                <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#1d4ed8;">Renewal submitted {{ $faPending->created_at->format('M d, Y') }}</div>
                <div class="fw-semibold mt-1">Valid until {{ $faPending->validity_date->format('F d, Y') }}</div>
                <a href="{{ route('admin.hcd.first_aider_certificates.view', $faPending->id) }}"
                   data-file-modal data-file-title="First-Aider Certificate (renewal)"
                   class="btn btn-outline-primary btn-xs px-2 py-0 mt-2" style="font-size:.78rem;">
                    <i class="bi bi-eye me-1"></i>View renewal
                </a>
            </div>
        </div>
        @endif
    </div>

    @if($faPending)
        @if($faCanDecide)
            <div class="d-flex flex-wrap align-items-start gap-2 mt-3">
                <form method="POST" action="{{ route('admin.hcd.first_aider_certificates.approve', $faPending->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Approve Renewal
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.hcd.first_aider_certificates.reject', $faPending->id) }}" class="flex-grow-1 d-flex flex-wrap gap-2">
                    @csrf
                    <input type="text" name="remarks" class="form-control form-control-sm" style="min-width:240px;max-width:520px;"
                           maxlength="1000" placeholder="Rejection remarks (optional) — e.g. certificate is not legible">
                    <button type="submit" class="btn btn-outline-danger btn-sm fw-bold">
                        <i class="bi bi-x-circle-fill me-1"></i> Reject
                    </button>
                </form>
            </div>
        @else
            <p class="text-muted mt-3 mb-0" style="font-size:.82rem;">
                <i class="bi bi-info-circle me-1"></i>
                Waiting for {{ $accreditation->application?->assignedEvaluator?->name ?? 'an Evaluator' }} to review this renewal.
            </p>
        @endif
    @endif

    @if($faHistory->isNotEmpty())
        <div class="mt-3" style="font-size:.8rem;">
            <div class="fw-semibold text-muted mb-1">Previous renewals</div>
            @foreach($faHistory as $past)
                <div class="d-flex flex-wrap gap-2 align-items-center py-1" style="border-top:1px dashed #e5e7eb;">
                    <span class="badge {{ $past->status === 'approved' ? 'bg-success' : 'bg-danger' }}" style="font-size:.68rem;">{{ ucfirst($past->status) }}</span>
                    <span>valid until {{ $past->validity_date->format('M d, Y') }}</span>
                    <span class="text-muted">
                        &middot; {{ $past->reviewed_at?->format('M d, Y') }}
                        @if($past->reviewedByUser) by {{ $past->reviewedByUser->name }} @endif
                    </span>
                    @if($past->remarks)
                        <span class="text-danger">&middot; {{ $past->remarks }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
