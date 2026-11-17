{{--
    Update Schedule — FATPro's interview schedule modal, but the new date and
    time are picked from the open interview slots (Manage Interview Slots).
    The applicant is emailed FATPro's schedule-updated notice.

    Expects $application, $slot (the booked one), $openSlots (grouped by Y-m-d).
--}}
<div class="modal fade" id="rescheduleInterviewModal" tabindex="-1" aria-labelledby="rescheduleInterviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header is-primary">
                <div class="d-flex align-items-center gap-3">
                    <span class="prac-modal-icon"><i class="bi bi-calendar2-week"></i></span>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="rescheduleInterviewModalLabel">Update Interview Schedule</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="{{ route('admin.accreditation.practitioner.reschedule_interview', $application->id) }}" data-prac-busy-form>
                @csrf
                <div class="modal-body p-4 prac-modal-body">
                    <div class="d-flex align-items-start gap-2 p-3 mb-3 prac-modal-note is-primary">
                        <i class="bi bi-envelope-fill text-primary mt-1"></i>
                        <small class="text-dark">
                            Current schedule: <strong>{{ $slot?->slot_date->format('l, F j, Y') }}, {{ $slot?->timeLabel() }}</strong>.
                            Choose an open slot below. An email notification with the updated schedule will be sent to the applicant upon saving.
                        </small>
                    </div>

                    @if($openSlots->isEmpty())
                        <div class="alert alert-secondary mb-0 small">
                            <i class="fas fa-info-circle me-1"></i> There are no other open interview slots. Open more on
                            <a href="{{ route('admin.accreditation.division.interviews.slots') }}">Manage Interview Slots</a>.
                        </div>
                    @else
                        <div class="prac-resched-list">
                            @foreach($openSlots as $date => $slots)
                                <fieldset class="prac-resched-day">
                                    <legend class="prac-resched-date">{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}</legend>
                                    <div class="prac-resched-times">
                                        @foreach($slots as $open)
                                            <label class="prac-resched-time">
                                                <input type="radio" name="slot_id" value="{{ $open->id }}" required>
                                                <span>
                                                    <strong>{{ $open->timeLabel() }}</strong>
                                                    <small>{{ $open->modeLabel() }}{{ $open->mode === 'f2f' && $open->venue ? ' · ' . $open->venue : '' }}</small>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="modal-footer border-0 prac-modal-body">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    @if($openSlots->isNotEmpty())
                        <button type="submit" class="btn btn-primary fw-bold px-4" data-prac-busy-label="Saving…">
                            <i class="bi bi-check2-circle me-1"></i> Update Schedule
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
