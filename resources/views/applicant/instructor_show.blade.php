@extends('layouts.applicant')

@section('title', 'Instructor Details')

@push('styles')
<style>
    .cred-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e4eaf2;
        box-shadow: 0 2px 8px rgba(0,0,0,.04);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .cred-header {
        background: linear-gradient(135deg, #1a2e5a, #0b3d91);
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .cred-header h6 { color: #fff; margin: 0; font-weight: 700; font-size: 0.95rem; }
    .cred-body { padding: 18px 20px; }
    .info-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 14px; }
    .info-item { flex: 1 1 180px; }
    .info-label { font-size: 0.75rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: .4px; }
    .info-val { font-size: 0.92rem; color: #2A3F54; font-weight: 600; margin-top: 2px; }
    .badge-pending  { background: #ffc107; color: #333; }
    .badge-approved { background: #28a745; color: #fff; }
    .badge-returned { background: #fd7e14; color: #fff; }
    .badge-rejected { background: #dc3545; color: #fff; }
    .update-section {
        background: #f8fafc;
        border-top: 1px dashed #dde3ef;
        padding: 14px 20px;
    }
    .update-section .form-label { font-size: 0.82rem; font-weight: 600; color: #2A3F54; }
    .remarks-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 0.82rem;
        color: #555;
        margin-bottom: 12px;
    }
</style>
@endpush

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
            <i class="bi bi-arrow-left me-1"></i> Back to List
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
                </div>
                <div class="update-section">
                    <form action="{{ route('applicant.instructors.service_agreement.update', $instructor->id) }}"
                          method="POST" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label">Replace Service Agreement (PDF only, max 10MB)</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="file" name="service_agreement" class="form-control form-control-sm" accept=".pdf">
                            <button type="submit" class="btn btn-sm btn-primary px-4">
                                <i class="bi bi-upload me-1"></i> Upload
                            </button>
                        </div>
                    </form>
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
                </div>

                {{-- Update Form --}}
                <div class="update-section">
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
                            Saving will reset the credential status to <strong>Pending</strong> for admin re-review. No interview will be required as you are an accredited FATPro.
                        </small>
                    </form>
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
