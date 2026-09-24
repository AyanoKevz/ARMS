@extends('layouts.applicant')

@section('title', 'Submission Report')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ntc.css') }}?v={{ filemtime(public_path('css/ntc.css')) }}">
<link rel="stylesheet" href="{{ asset('css/post-training.css') }}?v={{ filemtime(public_path('css/post-training.css')) }}">
@endpush

@section('content')
<div class="row">
    <div class="page-title">
        <div class="title_left">
            <h3><i class="fas fa-clipboard-list ntc-icon-gold"></i> Notice to Conduct (NTC)</h3>
        </div>
    </div>
</div>

<div class="clearfix"></div>

{{-- Flash Messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->has('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ $errors->first('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- No Active Accreditation Warning --}}
@if(!$accreditation)
    <div class="row">
        <div class="col-md-12">
            <div class="x_panel ntc-panel-danger">
                <div class="x_content">
                    <div class="text-center py-4">
                        <i class="fas fa-lock ntc-lock-icon"></i>
                        <h4 class="fw-bold text-danger">No Active Accreditation</h4>
                        <p class="text-muted">You must have an <strong>active accreditation</strong> before you can submit a Notice to Conduct (NTC) report.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else

{{-- ── ROW 0a: ACCREDITATION SUMMARY CARD (dashboard-style) ── --}}
<div class="row">
    <div class="col-md-12 col-sm-12">
        <div class="x_panel ntc-panel-gold">
            <div class="x_title border-0 mb-0 pb-0">
                <h2 class="fw-bold ntc-heading-navy"><i class="fas fa-award text-warning me-2"></i> Accreditation Summary</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content mt-2">
                <div class="row text-center text-md-start">
                    <div class="col-md mb-2 mb-md-0 border-end">
                        <p class="text-muted mb-1 ntc-label-caps">Accreditation Number</p>
                        <p class="fw-bold fs-5 mb-0 ntc-text-blue">{{ $accreditation->accreditation_number ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md mb-2 mb-md-0 border-end">
                        <p class="text-muted mb-1 ntc-label-caps">Date Accredited</p>
                        <p class="fw-bold fs-5 mb-0 ntc-heading-navy">
                            {{ $accreditation->date_of_accreditation ? \Carbon\Carbon::parse($accreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div class="col-md mb-2 mb-md-0 border-end">
                        <p class="text-muted mb-1 ntc-label-caps">Validity Period</p>
                        <p class="fw-bold fs-5 mb-0 ntc-heading-navy">
                            {{ $accreditation->validity_date ? \Carbon\Carbon::parse($accreditation->validity_date)->format('F d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div class="col-md">
                        <p class="text-muted mb-1 ntc-label-caps">Status</p>
                        <p class="mb-0 mt-1">
                            <span class="badge bg-success ntc-badge-lg">Active</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 0b: REGULATORY REMINDER ────────────────────────────── --}}
<div class="row mb-4">
    <div class="col-md-12">
        <div class="ntc-reminder">
            <div class="mb-2">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <strong>Regulatory Reminder (OSHC MC 04 Series 2025):</strong>
            </div>
            <p class="mb-2">
                Notice to Conduct must be submitted at least <strong>ten (10) working days</strong> before the first training day
                using the <strong>DOLE-OSHC-STO-RTCMan</strong> and <strong>DOLE-OSHC-STO-PROG</strong> forms as per <strong>OSHC MC 04 Series 2025</strong>.
            </p>
            <p class="mb-0">
                <i class="fas fa-calendar-check me-1"></i>
                Earliest allowed training start date:
                <strong>{{ \Carbon\Carbon::parse($earliestStartDate)->format('F d, Y') }}</strong>.
            </p>
        </div>
    </div>
</div>

{{-- ── ROW 0c: POST TRAINING OVERDUE ALERT ──────────────────── --}}
@php
    $ptrOverdue = $ptrPending->filter(fn($n) => $n->isPostTrainingReportOverdue());
@endphp
@if($ptrOverdue->isNotEmpty())
<div class="row">
    <div class="col-md-12">
        <div class="ptr-notice ptr-notice-danger">
            <div class="fw-bold mb-1">
                <i class="fas fa-exclamation-triangle me-1"></i>
                {{ $ptrOverdue->count() }} Post Training {{ Str::plural('Report', $ptrOverdue->count()) }} Overdue
            </div>
            <p class="mb-0">
                The submission deadline has passed for
                {{ $ptrOverdue->pluck('reference_number')->implode(', ') }}.
                Please file {{ $ptrOverdue->count() === 1 ? 'it' : 'them' }} immediately from the table below.
            </p>
        </div>
    </div>
</div>
@endif

{{-- ── ROW 1: SUBMIT NEW NTC FORM ──────────────────────────── --}}
<div class="row">
    <div class="col-md-12">
        <div class="x_panel ntc-panel-topgold">
            <div class="x_title">
                <h2><i class="fas fa-paper-plane me-2 ntc-icon-gold"></i>Submit New NTC</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">

                <form method="POST"
                      action="{{ route('applicant.ntc.store') }}"
                      enctype="multipart/form-data"
                      id="ntcSubmitForm"
                      novalidate>
                    @csrf

                    {{-- Training Type --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="ntc_training_type_id">
                            Type of Training <span class="text-danger">*</span>
                        </label>
                        <select id="ntc_training_type_id"
                                name="ntc_training_type_id"
                                class="form-control @error('ntc_training_type_id') is-invalid @enderror"
                                required>
                            <option value="" disabled selected>— Select Training Type —</option>
                            @foreach($trainingTypes as $type)
                                <option value="{{ $type->id }}"
                                    data-duration="{{ $type->durationDays() }}"
                                    {{ old('ntc_training_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }} &mdash; {{ $type->durationLabel() }}
                                </option>
                            @endforeach
                        </select>
                        @error('ntc_training_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Mode of Training --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="ntc_training_mode_id">
                            Mode of Training <span class="text-danger">*</span>
                        </label>
                        <select id="ntc_training_mode_id"
                                name="ntc_training_mode_id"
                                class="form-control @error('ntc_training_mode_id') is-invalid @enderror"
                                required>
                            <option value="" disabled selected>— Select Mode —</option>
                            @foreach($trainingModes as $mode)
                                <option value="{{ $mode->id }}"
                                    data-code="{{ $mode->code }}"
                                    {{ old('ntc_training_mode_id') == $mode->id ? 'selected' : '' }}>
                                    {{ $mode->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('ntc_training_mode_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Venue / Zoom Link --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="ntc_venue" id="ntc_venue_label">
                            Venue / Zoom Link <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               id="ntc_venue"
                               name="venue"
                               class="form-control @error('venue') is-invalid @enderror"
                               placeholder="Enter venue address or Zoom meeting link"
                               value="{{ old('venue') }}"
                               required>
                        @error('venue')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @include('applicant.partials.ntc_training_days', [
                        'prefix'       => '',
                        'minDate'      => $earliestStartDate,
                        'oldExtraDays' => (array) old('training_dates', []),
                    ])

                    @include('applicant.partials.ntc_instructor_picker', [
                        'prefix'                => '',
                        'selectedInstructorIds' => array_map('intval', (array) old('instructor_ids', [])),
                    ])

                    {{-- File: RTCMan Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="file_rtcman">
                            DOLE-OSHC-STO-RTCMan Form <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted mb-1 ntc-text-80">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone @error('file_rtcman') is-invalid-zone @enderror"
                             id="dropZoneRtcman"
                             data-input="file_rtcman">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace ntc-text-xs"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3 ntc-btn-pill">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="file_rtcman"
                                   name="file_rtcman"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none ntc-text-sm" id="error_file_rtcman">
                            Please upload the DOLE-OSHC-STO-RTCMan Form.
                        </div>
                        @error('file_rtcman')
                            <div class="text-danger mt-1 ntc-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- File: PROG Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="file_prog">
                            DOLE-OSHC-STO-PROG Form <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted mb-1 ntc-text-80">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone @error('file_prog') is-invalid-zone @enderror"
                             id="dropZoneProg"
                             data-input="file_prog">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace ntc-text-xs"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3 ntc-btn-pill">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="file_prog"
                                   name="file_prog"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none ntc-text-sm" id="error_file_prog">
                            Please upload the DOLE-OSHC-STO-PROG Form.
                        </div>
                        @error('file_prog')
                            <div class="text-danger mt-1 ntc-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit"
                                id="ntcSubmitBtn"
                                class="btn btn-block fw-bold ntc-btn-gold">
                            <i class="fas fa-paper-plane me-2"></i> Submit Notice to Conduct
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

{{-- ── ROW 2: TRAINING SUBMISSIONS ──────────────────────────────
     One table for the whole life of a training: the Notice to Conduct, any
     Report of Changes, and the Post Training Report it owes once the training
     has been held. --}}
<div class="row">
    <div class="col-md-12">
        <div class="card ntc-premium-card mb-4">
            <div class="card-header border-0 bg-transparent py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h4 class="m-0 fw-bold ntc-heading-navy">
                    <i class="fas fa-history me-2 ntc-icon-gold"></i> My Training Submissions
                </h4>
                @if($ptrPending->isNotEmpty())
                    <span class="badge-ptr-danger ntc-text-xs">
                        <i class="fas fa-flag-checkered me-1"></i>
                        {{ $ptrPending->count() }} post training
                        {{ Str::plural('report', $ptrPending->count()) }} awaiting submission
                    </span>
                @endif
            </div>
            <div class="card-body p-0">

                @if($ntcReports->isEmpty())
                    <div class="text-center py-5">
                        <i class="fas fa-inbox ntc-empty-icon"></i>
                        <p class="text-muted">No training submissions yet. Fill out the form to submit your first Notice to Conduct.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table ntc-table table-hover align-middle mb-0 ntc-text-88">
                            <thead class="ntc-thead">
                                <tr>
                                    <th class="ps-4 ntc-col-ref">Reference #</th>
                                    <th class="ntc-text-slate">Type</th>
                                    <th class="ntc-text-slate">Mode</th>
                                    <th class="ntc-text-slate">Submitted</th>
                                    <th class="ntc-text-slate ntc-col-period">Training Period</th>
                                    <th class="ntc-text-slate ntc-col-instructors">Instructors</th>
                                    <th class="ntc-text-slate">Status</th>
                                    <th class="ntc-col-docs">NTC Documents</th>
                                    <th class="pe-4 ntc-col-ptr">Post Training Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ntcReports as $ntc)
                                @php
                                    // True rejected: status is rejected AND file has been deleted/removed
                                    $hasRejected = $ntc->documents->contains(fn($d) => $d->status === 'rejected' && !$d->file_path);
                                    $rtcmanDoc = $ntc->documents->first(fn($d) => $d->documentType->code === 'RTCMAN');
                                    $progDoc = $ntc->documents->first(fn($d) => $d->documentType->code === 'PROG');
                                @endphp
                                <tr class="{{ $hasRejected ? 'ntc-row-attention' : '' }}">
                                    <td class="ps-4">
                                        <div class="ntc-ref-link">NTC-{{ str_pad($ntc->id, 6, '0', STR_PAD_LEFT) }}</div>
                                        @if($hasRejected)
                                            <div class="mt-1">
                                                <span class="badge bg-danger d-inline-flex align-items-center gap-1 ntc-badge-alert">
                                                    <i class="fas fa-exclamation-triangle"></i> Action Required
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge ntc-badge-type">
                                            {{ $ntc->trainingType->code ?? 'N/A' }}
                                        </span>
                                        <div class="text-secondary mt-1 ntc-type-name" title="{{ $ntc->trainingType->name ?? '' }}">
                                            {{ $ntc->trainingType->name ?? '' }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold ntc-text-ink">{{ $ntc->trainingMode->name ?? 'N/A' }}</span>
                                        @if($ntc->venue)
                                            <div class="text-muted mt-1 ntc-text-75">
                                                @if(optional($ntc->trainingMode)->code === 'BLENDED' || str_contains(strtolower($ntc->trainingMode->name ?? ''), 'blended'))
                                                    <a href="{{ $ntc->venue }}" target="_blank" class="text-primary text-decoration-none">{{ Str::limit($ntc->venue, 35) }}</a>
                                                @else
                                                    {{ Str::limit($ntc->venue, 35) }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ntc->submitted_at)
                                            <div class="ntc-date-strong">
                                                {{ $ntc->submitted_at->format('F d, Y') }}
                                            </div>
                                        @else
                                            <span class="text-muted ntc-text-sm">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Behind a dialog rather than in the cell: a day of the
                                             course may run over several dates, so the list has no
                                             fixed length for a column to hold. --}}
                                        @php $dayGroups = $ntc->trainingDatesByDay(); @endphp
                                        @if(empty($dayGroups))
                                            <span class="text-muted ntc-text-sm">N/A</span>
                                        @else
                                            <button type="button"
                                                    class="btn btn-xs btn-outline-primary fw-bold px-2 py-1 ntc-btn-xs btn-view-training-days"
                                                    data-ntc-ref="NTC-{{ str_pad($ntc->id, 6, '0', STR_PAD_LEFT) }}"
                                                    data-start="{{ $ntc->training_start_date?->format('F d, Y') ?? '—' }}"
                                                    data-end="{{ $ntc->training_end_date?->format('F d, Y') ?? '—' }}"
                                                    data-days="{{ json_encode(collect($dayGroups)->map(fn ($dates, $dayNo) => [
                                                        'day'   => $dayNo,
                                                        'dates' => array_map(
                                                            fn ($date) => \Carbon\Carbon::parse($date)->format('F d, Y'),
                                                            $dates
                                                        ),
                                                    ])->values()) }}">
                                                View
                                                <span class="badge bg-secondary ms-1">{{ count($dayGroups) }}</span>
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Names live behind a dialog rather than in the cell: a
                                             training may be conducted by any number of instructors
                                             and the column cannot grow with them. --}}
                                        @if($ntc->instructors->isEmpty())
                                            <span class="text-muted ntc-text-sm">None on record</span>
                                        @else
                                            <button type="button"
                                                    class="btn btn-xs btn-outline-primary fw-bold px-2 py-1 ntc-btn-xs btn-view-instructors"
                                                    data-ntc-ref="NTC-{{ str_pad($ntc->id, 6, '0', STR_PAD_LEFT) }}"
                                                    data-instructors="{{ json_encode($ntc->instructors->map(fn ($person) => [
                                                        'name'        => $person->listingName(),
                                                        // Scoped to this NTC's own application, so the dialog
                                                        // shows the credentials that were current for it.
                                                        'credentials' => $person->credentialLabelsFor($ntc->accreditation->application_id),
                                                    ])->values()) }}">
                                                View
                                                <span class="badge bg-secondary ms-1">{{ $ntc->instructors->count() }}</span>
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ntc->status === 'acknowledged')
                                            <span class="badge badge-premium-success">Acknowledged</span>
                                            @if($ntc->canSubmitReportChanges())
                                                <div class="mt-2">
                                                    <button type="button"
                                                            class="btn btn-xs btn-outline-primary fw-bold px-2 py-1 mt-1 btn-report-changes ntc-btn-xs"
                                                            data-id="{{ $ntc->id }}"
                                                            data-training-type="{{ $ntc->ntc_training_type_id }}"
                                                            data-training-mode="{{ $ntc->ntc_training_mode_id }}"
                                                            data-venue="{{ $ntc->venue }}"
                                                            data-start-date="{{ $ntc->training_start_date ? $ntc->training_start_date->format('Y-m-d') : '' }}"
                                                            data-end-date="{{ $ntc->training_end_date ? $ntc->training_end_date->format('Y-m-d') : '' }}"
                                                            data-training-dates="{{ json_encode((object) $ntc->trainingDatesByDay()) }}"
                                                            data-instructor-ids="{{ json_encode($ntc->instructors->pluck('id')) }}"
                                                            data-rtcman-file-name="{{ $rtcmanDoc ? $rtcmanDoc->original_filename : '' }}"
                                                            data-rtcman-file-url="{{ $rtcmanDoc && $rtcmanDoc->file_path ? route('applicant.ntc.document.view', $rtcmanDoc->id) : '' }}"
                                                            data-prog-file-name="{{ $progDoc ? $progDoc->original_filename : '' }}"
                                                            data-prog-file-url="{{ $progDoc && $progDoc->file_path ? route('applicant.ntc.document.view', $progDoc->id) : '' }}">
                                                        Report of Changes
                                                    </button>
                                                </div>
                                            @endif
                                            @if($ntc->reportChangesDeadlineDate())
                                                <div class="text-muted mt-1 ntc-text-70">
                                                    Changes until: {{ $ntc->reportChangesDeadlineDate()->format('F d, Y') }}
                                                </div>
                                            @endif
                                        @elseif($ntc->status === 'report_changes')
                                            <span class="badge bg-info text-white ntc-badge-info">Report of Changes</span>
                                        @elseif($hasRejected)
                                            <span class="badge badge-premium-danger">Requires Re-submission</span>
                                        @elseif($ntc->status === 'submitted')
                                            <span class="badge badge-premium-warning">Submitted</span>
                                        @else
                                            <span class="badge badge-premium-secondary">{{ ucfirst($ntc->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="pe-4">
                                        {{-- Behind a dialog: a declined document brings remarks and a
                                             drop zone with it, which no column can hold. --}}
                                        @php
                                            $declinedDocs = $ntc->documents->filter(fn($d) => $d->status === 'rejected' && !$d->file_path);
                                        @endphp
                                        @if($ntc->documents->isEmpty())
                                            <span class="text-muted ntc-text-sm">None</span>
                                        @else
                                            <button type="button"
                                                    class="btn btn-xs fw-bold px-2 py-1 ntc-btn-xs {{ $declinedDocs->isNotEmpty() ? 'btn-outline-danger' : 'btn-outline-primary' }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#ntcDocsModal-{{ $ntc->id }}">
                                                @if($declinedDocs->isNotEmpty())
                                                    Re-upload
                                                    <span class="badge bg-danger ms-1">{{ $declinedDocs->count() }}</span>
                                                @else
                                                    View
                                                    <span class="badge bg-secondary ms-1">{{ $ntc->documents->count() }}</span>
                                                @endif
                                            </button>
                                        @endif
                                    </td>

                                    {{-- Post Training Report: only becomes actionable
                                         once the training has actually been held. --}}
                                    <td class="pe-4 ptr-cell">
                                        @include('applicant.partials.post_training_cell', ['ntc' => $ntc])
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
{{-- Post Training Report modals (submission + declined re-uploads) --}}
@include('applicant.partials.post_training_section')

@include('applicant.partials.ntc_instructors_modal')

@include('applicant.partials.ntc_training_days_modal')

{{-- One per submission; they carry forms, so they cannot live in a cell. --}}
@foreach($ntcReports as $ntc)
    @include('applicant.partials.ntc_documents_modal', ['ntc' => $ntc])
@endforeach

@endif

{{-- Report of Changes Modal --}}
<div class="modal fade" id="reportChangesModal" tabindex="-1" aria-labelledby="reportChangesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg portal-scroll-modal">
        <div class="modal-content ntc-modal-surface">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold ntc-heading-navy" id="reportChangesModalLabel">
                    <i class="fas fa-exchange-alt text-warning me-2"></i> Submit Report of Changes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="reportChangesForm" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="modal-body">
                    <div class="alert alert-important py-2 mb-3 ntc-reminder">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Important Reminder:</strong> Reports of changes must be submitted at least three (3) working days before the first training day using the DOLE-OSHC-STO-RTCMan as per OSHC MC 04 series 2025.
                    </div>

                    <div class="alert alert-info alert-important py-2 ntc-text-sm">
                        <i class="fas fa-info-circle me-1"></i>
                        Use this form to update the training details and re-upload files for your acknowledged Notice to Conduct.
                    </div>

                    {{-- Training Type --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_ntc_training_type_id">
                            Type of Training <span class="text-danger">*</span>
                        </label>
                        <select id="modal_ntc_training_type_id"
                                name="ntc_training_type_id"
                                class="form-control"
                                required>
                            <option value="" disabled selected>— Select Training Type —</option>
                            @foreach($trainingTypes as $type)
                                <option value="{{ $type->id }}" data-duration="{{ $type->durationDays() }}">
                                    {{ $type->name }} &mdash; {{ $type->durationLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mode of Training --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_ntc_training_mode_id">
                            Mode of Training <span class="text-danger">*</span>
                        </label>
                        <select id="modal_ntc_training_mode_id"
                                name="ntc_training_mode_id"
                                class="form-control"
                                required>
                            <option value="" disabled selected>— Select Mode —</option>
                            @foreach($trainingModes as $mode)
                                <option value="{{ $mode->id }}" data-code="{{ $mode->code }}">{{ $mode->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Venue / Zoom Link --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_ntc_venue" id="modal_ntc_venue_label">
                            Venue / Zoom Link <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               id="modal_ntc_venue"
                               name="venue"
                               class="form-control"
                               placeholder="Enter venue address or Zoom meeting link"
                               required>
                    </div>

                    @include('applicant.partials.ntc_training_days', [
                        'prefix'       => 'modal_',
                        'minDate'      => $earliestStartDate,
                        'oldExtraDays' => [],
                    ])

                    @include('applicant.partials.ntc_instructor_picker', [
                        'prefix'                => 'modal_',
                        'selectedInstructorIds' => [],
                    ])

                    {{-- File: RTCMan Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_file_rtcman">
                            DOLE-OSHC-STO-RTCMan Form <span class="text-danger">*</span>
                        </label>
                        <div id="modal_rtcman_current_container" class="mb-2 ntc-current-file">
                            <span class="text-muted">Current file:</span>
                            <a href="#" id="modal_rtcman_current_link" target="_blank" class="font-monospace text-primary fw-semibold ms-1"></a>
                        </div>
                        <p class="text-muted mb-1 ntc-text-80">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone"
                             id="modalDropZoneRtcman"
                             data-input="modal_file_rtcman">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace ntc-text-xs"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3 ntc-btn-pill">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="modal_file_rtcman"
                                   name="file_rtcman"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none ntc-text-sm" id="error_modal_file_rtcman">
                            Please upload the DOLE-OSHC-STO-RTCMan Form.
                        </div>
                    </div>

                    {{-- File: PROG Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_file_prog">
                            DOLE-OSHC-STO-PROG Form <span class="text-danger">*</span>
                        </label>
                        <div id="modal_prog_current_container" class="mb-2 ntc-current-file">
                            <span class="text-muted">Current file:</span>
                            <a href="#" id="modal_prog_current_link" target="_blank" class="font-monospace text-primary fw-semibold ms-1"></a>
                        </div>
                        <p class="text-muted mb-1 ntc-text-80">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone"
                             id="modalDropZoneProg"
                             data-input="modal_file_prog">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace ntc-text-xs"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3 ntc-btn-pill">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="modal_file_prog"
                                   name="file_prog"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none ntc-text-sm" id="error_modal_file_prog">
                            Please upload the DOLE-OSHC-STO-PROG Form.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit"
                            id="modalSubmitBtn"
                            class="btn fw-bold ntc-btn-gold-sm">
                        <i class="fas fa-paper-plane me-1"></i> Submit Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection



@push('scripts')
{{-- NTC form: drop zones, derived end date, Report of Changes modal --}}
<script src="{{ asset('js/ntc-training-picker.js') }}?v={{ filemtime(public_path('js/ntc-training-picker.js')) }}"></script>
<script src="{{ asset('js/ntc.js') }}?v={{ filemtime(public_path('js/ntc.js')) }}"></script>
{{-- Post Training Report: drop zones, submit modal and re-upload forms --}}
<script src="{{ asset('js/post-training.js') }}?v={{ filemtime(public_path('js/post-training.js')) }}"></script>
<script src="{{ asset('js/post-training-corrections.js') }}?v={{ filemtime(public_path('js/post-training-corrections.js')) }}"></script>
@endpush
