{{--
    Certificate Issuance — FATPro's panel (admin/hcd/show_application_info),
    minus the inline styles, plus the ID card FATPro does not have:

      1. View Certificate PDF (the strip's button) → print and sign it
      2. Upload the signed certificate and the digital or scanned ID card
         (PCT Step 8 ends once both are in)
      3. Release them → the applicant is emailed (PCT Step 9 ends)

    Expects $application, $accreditation.
--}}
@php
    $hasCertificate = (bool) $accreditation->scanned_certificate;
    $hasIdCard      = (bool) $accreditation->id_card;
    $released       = (bool) $accreditation->released_at;
    $files = [
        'scanned_certificate' => ['label' => 'Signed Certificate of Accreditation (PDF)', 'icon' => 'fa-file-signature', 'route' => 'certificate', 'accept' => '.pdf', 'has' => $hasCertificate, 'path' => $accreditation->scanned_certificate],
        'id_card'             => ['label' => 'Identification Card — digital or scanned (PDF/JPG/PNG)', 'icon' => 'fa-id-card', 'route' => 'id-card', 'accept' => '.pdf,.jpg,.jpeg,.png', 'has' => $hasIdCard, 'path' => $accreditation->id_card],
    ];
@endphp
<div class="ai-card mb-4 mt-4 prac-cert-card">
    <div class="ai-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-check fs-5 text-dark"></i>
            <h5 class="fw-bold text-dark mb-0">Certificate Issuance</h5>
        </div>
    </div>
    <div class="x_content p-3 mt-2">
        <p class="text-muted small">
            This application has been approved and accredited. Generate the certificate with <strong>View Certificate PDF</strong>,
            then upload the signed certificate and the applicant's ID card, and release them to notify the applicant that they are ready for pickup.
        </p>

        @if($released)
            <div class="prac-verify-box is-primary prac-cert-released">
                <div class="fw-bold text-success"><i class="fas fa-check-circle me-1"></i> Certificate and ID Card Released</div>
                <small class="text-muted">Released on {{ $accreditation->released_at->format('F d, Y h:i A') }}. The applicant has been notified by email.</small>
                <div class="d-flex gap-2 flex-wrap mt-3">
                    @foreach($files as $file)
                        <a href="{{ route('admin.accreditation.practitioner.certificate_files.view', [$application->id, $file['route']]) }}" data-file-modal data-file-title="{{ $file['label'] }}" class="btn btn-outline-success btn-sm fw-semibold px-3">
                            <i class="fas fa-eye me-1"></i> View {{ $file['route'] === 'certificate' ? 'Signed Certificate' : 'ID Card' }}
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <form action="{{ route('admin.accreditation.practitioner.certificate_files.upload', $application->id) }}" method="POST" enctype="multipart/form-data" data-prac-busy-form>
                @csrf
                <div class="row g-3">
                    @foreach($files as $input => $file)
                    <div class="col-md-6">
                        <div class="prac-verify-box is-primary prac-cert-box">
                            <h6 class="fw-bold prac-verify-title"><i class="fas {{ $file['icon'] }} me-1"></i> {{ $file['label'] }} <span class="text-danger">*</span></h6>
                            @if($file['has'])
                                <div class="mt-2">
                                    <div class="text-success fw-semibold small mb-1"><i class="fas fa-check-circle me-1"></i>Already uploaded</div>
                                    <div class="text-muted small mb-2 prac-file-name">{{ basename($file['path']) }}</div>
                                    <a href="{{ route('admin.accreditation.practitioner.certificate_files.view', [$application->id, $file['route']]) }}?v={{ $accreditation->updated_at?->timestamp ?? time() }}"
                                       data-file-modal data-file-title="{{ $file['label'] }}" class="btn btn-outline-success btn-sm fw-semibold px-3">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                </div>
                                <label class="form-label small text-muted mt-3 mb-1" for="cert-{{ $input }}">Replace file <span class="fw-normal">— the current file is deleted once the new one is saved</span></label>
                            @else
                                <div class="mt-2 text-danger small mb-2"><i class="fas fa-exclamation-circle"></i> Missing</div>
                            @endif
                            <input type="file" name="{{ $input }}" id="cert-{{ $input }}" class="form-control form-control-sm" accept="{{ $file['accept'] }}">
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary fw-semibold px-4" data-prac-busy-label="Uploading…">
                        <i class="fas fa-save me-1"></i> Upload
                    </button>
                </div>
            </form>

            <div class="prac-cert-release">
                @if($hasCertificate && $hasIdCard)
                    <p class="mb-2 small text-muted">Both files are in. Release them to notify the applicant that the certificate and ID card are ready for pickup.</p>
                    <button type="button" class="btn btn-success fw-bold px-4" data-bs-toggle="modal" data-bs-target="#releaseCertificateModal">
                        <i class="bi bi-send-check-fill me-1"></i> Release Certificate &amp; ID
                    </button>
                @else
                    <div class="alert alert-secondary py-2 px-3 border-0 rounded small mb-0">
                        <i class="fas fa-info-circle me-1"></i> Upload both the signed certificate and the ID card to release them.
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- ══ Release confirmation ══ --}}
@if(! $released && $hasCertificate && $hasIdCard)
<div class="modal fade" id="releaseCertificateModal" tabindex="-1" aria-labelledby="releaseCertificateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header is-success">
                <div class="d-flex align-items-center gap-3">
                    <span class="prac-modal-icon"><i class="bi bi-send-check-fill"></i></span>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="releaseCertificateModalLabel">Release Certificate &amp; ID</h5>
                        <small class="text-white-50">{{ $accreditation->accreditation_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 prac-modal-body">
                <div class="d-flex align-items-start gap-2 p-3 prac-modal-note is-success">
                    <i class="bi bi-envelope-check-fill text-success mt-1"></i>
                    <small class="text-dark">
                        <strong>{{ $application->user?->name }}</strong> ({{ $application->user?->email }}) will be emailed that the
                        Certificate of Accreditation and Identification Card are ready for pickup.
                    </small>
                </div>
            </div>
            <div class="modal-footer border-0 prac-modal-body">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('admin.accreditation.practitioner.release', $application->id) }}" data-prac-busy-form>
                    @csrf
                    <button type="submit" class="btn btn-success fw-bold px-4" data-prac-busy-label="Releasing…">
                        <i class="bi bi-send-check-fill me-1"></i> Release &amp; Notify
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
