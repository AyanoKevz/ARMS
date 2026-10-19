{{--
    Directory of Participants — step one of the submission wizard.

    PHP request limits shape this grid. A directory of any size cannot be filed
    in one POST: post_max_size caps the whole request (295M here), and at up to
    5 MB per ID picture that is exhausted well before a large batch is. So each
    picture uploads on its own as it is chosen and the form carries only a token.

    The rows are likewise serialised into a single JSON field rather than ~19
    named inputs each, which keeps them clear of max_input_vars. This stack
    raises max_file_uploads and max_input_vars in .user.ini (250 / 10000), but
    shared hosts commonly ship far lower defaults and both limits truncate
    SILENTLY when exceeded — no error, just missing data. Neither is left to
    chance because the failure mode is invisible.

    The step frame supplies the heading; this partial is only the body.
--}}

{{-- ── Bulk controls ─────────────────────────────────────────────────────── --}}
<div class="ptr-dir-toolbar">
    <div class="ptr-dir-toolbar-group">
        <label class="ptr-dir-toolbar-label" for="ptrDirAddCount">Add</label>
        <input type="number"
               id="ptrDirAddCount"
               class="form-control form-control-sm ptr-dir-count"
               value="1" min="1" max="50" step="1"
               aria-label="Number of rows to add">
        <button type="button" class="btn btn-sm ptr-dir-add-btn" id="ptrDirAddRows">
            <i class="fas fa-plus me-1"></i> <span>Add rows</span>
        </button>
        <span class="ptr-dir-hint">up to 50 at a time</span>
    </div>

    <div class="ptr-dir-toolbar-group">
        <label class="ptr-dir-toolbar-label" for="ptrDirBatchAll">Batch No.</label>
        <input type="text"
               id="ptrDirBatchAll"
               class="form-control form-control-sm ptr-dir-batch"
               placeholder="e.g. 2026-01"
               aria-label="Batch number applied to all participants">
        <button type="button" class="btn btn-sm ptr-dir-apply-btn" id="ptrDirApplyBatch">
            Apply to all
        </button>
    </div>

    <div class="ptr-dir-counter ms-auto">
        <span id="ptrDirCount">0</span> participant<span id="ptrDirCountPlural"></span>
    </div>
</div>

{{-- ── Grid ──────────────────────────────────────────────────────────────── --}}
{{-- A red * marks every column the server requires; Middle Name and Suffix are
     the only optional ones. A participant is listed in the Pre- and Post-Test
     Summary Evaluation (requirement 5) once all of its * columns are filled. --}}
@php $req = '<span class="ptr-dir-req" aria-hidden="true">*</span>'; @endphp
<div class="ptr-dir-scroll">
    <table class="ptr-dir-table" id="ptrDirTable">
        <thead>
            <tr>
                <th class="ptr-dir-sticky ptr-dir-col-no">#</th>
                <th class="ptr-dir-w-cert">Certificate Number {!! $req !!}</th>
                <th class="ptr-dir-sticky-2 ptr-dir-w-name">Last Name {!! $req !!}</th>
                <th class="ptr-dir-sticky-3 ptr-dir-w-name">First Name {!! $req !!}</th>
                <th class="ptr-dir-w-name">Middle Name</th>
                <th class="ptr-dir-w-suffix">Suffix</th>
                <th class="ptr-dir-w-sex">Sex {!! $req !!}</th>
                <th class="ptr-dir-w-age">Age {!! $req !!}</th>
                <th class="ptr-dir-w-wide">Company {!! $req !!}</th>
                <th class="ptr-dir-w-wide">Position {!! $req !!}</th>
                <th class="ptr-dir-w-address">Company Address (Region) {!! $req !!}</th>
                <th class="ptr-dir-w-address">Company Address (City / Municipality) {!! $req !!}</th>
                <th class="ptr-dir-w-wide">Industry {!! $req !!}</th>
                <th class="ptr-dir-w-num">Total No. of Workers {!! $req !!}</th>
                <th class="ptr-dir-w-wide">Company Email {!! $req !!}</th>
                <th class="ptr-dir-w-wide">Personal Email {!! $req !!}</th>
                <th class="ptr-dir-w-phone">Mobile No. {!! $req !!}</th>
                <th class="ptr-dir-w-phone">Company Landline {!! $req !!}</th>
                <th class="ptr-dir-w-photo">ID Picture {!! $req !!}</th>
                <th class="ptr-dir-w-mode">Mode of Training {!! $req !!}</th>
                <th class="ptr-dir-w-batch">Batch No. {!! $req !!}</th>
                {{-- A participant who began this course in an earlier training
                     and completes it here. The two date columns appear once
                     any row says Yes. --}}
                <th class="ptr-dir-w-catchup">Catch Up {!! $req !!}</th>
                <th class="ptr-dir-w-catchup-date ptr-dir-catchup-col">Training Start Date {!! $req !!}</th>
                <th class="ptr-dir-w-catchup-date ptr-dir-catchup-col">Training End Date</th>
                <th class="ptr-dir-w-act"></th>
            </tr>
        </thead>
        <tbody id="ptrDirBody"></tbody>
    </table>
</div>

<div class="ptr-dir-empty" id="ptrDirEmpty">
    <i class="fas fa-table me-1"></i>
    No participants encoded yet &mdash; use <strong>Add rows</strong> above to begin.
</div>

<div class="ptr-field-error d-none" id="error_ptr_directory">
    Encode at least one participant, and complete every required field.
</div>

{{-- The whole grid rides in as one field: see the note at the top. --}}
<input type="hidden" name="participants" id="ptrDirPayload">

{{-- ── Row template ──────────────────────────────────────────────────────── --}}
<template id="ptrDirRowTemplate">
    <tr class="ptr-dir-row">
        <td class="ptr-dir-sticky ptr-dir-rowno"></td>
        <td><input type="text" class="ptr-dir-input" data-field="certificate_number" required></td>
        <td class="ptr-dir-sticky-2"><input type="text" class="ptr-dir-input" data-field="last_name" required></td>
        <td class="ptr-dir-sticky-3"><input type="text" class="ptr-dir-input" data-field="first_name" required></td>
        <td><input type="text" class="ptr-dir-input" data-field="middle_name"></td>
        <td><input type="text" class="ptr-dir-input" data-field="suffix" maxlength="20"></td>
        <td>
            <select class="ptr-dir-input" data-field="sex" required>
                {{-- Disabled so it cannot be chosen back: it is a prompt, not
                     an answer, and an empty sex fails validation either way. --}}
                <option value="" disabled selected>— Select —</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </td>
        <td><input type="number" class="ptr-dir-input" data-field="age" min="1" max="120" required></td>
        <td><input type="text" class="ptr-dir-input" data-field="company" required></td>
        <td><input type="text" class="ptr-dir-input" data-field="position" required></td>
        {{-- Left empty on purpose. Inlining 1,634 cities into a row template
             that is cloned up to 500 times would bloat the page for nothing;
             ph-fields.js fills these from a cached 20 KB asset instead, and
             the city list follows whichever region is picked. --}}
        <td><select class="ptr-dir-input" data-field="company_region" data-ph-region required></select></td>
        <td><select class="ptr-dir-input" data-field="company_city" data-ph-city required disabled></select></td>
        <td><input type="text" class="ptr-dir-input" data-field="industry" required></td>
        <td><input type="number" class="ptr-dir-input" data-field="total_workers" min="0" required></td>
        <td><input type="email" class="ptr-dir-input" data-field="company_email"
                   placeholder="name@company.com" maxlength="255" required></td>
        <td><input type="email" class="ptr-dir-input" data-field="personal_email"
                   placeholder="name@email.com" maxlength="255" required></td>
        {{-- The two shapes RegistrationController already enforces: a PH mobile
             number, and a ten-digit landline with its area code. --}}
        <td><input type="text" class="ptr-dir-input" data-field="mobile_no" data-ph-mobile
                   inputmode="tel" maxlength="13" placeholder="09171234567" required></td>
        <td><input type="text" class="ptr-dir-input" data-field="company_landline" data-ph-landline
                   inputmode="numeric" maxlength="10" placeholder="0281234567" required></td>
        <td>
            <div class="ptr-dir-photo">
                <input type="file" class="d-none ptr-dir-photo-input" accept=".jpg,.jpeg,.png">
                <button type="button" class="btn btn-sm ptr-dir-photo-btn">
                    <i class="fas fa-camera me-1"></i> Upload
                </button>
                <span class="ptr-dir-photo-state"></span>
            </div>
        </td>
        <td><input type="text" class="ptr-dir-input" data-field="mode_of_training" required></td>
        <td><input type="text" class="ptr-dir-input" data-field="batch_no" required></td>
        <td>
            <select class="ptr-dir-input" data-field="catch_up" data-catchup-toggle required>
                <option value="No" selected>No</option>
                <option value="Yes">Yes</option>
            </select>
        </td>
        {{-- Filled by post-training.js with the FATPro's other trainings
             that have already been held: picking one sets the start date. --}}
        <td class="ptr-dir-catchup-col">
            <select class="ptr-dir-input" data-field="catch_up_ntc_report_id" data-catchup-source disabled></select>
        </td>
        {{-- Always this training's last day; shown, never typed or posted. --}}
        <td class="ptr-dir-catchup-col">
            <span class="ptr-dir-catchup-end" data-catchup-end>—</span>
        </td>
        <td class="ptr-dir-act">
            <button type="button" class="btn btn-sm ptr-dir-remove" title="Remove this participant">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    </tr>
</template>
