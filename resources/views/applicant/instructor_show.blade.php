@extends('layouts.applicant')

@section('title', 'Instructor Details')



@section('content')
<div class="">
    <div class="page-title d-flex justify-content-between align-items-center">
        <div class="title_left">
            <h3>
                {{ $instructor->last_name }}, {{ $instructor->first_name }}
                @if($instructor->middle_name) {{ $instructor->middle_name }} @endif
            </h3>
        </div>
        <a href="{{ route('applicant.instructors.index') }}" class="btn btn-secondary btn-sm mt-3">
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
    <div class="alert alert-warning d-flex align-items-start gap-3 mb-3">
        <i class="bi bi-exclamation-triangle-fill fs-4 mt-1 text-warning"></i>
        <div>
            <strong>Action Required: Credential Update Requested by Admin</strong><br>
            <span style="font-size:0.88rem;">
                <strong>Reason:</strong> {{ $instructor->update_request_reason }}<br>
                @if($instructor->update_request_fields)
                <strong>Fields to update:</strong>
                @php
                    $fieldLabels = [
                        'service_agreement' => 'Service Agreement',
                        'EMS'  => 'TESDA EMS NC II or III Certificate',
                        'TM1'  => 'TESDA Trainers Methodology Certificate 1',
                        'NTTC' => 'TESDA National TVET Trainer Certificate',
                        'BOSH' => 'BOSH SO1 or SO2 Certificate',
                    ];
                @endphp
                {{ implode(', ', array_map(fn($f) => $fieldLabels[$f] ?? $f, $instructor->update_request_fields)) }}
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
    @elseif($instructor->update_request_status === 'completed')
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>
            <strong>Update Approved</strong><br>
            <small>Your instructor's updated credentials have been approved by the admin.</small>
        </div>
    </div>
    @endif

    @php
        $requestedFields = $instructor->update_request_fields ?? [];
        $isUpdateMode    = $instructor->update_request_status === 'admin_requested';
    @endphp

    <div class="row pt-2">
        <div class="col-12">

            {{-- ── Service Agreement ── --}}
            <div class="cred-card">
                <div class="cred-header">
                    <h6><i class="bi bi-file-earmark-pdf me-2"></i> Service Agreement</h6>
                    @php
                        $saColor = match($instructor->status) {
                            'approved' => 'badge-approved',
                            'returned' => 'badge-returned',
                            'rejected' => 'badge-rejected',
                            default    => 'badge-pending',
                        };
                    @endphp
                    <span class="badge {{ $saColor }}">{{ ucfirst($instructor->status) }}</span>
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
                                           target="_blank" class="btn btn-sm btn-outline-primary">
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
                        <form action="{{ route('applicant.instructors.service_agreement.update', $instructor->id) }}"
                              method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="d-flex align-items-end gap-2">
                                <div class="flex-grow-1">
                                    <label class="form-label">Replace Service Agreement PDF <span class="text-danger">*</span> (max 10MB)</label>
                                    <input type="file" name="service_agreement" class="form-control form-control-sm" accept=".pdf" required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary px-4">
                                    <i class="bi bi-save me-1"></i> Submit
                                </button>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="bi bi-info-circle me-1"></i>
                                Uploading will replace the existing file and submit for admin re-review.
                            </small>
                        </form>
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
                $credColor = match($credential->status) {
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
                    <span class="badge {{ $credColor }}">{{ ucfirst($credential->status) }}</span>
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
                                           target="_blank" class="btn btn-sm btn-outline-primary">
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
                        <form action="{{ route('applicant.instructors.credentials.update', [$instructor->id, $credential->id]) }}"
                              method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">Certificate Number</label>
                                    <input type="text" name="number" class="form-control form-control-sm"
                                           value="{{ old('number', $credential->number) }}" placeholder="e.g. TESDA-2024-0001">
                                </div>
                                @if($credential->type !== 'BOSH')
                                <div class="col-md-4">
                                    <label class="form-label">Issued Date</label>
                                    <input type="date" name="issued_date" class="form-control form-control-sm"
                                           value="{{ old('issued_date', $credential->issued_date?->format('Y-m-d')) }}">
                                </div>
                                @endif
                                <div class="col-md-4">
                                    <label class="form-label">Valid Until</label>
                                    <input type="date" name="validity_date" class="form-control form-control-sm"
                                           value="{{ old('validity_date', $credential->validity_date?->format('Y-m-d')) }}">
                                </div>
                                @if($credential->type === 'BOSH')
                                <div class="col-md-8">
                                    <label class="form-label">Training Date(s)</label>
                                    <input type="text" name="training_dates" class="form-control form-control-sm"
                                           value="{{ old('training_dates', $credential->training_dates) }}"
                                           placeholder="e.g. April 10-14, 2024">
                                </div>
                                @endif
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <div class="flex-grow-1">
                                    <label class="form-label">Replace Credential PDF (optional, max 10MB)</label>
                                    <input type="file" name="pdf_file" class="form-control form-control-sm" accept=".pdf">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary px-4">
                                    <i class="bi bi-save me-1"></i> Save & Submit for Review
                                </button>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="bi bi-info-circle me-1"></i>
                                Saving will reset this credential to <strong>Pending</strong> for admin re-review.
                            </small>
                        </form>
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

        </div>
    </div>
</div>
@endsection
