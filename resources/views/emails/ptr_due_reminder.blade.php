@extends('emails.layout')

@php
    use App\Mail\PostTrainingReportDueEmail as Stage;
    use Illuminate\Support\Str;

    $deadline    = $ntcReport->postTrainingDeadlineDate();
    $daysAllowed = $ntcReport->postTrainingDaysAllowed();
    $workingLeft = $ntcReport->postTrainingWorkingDaysRemaining();

    $isOverdue   = $stage === Stage::STAGE_OVERDUE;
    $isCountdown = $stage === Stage::STAGE_COUNTDOWN;
    $isFinalDay  = $isCountdown && $workingLeft === 0;

    // The final day reads as urgently as the overdue notice; the earlier days
    // of the window stay in the softer amber.
    $urgent = $isOverdue || $isFinalDay;

    // Carbon 3 returns a SIGNED difference, so counting from today back to a
    // past deadline yields a negative. Order the operands oldest-first and the
    // count reads as the plain "days late" figure the reader expects.
    $daysPastDue = ($isOverdue && $deadline)
        ? $deadline->diffInDays(\Carbon\Carbon::today())
        : 0;

    $title = match (true) {
        $isOverdue   => 'Overdue: Post Training Report — ARMS',
        $isFinalDay  => 'Final Day: Post Training Report Due Today — ARMS',
        $isCountdown => 'Reminder: Post Training Report Due Soon — ARMS',
        default      => 'Action Required: Post Training Report Due — ARMS',
    };
@endphp

@section('title', $title)

@section('css')
    .icon-circle {
        background: {{ $urgent ? 'linear-gradient(135deg, #fde8e8, #fcc5c5)' : 'linear-gradient(135deg, #fef9e7, #fdebd0)' }};
        color: {{ $urgent ? '#7a2222' : '#7a5c00' }};
    }
    .deadline-note {
        background: {{ $urgent ? '#fff5f5' : '#fff8e6' }};
        border-left: 4px solid {{ $urgent ? '#d32f2f' : '#D4AC4B' }};
        border-radius: 6px;
        padding: 12px 16px;
        font-size: 0.9rem;
        color: {{ $urgent ? '#7a2222' : '#7a5c00' }};
        margin: 18px 0;
        text-align: left;
    }
    .countdown-box {
        background: {{ $urgent ? '#fff5f5' : '#f4f8ff' }};
        border: 1px solid {{ $urgent ? '#f5b5b5' : '#c7dbf7' }};
        border-radius: 10px;
        padding: 18px 16px;
        margin: 20px 0;
        text-align: center;
    }
    .countdown-number {
        font-size: 2.4rem;
        font-weight: bold;
        line-height: 1.1;
        color: {{ $urgent ? '#b71c1c' : '#0b3d91' }};
        display: block;
    }
    .countdown-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: {{ $urgent ? '#7a2222' : '#456' }};
        margin-top: 4px;
        display: block;
    }
    .allowance-row {
        border-top: 1px solid {{ $urgent ? '#f5b5b5' : '#c7dbf7' }};
        margin-top: 14px;
        padding-top: 12px;
        font-size: 0.85rem;
        color: {{ $urgent ? '#7a2222' : '#456' }};
    }
@endsection

@section('content')
    <div class="icon-circle">{{ $isOverdue ? '⏰' : ($isFinalDay ? '🚨' : ($isCountdown ? '📅' : '🏁')) }}</div>

    <h2>
        @if($isOverdue)
            Post Training Report Overdue
        @elseif($isFinalDay)
            Today Is the Last Day to Submit
        @elseif($isCountdown)
            {{ $workingLeft }} Working {{ Str::plural('Day', $workingLeft) }} Left to Submit
        @else
            Post Training Report Now Due
        @endif
    </h2>

    <p>
        @if($isOverdue)
            The deadline to submit the Post Training Report for the training below passed
            <strong>{{ $daysPastDue }} {{ Str::plural('day', $daysPastDue) }} ago</strong>
            and we have not yet received a complete submission. Please file it immediately.
        @elseif($isFinalDay)
            Today is the final day to submit the Post Training Report for the training below.
            A <strong>{{ $ntcReport->trainingType->name ?? 'training' }}</strong> allows
            <strong>{{ $daysAllowed }} working {{ Str::plural('day', $daysAllowed) }}</strong>
            after the last training day, and that window closes at the end of today.
        @elseif($isCountdown)
            This is a reminder that your Post Training Report is still outstanding. A
            <strong>{{ $ntcReport->trainingType->name ?? 'training' }}</strong> allows
            <strong>{{ $daysAllowed }} working {{ Str::plural('day', $daysAllowed) }}</strong>
            after the last training day to file it.
        @else
            Your training has concluded. You are required to submit the <strong>Post Training Report</strong>
            for the training below within
            <strong>{{ $daysAllowed }} working {{ Str::plural('day', $daysAllowed) }}</strong>
            after the last training day, as the training you conducted was a
            <strong>{{ $ntcReport->trainingType->name ?? 'first aid training' }}</strong>.
        @endif
    </p>

    {{-- Countdown panel: the headline number, then how it was arrived at --}}
    <div class="countdown-box">
        @if($isOverdue)
            <span class="countdown-number">{{ $daysPastDue }}</span>
            <span class="countdown-label">{{ Str::plural('Day', $daysPastDue) }} Past the Deadline</span>
        @else
            <span class="countdown-number">{{ $workingLeft }}</span>
            <span class="countdown-label">
                Working {{ Str::plural('Day', $workingLeft) }} Remaining
                {{ $workingLeft === 0 ? '(Due Today)' : '' }}
            </span>
        @endif

        <div class="allowance-row">
            <strong>{{ $ntcReport->trainingType->name ?? 'Training' }}</strong>
            ({{ $ntcReport->trainingType->code ?? '—' }})
            &mdash; {{ $daysAllowed }} working {{ Str::plural('day', $daysAllowed) }} allowed<br>
            Last training day: <strong>{{ $ntcReport->training_end_date?->format('F d, Y') ?? 'N/A' }}</strong><br>
            Deadline: <strong>{{ $deadline?->format('F d, Y') ?? 'N/A' }}</strong>
        </div>
    </div>

    <div class="tracking-card">
        <p class="label">NTC Reference Number</p>
        <p class="value">{{ $ntcReport->reference_number }}</p>

        <p class="label">Training Type &amp; Mode</p>
        <p class="value">{{ $ntcReport->trainingType->name ?? 'N/A' }} ({{ $ntcReport->trainingMode->name ?? 'N/A' }})</p>

        <p class="label">Training Period</p>
        <p class="value">
            {{ $ntcReport->training_start_date?->format('M d, Y') }} &ndash; {{ $ntcReport->training_end_date?->format('M d, Y') }}
        </p>

        <p class="label">Submission Deadline</p>
        <p class="value-status" style="color: {{ $urgent ? '#e74c3c' : '#f1c40f' }};">
            {{ $deadline?->format('F d, Y') ?? 'N/A' }}
        </p>
    </div>

    <div class="deadline-note">
        <strong>Required attachments (all six are mandatory, max 25 MB each):</strong>
        <ol style="margin: 8px 0 0 18px; padding: 0;">
            <li>Directory of Participants — Excel format</li>
            <li>List of instructors who conducted the training — PDF</li>
            <li>Actual program of training activities — PDF</li>
            <li>Scanned copies of Daily Attendance Sheets — PDF</li>
            <li>Pre- and post-test summary evaluation — PDF</li>
            <li>General and Trainer's Summary evaluation — PDF</li>
        </ol>
    </div>

    <div class="btn-wrap">
        <a href="{{ url('/applicant/ntc') }}" class="btn-primary">
            Submit Post Training Report
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888;">
        This is an automated reminder from the ARMS portal. If you have already submitted this report,
        you may disregard this message.
    </p>
@endsection
