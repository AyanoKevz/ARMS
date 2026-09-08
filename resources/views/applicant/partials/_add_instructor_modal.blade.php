{{--
    Add Instructor modal — FATPRO portal.
    Submits the same fields the registration form collects per instructor, so the
    assigned evaluator reviews a portal-added instructor exactly like one filed
    with a new or renewal application.

    Sections collapse independently (no data-bs-parent) so a long form can be
    narrowed down without losing what is already filled in. A required field
    inside a collapsed section is re-opened by the invalid-field handler in
    public/js/portal.js, otherwise the browser cannot report on it.

    Expects: $credentialTypes (App\Http\Controllers\RegistrationController::CREDENTIAL_TYPES)
--}}
@php
    $credLabels = [
        'EMS'  => 'TESDA EMS NC II or III Certificate',
        'TM1'  => 'TESDA Trainers Methodology Certificate 1',
        'NTTC' => 'TESDA National TVET Trainer Certificate',
    ];
@endphp

<div class="modal fade" id="addInstructorModal" tabindex="-1" aria-labelledby="addInstructorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{ route('applicant.instructors.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="addInstructorModalLabel">
                        <i class="fas fa-user-plus me-2"></i> Add New Instructor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                {{-- Explicit cap as well as .modal-dialog-scrollable: the portal
                     theme restyles .modal-body, so the scroll is pinned here. --}}
                <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
                    <div class="alert alert-info" style="font-size:.85rem;">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        The instructor will be submitted for evaluation. Your assigned evaluator reviews the
                        details and files before the instructor is added to your accredited roster.
                        <br>
                        <span class="fw-semibold">All uploads must be PDF files, up to 15 MB each.</span>
                    </div>

                    <div class="accordion" id="addInstructorAccordion">

                        {{-- ── Instructor details ────────────────────────── --}}
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="addInstHeadingDetails">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#addInstSectionDetails" aria-expanded="true"
                                        aria-controls="addInstSectionDetails">
                                    <i class="fas fa-id-card me-2"></i> Instructor Details
                                </button>
                            </h2>
                            <div id="addInstSectionDetails" class="accordion-collapse collapse show"
                                 aria-labelledby="addInstHeadingDetails">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="add_inst_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="add_inst_first_name"
                                                   name="first_name" value="{{ old('first_name') }}" maxlength="255" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="add_inst_middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control form-control-sm" id="add_inst_middle_name"
                                                   name="middle_name" value="{{ old('middle_name') }}" maxlength="255">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="add_inst_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="add_inst_last_name"
                                                   name="last_name" value="{{ old('last_name') }}" maxlength="255" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="add_inst_sex" class="form-label">Sex <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="add_inst_sex" name="ins_sex" required>
                                                <option value="">Select&hellip;</option>
                                                <option value="Male" @selected(old('ins_sex') === 'Male')>Male</option>
                                                <option value="Female" @selected(old('ins_sex') === 'Female')>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="add_inst_sa" class="form-label">Service Agreement (PDF) <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control form-control-sm" id="add_inst_sa"
                                                   name="service_agreement" accept="application/pdf,.pdf" data-validate-pdf required>
                                            <div class="form-text" style="font-size:.75rem;">PDF only, max 15 MB.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="add_inst_cv" class="form-label">CV / Resume (PDF) <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control form-control-sm" id="add_inst_cv"
                                                   name="cv" accept="application/pdf,.pdf" data-validate-pdf required>
                                            <div class="form-text" style="font-size:.75rem;">PDF only, max 15 MB.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ── One section per credential type ───────────── --}}
                        @foreach($credentialTypes as $type)
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="addInstHeading{{ $type }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#addInstSection{{ $type }}" aria-expanded="false"
                                            aria-controls="addInstSection{{ $type }}">
                                        <span class="badge bg-primary me-2" style="font-size:.7rem;">{{ $type }}</span>
                                        {{ $credLabels[$type] ?? $type }}
                                    </button>
                                </h2>
                                <div id="addInstSection{{ $type }}" class="accordion-collapse collapse"
                                     aria-labelledby="addInstHeading{{ $type }}">
                                    <div class="accordion-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="add_cred_{{ $type }}_number" class="form-label">Certificate Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="add_cred_{{ $type }}_number"
                                                       name="credentials[{{ $type }}][number]"
                                                       value="{{ old('credentials.' . $type . '.number') }}" maxlength="255" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="add_cred_{{ $type }}_pdf" class="form-label">Certificate (PDF) <span class="text-danger">*</span></label>
                                                <input type="file" class="form-control form-control-sm" id="add_cred_{{ $type }}_pdf"
                                                       name="credentials[{{ $type }}][pdf]" accept="application/pdf,.pdf" data-validate-pdf required>
                                                <div class="form-text" style="font-size:.75rem;">PDF only, max 15 MB.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="add_cred_{{ $type }}_issued" class="form-label">Date Issued <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="add_cred_{{ $type }}_issued"
                                                       name="credentials[{{ $type }}][issued_date]"
                                                       value="{{ old('credentials.' . $type . '.issued_date') }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="add_cred_{{ $type }}_validity" class="form-label">Valid Until <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="add_cred_{{ $type }}_validity"
                                                       name="credentials[{{ $type }}][validity_date]"
                                                       value="{{ old('credentials.' . $type . '.validity_date') }}" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-paper-plane me-1"></i> Submit for Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
