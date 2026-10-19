{{--
    Where an NTC's training is held.

    Every training has a venue. A blended one is partly online as well, so it
    also takes a meeting link — shown and required only while the mode select
    beside it is on Blended (toggled by NtcTrainingPicker.wireVenueFields).

    Shared by the new-NTC form, the Report of Changes modal and the evaluator's
    details editor, which differ only by the id prefix their scripts expect.

    Expects: $prefix (id prefix), $venue, $meetingLink (current values),
             $showErrors (bool — only the new-NTC form posts back with errors)
--}}

@php
    $venueId = $prefix . 'ntc_venue';
    $linkId  = $prefix . 'ntc_meeting_link';
    $showErrors = $showErrors ?? false;
@endphp

<div data-venue-fields data-prefix="{{ $prefix }}">
    {{-- Venue --}}
    <div class="form-group mb-3">
        <label class="fw-semibold" for="{{ $venueId }}">
            Venue <span class="text-danger">*</span>
        </label>
        <input type="text"
               id="{{ $venueId }}"
               name="venue"
               class="form-control {{ $showErrors && $errors->has('venue') ? 'is-invalid' : '' }}"
               placeholder="e.g. OSHC Main Auditorium, Quezon City"
               value="{{ $venue }}"
               maxlength="500"
               required>
        @if($showErrors)
            @error('venue')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>

    {{-- Zoom / Meeting Link — blended only --}}
    <div class="form-group mb-3" data-meeting-link-group hidden>
        <label class="fw-semibold" for="{{ $linkId }}">
            Zoom Link / Meeting Link <span class="text-danger">*</span>
        </label>
        <input type="url"
               id="{{ $linkId }}"
               name="meeting_link"
               class="form-control {{ $showErrors && $errors->has('meeting_link') ? 'is-invalid' : '' }}"
               placeholder="e.g. https://zoom.us/j/123456789"
               value="{{ $meetingLink }}"
               maxlength="500"
               aria-describedby="{{ $linkId }}_note"
               disabled>
        @if($showErrors)
            @error('meeting_link')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
        <div class="alert alert-warning py-2 mt-2 mb-0 ntc-text-sm" id="{{ $linkId }}_note">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Ensure the meeting link is open access: no waiting room, host approval, or
            organization sign-in required. Include the passcode in the link if applicable.
            Keep it valid for all training dates.
        </div>
    </div>
</div>
