@extends('emails.layout')

@section('title', 'Your Notice to Conduct Was Updated — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #e8f0fd, #c7dcfb);
        color: #1a4a8a;
    }
    .change-row {
        border-left: 3px solid #1a4a8a;
        padding: 8px 12px;
        margin-bottom: 10px;
        background: #f6f9ff;
        border-radius: 0 6px 6px 0;
    }
    .change-field {
        font-weight: 700;
        color: #1a4a8a;
        font-size: 13px;
        margin: 0 0 4px;
    }
    .change-was {
        color: #8a8a8a;
        text-decoration: line-through;
        font-size: 13px;
        margin: 0;
    }
    .change-now {
        color: #14532d;
        font-weight: 600;
        font-size: 13px;
        margin: 2px 0 0;
    }
@endsection

@section('content')
    <div class="icon-circle">✏️</div>
    <h2>Your Notice to Conduct Was Updated</h2>
    <p>
        A DOLE-OSHC Training Evaluator has corrected the details of your acknowledged
        Notice to Conduct on your behalf. No action is needed from you — this notice is
        so that your records match ours.
    </p>

    <div class="tracking-card">
        <p class="label">NTC Reference Number</p>
        <p class="value">{{ $ntcReport->reference_number }}</p>

        <p class="label">Training Type &amp; Mode</p>
        <p class="value">{{ $ntcReport->trainingType->name ?? 'N/A' }} ({{ $ntcReport->trainingMode->name ?? 'N/A' }})</p>

        <p class="label">Training Days</p>
        <p class="value">{{ $ntcReport->trainingPeriodLabel() }}</p>

        <p class="label">Status</p>
        <p class="value-status" style="color: #2ecc71;">Acknowledged</p>
    </div>

    @if(!empty($changes))
        <div class="doc-list">
            <p class="doc-list-title">✏️ What Changed</p>
            @foreach($changes as $field => $change)
            <div class="change-row">
                <p class="change-field">{{ $field }}</p>
                <p class="change-was">{{ $change['from'] !== '' ? $change['from'] : '—' }}</p>
                <p class="change-now">{{ $change['to'] !== '' ? $change['to'] : '—' }}</p>
            </div>
            @endforeach
        </div>
    @endif

    @if($note)
        <div class="doc-list">
            <p class="doc-list-title">💬 Evaluator's Note</p>
            <div class="doc-item">
                <div class="doc-item-remark">{{ $note }}</div>
            </div>
        </div>
    @endif

    @if(isset($changes['Training Days']))
        <p>
            <strong>Please note:</strong> the training dates have changed, so the deadline for
            your Post Training Report has moved with them. It is now due by
            <strong>{{ $ntcReport->postTrainingDeadlineDate()?->format('F d, Y') ?? 'N/A' }}</strong>.
        </p>
    @endif

    <p>
        You can review the full submission at any time from the Submission Report page
        in your ARMS portal. If anything here looks wrong, please contact DOLE-OSHC.
    </p>
@endsection
