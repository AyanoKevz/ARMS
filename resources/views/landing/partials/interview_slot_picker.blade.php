{{--
    Tracking page: an approved OSH Practitioner / Consultant applicant chooses
    one of the Accreditation division's interview slots from a calendar.
    Posts to TrackingController::bookInterview.

    Expects $application and $slotsByDate (upcoming slots, booked or not,
    grouped by Y-m-d). Taken slots show as Fully Booked; a date whose slots
    are all taken is marked Fully Booked on the calendar.
--}}
@php
    $hasOpen = fn ($slots) => $slots->contains(fn ($slot) => ! $slot->isBooked());
    $calendarDays = $slotsByDate->map(fn ($slots) => $hasOpen($slots) ? 'open' : 'full');
    $earliestDate = $calendarDays->search('open');
@endphp
@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/track-interview.css') }}">
@endpush
@push('scripts')
<script src="{{ \App\Support\AssetVersion::url('js/track-interview.js') }}" defer></script>
@endpush

<div class="ti-card">
    <div class="ti-card-head">
        <i class="bi bi-calendar2-check-fill"></i> Documents Approved — Choose Your Interview Schedule
    </div>
    <div class="ti-card-body">
        <p class="ti-intro">
            All your documents were approved. Pick an available date on the calendar, then choose a time.
            Slots are first come, first served, and your choice cannot be changed online once confirmed.
        </p>

        <form method="POST" action="{{ route('track.interview.book') }}" class="ti-picker" data-ti-form
              data-ti-days="{{ $calendarDays->toJson() }}"
              data-ti-today="{{ now()->toDateString() }}"
              data-ti-initial="{{ $earliestDate ?: '' }}">
            @csrf
            <input type="hidden" name="tracking_number" value="{{ $application->tracking_number }}">

            <div class="ti-columns">
                {{-- ── Date ─────────────────────────────────────────── --}}
                <section class="ti-col">
                    <h3 class="ti-col-title">Date</h3>
                    <div class="ti-earliest">
                        Earliest available interview:
                        <strong>{{ $earliestDate ? \Carbon\Carbon::parse($earliestDate)->format('l, F j, Y') : 'No available date' }}</strong>
                    </div>
                    <p class="ti-regular">Additional interview slots are opened regularly.</p>

                    <div class="ti-calendar" data-ti-calendar aria-live="polite"></div>

                    <div class="ti-legend">
                        <span class="ti-legend-item is-open">Available</span>
                        <span class="ti-legend-item is-full">Fully Booked</span>
                    </div>
                </section>

                {{-- ── Time ─────────────────────────────────────────── --}}
                <section class="ti-col">
                    <h3 class="ti-col-title">Time</h3>

                    <p class="ti-placeholder" data-ti-placeholder>
                        @if($slotsByDate->isEmpty())
                            <i class="bi bi-hourglass-split"></i>
                            No interview slots are open right now. New dates are added regularly — please check back on this page.
                        @else
                            <i class="bi bi-hand-index"></i>
                            Select a highlighted date on the calendar to see its interview times.
                        @endif
                    </p>

                    @foreach($slotsByDate as $date => $slots)
                        @php $dateLabel = \Carbon\Carbon::parse($date)->format('l, F j, Y'); @endphp
                        <fieldset class="ti-slots" data-ti-date="{{ $date }}">
                            <legend class="ti-slots-date">{{ $dateLabel }}</legend>
                            @foreach($slots as $slot)
                                <label class="ti-slot {{ $slot->isBooked() ? 'is-full' : '' }}">
                                    <input type="radio" name="slot_id" value="{{ $slot->id }}" required
                                           data-ti-slot
                                           data-ti-label="{{ $dateLabel }}, {{ $slot->timeLabel() }}"
                                           data-ti-date-label="{{ $dateLabel }}"
                                           data-ti-time-label="{{ $slot->timeLabel() }}"
                                           data-ti-mode-label="{{ $slot->modeLabel() }}{{ $slot->mode === 'f2f' && $slot->venue ? ' · ' . $slot->venue : '' }}"
                                           @disabled($slot->isBooked())>
                                    <span class="ti-slot-time">
                                        {{ $slot->timeLabel() }}
                                        <small>
                                            <i class="bi {{ $slot->mode === 'online' ? 'bi-camera-video' : 'bi-geo-alt' }}"></i>
                                            {{ $slot->modeLabel() }}{{ $slot->mode === 'f2f' && $slot->venue ? ' · ' . $slot->venue : '' }}
                                        </small>
                                    </span>
                                    <span class="ti-slot-state">{{ $slot->isBooked() ? 'Fully Booked' : 'Available' }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </section>
            </div>

            <div class="ti-submit">
                <span class="ti-chosen" data-ti-chosen>No schedule selected yet.</span>
                <button type="submit" class="btn btn-primary fw-semibold px-4" data-ti-submit disabled>
                    <i class="bi bi-check2-circle me-1"></i> Confirm Interview Schedule
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Confirm the chosen slot — the Accreditation admins' modal (Update
     Interview Schedule), restyled for this page in track-interview.css.
     track-interview.js fills it in and submits the form on Confirm. --}}
<div class="modal fade" id="tiConfirmModal" tabindex="-1" aria-labelledby="tiConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ti-modal">
            <div class="modal-header border-0 ti-modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="ti-modal-icon"><i class="bi bi-calendar2-check"></i></span>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="tiConfirmModalLabel">Confirm Interview Schedule</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 ti-modal-body">
                <div class="ti-modal-slot">
                    <div class="ti-modal-row"><i class="bi bi-calendar-event"></i><span data-ti-confirm="date"></span></div>
                    <div class="ti-modal-row"><i class="bi bi-clock"></i><span data-ti-confirm="time"></span></div>
                    <div class="ti-modal-row"><i class="bi bi-geo-alt"></i><span data-ti-confirm="mode"></span></div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 mt-3 ti-modal-note">
                    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                    <small class="text-dark">
                        This cannot be changed online once confirmed. You will receive an email with your interview details.
                    </small>
                </div>
            </div>
            <div class="modal-footer border-0 ti-modal-body">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary fw-bold px-4" data-ti-confirm-ok>
                    <i class="bi bi-check2-circle me-1"></i> Confirm Schedule
                </button>
            </div>
        </div>
    </div>
</div>
