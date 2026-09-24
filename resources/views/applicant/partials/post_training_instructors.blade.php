{{--
    Requirement 2 of the Post Training Report: who actually conducted it.

    No longer a scanned PDF. The list arrives already ticked from the parent
    NTC's declared instructors, and the FATPro either proceeds or amends it.

    The whole accredited roster is rendered once here, because the dialog is a
    single modal retargeted per training — post-training.js ticks the ones this
    NTC declared, using data-instructor-ids on the button that opened it.

    Someone declared on the NTC stays selectable whatever has happened to their
    credentials since: they taught the course, and a later lapse does not unmake
    that. Anyone else is a late addition and is disabled here if not currently
    eligible, matching what resolveReportInstructors() will accept.

    Expects: $instructorRoster
--}}

<div class="ptr-instructor-picker" data-ptr-instructors>

    <p class="ptr-instructor-lead">
        <i class="fas fa-info-circle me-1"></i>
        Carried over from your Notice to Conduct. Tick or untick to match who
        actually conducted the training.
    </p>

    @if($instructorRoster->isEmpty())
        <div class="ptr-notice ptr-notice-danger mb-0">
            <i class="fas fa-exclamation-triangle me-1"></i>
            You have no instructors on your accredited roster.
        </div>
    @else
        <div class="ptr-instructor-list" role="group" aria-label="Instructors who conducted the training">
            @foreach($instructorRoster as $instructor)
                @php
                    $personId = $instructor->instructor_person_id;
                    $reason   = $personId
                        ? $instructor->ineligibility_reason
                        : 'This instructor record predates the roster and cannot be selected.';
                @endphp
                <label class="ptr-instructor-option"
                       data-ptr-instructor-option
                       data-reason="{{ $reason }}">
                    <input type="checkbox"
                           class="form-check-input ptr-instructor-check"
                           name="instructor_ids[]"
                           value="{{ $personId }}">
                    <span class="ptr-instructor-body">
                        <span class="ptr-instructor-name">{{ $instructor->listingName() }}</span>
                        {{-- Only shown when something blocks the pick. --}}
                        <span class="ptr-instructor-meta{{ $reason ? '' : ' d-none' }}">{{ $reason }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    @endif

    <div class="ptr-field-error d-none" id="error_ptr_instructors">
        Select at least one instructor who conducted this training.
    </div>
</div>
