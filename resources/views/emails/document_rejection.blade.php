@extends('emails.layout')

@section('title', 'Action Required: Document Revision — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fde8e8, #fcc5c5);
        }

        .doc-item-remark {
            color: #7a2222;
        }
@endsection

@section('content')
    <div class="icon-circle">⚠️</div>
    <h2>Action Required: Document Revision</h2>
    <p>
        We have reviewed your application and found that some of your submitted documents
        require revision before your evaluation can proceed.
    </p>

    {{-- Highlighted 5-day deadline notice --}}
    <div style="background-color: #fff3cd; border: 2px solid #ffe69c; border-left: 6px solid #d97706; border-radius: 10px; padding: 16px 20px; margin: 18px 0; text-align: center;">
        <p style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">
            ⚠️ Important Resubmission Deadline
        </p>
        <p style="margin: 6px 0 0 0; font-size: 1.05rem; font-weight: 700; color: #b45309;">
            You have 5 days to resubmit your required documents.
        </p>
        <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #78350f;">
            Please re-upload all requested revisions within 5 calendar days to avoid cancellation of your application.
        </p>
    </div>

    <div class="tracking-card">
        <p class="label">Your Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>

        <p class="label">Current Status</p>
        <p class="value-status">For Update</p>
    </div>

    <p>Please review the documents listed below and re-submit the corrected files through the tracking portal.</p>

    {{-- Rejected documents list --}}
    <div class="doc-list">
        <p class="doc-list-title">📋 Documents Requiring Revision</p>
        @foreach($rejectedDocuments as $doc)
        <div class="doc-item red">
            <div class="doc-item-name">{{ $doc->documentField?->name ?? 'Document' }}</div>
            <div class="doc-item-remark">
                <span>Remarks:</span> {{ $doc->remarks ?: 'No specific remark provided. Please ensure the document is complete and legible.' }}
            </div>
        </div>
        @endforeach
        
        @if(isset($rejectedInstructors))
            @foreach($rejectedInstructors as $rInst)
            <div class="doc-item red">
                <div class="doc-item-name">Service Agreement - {{ $rInst->first_name }} {{ $rInst->last_name }}</div>
                <div class="doc-item-remark">
                    <span>Remarks:</span> {{ $rInst->remarks ?: 'No specific remark provided. Please ensure the document is complete and legible.' }}
                </div>
            </div>
            @endforeach
        @endif
        
        @if(isset($rejectedCredentials))
            @foreach($rejectedCredentials as $rCred)
            <div class="doc-item red">
                <div class="doc-item-name">{{ $rCred->type }} Credential - {{ $rCred->instructor->first_name }} {{ $rCred->instructor->last_name }}</div>
                <div class="doc-item-remark">
                    <span>Remarks:</span> {{ $rCred->remarks ?: 'No specific remark provided. Please ensure the document is complete and legible.' }}
                </div>
            </div>
            @endforeach
        @endif
    </div>

    <div class="btn-wrap">
        @if(($rejectedInstructors->isNotEmpty() || $rejectedCredentials->isNotEmpty()) && $application->user?->accreditations()->where('status', 'active')->exists())
        <a href="{{ url('/applicant/instructors') }}" class="btn-primary">
            Log In &amp; Update Instructor Credentials
        </a>
        @elseif(in_array($application->application_type, ['renewal', 'reinstatement']))
        <a href="{{ url('/applicant/renewal/reupload?application_id=' . $application->id) }}" class="btn-primary">
            Log In &amp; Update My Documents
        </a>
        @else
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
            Update My Documents
        </a>
        @endif
    </div>

    <p style="font-size:0.85rem; color:#888;">
        If you have any questions, please contact our office directly.
    </p>
@endsection
