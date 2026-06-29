@extends('layouts.applicant')

@section('title', 'Instructor Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/instructor-show.css') }}?v={{ filemtime(public_path('css/instructor-show.css')) }}">
@endpush



@section('content')
<div class="">
    {{-- Accreditation Summary --}}
    @if($accreditation)
    <div class="x_panel mb-4" style="border-left:4px solid var(--portal-gold, #d4ac4b); border-top:none;">
        <div class="x_title border-0 mb-0 pb-0">
            <h2 class="fw-bold" style="color:#2A3F54;"><i class="fas fa-award text-warning me-2"></i>Current Accreditation</h2>
            <div class="clearfix"></div>
        </div>
        <div class="x_content mt-2">
            <div class="row text-center text-md-start">
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Accreditation Number</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#0b3d91;">{{ $accreditation->accreditation_number ?? 'N/A' }}</p>
                </div>
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Date Accredited</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#2A3F54;">{{ $accreditation->date_of_accreditation ? \Carbon\Carbon::parse($accreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}</p>
                </div>
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Validity Period</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#2A3F54;">{{ $accreditation->validity_date ? \Carbon\Carbon::parse($accreditation->validity_date)->format('F d, Y') : 'N/A' }}</p>
                </div>
                @if($accreditation->status === 'revoked')
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Revoked Date</p>
                    <p class="fw-bold fs-5 mb-0 text-danger">{{ $accreditation->updated_at ? $accreditation->updated_at->format('F d, Y') : 'N/A' }}</p>
                </div>
                @endif
                <div class="col-md">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Status</p>
                    @if($accreditation->status === 'active')
                        <span class="badge bg-success" style="font-size:.9rem;padding:6px 12px;">Active</span>
                    @elseif($accreditation->status === 'expired')
                        <span class="badge bg-warning text-dark" style="font-size:.9rem;padding:6px 12px;">Expired</span>
                    @elseif($accreditation->status === 'revoked')
                        <span class="badge bg-danger" style="font-size:.9rem;padding:6px 12px;">Revoked</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="page-title d-flex justify-content-between align-items-center">
        <div class="title_left d-flex align-items-center">
            <h3 class="mb-0 me-2">
                {{ $instructor->last_name }}, {{ $instructor->first_name }}
                @if($instructor->middle_name) {{ $instructor->middle_name }} @endif
            </h3>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editNameModal" title="Edit Instructor Name">
                <i class="bi bi-pencil"></i>
            </button>
        </div>
        <a href="{{ route('applicant.instructors.index') }}" class="btn btn-secondary btn-sm">
            Back
        </a>
    </div>
    <div class="clearfix"></div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Admin Update Request Banner ── --}}
    @if($instructor->update_request_status === 'admin_requested')
    <div class="p-3 mb-3 rounded" style="background-color: #6c757d; color: #ffffff; display: flex; gap: 1rem; align-items: flex-start;">
        <i class="bi bi-info-circle fs-4 mt-1"></i>
        <div>
            <strong>Action Required: Credential Update Requested by Admin</strong><br>
            <span style="font-size:0.88rem;">
                @php
                    $reasons = json_decode($instructor->update_request_reason, true);
                    $fieldLabels = [
                        'service_agreement' => 'Service Agreement',
                        'EMS'  => 'TESDA EMS NC II or III Certificate',
                        'TM1'  => 'TESDA Trainers Methodology Certificate 1',
                        'NTTC' => 'TESDA National TVET Trainer Certificate',
                        'BOSH' => 'BOSH SO1 or SO2 Certificate',
                    ];
                @endphp
                @if(is_array($reasons))
                    <strong>Documents to Update:</strong>
                    <ul class="mb-0 mt-1">
                    @foreach($instructor->update_request_fields as $f)
                        <li><strong>{{ $fieldLabels[$f] ?? $f }}:</strong> <span>{{ $reasons[$f] ?? 'No reason provided' }}</span></li>
                    @endforeach
                    </ul>
                @else
                    <strong>Reason:</strong> {{ $instructor->update_request_reason }}<br>
                    @if($instructor->update_request_fields)
                    <strong>Fields to update:</strong>
                    {{ implode(', ', array_map(fn($f) => $fieldLabels[$f] ?? $f, $instructor->update_request_fields)) }}
                    @endif
                @endif
            </span>
        </div>
    </div>
    @elseif($instructor->update_request_status === 'pending_review')
    <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-hourglass-split fs-5"></i>
        <div>
            <strong>Files Submitted — Awaiting Admin Review</strong><br>
            <small>Your updated files have been submitted. The admin will review them shortly.</small>
        </div>
    </div>
    @endif

    @php
        $requestedFields = $instructor->update_request_fields ?? [];
        $isUpdateMode    = $instructor->update_request_status === 'admin_requested';
    @endphp

    <div class="row pt-2">
        <div class="col-12">
            @if($isUpdateMode)
            <form action="{{ route('applicant.instructors.batch_update', $instructor->id) }}" method="POST" enctype="multipart/form-data" id="batch-update-form">
                @csrf
            @endif

            {{-- ── Service Agreement ── --}}
            <div class="cred-card">
                <div class="cred-header">
                    <h6><i class="bi bi-file-earmark-pdf me-2"></i> Service Agreement</h6>
                    @php
                        $saStatus = $instructor->status;
                        if (in_array('service_agreement', $requestedFields)) {
                            if ($instructor->update_request_status === 'admin_requested') {
                                $saStatus = 'update requested';
                            } elseif ($instructor->update_request_status === 'pending_review') {
                                $saStatus = 'pending review';
                            }
                        }
                        $saColor = match($saStatus) {
                            'approved' => 'badge-approved',
                            'returned' => 'badge-returned',
                            'rejected' => 'badge-rejected',
                            default    => 'badge-pending',
                        };
                    @endphp
                    <span class="badge {{ $saColor }}">{{ ucwords($saStatus) }}</span>
                </div>
                <div class="cred-body">
                    @if($instructor->remarks)
                        <div class="remarks-box">
                            <i class="bi bi-exclamation-circle-fill text-warning me-1"></i>
                            <strong>Admin Remarks:</strong> {{ $instructor->remarks }}
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">File</div>
                            <div class="info-val">
                                @if($instructor->service_agreement_path)
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="text-muted" style="font-size:0.82rem;">
                                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                            {{ basename($instructor->service_agreement_path) }}
                                        </span>
                                        <a href="{{ route('applicant.instructors.service_agreement.view', $instructor->id) }}?v={{ $instructor->updated_at->timestamp }}"
                                           data-file-modal data-file-title="Service Agreement – {{ $instructor->first_name }} {{ $instructor->last_name }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> View PDF
                                        </a>
                                    </div>
                                @else
                                    <span class="text-muted fst-italic">No file uploaded</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Update form: only when admin requested AND service_agreement is in the fields list --}}
                    @if($isUpdateMode && in_array('service_agreement', $requestedFields))
                    <div class="update-section mt-2">
                        <label class="form-label mb-1">Replace Service Agreement PDF <span class="text-danger">*</span></label>
                        <div class="file-upload-wrapper mt-1">
                            <input class="real-file-input visually-hidden" type="file" name="service_agreement" id="service_agreement" accept=".pdf" required>
                            <div class="d-flex align-items-center gap-2">
                                <label for="service_agreement" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                    <i class="fas fa-upload me-1"></i> Choose PDF
                                </label>
                                <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 250px;">No file chosen</span>
                            </div>
                            <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            Uploading will replace the existing file and submit for admin re-review (max 10MB).
                        </small>
                    </div>
                    @endif
                </div>
            </div>


            {{-- ── Credentials ── --}}
            @php
                $credLabels = [
                    'EMS'  => 'TESDA Emergency Medical Services NC II or III',
                    'TM1'  => 'TESDA Trainers Methodology Certificate 1',
                    'NTTC' => 'TESDA National TVET Trainer Certificate',
                    'BOSH' => 'BOSH SO1 or SO2 Certificate',
                ];
            @endphp

            @foreach($instructor->credentials as $credential)
            @php
                $credStatus = $credential->status;
                if (in_array($credential->type, $requestedFields)) {
                    if ($instructor->update_request_status === 'admin_requested') {
                        $credStatus = 'update requested';
                    } elseif ($instructor->update_request_status === 'pending_review') {
                        $credStatus = 'pending review';
                    }
                }
                $credColor = match($credStatus) {
                    'approved' => 'badge-approved',
                    'returned' => 'badge-returned',
                    'rejected' => 'badge-rejected',
                    default    => 'badge-pending',
                };
                $label = $credLabels[$credential->type] ?? $credential->type;
            @endphp
            <div class="cred-card">
                <div class="cred-header">
                    <h6><i class="bi bi-award me-2"></i> {{ $label }}</h6>
                    <span class="badge {{ $credColor }}">{{ ucwords($credStatus) }}</span>
                </div>
                <div class="cred-body">
                    @if($credential->remarks)
                        <div class="remarks-box">
                            <i class="bi bi-exclamation-circle-fill text-warning me-1"></i>
                            <strong>Admin Remarks:</strong> {{ $credential->remarks }}
                        </div>
                    @endif
                    <div class="info-row">
                        @if($credential->number)
                        <div class="info-item">
                            <div class="info-label">Certificate Number</div>
                            <div class="info-val">{{ $credential->number }}</div>
                        </div>
                        @endif
                        @if($credential->type !== 'BOSH')
                            @if($credential->issued_date)
                            <div class="info-item">
                                <div class="info-label">Issued Date</div>
                                <div class="info-val">{{ \Carbon\Carbon::parse($credential->issued_date)->format('F d, Y') }}</div>
                            </div>
                            @endif
                        @endif
                        @if($credential->validity_date)
                        <div class="info-item">
                            <div class="info-label">Valid Until</div>
                            <div class="info-val">{{ \Carbon\Carbon::parse($credential->validity_date)->format('F d, Y') }}</div>
                        </div>
                        @endif
                        @if($credential->type === 'BOSH' && $credential->training_dates)
                        <div class="info-item">
                            <div class="info-label">Training Date(s)</div>
                            <div class="info-val">{{ $credential->training_dates }}</div>
                        </div>
                        @endif
                        <div class="info-item">
                            <div class="info-label">Credential File</div>
                            <div class="info-val">
                                @if($credential->pdf_path)
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="text-muted" style="font-size:0.82rem;">
                                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                            {{ basename($credential->pdf_path) }}
                                        </span>
                                        <a href="{{ route('applicant.instructors.credentials.view', $credential->id) }}?v={{ $credential->updated_at->timestamp }}"
                                           data-file-modal data-file-title="{{ $credLabels[$credential->type] ?? $credential->type }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> View PDF
                                        </a>
                                    </div>
                                @else
                                    <span class="text-muted fst-italic">No file uploaded</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Update Form: only when admin requested AND this credential type is in the fields list --}}
                    @if($isUpdateMode && in_array($credential->type, $requestedFields))
                    <div class="update-section mt-2">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">Certificate Number <span class="text-danger">*</span></label>
                                <input type="text" name="credentials[{{ $credential->id }}][number]" class="form-control form-control-sm"
                                       value="{{ old('credentials.'.$credential->id.'.number', $credential->number) }}" placeholder="e.g. TESDA-2024-0001" required>
                            </div>
                            @if($credential->type !== 'BOSH')
                            <div class="col-md-4">
                                <label class="form-label">Issued Date <span class="text-danger">*</span></label>
                                <input type="date" name="credentials[{{ $credential->id }}][issued_date]" class="form-control form-control-sm"
                                       value="{{ old('credentials.'.$credential->id.'.issued_date', $credential->issued_date?->format('Y-m-d')) }}" required>
                            </div>
                            @endif
                            <div class="col-md-4">
                                <label class="form-label">Valid Until <span class="text-danger">*</span></label>
                                <input type="date" name="credentials[{{ $credential->id }}][validity_date]" class="form-control form-control-sm"
                                       value="{{ old('credentials.'.$credential->id.'.validity_date', $credential->validity_date?->format('Y-m-d')) }}" required>
                            </div>
                            @if($credential->type === 'BOSH')
                            <div class="col-md-8">
                                <label class="form-label">Training Date(s) <span class="text-danger">*</span></label>
                                <input type="text" name="credentials[{{ $credential->id }}][training_dates]" class="form-control form-control-sm"
                                       value="{{ old('credentials.'.$credential->id.'.training_dates', $credential->training_dates) }}"
                                       placeholder="e.g. April 10-14, 2024" required>
                            </div>
                            @endif
                        </div>
                        <label class="form-label mb-1">Replace Credential PDF (optional)</label>
                        <div class="file-upload-wrapper mt-1">
                            <input class="real-file-input visually-hidden" type="file" name="credentials[{{ $credential->id }}][pdf_file]" id="cred_pdf_{{ $credential->id }}" accept=".pdf">
                            <div class="d-flex align-items-center gap-2">
                                <label for="cred_pdf_{{ $credential->id }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                    <i class="fas fa-upload me-1"></i> Choose PDF
                                </label>
                                <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 250px;">No file chosen</span>
                            </div>
                            <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            Saving will reset this credential to <strong>Pending</strong> for admin re-review (max 10MB).
                        </small>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach

            @if($instructor->credentials->isEmpty())
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> No credentials are currently on file for this instructor.
            </div>
            @endif

            @if($isUpdateMode)
            <div class="text-end mt-4 mb-4">
                <button type="submit" class="btn btn-primary btn-lg px-5" id="batchUpdateBtn" style="border-radius:10px;">
                    <span id="batchUpdateText"><i class="bi bi-send me-1"></i> Submit All Updates</span>
                    <span id="batchUpdateSpinner" class="d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span> Submitting...
                    </span>
                </button>
            </div>
            </form>
            @endif

        </div>
    </div>
</div>

{{-- Edit Name Modal --}}
<div class="modal fade" id="editNameModal" tabindex="-1" aria-labelledby="editNameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editNameModalLabel">Edit Instructor Name</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('applicant.instructors.update_name', $instructor->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="{{ old('first_name', $instructor->first_name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="middle_name" class="form-label">Middle Name</label>
                        <input type="text" class="form-control" id="middle_name" name="middle_name" value="{{ old('middle_name', $instructor->middle_name) }}">
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="{{ old('last_name', $instructor->last_name) }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/instructor-show.js') }}?v={{ filemtime(public_path('js/instructor-show.js')) }}"></script>
@endpush
