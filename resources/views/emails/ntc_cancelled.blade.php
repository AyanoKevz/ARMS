@extends('emails.layout')

@section('title', 'Training Cancelled — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #fdecec, #f9d2d2);
        color: #a32020;
    }
    .reason-box {
        border-left: 3px solid #c0392b;
        padding: 10px 14px;
        margin: 6px 0 0;
        background: #fff6f6;
        border-radius: 0 6px 6px 0;
        color: #5a2222;
        font-size: 13px;
        white-space: pre-line;
    }
    .no-action {
        background: #f4f7fb;
        border: 1px solid #dbe5f1;
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 13px;
        color: #38506b;
        margin-top: 18px;
    }
@endsection

@section('content')
    <div class="icon-circle">🚫</div>
    <h2>Training Cancelled</h2>
    <p>
        <strong>{{ $fatproName }}</strong> has filed a Notice of Cancellation for the
        training below. It has been withdrawn and will not take place.
    </p>

    <div class="tracking-card">
        <p class="label">NTC Reference Number</p>
        <p class="value">{{ $ntcReport->reference_number }}</p>

        <p class="label">Training Type &amp; Mode</p>
        <p class="value">{{ $ntcReport->trainingType->name ?? 'N/A' }} ({{ $ntcReport->trainingMode->name ?? 'N/A' }})</p>

        <p class="label">Training Days</p>
        <p class="value">{{ $ntcReport->trainingPeriodLabel() }}</p>

        <p class="label">Venue</p>
        <p class="value">{{ $ntcReport->venue ?: 'N/A' }}</p>

        <p class="label">Cancelled On</p>
        <p class="value">{{ $ntcReport->cancelled_at?->format('F d, Y h:i A') ?? 'N/A' }}</p>

        <p class="label">Status</p>
        <p class="value-status" style="color: #c0392b;">Cancelled</p>
    </div>

    <div class="doc-list">
        <p class="doc-list-title">💬 Reason Given</p>
        <div class="reason-box">{{ $ntcReport->cancellation_reason ?: '—' }}</div>
    </div>

    {{-- Said plainly: an evaluator who reads this and goes looking for an
         Approve button will not find one, and should not. --}}
    <div class="no-action">
        <strong>No action is required.</strong> A cancellation takes effect as soon as it is
        filed — it is not reviewed or acknowledged. This training no longer appears in the
        evaluation queue and no Post Training Report is owed for it.
    </div>

    <div class="btn-wrap">
        <a href="{{ url('/admin/hcd/reports/ntc/' . $ntcReport->id) }}" class="btn-primary">
            View Submission
        </a>
    </div>
@endsection
