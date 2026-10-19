{{--
    The participants an evaluator turned down, laid out for correction.

    Only the rejected rows appear: an approved participant is settled, and
    applyParticipantCorrections() ignores anything else that arrives anyway.
    The evaluator judges the Directory as a whole, so a declined Directory
    declines — and lists here — every participant.

    A card per participant rather than the encoding grid's nineteen columns —
    a labelled card reads far better than scrolling a wide table sideways to
    find the field that was wrong.

    post-training-corrections.js serialises these back into the `participants`
    JSON the server already understands, so nothing downstream changes.

    Expects: $report (PostTrainingReport)
--}}

@php
    $rejectedRows = $report->rejectedParticipants()->sortBy('row_no');
    // The trainings a catch-up participant may have started in — one query
    // for the whole card list.
    $catchUpOptions = $report->ntcReport?->catchUpOptions() ?? [];
@endphp

<div class="ptr-correction-rows" data-correction-participants>
    @foreach($rejectedRows as $participant)
    <div class="ptr-correction-card" data-participant-id="{{ $participant->id }}">
        <div class="ptr-correction-card-head">
            <span class="ptr-correction-badge">#{{ $participant->row_no }}</span>
            <span class="ptr-correction-name">
                {{ trim($participant->last_name . ', ' . $participant->first_name) }}
            </span>
            <span class="badge badge-ptr-danger ms-auto">Declined</span>
        </div>

        <div class="ptr-correction-grid">
            @php
                $textFields = [
                    'certificate_number' => ['Certificate Number', true],
                    'last_name'          => ['Last Name', true],
                    'first_name'         => ['First Name', true],
                    'middle_name'        => ['Middle Name', false],
                    'suffix'             => ['Suffix', false],
                ];
            @endphp

            @foreach($textFields as $field => [$label, $required])
            <label class="ptr-correction-field">
                <span>{{ $label }} @if($required)<b class="text-danger">*</b>@endif</span>
                <input type="text"
                       class="form-control form-control-sm"
                       data-field="{{ $field }}"
                       value="{{ $participant->{$field} }}"
                       @if($required) data-required @endif>
            </label>
            @endforeach

            <label class="ptr-correction-field">
                <span>Sex <b class="text-danger">*</b></span>
                <select class="form-control form-control-sm" data-field="sex" data-required>
                    <option value="" disabled {{ $participant->sex ? '' : 'selected' }}>— Select —</option>
                    <option value="Male" {{ $participant->sex === 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ $participant->sex === 'Female' ? 'selected' : '' }}>Female</option>
                </select>
            </label>

            <label class="ptr-correction-field">
                <span>Age <b class="text-danger">*</b></span>
                <input type="number" min="1" max="120"
                       class="form-control form-control-sm"
                       data-field="age"
                       value="{{ $participant->age }}"
                       data-required>
            </label>

            @php
                // Same fields, same order and same formats as the encoding grid
                // — a correction that would not have passed on a first filing
                // should not pass here either. 'kind' picks the control:
                // region/city are PSGC pickers, mobile/landline carry the
                // registration form's shapes, the rest are plain inputs.
                $moreFields = [
                    'company'          => ['Company', true, 'text'],
                    'position'         => ['Position', true, 'text'],
                    'company_region'   => ['Company Address (Region)', true, 'region'],
                    'company_city'     => ['Company Address (City / Municipality)', true, 'city'],
                    'industry'         => ['Industry', true, 'text'],
                    'total_workers'    => ['Total No. of Workers', true, 'number'],
                    'company_email'    => ['Company Email', true, 'email'],
                    'personal_email'   => ['Personal Email', true, 'email'],
                    'mobile_no'        => ['Mobile No.', true, 'mobile'],
                    'company_landline' => ['Company Landline', true, 'landline'],
                    'mode_of_training' => ['Mode of Training', true, 'text'],
                    'batch_no'         => ['Batch No.', true, 'text'],
                ];
            @endphp

            @foreach($moreFields as $field => [$label, $required, $kind])
            <label class="ptr-correction-field">
                <span>{{ $label }} @if($required)<b class="text-danger">*</b>@endif</span>

                @if($kind === 'region' || $kind === 'city')
                    {{-- Options come from ph-fields.js; data-value is what it
                         restores once the register has been fetched. --}}
                    <select class="form-control form-control-sm"
                            data-field="{{ $field }}"
                            data-ph-{{ $kind }}
                            data-value="{{ $participant->{$field} }}"
                            @if($kind === 'city') disabled @endif
                            @if($required) data-required @endif></select>
                @else
                    <input type="{{ in_array($kind, ['number', 'email'], true) ? $kind : 'text' }}"
                           @class(['form-control', 'form-control-sm'])
                           @if($kind === 'number') min="0" @endif
                           @if($kind === 'mobile') data-ph-mobile inputmode="tel" maxlength="13" placeholder="09171234567" @endif
                           @if($kind === 'landline') data-ph-landline inputmode="numeric" maxlength="10" placeholder="0281234567" @endif
                           @if($kind === 'email') maxlength="255" @endif
                           data-field="{{ $field }}"
                           value="{{ $participant->{$field} }}"
                           @if($required) data-required @endif>
                @endif
            </label>
            @endforeach

            {{-- Catch-up participant: began this course in an earlier training. --}}
            <label class="ptr-correction-field">
                <span>Catch Up <b class="text-danger">*</b></span>
                <select class="form-control form-control-sm" data-field="catch_up" data-catchup-toggle data-required>
                    <option value="No" {{ $participant->is_catch_up ? '' : 'selected' }}>No</option>
                    <option value="Yes" {{ $participant->is_catch_up ? 'selected' : '' }}>Yes</option>
                </select>
            </label>

            <label class="ptr-correction-field" data-catchup-only @if(!$participant->is_catch_up) hidden @endif>
                <span>Training Start Date <b class="text-danger">*</b></span>
                <select class="form-control form-control-sm" data-field="catch_up_ntc_report_id" data-catchup-source>
                    <option value="">{{ $catchUpOptions ? '— Select the training they started in —' : 'No completed training yet' }}</option>
                    @foreach($catchUpOptions as $option)
                        <option value="{{ $option['id'] }}" {{ (int) $participant->catch_up_ntc_report_id === $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="ptr-correction-field" data-catchup-only @if(!$participant->is_catch_up) hidden @endif>
                <span>Training End Date</span>
                <input type="text" class="form-control form-control-sm" value="{{ $report->ntcReport?->training_end_date?->format('M d, Y') }}" readonly>
            </label>
        </div>

        {{-- The row already has a picture, so a replacement is optional. --}}
        <div class="ptr-correction-photo">
            <span class="ptr-correction-photo-label">ID Picture</span>
            <input type="file" class="d-none" data-correction-photo accept=".jpg,.jpeg,.png">
            <button type="button" class="btn btn-sm ptr-dir-photo-btn" data-correction-photo-btn>
                Replace
            </button>
            <span class="ptr-correction-photo-state" data-correction-photo-state>
                Keeping the picture already on file
            </span>
        </div>
    </div>
    @endforeach
</div>
