@extends('emails.layout')

@section('title', $isUpdate ? 'Interview Schedule Updated — ARMS' : 'Interview Schedule Confirmation — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, {{ $isUpdate ? '#e3f2fd, #bbdefb' : '#e8f5e9, #c8e6c9' }});
        }
@endsection

@section('content')
    <div class="icon-circle">{{ $isUpdate ? '🔄' : '✅' }}</div>
    <h2>{{ $isUpdate ? 'Interview Schedule Updated' : 'Interview Schedule Confirmed' }}</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        @if($isUpdate)
            Please be informed that your interview schedule has been updated.
        @else
            We are pleased to inform you that your application has successfully passed the document evaluation phase and you are now scheduled for an interview.
        @endif
    </p>

    <div class="tracking-card">
        <p class="label">Your Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>

        <p class="label">Current Status</p>
        <p class="value-status">Scheduled for Interview</p>
    </div>

    <p>Please review your interview details below.</p>

    {{-- Interview Details --}}
    <div class="doc-list">
        <p class="doc-list-title">📅 Interview Details</p>
        
        <div class="doc-item green">
            <div class="doc-item-name">Date & Time</div>
            <div class="doc-item-remark">
                <span>Date:</span> {{ \Carbon\Carbon::parse($interview->interview_date)->format('l, F j, Y') }}<br>
                <span>Time:</span> {{ \Carbon\Carbon::parse($interview->interview_time)->format('h:i A') }}
            </div>
        </div>

        <div class="doc-item green">
            <div class="doc-item-name">Mode & Venue</div>
            <div class="doc-item-remark">
                <span>Mode:</span> {{ $interview->mode === 'online' ? 'Online Interview' : 'Face-to-Face' }}<br>
                @if($interview->mode === 'f2f' && $interview->venue)
                <span>Venue:</span> {{ $interview->venue }}
                @endif
            </div>
        </div>
    </div>

    @if($interview->mode === 'online')
    <p style="font-size:0.85rem; color:#1a6fbd; background-color:#e6f2ff; padding:12px; border-radius:8px; border:1px solid #b3d7ff;">
        <strong>Note:</strong> As your interview is scheduled online, a separate email containing the meeting link and precise instructions will be sent to you shortly.
    </p>
    @else
    <p style="font-size:0.85rem; color:#555;">
        Please ensure you arrive at the venue at least 15 minutes before your scheduled time and bring any original documents that may be required for verification.
    </p>
    @endif

    <div class="btn-wrap">
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
            Track Application
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888;">
        If you have any questions or need to request a reschedule, please contact our office as soon as possible.
    </p>
@endsection
