{{--
    OSH Practitioner / OSH Consultant requirements notice.

    Rendered next to #fatproRequirementsNotice so the checklist is on screen
    before the applicant starts typing, exactly as the FATPro flow does.
    practitioner-form.js reveals it when accreditation type 1 or 2 is
    selected, and shows only the selected type's data-prac-for parts — the
    two fill in the same form; the photo background and the documents differ.
--}}
@php
    $practitionerId = (int) config('accreditation.types.practitioner');
    $consultantId   = (int) config('accreditation.types.consultant');
@endphp
<div id="practitionerNotice" class="prac-requirements-notice mb-4 d-none">
    <div class="d-flex align-items-start gap-3">
        <div class="prac-requirements-icon">
            <i class="bi bi-person-vcard-fill"></i>
        </div>
        <div class="w-100">
            <h6 class="fw-bold mb-1 prac-notice-title">
                <i class="bi bi-bell-fill text-warning me-1"></i>
                <span data-prac-for="{{ $practitionerId }}">OSH Practitioner</span><span data-prac-for="{{ $consultantId }}">OSH Consultant</span>
                Accreditation &mdash; Requirements &amp; Checklist Reminder
            </h6>
            <p class="mb-2 text-dark prac-notice-lead">
                This is a <strong>new application</strong> for accreditation as an
                <span data-prac-for="{{ $practitionerId }}">OSH Practitioner</span><span data-prac-for="{{ $consultantId }}">OSH Consultant</span>.
                Before proceeding, prepare your personal records and all supporting documents in
                <strong>PDF format (max 15 MB per file)</strong>, plus a recent <strong>1" x 1" ID photo with a
                <span data-prac-for="{{ $practitionerId }}">red</span><span data-prac-for="{{ $consultantId }}">blue</span>
                background</strong> (JPG or PNG).
            </p>
            <div class="row g-2 text-dark mt-1 prac-notice-grid">
                <div class="col-md-6">
                    <div class="p-2 bg-white rounded border shadow-sm h-100">
                        <div class="fw-bold mb-1 prac-notice-col-title">
                            <i class="bi bi-person-lines-fill me-1"></i>A. Information You Will Encode
                        </div>
                        <ul class="mb-0 ps-3 text-secondary prac-notice-list">
                            <li>Personal profile &amp; 1" x 1" ID photo
                                <em data-prac-for="{{ $practitionerId }}">(red background)</em><em data-prac-for="{{ $consultantId }}">(blue background)</em></li>
                            <li>Workplace character &amp; demographics</li>
                            <li>Educational attainment &amp; professional licenses</li>
                            <li>Work experience (most recent first)</li>
                            <li>OSH trainings attended &amp; lectures conducted</li>
                            <li>Skills, awards, examinations &amp; memberships</li>
                            <li>At least three (3) character references</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-2 bg-white rounded border shadow-sm h-100">
                        <div class="fw-bold mb-1 prac-notice-col-title">
                            <i class="bi bi-folder-fill me-1"></i>B. Documents You Will Upload
                        </div>
                        <ul class="mb-0 ps-3 text-secondary prac-notice-list" data-prac-for="{{ $practitionerId }}">
                            <li>Original Certificate of Employment (signed by Employer/HR)</li>
                            <li>Actual Duties and Responsibilities (signed by Supervisor)</li>
                            <li>Certificate of Employment from previous employer <em>(if applicable)</em></li>
                            <li>Certificate of Completion &mdash; Bureau-prescribed 40-hour Basic OSH Training</li>
                            <li>Certificates of Attendance for other OSH trainings/seminars</li>
                            <li>College Diploma or Transcript of Records</li>
                            <li>Proof of OSH-related accomplishments or participation</li>
                        </ul>
                        <ul class="mb-0 ps-3 text-secondary prac-notice-list" data-prac-for="{{ $consultantId }}">
                            <li>Certificate of Employment or Consultancy Contract</li>
                            <li>List of OSH Consultancy Projects with client validation</li>
                            <li>Certificate of Completion of Advanced OSH Courses</li>
                            <li>Proof of at least 5 years OSH experience in a supervisory capacity</li>
                            <li>Photocopy of College Diploma or Transcript of Records</li>
                            <li>Professional License (PRC ID) <em>(if applicable)</em></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mt-2 pt-2 border-top d-flex align-items-center gap-2 prac-notice-foot">
                <i class="bi bi-info-circle-fill"></i>
                <span><strong>Note:</strong> Fields marked <span class="text-danger">*</span> are required.
                    Optional fields left blank appear as <strong>N/A</strong> on your application form.</span>
            </div>
        </div>
    </div>
</div>
