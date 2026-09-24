{{--
    The instructors a FATPro declares will conduct a training.

    Drawn from their own accredited roster, any number of them, at least one.
    Ineligible entries are shown disabled with the reason rather than hidden —
    a name that is simply missing leaves the FATPro guessing why.

    Two kinds of ineligibility are in play. Whether the instructor and their
    credentials cleared evaluation is settled server-side and baked into
    data-reason. Whether a credential outlasts the training cannot be: the form
    does not know the last training day until it is picked, so data-expires
    carries the soonest expiry and ntc.js greys the row out once the chosen
    dates run past it. Both are re-checked on submit.

    Expects: $prefix (''|'modal_'), $instructorRoster, $selectedInstructorIds
--}}

<div class="form-group mb-3 ntc-instructor-picker"
     data-instructor-picker
     data-prefix="{{ $prefix }}">

    <label class="fw-semibold" for="{{ $prefix }}instructor_picker">
        Instructors Who Will Conduct the Training <span class="text-danger">*</span>
    </label>
    <p class="text-muted mb-2 ntc-text-80">
        Select from your accredited instructors — as many as will be conducting
        this training. This list carries over to your Post Training Report.
    </p>

    @if($instructorRoster->isEmpty())
        <div class="alert alert-warning py-2 mb-0 ntc-text-sm">
            <i class="fas fa-exclamation-triangle me-1"></i>
            You have no instructors on your accredited roster yet. Add one from
            your Instructor list before filing a Notice to Conduct.
        </div>
    @else
        <div class="ntc-instructor-list" id="{{ $prefix }}instructor_picker" role="group">
            @foreach($instructorRoster as $instructor)
                @php
                    // The person, not this application's copy of them: a renewal
                    // replaces the copy, and a declared training must outlive it.
                    $personId = $instructor->instructor_person_id;
                    $reason   = $personId
                        ? $instructor->ineligibility_reason
                        : 'This instructor record predates the roster and cannot be selected.';
                    $expires  = $instructor->earliestCredentialExpiry();
                    $checked  = $personId && in_array($personId, $selectedInstructorIds, true);
                @endphp
                <label class="ntc-instructor-option{{ $reason ? ' is-ineligible' : '' }}"
                       data-instructor-option
                       data-reason="{{ $reason }}"
                       data-expires="{{ $expires?->toDateString() }}">
                    <input type="checkbox"
                           class="form-check-input ntc-instructor-check"
                           name="instructor_ids[]"
                           value="{{ $personId }}"
                           {{ $checked && !$reason ? 'checked' : '' }}
                           {{ $reason ? 'disabled' : '' }}>
                    <span class="ntc-instructor-body">
                        <span class="ntc-instructor-name">{{ $instructor->listingName() }}</span>
                        {{-- Nothing under the name while the instructor is
                             pickable; the reason appears only when they are
                             not, so a disabled row is never unexplained. --}}
                        <span class="ntc-instructor-meta{{ $reason ? '' : ' d-none' }}" data-instructor-meta>{{ $reason }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        <div class="text-danger mt-1 ntc-text-sm d-none" data-instructor-error>
            Select at least one instructor to conduct this training.
        </div>
    @endif

    @error('instructor_ids')
        <div class="text-danger mt-1 ntc-text-sm">{{ $message }}</div>
    @enderror
</div>
