{{--
    First-aider certificate — dashboard card.

    The certificate filed with the FATPro's application (PREM_07) and its
    validity date (PREM_DATE). The FATPro renews it here at any time while the
    accreditation is active; the renewal goes to the application's evaluator,
    and the certificate on file stays in force until it is approved.

    Expects: $accreditation (an active Accreditation with a first-aider certificate)
--}}
@php
    $validity = $accreditation->firstAiderCertificateValidity();
    $state    = $accreditation->firstAiderCertificateState($validity);
    $pending  = $accreditation->pendingFirstAiderCertificate;
    $latest   = $accreditation->latestFirstAiderCertificate;
    $rejected = !$pending && $latest?->isRejected() ? $latest : null;

    [$tone, $label, $icon] = match (true) {
        (bool) $pending      => ['info',    'Renewal Under Review', 'fa-hourglass-half'],
        $state === 'expired' => ['danger',  'Expired',              'fa-exclamation-triangle'],
        $state === 'expiring'=> ['warning', 'Expiring Soon',        'fa-clock'],
        default              => ['success', 'Valid',                'fa-check-circle'],
    };

    $daysLeft = $validity ? (int) \Carbon\Carbon::today()->diffInDays($validity, false) : null;
    $openModal = $errors->has('certificate') || $errors->has('validity_date');
@endphp

<div class="row">
    <div class="col-md-12">
        <div class="x_panel" style="border-left: 4px solid var(--bs-{{ $tone }}); border-top: none;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-bold mb-1" style="color:#2A3F54;">
                        <i class="fas fa-briefcase-medical me-1"></i> First-Aider Certificate
                        <span class="badge bg-{{ $tone }} {{ $tone === 'warning' ? 'text-dark' : '' }} ms-1" style="font-size:.72rem;">
                            <i class="fas {{ $icon }} me-1"></i>{{ $label }}
                        </span>
                    </div>

                    <div style="font-size:.88rem;">
                        Valid until <strong>{{ $validity?->format('F d, Y') ?? 'N/A' }}</strong>
                        @if($daysLeft !== null && $state === 'expired')
                            &mdash; expired {{ abs($daysLeft) }} {{ Str::plural('day', abs($daysLeft)) }} ago.
                        @elseif($daysLeft !== null && $state === 'expiring')
                            &mdash; {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} left.
                        @endif
                    </div>

                    @if($pending)
                        <div class="text-muted mt-1" style="font-size:.82rem;">
                            Your renewal (valid until {{ $pending->validity_date->format('F d, Y') }}) was submitted
                            {{ $pending->created_at->format('F d, Y') }} and is waiting for the evaluator.
                        </div>
                    @elseif($rejected)
                        <div class="mt-1 p-2 rounded" style="font-size:.82rem; background:#fff5f5; border:1px solid #fecaca; color:#991b1b;">
                            <i class="fas fa-times-circle me-1"></i>
                            Your last renewal was rejected{{ $rejected->remarks ? ':' : '.' }}
                            @if($rejected->remarks) <strong>{{ $rejected->remarks }}</strong> @endif
                            Please upload a corrected certificate.
                        </div>
                    @elseif(in_array($state, ['expired', 'expiring'], true))
                        <div class="mt-1" style="font-size:.82rem;">
                            Upload your renewed certificate and its new validity date for the evaluator to review.
                        </div>
                    @endif
                </div>

                @unless($pending)
                    <button type="button"
                            class="btn btn-sm {{ $state === 'expired' ? 'btn-danger' : 'btn-outline-primary' }} fw-bold px-3"
                            data-bs-toggle="modal"
                            data-bs-target="#faCertModal">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Update Certificate
                    </button>
                @endunless
            </div>
        </div>
    </div>
</div>

@unless($pending)
<div class="modal fade" id="faCertModal" tabindex="-1" aria-labelledby="faCertModalLabel" aria-hidden="true"
     @if($openModal) data-open-on-load="1" @endif>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('applicant.first_aider_certificate.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="faCertModalLabel">Update First-Aider Certificate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted" style="font-size:.85rem;">
                        Your current certificate (valid until {{ $validity?->format('F d, Y') ?? 'N/A' }}) stays on file
                        until the evaluator approves the new one.
                    </p>

                    <div class="mb-3">
                        <label for="faCertFile" class="form-label fw-semibold">
                            Renewed First-Aider Certificate <span class="text-danger">*</span>
                        </label>
                        <input type="file" id="faCertFile" name="certificate" accept=".pdf"
                               class="form-control @error('certificate') is-invalid @enderror" required>
                        <div class="form-text">PDF, up to 15 MB.</div>
                        @error('certificate') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-1">
                        <label for="faCertDate" class="form-label fw-semibold">
                            Certificate Validity Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" id="faCertDate" name="validity_date"
                               min="{{ \Carbon\Carbon::tomorrow()->toDateString() }}"
                               value="{{ old('validity_date') }}"
                               class="form-control @error('validity_date') is-invalid @enderror" required>
                        <div class="form-text">The date the renewed certificate expires.</div>
                        @error('validity_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fas fa-paper-plane me-1"></i> Submit for Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($openModal)
<script>
    // Reopen the form when the server sent it back with errors.
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('faCertModal');
        if (el && window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
    });
</script>
@endif
@endunless
