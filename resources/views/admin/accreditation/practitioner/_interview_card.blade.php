{{--
    Interview Schedule Details — FATPro's interview card (admin/hcd/
    show_application_info), minus the inline styles: the schedule chips,
    then Start Interview (opens the meeting link when online) → Stop
    Interview → Passed / Not Passed. evaluation.js and
    accreditation-practitioner.js drive the automatic start and the 2-hour stop.

    While it waits, Update Schedule moves it to another open slot
    (_reschedule_modal) and emails the applicant.

    Expects $application, $interview, $slot, $status, $interviewState,
    $interviewLink, $openSlots.
--}}
@php
    $didNotPass = $status === 'Rejected'
        && $application->archive_reason === \App\Services\PractitionerArchiveService::INTERVIEW_FAILED;
    $passed     = in_array($status, ['For Recommendation', 'Awaiting Payment', 'Payment Verification', 'Approved'], true);
@endphp
<div class="prac-int-card">
    <div class="prac-int-head">
        <span class="prac-int-head-icon"><i class="bi bi-calendar-check-fill"></i></span>
        <h6 class="mb-0">Interview Schedule Details</h6>
    </div>

    <div class="prac-int-body">
        <div class="prac-int-chips">
            <div class="prac-int-chip">
                <div class="prac-int-chip-label">Date</div>
                <div class="prac-int-chip-value">{{ $interview->interview_date->format('F d, Y') }}</div>
            </div>
            <div class="prac-int-chip">
                <div class="prac-int-chip-label">Time</div>
                <div class="prac-int-chip-value">{{ $slot ? $slot->timeLabel() : \Carbon\Carbon::parse($interview->interview_time)->format('h:i A') }}</div>
            </div>
            <div class="prac-int-chip">
                <div class="prac-int-chip-label">Mode</div>
                <div><span class="badge {{ $interview->mode === 'online' ? 'bg-info' : 'bg-secondary' }} text-white">{{ strtoupper($interview->mode) }}</span></div>
            </div>
            @if($interview->venue)
            <div class="prac-int-chip">
                <div class="prac-int-chip-label">{{ $interview->mode === 'online' ? 'Meeting Link' : 'Venue' }}</div>
                <div class="prac-int-chip-value">
                    @if($interviewLink)
                        <a href="{{ $interviewLink }}" target="_blank" rel="noopener noreferrer" class="prac-int-link">Open Link <i class="bi bi-box-arrow-up-right ms-1"></i></a>
                    @else
                        {{ $interview->venue }}
                    @endif
                </div>
            </div>
            @endif
        </div>
        @if($slot?->booked_at)
            <small class="text-muted"><i class="bi bi-person-check me-1"></i>Chosen by the applicant on {{ $slot->booked_at->format('M d, Y h:i A') }}.</small>
        @endif
    </div>

    @if($didNotPass)
    <div class="prac-int-foot is-result">
        <i class="bi bi-x-circle-fill fs-4 text-danger"></i>
        <div class="text-start">
            <div class="fw-bold">Interview Result: Failed / Rejected</div>
            <small class="text-muted">This application did not pass the interview process.</small>
        </div>
    </div>
    @elseif($passed)
    <div class="prac-int-foot is-result is-passed">
        <i class="bi bi-check-circle-fill fs-4 text-success"></i>
        <div class="text-start">
            <div class="fw-bold">Interview Result: Passed</div>
            <small class="text-muted">This application passed the interview process.</small>
        </div>
    </div>
    @elseif($interviewState === 'waiting')
    {{-- Update Schedule — FATPro's, onto another open slot --}}
    <div class="prac-int-update">
        <button type="button" class="btn btn-outline-primary btn-sm fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#rescheduleInterviewModal">
            <i class="bi bi-calendar2-week me-1"></i> Update Schedule
        </button>
    </div>
    {{-- Interview is scheduled, but PCT is paused (waiting to start) --}}
    <div class="prac-int-foot">
        <p class="prac-int-lead">
            <i class="bi bi-play-circle-fill me-1 text-primary"></i>
            The interview is scheduled. Click below when it actually begins:
        </p>
        <form id="start-interview-form" action="{{ route('admin.accreditation.practitioner.start_interview', $application->id) }}" method="POST"
              data-interview-mode="{{ $interview->mode }}"
              data-interview-link="{{ $interviewLink }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 prac-pill-btn">
                <span class="btn-text"><i class="bi bi-play-fill me-1"></i> Start Interview</span>
                <span class="btn-spinner d-none">
                    <span class="spinner-border spinner-border-sm me-1" role="status"></span> Starting...
                </span>
            </button>
        </form>
    </div>
    @elseif($interviewState === 'running')
    {{-- Interview is Running --}}
    <div class="prac-int-foot is-running">
        <p class="prac-interview-live">
            <i class="bi bi-record-circle-fill me-1"></i> Interview is currently running...
        </p>
        <button type="button" class="btn btn-danger btn-sm fw-bold px-4 prac-pill-btn prac-stop-btn" data-bs-toggle="modal" data-bs-target="#stopInterviewModal">
            <i class="bi bi-stop-fill me-1"></i> Stop Interview
        </button>
    </div>
    @elseif($interviewState === 'result')
    {{-- Interview completed, record result --}}
    <div class="prac-int-foot">
        <p class="prac-int-lead">
            <i class="bi bi-question-circle-fill me-1 text-primary"></i>
            Please record the outcome of the scheduled interview:
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
            <button type="button" class="btn btn-success btn-sm fw-bold px-4 prac-pill-btn" data-bs-toggle="modal" data-bs-target="#passedConfirmModal">
                <i class="bi bi-check-circle-fill me-1"></i>Passed
            </button>
            <button type="button" class="btn btn-danger btn-sm fw-bold px-4 prac-pill-btn" data-bs-toggle="modal" data-bs-target="#notPassedConfirmModal">
                <i class="bi bi-x-circle-fill me-1"></i>Not Passed
            </button>
        </div>
    </div>
    @endif
</div>

@if($interviewState === 'waiting')
    @include('admin.accreditation.practitioner._reschedule_modal')
@endif
