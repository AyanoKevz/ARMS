@extends('layouts.applicant')

@section('title', 'Re-upload Documents')

@section('content')
<div class="">
    <div class="page-title d-flex justify-content-between align-items-center">
        <div class="title_left"><h3>Re-upload Rejected Documents</h3></div>
        <a href="{{ route('applicant.dashboard') }}" class="btn btn-secondary btn-sm mt-3">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
    <div class="clearfix"></div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Application Info --}}
    <div class="x_panel" style="border-left:4px solid #dc3545;">
        <div class="x_title border-0 mb-0 pb-0">
            <h2 class="fw-bold" style="color:#2A3F54;"><i class="fas fa-exclamation-circle text-danger me-2"></i>{{ ucfirst($application->application_type) }} — {{ $application->tracking_number }}</h2>
            <div class="clearfix"></div>
        </div>
        <div class="x_content mt-2">
            <p class="text-muted mb-0">Some of your documents have been rejected. Please re-upload the corrected files below and submit.</p>
        </div>
    </div>

    <form action="{{ route('applicant.renewal.reupload.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="application_id" value="{{ $application->id }}">

        {{-- Rejected Documents --}}
        @if($rejectedDocs->count() > 0)
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-file-alt me-2 text-danger"></i>Rejected Documents</h2><div class="clearfix"></div></div>
            <div class="x_content">
                @foreach($rejectedDocs as $doc)
                @php
                    $field = $doc->documentField;
                    $userDoc = $doc->userDocument;
                @endphp
                <div class="border rounded-3 p-3 mb-3" style="background:#fff5f5;border-color:#f5c6cb !important;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="fw-bold mb-1" style="color:#922b21;">{{ $field?->name ?? 'Unknown Field' }}</h6>
                            <small class="text-muted">{{ optional($field?->documentType)->name ?? '' }}</small>
                        </div>
                        <span class="badge bg-danger">Rejected</span>
                    </div>
                    @if($doc->remarks)
                    <div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.85rem;">
                        <i class="fas fa-comment-dots me-1"></i><strong>Remarks:</strong> {{ $doc->remarks }}
                    </div>
                    @endif
                    @if($field?->input_type === 'file')
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Upload corrected PDF <span class="text-danger">*</span></label>
                        <input type="file" class="form-control form-control-sm" name="files[{{ $doc->id }}]" accept=".pdf" required>
                    @elseif($field?->input_type === 'text')
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Enter corrected value <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="values[{{ $doc->id }}]" value="{{ $userDoc?->value }}" required>
                    @elseif($field?->input_type === 'date')
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Enter corrected date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" name="values[{{ $doc->id }}]" value="{{ $userDoc?->value }}" required>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Rejected Instructor Service Agreements --}}
        @if($rejectedInstructors->count() > 0)
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-user-times me-2 text-danger"></i>Rejected Service Agreements</h2><div class="clearfix"></div></div>
            <div class="x_content">
                @foreach($rejectedInstructors as $inst)
                <div class="border rounded-3 p-3 mb-3" style="background:#fff5f5;border-color:#f5c6cb !important;">
                    <h6 class="fw-bold mb-1" style="color:#922b21;">{{ $inst->first_name }} {{ $inst->last_name }} — Service Agreement</h6>
                    @if($inst->remarks)<div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.85rem;"><i class="fas fa-comment-dots me-1"></i><strong>Remarks:</strong> {{ $inst->remarks }}</div>@endif
                    <input type="file" class="form-control form-control-sm" name="instructor_files[{{ $inst->id }}]" accept=".pdf" required>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Rejected Credentials --}}
        @if($rejectedCredentials->count() > 0)
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-certificate me-2 text-danger"></i>Rejected Credentials</h2><div class="clearfix"></div></div>
            <div class="x_content">
                @foreach($rejectedCredentials as $cred)
                <div class="border rounded-3 p-3 mb-3" style="background:#fff5f5;border-color:#f5c6cb !important;">
                    <h6 class="fw-bold mb-1" style="color:#922b21;">{{ $cred->instructor->first_name ?? '' }} {{ $cred->instructor->last_name ?? '' }} — {{ $cred->type }} Certificate</h6>
                    @if($cred->remarks)<div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.85rem;"><i class="fas fa-comment-dots me-1"></i><strong>Remarks:</strong> {{ $cred->remarks }}</div>@endif
                    <input type="file" class="form-control form-control-sm" name="credential_files[{{ $cred->id }}]" accept=".pdf" required>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="x_panel">
            <div class="x_content text-center py-4">
                <button type="submit" class="btn btn-primary btn-lg fw-bold px-5" style="border-radius:10px;">
                    <i class="fas fa-upload me-2"></i>Re-submit Documents
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
