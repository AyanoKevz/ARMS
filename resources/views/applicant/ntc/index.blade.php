@extends('layouts.applicant')

@section('title', 'Notice to Conduct')

@section('content')
<div class="row">
    <div class="page-title">
        <div class="title_left">
            <h3><i class="fas fa-clipboard-list" style="color: var(--portal-gold);"></i> Notice to Conduct (NTC)</h3>
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
            <div class="x_panel" style="border-left: 4px solid #e74c3c;">
                <div class="x_content">
                    <div class="text-center py-4">
                        <i class="fas fa-lock" style="font-size: 3rem; color: #e74c3c; margin-bottom: 1rem; display:block;"></i>
                        <h4 class="fw-bold text-danger">No Active Accreditation</h4>
                        <p class="text-muted">You must have an <strong>active accreditation</strong> before you can submit a Notice to Conduct (NTC) report.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else

<div class="row">

    {{-- ── SUBMIT NEW NTC FORM ─────────────────────────────── --}}
    <div class="col-md-5">
        <div class="x_panel" style="border-top: 3px solid var(--portal-gold);">
            <div class="x_title">
                <h2><i class="fas fa-paper-plane me-2" style="color: var(--portal-gold);"></i>Submit New NTC</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">

                {{-- Accreditation Info Banner --}}
                <div class="alert mb-3 py-2 px-3" style="background: #eef5ff; border: 1px solid #b8d4ff; border-radius: 8px; font-size: 0.875rem;">
                    <i class="fas fa-id-badge me-1" style="color: #0b3d91;"></i>
                    <strong>Accreditation:</strong> {{ $accreditation->accreditation_number }}
                    &nbsp;|&nbsp;
                    <span class="badge bg-success" style="font-size:0.75rem;">Active</span>
                </div>

                {{-- 10-day rule notice --}}
                <div class="alert py-2 px-3 mb-3" style="background: #fff8e6; border: 1px solid #f5d98a; border-radius: 8px; font-size: 0.84rem; color: #7a5c00;">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Reminder:</strong> NTC must be submitted at least <strong>10 working days</strong> before the first training day.
                    The earliest allowed training start date is
                    <strong>{{ \Carbon\Carbon::parse($earliestStartDate)->format('F d, Y') }}</strong>.
                </div>

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
                                    {{ old('ntc_training_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
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
                                    {{ old('ntc_training_mode_id') == $mode->id ? 'selected' : '' }}>
                                    {{ $mode->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('ntc_training_mode_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Training Dates --}}
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="fw-semibold" for="training_start_date">
                                    Training Start Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       id="training_start_date"
                                       name="training_start_date"
                                       class="form-control @error('training_start_date') is-invalid @enderror"
                                       min="{{ $earliestStartDate }}"
                                       value="{{ old('training_start_date') }}"
                                       required>
                                @error('training_start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="fw-semibold" for="training_end_date">
                                    Training End Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       id="training_end_date"
                                       name="training_end_date"
                                       class="form-control @error('training_end_date') is-invalid @enderror"
                                       value="{{ old('training_end_date') }}"
                                       required>
                                @error('training_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- File: RTCMan Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="file_rtcman">
                            DOLE-OSHC-STO-RTCMan Form <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted mb-1" style="font-size:0.8rem;">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone @error('file_rtcman') is-invalid-zone @enderror"
                             id="dropZoneRtcman"
                             data-input="file_rtcman">
                            <i class="fas fa-file-upload ntc-file-icon"></i>
                            <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                            <p class="ntc-file-selected" id="rtcmanFileName">No file selected</p>
                            <input type="file"
                                   id="file_rtcman"
                                   name="file_rtcman"
                                   class="ntc-file-hidden"
                                   accept=".pdf,.doc,.docx"
                                   required>
                        </div>
                        @error('file_rtcman')
                            <div class="text-danger mt-1" style="font-size: 0.85rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- File: PROG Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="file_prog">
                            DOLE-OSHC-STO-PROG Form <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted mb-1" style="font-size:0.8rem;">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone @error('file_prog') is-invalid-zone @enderror"
                             id="dropZoneProg"
                             data-input="file_prog">
                            <i class="fas fa-file-upload ntc-file-icon"></i>
                            <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                            <p class="ntc-file-selected" id="progFileName">No file selected</p>
                            <input type="file"
                                   id="file_prog"
                                   name="file_prog"
                                   class="ntc-file-hidden"
                                   accept=".pdf,.doc,.docx"
                                   required>
                        </div>
                        @error('file_prog')
                            <div class="text-danger mt-1" style="font-size: 0.85rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit"
                                id="ntcSubmitBtn"
                                class="btn btn-block fw-bold"
                                style="background: linear-gradient(135deg, #D4AC4B, #b8922e); color: #fff; border: none; padding: 12px; border-radius: 8px; font-size: 1rem; letter-spacing: 0.5px;">
                            <i class="fas fa-paper-plane me-2"></i> Submit Notice to Conduct
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    {{-- ── NTC SUBMISSION HISTORY ──────────────────────────── --}}
    <div class="col-md-7">
        <div class="x_panel" style="border-top: 3px solid #0b3d91;">
            <div class="x_title">
                <h2><i class="fas fa-history me-2" style="color: #0b3d91;"></i>My NTC Submissions</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">

                @if($ntcReports->isEmpty())
                    <div class="text-center py-5">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; display:block; margin-bottom: 1rem;"></i>
                        <p class="text-muted">No NTC submissions yet. Fill out the form to submit your first Notice to Conduct.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover" style="font-size: 0.88rem;">
                            <thead style="background: #f1f3f8;">
                                <tr>
                                    <th style="color: #0b3d91;">Reference #</th>
                                    <th style="color: #0b3d91;">Type</th>
                                    <th style="color: #0b3d91;">Mode</th>
                                    <th style="color: #0b3d91;">Training Period</th>
                                    <th style="color: #0b3d91;">Status</th>
                                    <th style="color: #0b3d91;">Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ntcReports as $ntc)
                                <tr>
                                    <td class="fw-bold" style="color: #0b3d91;">
                                        NTC-{{ str_pad($ntc->id, 6, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td>
                                        <span class="badge"
                                              style="background: #eef5ff; color: #0b3d91; font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; font-weight: 600;">
                                            {{ $ntc->trainingType->code ?? 'N/A' }}
                                        </span>
                                        <div style="font-size:0.75rem; color: #666; margin-top:2px;">{{ $ntc->trainingType->name ?? '' }}</div>
                                    </td>
                                    <td>{{ $ntc->trainingMode->name ?? 'N/A' }}</td>
                                    <td style="white-space: nowrap;">
                                        <div>{{ $ntc->training_start_date ? $ntc->training_start_date->format('M d, Y') : 'N/A' }}</div>
                                        <div style="font-size:0.75rem; color:#999;">to</div>
                                        <div>{{ $ntc->training_end_date ? $ntc->training_end_date->format('M d, Y') : 'N/A' }}</div>
                                    </td>
                                    <td>
                                        @if($ntc->status === 'submitted')
                                            <span class="badge bg-warning text-dark" style="font-size:0.75rem;">Submitted</span>
                                        @elseif($ntc->status === 'acknowledged')
                                            <span class="badge bg-success" style="font-size:0.75rem;">Acknowledged</span>
                                        @else
                                            <span class="badge bg-secondary" style="font-size:0.75rem;">{{ ucfirst($ntc->status) }}</span>
                                        @endif
                                        @if($ntc->submitted_at)
                                            <div style="font-size:0.72rem; color:#999; margin-top:2px;">
                                                {{ $ntc->submitted_at->format('M d, Y') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @foreach($ntc->documents as $doc)
                                            <a href="{{ route('applicant.ntc.document.view', $doc->id) }}"
                                               target="_blank"
                                               title="{{ $doc->documentType->name ?? $doc->original_filename }}"
                                               class="btn btn-sm mb-1"
                                               style="background: #f1f3f8; border: 1px solid #dde; color: #0b3d91; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px;">
                                                <i class="fas fa-file-alt me-1"></i>
                                                {{ $doc->documentType->code ?? 'DOC' }}
                                            </a>
                                        @endforeach
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
@endif

@endsection

@push('styles')
<style>
    /* ── NTC File Drop Zone ─────────────────────────────── */
    .ntc-file-drop-zone {
        border: 2px dashed #b8c8e8;
        border-radius: 10px;
        padding: 20px 16px;
        text-align: center;
        cursor: pointer;
        background: #f7f9fd;
        transition: border-color 0.2s, background 0.2s;
        position: relative;
    }
    .ntc-file-drop-zone:hover,
    .ntc-file-drop-zone.drag-over {
        border-color: var(--portal-gold);
        background: #fffbf0;
    }
    .ntc-file-drop-zone.is-invalid-zone {
        border-color: #dc3545;
        background: #fff8f8;
    }
    .ntc-file-hidden {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    .ntc-file-icon {
        font-size: 1.8rem;
        color: #b8c8e8;
        display: block;
        margin-bottom: 6px;
        pointer-events: none;
    }
    .ntc-file-label {
        font-size: 0.88rem;
        color: #666;
        margin-bottom: 4px;
        pointer-events: none;
    }
    .ntc-browse-link {
        color: #0b3d91;
        font-weight: 600;
        text-decoration: underline;
    }
    .ntc-file-selected {
        font-size: 0.78rem;
        color: #888;
        margin: 0;
        pointer-events: none;
        word-break: break-all;
    }
    .ntc-file-selected.has-file {
        color: #27ae60;
        font-weight: 600;
    }

    /* ── Spin loading on submit ─────────────────────────── */
    #ntcSubmitBtn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── File Drop Zone Behaviour ──────────────────────────
    const dropZones = document.querySelectorAll('.ntc-file-drop-zone');

    dropZones.forEach(function (zone) {
        const inputId  = zone.getAttribute('data-input');
        const input    = document.getElementById(inputId);
        const label    = zone.querySelector('.ntc-file-selected');
        const MAX_MB   = 100;
        const MAX_BYTES = MAX_MB * 1024 * 1024;

        const validMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        const validExts = ['.pdf', '.doc', '.docx'];

        function formatBytes(bytes) {
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function applyFile(file) {
            if (!file) return;

            const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            if (!validExts.includes(ext)) {
                label.textContent = '❌ Invalid file type. Use PDF, DOC, or DOCX.';
                label.classList.remove('has-file');
                input.value = '';
                return;
            }

            if (file.size > MAX_BYTES) {
                label.textContent = '❌ File too large. Max 100 MB allowed.';
                label.classList.remove('has-file');
                input.value = '';
                return;
            }

            label.textContent = '✓ ' + file.name + ' (' + formatBytes(file.size) + ')';
            label.classList.add('has-file');
        }

        // Click to pick a file
        zone.addEventListener('click', function (e) {
            if (e.target !== input) input.click();
        });

        // File chosen via picker
        input.addEventListener('change', function () {
            applyFile(input.files[0]);
        });

        // Drag & drop
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('drag-over');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const dt = e.dataTransfer;
            if (dt && dt.files.length > 0) {
                // Transfer the file to the hidden input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(dt.files[0]);
                input.files = dataTransfer.files;
                applyFile(dt.files[0]);
            }
        });
    });

    // ── Training End Date: enforce >= Start Date ──────────
    const startInput = document.getElementById('training_start_date');
    const endInput   = document.getElementById('training_end_date');

    if (startInput && endInput) {
        startInput.addEventListener('change', function () {
            endInput.min = startInput.value;
            if (endInput.value && endInput.value < startInput.value) {
                endInput.value = startInput.value;
            }
        });
    }

    // ── Submit spinner guard ──────────────────────────────
    const form       = document.getElementById('ntcSubmitForm');
    const submitBtn  = document.getElementById('ntcSubmitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            // Basic client-side guard
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
        });
    }

});
</script>
@endpush
