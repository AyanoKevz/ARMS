{{--
    Training day selection for one NTC form.

    The training type fixes how MANY days a course runs, not which dates: they
    are calendar days the FATPro picks, so weekends count and the days need not
    be consecutive. A single day of the course may also be delivered over more
    than one date, so each day is a group that can take further dates.

    The days come first because everything else follows from them: the start and
    end dates below are simply the earliest and latest of whatever is picked,
    filled in by ntc.js and never typed. Only the dates are posted;
    training_start_date is re-derived server-side.

    Shared by the new-NTC form and the Report of Changes modal, which differ
    only by the id prefix their scripts expect.

    Expects: $prefix (id prefix), $minDate (earliest selectable date),
             $oldExtraDays (array, dayNo => dates, to repopulate with)
--}}

@php
    $startId = $prefix . 'training_start_date';
    $endId   = $prefix . 'training_end_date';
@endphp

<div class="ntc-training-days"
     data-training-days
     data-prefix="{{ $prefix }}"
     data-min-date="{{ $minDate }}"
     data-preset="{{ json_encode((object) $oldExtraDays) }}">

    {{-- Shown until a type is chosen, since the type decides how many days
         there are to pick. --}}
    <div class="alert alert-secondary py-2 ntc-text-sm" data-days-locked>
        <i class="bi bi-info-circle me-1"></i>
        Select a type of training above to choose the training days.
    </div>

    {{-- One group per day of the course, built by ntc.js once a type is picked. --}}
    <div class="form-group mb-3 ntc-extra-days" data-extra-days hidden>
        <label class="fw-semibold">
            Training Days <span class="text-danger">*</span>
        </label>
        <p class="text-muted mb-2 ntc-text-80" data-extra-days-hint></p>
        <div class="ntc-day-groups" data-day-groups></div>
        <div class="text-danger mt-1 ntc-text-sm d-none" data-days-error></div>
        @error('training_dates')
            <div class="text-danger mt-1 ntc-text-sm">{{ $message }}</div>
        @enderror
        @error('training_dates.*.*')
            <div class="text-danger mt-1 ntc-text-sm">{{ $message }}</div>
        @enderror
    </div>

    {{-- Both derived from the days above, so neither is typed. --}}
    <div class="row">
        <div class="col-6">
            <div class="form-group mb-3">
                <label class="fw-semibold" for="{{ $startId }}">
                    Training Start Date
                </label>
                <input type="date"
                       id="{{ $startId }}"
                       class="form-control ntc-derived-field"
                       readonly
                       tabindex="-1"
                       aria-describedby="{{ $startId }}_hint">
                <div class="form-text" id="{{ $startId }}_hint">
                    <i class="bi bi-info-circle me-1"></i>
                    The first of the training dates you select.
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="form-group mb-3">
                <label class="fw-semibold" for="{{ $endId }}">
                    Training End Date
                </label>
                <input type="date"
                       id="{{ $endId }}"
                       class="form-control ntc-derived-field"
                       readonly
                       tabindex="-1"
                       aria-describedby="{{ $endId }}_hint">
                <div class="form-text" id="{{ $endId }}_hint">
                    <i class="bi bi-info-circle me-1"></i>
                    The last of the training dates you select.
                </div>
            </div>
        </div>
    </div>
</div>
