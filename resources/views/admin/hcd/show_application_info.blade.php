@extends('layouts.admin')

@section('title', 'Application – ' . $application->tracking_number)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/show-application.css') }}">
@endpush

@section('content')

@php
$isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
$isEvaluator = strtolower($isAdminRole) === 'evaluator';
$isVerifier  = strtolower($isAdminRole) === 'verifier';

$user = $application->user;
$isOrg = $user->profile_type === 'Organization';
$org = $user->organizationProfile;
$ind = $user->individualProfile;
$reps = $org?->authorizedRepresentatives ?? collect();

$grouped = $application->documents->groupBy(
fn($doc) => optional($doc->documentField?->documentType)->id
);

$currentStatus = $application->latestStatus?->status?->name ?? 'Under Evaluation';
$isScheduled = $currentStatus === 'Scheduled for Interview';
$docApproved = $application->documents->count() === 0 || $application->documents->every(fn($d) => $d->status === 'approved');
$instApproved = !$application->user || $application->user->instructors->count() === 0 || $application->user->instructors->every(fn($i) => $i->status === 'approved');
$credApproved = !$application->user || $application->user->instructors->count() === 0 || \App\Models\InstructorCredential::whereIn('instructor_id', $application->user->instructors->pluck('id'))->get()->every(fn($c) => $c->status === 'approved');
$allApproved = $application->documents->count() > 0 && $docApproved && $instApproved && $credApproved;

$interview = $application->interview;
$isAccredited = (bool) $application->accreditation;
$isApproved = $currentStatus === 'Approved';
$isRejected = $currentStatus === 'Rejected';

$hasPendingUpdate = false;
if ($application->user && $application->user->instructors) {
$hasPendingUpdate = $application->user->instructors->contains('update_request_status', 'pending_review');
}

$activePct = $application->activePctEntry;
$activeStep = $activePct->step_number ?? 0;
$pctStatus = $activePct ? $activePct->stepStatus() : '';
@endphp

{{-- ── Flash Messages ── --}}
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Page Header ── --}}
<div class="page-title d-flex justify-content-between align-items-center">
    <div class="title_left">
        <h3>Application Details</h3>
    </div>
    <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm mt-3">
        Back
    </a>
</div>
<div class="clearfix"></div>

{{-- ── Tracking or Accreditation Strip ── --}}
@if($isAccredited && $application->accreditation->status === 'active')
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #bbf7d0;">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#166534;letter-spacing:.45px;"><i class="bi bi-patch-check-fill me-1"></i>Accreditation Number</div>
        <h4 class="m-0 fw-bold" style="color:#14532d;">{{ $application->accreditation->accreditation_number }}</h4>
        <small style="color:#166534;"><i class="bi bi-calendar-check me-1"></i>Valid Until: {{ \Carbon\Carbon::parse($application->accreditation->validity_date)->format('F d, Y') }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge fs-6 px-3 py-2 bg-success text-white">
                <i class="bi bi-check-circle-fill me-1"></i> Active
            </span>
            @if($isVerifier)
            <button type="button"
               class="btn btn-success btn-sm m-0 fw-semibold"
               style="border-radius:8px;font-size:.82rem;background:#15803d;border-color:#166534;"
               data-bs-toggle="modal"
               data-bs-target="#certDirectorModal"
               onclick="setCertUrl('{{ route('admin.hcd.accreditations.certificate', $application->accreditation->id) }}')">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> View Certificate PDF
            </button>
            @endif
            <div class="dropdown">
                <button class="btn btn-light btn-sm m-0 px-2" type="button" data-bs-toggle="dropdown" style="border-radius:8px; border: 1px solid #bbf7d0; color: #166534;">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius:8px; font-size:.85rem;">
                    <li>
                        <a class="dropdown-item text-danger fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#revokeAccreditationModal">
                            <i class="bi bi-shield-x me-2"></i> Revoke Accreditation
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <small style="color:#166534;">
            {{ $application->accreditationType->name ?? 'N/A' }}
        </small>
    </div>
</div>
@elseif($isAccredited && $application->accreditation->status === 'revoked')
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3" style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #fecaca;">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#991b1b;letter-spacing:.45px;"><i class="bi bi-shield-x me-1"></i>Accreditation Number</div>
        <h4 class="m-0 fw-bold" style="color:#7f1d1d; text-decoration: line-through;">{{ $application->accreditation->accreditation_number }}</h4>
        <small style="color:#991b1b; display: block;"><i class="bi bi-calendar-check me-1"></i>Date Accredited: {{ \Carbon\Carbon::parse($application->accreditation->date_of_accreditation)->format('F d, Y') }}</small>
        <small style="color:#991b1b; display: block;"><i class="bi bi-calendar-x me-1"></i>Valid Until: {{ \Carbon\Carbon::parse($application->accreditation->validity_date)->format('F d, Y') }}</small>
        <small style="color:#991b1b; display: block;"><i class="bi bi-calendar-minus me-1"></i>Revoked Date: {{ $application->accreditation->updated_at->format('F d, Y') }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        <span class="badge fs-6 px-3 py-2 bg-danger text-white">
            <i class="bi bi-x-circle-fill me-1"></i> Revoked
        </span>
        <small style="color:#991b1b;">
            {{ $application->accreditationType->name ?? 'N/A' }}
        </small>
    </div>
</div>
@elseif($isAccredited && $application->accreditation->status === 'expired')
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1px solid #fde68a;">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#92400e;letter-spacing:.45px;"><i class="bi bi-clock-history me-1"></i>Accreditation Number</div>
        <h4 class="m-0 fw-bold" style="color:#78350f;">{{ $application->accreditation->accreditation_number }}</h4>
        <small style="color:#92400e; display: block;"><i class="bi bi-calendar-check me-1"></i>Date Accredited: {{ \Carbon\Carbon::parse($application->accreditation->date_of_accreditation)->format('F d, Y') }}</small>
        <small style="color:#92400e; display: block;"><i class="bi bi-calendar-x me-1"></i>Valid Until: {{ \Carbon\Carbon::parse($application->accreditation->validity_date)->format('F d, Y') }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        <span class="badge fs-6 px-3 py-2 bg-warning text-dark">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Expired
        </span>
        <small style="color:#92400e;">
            {{ $application->accreditationType->name ?? 'N/A' }}
        </small>
    </div>
</div>
@else
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#999;letter-spacing:.45px;">Tracking Number</div>
        <h4 class="m-0 fw-bold" style="color:#2A3F54;">{{ $application->tracking_number }}</h4>
        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i>Submitted: {{ $application->created_at->format('F d, Y h:i A') }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        @php
        $displayStatusName = $currentStatus;
        if ($currentStatus === 'Scheduled for Interview' && !$interview) {
            $displayStatusName = 'Pending Interview';
        }

        $statusColor = match($displayStatusName) {
        'Scheduled for Interview' => 'bg-primary',
        'Pending Interview' => 'bg-info',
        'For Update' => 'bg-warning text-dark',
        'Approved' => 'bg-success',
        'Rejected' => 'bg-danger',
        'Awaiting Payment' => 'bg-warning text-dark',
        default => 'bg-info',
        };
        @endphp
        <span id="app-status-badge" class="badge fs-6 px-3 py-2 {{ $statusColor }}">
            {{ $displayStatusName === 'Awaiting Payment' ? 'Recommendation/Payment' : $displayStatusName }}
        </span>
        <small class="text-muted">
            {{ ucfirst($application->application_type) }} Application &mdash;
            {{ $application->accreditationType->name ?? 'N/A' }}
        </small>
    </div>
</div>
@endif

{{-- ══ Process Cycle Time (PCT) Timeline ══ --}}
@if($pctSummary['has_entries'])
@php
    $pctPercent = $pctSummary['percent'];
    $pctOverdue = $pctSummary['is_overdue'];
    $pctColor = $pctOverdue ? '#dc2626' : ($pctPercent >= 80 ? '#f59e0b' : '#22c55e');
    $pctStrokeDash = round(($pctPercent / 100) * 251.2, 1);
@endphp
<div class="ai-card mb-4 pct-card">
    <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#pctTimelineBody" 
aria-expanded="{{ $isAccredited || $isApproved || $isRejected ? 'false' : 'true' }}">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#1A4A8A,#0D2B55);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-clock-history text-white" style="font-size:.95rem;"></i>
        </div>
        <h5 class="mb-0">Process Cycle Time (PCT)</h5>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="badge {{ $pctOverdue ? 'bg-danger' : ($pctPercent >= 80 ? 'bg-warning text-dark' : 'bg-success') }}" style="font-size:.75rem;">
                {{ $pctSummary['total_elapsed'] }} / {{ $pctSummary['total_target'] }} Days
            </span>
            <i class="bi bi-chevron-down" id="pctChevron"></i>
        </div>
    </div>
    <div id="pctTimelineBody" class="collapse {{ $isAccredited || $isApproved || $isRejected ? '' : 'show' }}">
        <div class="pct-summary-row">
            {{-- Progress Ring --}}
            <div class="pct-ring-wrapper">
                <svg class="pct-ring" viewBox="0 0 90 90">
                    <circle class="pct-ring-bg" cx="45" cy="45" r="40" />
                    <circle class="pct-ring-fill" cx="45" cy="45" r="40"
                            style="stroke: {{ $pctColor }}; stroke-dasharray: {{ $pctStrokeDash }} 251.2;" />
                </svg>
                <div class="pct-ring-text">
                    <span class="pct-ring-value" style="color: {{ $pctColor }};">{{ $pctPercent }}%</span>
                </div>
            </div>
            {{-- Summary Stats --}}
            <div class="pct-stats">
                <div class="pct-stat-item">
                    <div class="pct-stat-label">Elapsed</div>
                    <div class="pct-stat-value" style="color: {{ $pctColor }};">{{ $pctSummary['total_elapsed'] }} <small>days</small></div>
                </div>
                <div class="pct-stat-item">
                    <div class="pct-stat-label">Max Limit</div>
                    <div class="pct-stat-value" style="color:#2A3F54;">{{ $pctSummary['total_target'] }} <small>days</small></div>
                </div>
                <div class="pct-stat-item">
                    <div class="pct-stat-label">Remaining</div>
                    @php $remaining = max(0, $pctSummary['total_target'] - $pctSummary['total_elapsed']); @endphp
                    <div class="pct-stat-value" style="color: {{ $remaining > 0 ? '#22c55e' : '#dc2626' }};">{{ $remaining }} <small>days</small></div>
                </div>
            </div>
        </div>

        {{-- Step Timeline --}}
        <div class="pct-timeline">
            @foreach($pctSummary['steps'] as $step)
            @php
                $stepStatus = $step['status'];
                $stepColorClass = match($stepStatus) {
                    'completed' => 'pct-step-completed',
                    'active'    => 'pct-step-active',
                    'paused'    => 'pct-step-paused',
                    default     => 'pct-step-pending',
                };
                $stepIcon = match($stepStatus) {
                    'completed' => 'bi-check-circle-fill',
                    'active'    => 'bi-play-circle-fill',
                    'paused'    => 'bi-pause-circle-fill',
                    default     => 'bi-circle',
                };
                $stepSlaClass = '';
                if ($stepStatus !== 'pending') {
                    if ($step['is_overdue']) {
                        $stepSlaClass = 'pct-sla-overdue';
                    } elseif ($step['percent'] >= 80) {
                        $stepSlaClass = 'pct-sla-warning';
                    } else {
                        $stepSlaClass = 'pct-sla-ok';
                    }
                }
            @endphp
            <div class="pct-step {{ $stepColorClass }}">
                <div class="pct-step-connector"></div>
                <div class="pct-step-icon">
                    <i class="bi {{ $stepIcon }}"></i>
                </div>
                <div class="pct-step-content">
                    <div class="pct-step-header">
                        <span class="pct-step-number">Step {{ $step['number'] }}</span>
                        <span class="pct-step-name">{{ $step['name'] }}</span>
                        @if($stepStatus !== 'pending')
                        @php
                            $s = $step['elapsed_seconds'] ?? 0;
                            $dCount = round($s / 32400, 1);
                            $hCount = floor($s / 3600);
                            $mCount = floor(($s % 3600) / 60);
                            $sCount = $s % 60;
                            $timeFmt = "{$hCount}h {$mCount}m {$sCount}s";
                        @endphp
                        <span class="pct-step-badge {{ $stepSlaClass }}" 
                              @if($stepStatus === 'active') id="livePctCounter" data-seconds="{{ $s }}" data-target="{{ $step['target_days'] }}" @endif>
                            <i class="bi bi-stopwatch me-1"></i>({{ $timeFmt }}) &nbsp;&nbsp;{{ $dCount }} days / {{ $step['target_days'] }} days
                        </span>
                        @else
                        <span class="pct-step-badge pct-sla-pending">{{ $step['target_days'] }} days target</span>
                        @endif
                    </div>
                    @if($stepStatus !== 'pending')
                    <div class="pct-step-bar-wrapper">
                        <div class="pct-step-bar">
                            <div class="pct-step-bar-fill {{ $stepSlaClass }}" style="width: {{ min(100, $step['percent']) }}%;"></div>
                        </div>
                    </div>
                    @endif
                    @if($step['started_at'] || $step['completed_at'])
                    <div class="pct-step-meta">
                        @if($step['started_at'])
                            <span><i class="bi bi-arrow-right-circle me-1"></i>Started: {{ $step['started_at']->format('M d, Y h:i A') }}</span>
                        @endif
                        @if($step['completed_at'])
                            <span><i class="bi bi-check2 me-1"></i>Completed: {{ $step['completed_at']->format('M d, Y h:i A') }}</span>
                        @endif
                        @if($stepStatus === 'paused')
                            <span class="text-warning"><i class="bi bi-pause-fill me-1"></i>Paused</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="pct-legend">
            <span class="pct-legend-item"><i class="bi bi-check-circle-fill" style="color:#22c55e;"></i> Completed</span>
            <span class="pct-legend-item"><i class="bi bi-play-circle-fill" style="color:#3b82f6;"></i> Active</span>
            <span class="pct-legend-item"><i class="bi bi-pause-circle-fill" style="color:#f59e0b;"></i> Paused</span>
            <span class="pct-legend-item"><i class="bi bi-circle" style="color:#d1d5db;"></i> Pending</span>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pctBody = document.getElementById('pctTimelineBody');
    const pctChevron = document.getElementById('pctChevron');
    if (pctBody && pctChevron) {
        pctBody.addEventListener('show.bs.collapse', () => pctChevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
        pctBody.addEventListener('hide.bs.collapse', () => pctChevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
    }

    const docsBody = document.getElementById('submittedDocumentsBody');
    const docsChevron = document.getElementById('docsChevron');
    if (docsBody && docsChevron) {
        docsBody.addEventListener('show.bs.collapse', () => docsChevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
        docsBody.addEventListener('hide.bs.collapse', () => docsChevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
    }

    const credsBody = document.getElementById('instructorCredentialsBody');
    const credsChevron = document.getElementById('credsChevron');
    if (credsBody && credsChevron) {
        credsBody.addEventListener('show.bs.collapse', () => credsChevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
        credsBody.addEventListener('hide.bs.collapse', () => credsChevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
    }

    // Dynamic chevrons for subfolders
    document.querySelectorAll('[id^="folder-body-"]').forEach(body => {
        const typeId = body.id.replace('folder-body-', '');
        const chevron = document.getElementById(`folder-chevron-${typeId}`);
        if (chevron) {
            body.addEventListener('show.bs.collapse', () => chevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
            body.addEventListener('hide.bs.collapse', () => chevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
        }
    });

    // Dynamic chevrons for instructors
    document.querySelectorAll('[id^="instructor-body-"]').forEach(body => {
        const instructorId = body.id.replace('instructor-body-', '');
        const chevron = document.getElementById(`instructor-chevron-${instructorId}`);
        if (chevron) {
            body.addEventListener('show.bs.collapse', () => chevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
            body.addEventListener('hide.bs.collapse', () => chevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
        }
    });

    // Live Ticker for Active Step
    const liveCounter = document.getElementById('livePctCounter');
    if (liveCounter) {
        let seconds = parseInt(liveCounter.getAttribute('data-seconds'), 10) || 0;
        const targetDays = liveCounter.getAttribute('data-target');
        const holidaysList = {!! json_encode(\App\Services\PctService::getHolidays(now()->year - 1, now()->year + 1)) !!};
        
        setInterval(() => {
            const now = new Date();
            
            // 1. Working days: Monday - Friday (exclude weekends)
            const day = now.getDay();
            if (day === 0 || day === 6) return;
            
            // 2. Holidays: Exclude declared holidays
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;
            if (holidaysList.includes(dateStr)) return;
            
            // 3. Working hours: 8:00 AM – 5:00 PM
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const secondsOfDay = hours * 3600 + minutes * 60 + now.getSeconds();
            const startSeconds = 8 * 3600;  // 8:00 AM
            const endSeconds = 17 * 3600;  // 5:00 PM
            if (secondsOfDay < startSeconds || secondsOfDay >= endSeconds) return;
            
            // Increment working seconds
            seconds++;
            
            const days = (seconds / 32400).toFixed(1);
            const hrs = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            let liveTime = `${hrs}h ${mins}m ${secs}s`;
            
            liveCounter.innerHTML = `<i class="bi bi-stopwatch me-1"></i>(${liveTime}) &nbsp;&nbsp;${days} days / ${targetDays} days`;
        }, 1000);
    }
});
</script>
@endif

{{-- ══ Org / Reps Card ══ --}}
<div class="ai-card mb-4">
    <div class="ai-card-header">
        <i class="bi bi-building fs-5 text-dark"></i>
        <h5>Organization Information</h5>
    </div>
    @if($isOrg && $org)
    <div class="row">
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Organization Name</div>
                <div class="val">{{ $org->name }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Head of Organization</div>
                <div class="val">{{ $org->head_name ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Designation</div>
                <div class="val">{{ $org->designation ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Organization Email</div>
                <div class="val">{{ $org->email ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Telephone</div>
                <div class="val">{{ $org->telephone ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="info-pair">
                <div class="lbl">Fax</div>
                <div class="val">{{ $org->fax ?? '—' }}</div>
            </div>
        </div>
        <div class="col-md-6 col-12">
            <div class="info-pair mb-0">
                <div class="lbl">Address</div>
                <div class="val">{{ $org->address ?? '—' }}</div>
            </div>
        </div>
    </div>
    @else
    <p class="text-muted small mb-0">Organization profile not found.</p>
    @endif

    <hr style="border:0; border-top:1px dashed #dde3ef; margin:16px -20px;">

    <div class="ai-card-header">
        <i class="bi bi-person-badge fs-5 text-dark"></i>
        <h5>Authorized Representative(s)</h5>
    </div>
    @forelse($reps as $rep)
    <div class="rep-block">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Full Name</div>
                    <div class="val">{{ $rep->full_name }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair">
                    <div class="lbl">Position</div>
                    <div class="val">{{ $rep->position ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair mb-0">
                    <div class="lbl">Contact Number</div>
                    <div class="val">{{ $rep->contact_number ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-pair mb-0">
                    <div class="lbl">Email</div>
                    <div class="val">{{ $rep->email ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <p class="text-muted small mb-0">No authorized representatives found.</p>
    @endforelse
</div>

{{-- ══ Accreditation History Card ══ --}}
@if($accreditationHistory->count() > 0)
<div class="ai-card mb-4">
    <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#accreditationHistoryBody" aria-expanded="false">
        <i class="bi bi-clock-history fs-5 text-dark"></i>
        <h5 class="mb-0">Accreditation History <span class="badge bg-secondary ms-2" style="font-size:.72rem;">{{ $accreditationHistory->count() }} records</span></h5>
        <i class="bi bi-chevron-down ms-auto" id="historyChevron"></i>
    </div>
    <div id="accreditationHistoryBody" class="collapse">
        <div class="table-responsive mt-3">
            <table class="table table-sm table-bordered mb-0" style="font-size:.85rem;">
                <thead style="background:#f0f4f8;">
                    <tr>
                        <th>Accreditation No.</th>
                        <th>Type</th>
                        <th class="text-center">Date Accredited</th>
                        <th class="text-center">Valid Until</th>
                        <th class="text-center">Status</th>
                        <th class="text-center no-sort" style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accreditationHistory as $histAcc)
                    <tr>
                        <td class="fw-semibold">{{ $histAcc->accreditation_number ?? '—' }}</td>
                        <td>{{ $histAcc->accreditationType->name ?? '—' }}</td>
                        <td class="text-center">{{ $histAcc->date_of_accreditation ? \Carbon\Carbon::parse($histAcc->date_of_accreditation)->format('M d, Y') : '—' }}</td>
                        <td class="text-center">{{ $histAcc->validity_date ? \Carbon\Carbon::parse($histAcc->validity_date)->format('M d, Y') : '—' }}</td>
                        <td class="text-center">
                            @php
                            $histBadge = match($histAcc->status) {
                            'active' => 'bg-success',
                            'expired' => 'bg-warning text-dark',
                            'revoked' => 'bg-danger',
                            default => 'bg-secondary',
                            };
                            @endphp
                            <span class="badge {{ $histBadge }}">{{ ucfirst($histAcc->status) }}</span>
                        </td>
                        <td class="text-center" style="white-space: nowrap;">
                            @if($isVerifier)
                            <button type="button"
                               class="btn btn-xs btn-outline-success m-0 px-2 py-0"
                               style="font-size:.72rem;" title="View Certificate"
                               data-bs-toggle="modal"
                               data-bs-target="#certDirectorModal"
                               onclick="setCertUrl('{{ route('admin.hcd.accreditations.certificate', $histAcc->id) }}')">
                                <i class="bi bi-file-earmark-pdf"></i> PDF
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const historyBody = document.getElementById('accreditationHistoryBody');
        const chevron = document.getElementById('historyChevron');
        if (historyBody && chevron) {
            historyBody.addEventListener('show.bs.collapse', () => chevron.classList.replace('bi-chevron-down', 'bi-chevron-up'));
            historyBody.addEventListener('hide.bs.collapse', () => chevron.classList.replace('bi-chevron-up', 'bi-chevron-down'));
        }
    });
</script>
@endif

{{-- ══ Evaluation Form ══ --}}
<form id="evaluation-form"
    data-url="{{ route('admin.hcd.applications.finalize_evaluation', $application->id) }}">
    @csrf

    {{-- ── Documents Card ── --}}
    @php
        $totalDocs = $application->documents->count();
        $approvedDocs = $application->documents->where('status', 'approved')->count();
    @endphp
    <div class="ai-card mb-4">
        <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#submittedDocumentsBody" 
             aria-expanded="{{ $isAccredited || $isApproved || $isRejected ? 'false' : 'true' }}">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#1A4A8A,#0D2B55);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-folder2-open text-white" style="font-size:.95rem;"></i>
            </div>
            <h5 class="mb-0">Submitted Documents</h5>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="badge bg-success" id="submitted-docs-progress" style="font-size:.75rem;">
                    {{ $approvedDocs }} / {{ $totalDocs }} Accepted
                </span>
                <i class="bi bi-chevron-down" id="docsChevron"></i>
            </div>
        </div>

        <div id="submittedDocumentsBody" class="collapse {{ $isAccredited || $isApproved || $isRejected ? '' : 'show' }}">
            <div class="pt-2">
                @if($application->documents->count() > 0)
                <div class="row g-3">
                    @foreach($grouped as $typeId => $docs)
                    @php
                        $sectionName = optional($docs->first()?->documentField?->documentType)->name ?? 'General Documents';
                        $totalDocsInFolder = $docs->count();
                        $approvedDocsInFolder = $docs->where('status', 'approved')->count();
                    @endphp
                    <div class="col-md-6" id="folder-section-{{ $typeId }}">
                        <div class="doc-section">
                            <div class="doc-section-header d-flex justify-content-between align-items-center"
                                 style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#folder-body-{{ $typeId }}"
                                 aria-expanded="true">
                                <div>
                                    <i class="bi bi-folder-fill me-1"></i> {{ $sectionName }}
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary" id="folder-progress-{{ $typeId }}" style="font-size:.72rem;">
                                        {{ $approvedDocsInFolder }} / {{ $totalDocsInFolder }} Accepted
                                    </span>
                                    <i class="bi bi-chevron-down" id="folder-chevron-{{ $typeId }}"></i>
                                </div>
                            </div>

                            <div id="folder-body-{{ $typeId }}" class="collapse show">
                                @foreach($docs as $doc)
                                @php
                                $field = $doc->documentField;
                                $userDoc = $doc->userDocument;
                                $inputType = $field?->input_type ?? 'text';
                                $filePath = $userDoc?->file_path;
                                $textVal = $userDoc?->value;

                                // Normalise: treat anything not approved/rejected as pending for JS
                                $evalStatus = in_array($doc->status, ['approved','rejected']) ? $doc->status : 'pending';

                                $badgeClass = match($doc->status) {
                                'approved' => 'doc-badge-approved',
                                'rejected' => 'doc-badge-rejected',
                                'for_revision' => 'doc-badge-for_revision',
                                default => 'doc-badge-pending',
                                };
                                $badgeLabel = match($doc->status) {
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'for_revision' => 'For Revision',
                                default => 'Pending',
                                };
                                @endphp
                                <div class="doc-row" id="doc-row-{{ $doc->id }}">
                                    {{-- Hidden form inputs --}}
                                    <input type="hidden" name="evaluations[{{ $doc->id }}][id]" value="{{ $doc->id }}">
                                    <input type="hidden" name="evaluations[{{ $doc->id }}][status]"
                                        id="status-input-{{ $doc->id }}" value="{{ $evalStatus }}" data-db-status="{{ $doc->status }}">

                                    {{-- Field name --}}
                                    <div class="doc-field-name">
                                        @if($inputType === 'file') <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                        @elseif($inputType === 'date') <i class="bi bi-calendar-event text-info me-1"></i>
                                        @else <i class="bi bi-fonts text-secondary me-1"></i>
                                        @endif
                                        {{ $field?->name ?? 'Unknown Field' }}
                                    </div>

                                    {{-- Value display --}}
                                    @if($inputType === 'date' && $textVal)
                                    <div class="doc-value">{{ \Carbon\Carbon::parse($textVal)->format('M d, Y') }}</div>
                                    @elseif($inputType === 'text' && $textVal)
                                    <div class="doc-value">{{ $textVal }}</div>
                                    @elseif($inputType === 'file')
                                    <div class="doc-value text-muted" style="font-size:.75rem;">
                                        {{ $filePath ? basename($filePath) : 'No file' }}
                                    </div>
                                    @endif

                                    {{-- Status badge --}}
                                    <span class="doc-badge {{ $badgeClass }}" id="badge-{{ $doc->id }}">{{ $badgeLabel }}</span>

                                    {{-- View button --}}
                                    @if($inputType === 'file' && $filePath)
                                    <div class="doc-actions">
                                        <a href="{{ route('admin.hcd.documents.view', $doc->id) }}?v={{ $doc->updated_at->timestamp ?? time() }}"
                                            target="_blank" class="btn btn-outline-primary btn-xs px-2 py-0"
                                            style="font-size:.78rem;">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </div>
                                    @endif

                                    {{-- Approve / Reject buttons + Reject panel (hidden once all docs approved) --}}
                                    @if(!$allApproved && !$isScheduled && !$isAccredited && !$isApproved)
                                    <div class="doc-eval-actions">
                                        <button type="button"
                                            class="btn-eval btn-approve {{ $evalStatus === 'approved' ? 'active' : '' }}"
                                            data-doc-id="{{ $doc->id }}"
                                            onclick="setDocStatus({{ $doc->id }}, 'approved')">
                                            <i class="bi bi-check-circle-fill"></i> Approve
                                        </button>
                                        <button type="button"
                                            class="btn-eval btn-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}"
                                            data-doc-id="{{ $doc->id }}"
                                            onclick="setDocStatus({{ $doc->id }}, 'rejected')">
                                            <i class="bi bi-x-circle-fill"></i> Reject
                                        </button>
                                    </div>
                                    <div class="reject-panel" id="reject-panel-{{ $doc->id }}"
                                        style="{{ $evalStatus === 'rejected' ? '' : 'display:none;' }}">
                                        <label class="reject-remarks-label">
                                            <i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span class="text-muted">(optional)</span>
                                        </label>
                                        <textarea class="reject-remarks-input"
                                            name="evaluations[{{ $doc->id }}][remarks]"
                                            id="remarks-{{ $doc->id }}"
                                            placeholder="Explain why this document was rejected…"
                                            rows="2">{{ $doc->remarks }}</textarea>
                                    </div>
                                    @endif

                                </div>{{-- /doc-row --}}
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="alert alert-secondary text-center mb-0">
                    No documents have been uploaded for this application.
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Instructor Credentials Card ── --}}
    @if($application->user && $application->user->instructors && $application->user->instructors->count() > 0)
    @php
        $totalInstructorItems = 0;
        $approvedInstructorItems = 0;
        foreach($application->user->instructors as $inst) {
            $totalInstructorItems += $inst->credentials->count() + 1;
            $approvedInstructorItems += $inst->credentials->where('status', 'approved')->count();
            if ($inst->status === 'approved') {
                $approvedInstructorItems += 1;
            }
        }
    @endphp
    <div class="ai-card mt-4">
        <div class="ai-card-header" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#instructorCredentialsBody"
             aria-expanded="{{ $isAccredited || $isApproved || $isRejected ? 'false' : 'true' }}">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#1A4A8A,#0D2B55);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-people-fill text-white" style="font-size:.95rem;"></i>
            </div>
            <h5 class="mb-0">Instructor Credentials</h5>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="badge bg-success" id="instructor-creds-progress" style="font-size:.75rem;">
                    {{ $approvedInstructorItems }} / {{ $totalInstructorItems }} Accepted
                </span>
                <i class="bi bi-chevron-down" id="credsChevron"></i>
            </div>
        </div>

        <div id="instructorCredentialsBody" class="collapse {{ $isAccredited || $isApproved || $isRejected ? '' : 'show' }}">
            <div class="pt-2">
                <div class="row g-3">
                    @foreach($application->user->instructors as $instructor)
                    @php
                        $totalInstItems = $instructor->credentials->count() + 1;
                        $approvedInstItems = $instructor->credentials->where('status', 'approved')->count() + ($instructor->status === 'approved' ? 1 : 0);
                    @endphp
                    <div class="col-md-12" id="instructor-section-{{ $instructor->id }}">
                        <div class="doc-section">
                            <div class="doc-section-header d-flex justify-content-between align-items-center"
                                 style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#instructor-body-{{ $instructor->id }}"
                                 aria-expanded="true">
                                <div><i class="bi bi-person-badge-fill me-1"></i> {{ $instructor->first_name }} {{ $instructor->last_name }}</div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary me-2" id="instructor-progress-{{ $instructor->id }}" style="font-size:.72rem;">
                                        {{ $approvedInstItems }} / {{ $totalInstItems }} Accepted
                                    </span>
                                    @if($isAccredited && $application->accreditation->status === 'active' && in_array($instructor->update_request_status, ['none', 'completed']))
                                    <button type="button" class="btn btn-outline-warning btn-xs py-0 px-2" style="font-size:.72rem;" data-bs-toggle="modal" data-bs-target="#reqModal-inst-{{ $instructor->id }}" onclick="event.stopPropagation();">
                                        <i class="bi bi-pencil-square me-1"></i>Request Update
                                    </button>
                                    @endif
                                    <i class="bi bi-chevron-down" id="instructor-chevron-{{ $instructor->id }}"></i>
                                </div>
                            </div>

                            <div id="instructor-body-{{ $instructor->id }}" class="collapse show">
                                {{-- Update request status banners (non-none states) --}}
                                @if($instructor->update_request_status === 'admin_requested')
                                <div class="m-3 mb-0 p-3 rounded" style="background-color: #e0f7fa; color: #006064; border: 1px solid #b2ebf2;">
                                    <i class="bi bi-hourglass-split me-1"></i>
                                    <strong>Awaiting Applicant Upload</strong><br>
                                    @if($instructor->update_request_fields)
                                    <div class="mt-1" style="font-size:0.85rem;">
                                        <span class="fw-semibold">Fields:</span>
                                        <ul class="mb-0 mt-1" style="padding-left:1.2rem;">
                                            @foreach($instructor->update_request_fields as $field)
                                            <li>
                                                @if($field === 'service_agreement') Service Agreement between FATPro head and instructor
                                                @elseif($field === 'EMS') TESDA EMS NC II/III
                                                @elseif($field === 'TM1') TESDA TM1
                                                @elseif($field === 'NTTC') TESDA NTTC
                                                @elseif($field === 'BOSH') BOSH SO1/SO2
                                                @else {{ $field }}
                                                @endif
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif
                                </div>
                                @elseif($instructor->update_request_status === 'pending_review')
                                <div class="m-3 mb-0 p-3 rounded" style="background-color: #fff8e1; color: #f57f17; border: 1px solid #ffecb3;">
                                    <i class="bi bi-upload me-1"></i>
                                    <strong>Applicant Uploaded — Pending Re-evaluation</strong><br>
                                    <span style="font-size:0.85rem;">Review the updated credentials below and approve or reject them.</span>
                                </div>
                                @elseif($instructor->update_request_status === 'completed')
                                <div class="m-3 mb-0 p-3 rounded" style="background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9;">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    <strong>Update Completed</strong><br>
                                    <span style="font-size:0.85rem;">All updated credentials have been approved and the applicant was notified.</span>
                                </div>
                                @endif


                                @foreach($instructor->credentials as $credential)
                                @php
                                $evalStatusCred = in_array($credential->status, ['approved','rejected']) ? $credential->status : 'pending';
                                $isRequestedCred = is_array($instructor->update_request_fields) && in_array($credential->type, $instructor->update_request_fields);

                                if ($instructor->update_request_status === 'admin_requested' && $isRequestedCred) {
                                $badgeClassCred = 'doc-badge-pending';
                                $badgeLabelCred = 'Awaiting Upload';
                                } else {
                                $badgeClassCred = match($credential->status) {
                                'approved' => 'doc-badge-approved',
                                'rejected' => 'doc-badge-rejected',
                                'for_revision' => 'doc-badge-for_revision',
                                default => 'doc-badge-pending',
                                };
                                $badgeLabelCred = match($credential->status) {
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'for_revision' => 'For Revision',
                                default => 'Pending',
                                };
                                }
                                @endphp
                                <div class="doc-row" id="doc-row-cred-{{ $credential->id }}">
                                    <input type="hidden" name="credential_evaluations[{{ $credential->id }}][id]" value="{{ $credential->id }}">
                                    <input type="hidden" name="credential_evaluations[{{ $credential->id }}][status]" id="status-input-cred-{{ $credential->id }}" value="{{ $evalStatusCred }}" data-db-status="{{ $credential->status }}">

                                    <div class="doc-field-name">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                            <span class="fw-bold">
                                                @if($credential->type === 'EMS')
                                                TESDA Emergency Medical Services NC II or III Certificate
                                                @elseif($credential->type === 'TM1')
                                                TESDA Trainers Methodology Certificate 1
                                                @elseif($credential->type === 'NTTC')
                                                TESDA National TVET Trainer Certificate
                                                @elseif($credential->type === 'BOSH')
                                                BOSH SO1 or SO2 Certificate
                                                @else
                                                {{ $credential->type }} Credential
                                                @endif
                                            </span>
                                        </div>
                                        <div class="ms-4 text-muted" style="font-size: 0.78rem; line-height: 1.5;">
                                            @if($credential->number)
                                            <div><strong style="color:#555;">Certificate Number:</strong> {{ $credential->number }}</div>
                                            @endif
                                            @if($credential->issued_date)
                                            <div><strong style="color:#555;">Issued On:</strong> {{ \Carbon\Carbon::parse($credential->issued_date)->format('M d, Y') }}</div>
                                            @endif
                                            @if($credential->validity_date)
                                            <div><strong style="color:#555;">Valid Until:</strong> {{ \Carbon\Carbon::parse($credential->validity_date)->format('M d, Y') }}</div>
                                            @endif
                                            @if($credential->training_dates)
                                            <div><strong style="color:#555;">Training date(s):</strong> {{ $credential->training_dates }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="doc-value text-muted" style="font-size:.75rem;">
                                        {{ $credential->pdf_path ? basename($credential->pdf_path) : 'No file' }}
                                    </div>

                                    <span class="doc-badge {{ $badgeClassCred }}" id="badge-cred-{{ $credential->id }}">{{ $badgeLabelCred }}</span>

                                    @if($credential->pdf_path || ($isAccredited && $allApproved && $instructor->update_request_status === 'none'))
                                    <div class="doc-actions">
                                        @if($credential->pdf_path)
                                        <a href="{{ route('admin.hcd.instructors.credentials.view', $credential->id) }}?v={{ $credential->updated_at->timestamp ?? time() }}" target="_blank" class="btn btn-outline-primary btn-xs px-2 py-0" style="font-size:.78rem;">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        @endif
                                    </div>
                                    @endif

                                    @php
                                    $isRequested = $instructor->update_request_status === 'pending_review' &&
                                    is_array($instructor->update_request_fields) &&
                                    in_array($credential->type, $instructor->update_request_fields);
                                    $showEvalButtons = (!$isAccredited && !$isScheduled) || $isRequested;
                                    @endphp

                                    @if($showEvalButtons)
                                    <div class="doc-eval-actions">
                                        <button type="button" class="btn-eval btn-approve {{ $evalStatusCred === 'approved' ? 'active' : '' }}" data-doc-id="cred-{{ $credential->id }}" onclick="setDocStatus('cred-{{ $credential->id }}', 'approved')">
                                            <i class="bi bi-check-circle-fill"></i> Approve
                                        </button>
                                        <button type="button" class="btn-eval btn-reject {{ $evalStatusCred === 'rejected' ? 'active' : '' }}" data-doc-id="cred-{{ $credential->id }}" onclick="setDocStatus('cred-{{ $credential->id }}', 'rejected')">
                                            <i class="bi bi-x-circle-fill"></i> Reject
                                        </button>
                                    </div>
                                    <div class="reject-panel" id="reject-panel-cred-{{ $credential->id }}" style="{{ $evalStatusCred === 'rejected' ? '' : 'display:none;' }}">
                                        <label class="reject-remarks-label"><i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span class="text-muted">(optional)</span></label>
                                        <textarea class="reject-remarks-input" name="credential_evaluations[{{ $credential->id }}][remarks]" id="remarks-cred-{{ $credential->id }}" placeholder="Explain why this document was rejected…" rows="2">{{ $credential->remarks }}</textarea>
                                    </div>
                                    @endif
                                </div>
                                @endforeach

                                {{-- Service Agreement --}}
                                @php
                                $evalStatus = in_array($instructor->status, ['approved','rejected']) ? $instructor->status : 'pending';
                                $isSaRequested = is_array($instructor->update_request_fields) && in_array('service_agreement', $instructor->update_request_fields);

                                if ($instructor->update_request_status === 'admin_requested' && $isSaRequested) {
                                $badgeClass = 'doc-badge-pending';
                                $badgeLabel = 'Awaiting Upload';
                                } else {
                                $badgeClass = match($instructor->status) {
                                'approved' => 'doc-badge-approved',
                                'rejected' => 'doc-badge-rejected',
                                'for_revision' => 'doc-badge-for_revision',
                                default => 'doc-badge-pending',
                                };
                                $badgeLabel = match($instructor->status) {
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'for_revision' => 'For Revision',
                                default => 'Pending',
                                };
                                }
                                @endphp
                                <div class="doc-row" id="doc-row-inst-{{ $instructor->id }}">
                                    <input type="hidden" name="instructor_evaluations[{{ $instructor->id }}][id]" value="{{ $instructor->id }}">
                                    <input type="hidden" name="instructor_evaluations[{{ $instructor->id }}][status]" id="status-input-inst-{{ $instructor->id }}" value="{{ $evalStatus }}" data-db-status="{{ $instructor->status }}">

                                    <div class="doc-field-name">
                                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                        <span class="fw-bold">Service Agreement between FATPro head and instructor</span>
                                    </div>
                                    <div class="doc-value text-muted" style="font-size:.75rem;">
                                        {{ $instructor->service_agreement_path ? basename($instructor->service_agreement_path) : 'No file' }}
                                    </div>

                                    <span class="doc-badge {{ $badgeClass }}" id="badge-inst-{{ $instructor->id }}">{{ $badgeLabel }}</span>

                                    @if($instructor->service_agreement_path || ($isAccredited && $allApproved && $instructor->update_request_status === 'none'))
                                    <div class="doc-actions">
                                        @if($instructor->service_agreement_path)
                                        <a href="{{ route('admin.hcd.instructors.service_agreement.view', $instructor->id) }}?v={{ $instructor->updated_at->timestamp ?? time() }}" target="_blank" class="btn btn-outline-primary btn-xs px-2 py-0" style="font-size:.78rem;">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        @endif
                                    </div>
                                    @endif

                                    @php
                                    $isSaRequested = $instructor->update_request_status === 'pending_review' &&
                                    is_array($instructor->update_request_fields) &&
                                    in_array('service_agreement', $instructor->update_request_fields);
                                    $showSaEvalButtons = (!$isAccredited && !$isScheduled) || $isSaRequested;
                                    @endphp

                                    @if($showSaEvalButtons)
                                    <div class="doc-eval-actions">
                                        <button type="button" class="btn-eval btn-approve {{ $evalStatus === 'approved' ? 'active' : '' }}" data-doc-id="inst-{{ $instructor->id }}" onclick="setDocStatus('inst-{{ $instructor->id }}', 'approved')">
                                            <i class="bi bi-check-circle-fill"></i> Approve
                                        </button>
                                        <button type="button" class="btn-eval btn-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}" data-doc-id="inst-{{ $instructor->id }}" onclick="setDocStatus('inst-{{ $instructor->id }}', 'rejected')">
                                            <i class="bi bi-x-circle-fill"></i> Reject
                                        </button>
                                    </div>
                                    <div class="reject-panel" id="reject-panel-inst-{{ $instructor->id }}" style="{{ $evalStatus === 'rejected' ? '' : 'display:none;' }}">
                                        <label class="reject-remarks-label"><i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span class="text-muted">(optional)</span></label>
                                        <textarea class="reject-remarks-input" name="instructor_evaluations[{{ $instructor->id }}][remarks]" id="remarks-inst-{{ $instructor->id }}" placeholder="Explain why this document was rejected…" rows="2">{{ $instructor->remarks }}</textarea>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

</form>


{{-- ══ Interview Schedule Card (shown when not yet accredited/approved) ══ --}}
@if(!$isAccredited && !$isApproved && $currentStatus !== 'Awaiting Payment')
@if($interview)
<div class="mt-3 mb-3"
    style="background:#fff;border:1px solid #dee2e6;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);">

    {{-- Card Header --}}
    <div class="d-flex align-items-center justify-content-center gap-3 px-4 py-3"
        style="background:linear-gradient(135deg,#1A4A8A,#0D2B55);">
        <div style="width:38px;height:38px;background:rgba(255,255,255,.15);border-radius:8px;
                            display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-calendar-check-fill text-white fs-5"></i>
        </div>
        <div class="text-start">
            <h6 class="text-white mb-0 fw-bold">Interview Schedule</h6>
            <small class="text-white-50">Schedule has been set.</small>
        </div>
    </div>

    {{-- Card Body --}}
    <div class="px-4 pt-3 pb-2 text-center">
        {{-- Info chips row — centered --}}
        <div class="row g-2 mb-3 justify-content-center text-start">
            <div class="col-auto">
                <div style="background:#f0f5ff;border:1px solid #d0ddf7;border-radius:8px;padding:8px 14px;">
                    <div style="font-size:.7rem;color:#6b7c9e;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;">Date</div>
                    <div class="fw-semibold" style="font-size:.88rem;color:#1A3A6A;">{{ $interview->interview_date->format('F d, Y') }}</div>
                </div>
            </div>
            <div class="col-auto">
                <div style="background:#f0f5ff;border:1px solid #d0ddf7;border-radius:8px;padding:8px 14px;">
                    <div style="font-size:.7rem;color:#6b7c9e;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;">Time</div>
                    <div class="fw-semibold" style="font-size:.88rem;color:#1A3A6A;">{{ \Carbon\Carbon::parse($interview->interview_time)->format('h:i A') }}</div>
                </div>
            </div>
            <div class="col-auto">
                <div style="background:#f0f5ff;border:1px solid #d0ddf7;border-radius:8px;padding:8px 14px;">
                    <div style="font-size:.7rem;color:#6b7c9e;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;">Mode</div>
                    <div>
                        <span class="badge {{ $interview->mode === 'online' ? 'bg-info' : 'bg-secondary' }} text-white" style="font-size:.78rem;">
                            {{ strtoupper($interview->mode) }}
                        </span>
                    </div>
                </div>
            </div>
            @if($interview->venue)
            <div class="col-auto">
                <div style="background:#f0f5ff;border:1px solid #d0ddf7;border-radius:8px;padding:8px 14px;">
                    <div style="font-size:.7rem;color:#6b7c9e;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;">{{ $interview->mode === 'online' ? 'Meeting Link' : 'Venue' }}</div>
                    <div class="fw-semibold" style="font-size:.88rem;color:#1A3A6A;">
                        @if($interview->mode === 'online')
                        <a href="{{ $interview->venue }}" target="_blank" style="text-decoration:underline;">Open Link</a>
                        @else
                        {{ $interview->venue }}
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Card Footer — centered button --}}
    @if(!$isRejected && !in_array($currentStatus, ['Awaiting Payment', 'Approved']) && ($activeStep === 5 && $pctStatus === 'paused'))
    <div class="px-4 py-2 text-center" style="border-top:1px solid #f0f0f0;">
        <button type="button"
            id="btn-open-schedule"
            class="btn btn-outline-primary btn-sm fw-semibold px-4"
            disabled
            data-bs-toggle="modal" data-bs-target="#scheduleInterviewModal"
            style="border-radius:6px;">
            <span id="btn-schedule-icon"></span>
            <span id="btn-schedule-text">Update Schedule</span>
        </button>
    </div>
    @endif

    {{-- Interview Result Actions / Status inside Card --}}
    @if(!$isAccredited && !$isApproved && $currentStatus !== 'Awaiting Payment')
    @if($isRejected)
    <div class="d-flex align-items-center justify-content-center gap-3 p-3 bg-light border-top" style="color: #dc3545;">
        <i class="bi bi-x-circle-fill fs-4 text-danger"></i>
        <div class="text-start">
            <div class="fw-bold" style="font-size:0.95rem;">Interview Result: Failed / Rejected</div>
            <small class="text-muted">This application did not pass the interview process.</small>
        </div>
    </div>
    @elseif($activeStep === 5 && $pctStatus === 'paused')
    {{-- Interview is scheduled, but PCT is paused (waiting to start) --}}
    <div class="px-4 py-3 bg-light border-top text-center">
        <p class="fw-semibold mb-2" style="font-size: 0.9rem; color: #2A3F54;">
            <i class="bi bi-play-circle-fill me-1 text-primary"></i>
            The interview is scheduled. Click below when it actually begins:
        </p>
        <form action="{{ route('admin.hcd.applications.start_interview', $application->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4" style="border-radius:8px;">
                <i class="bi bi-play-fill me-1"></i> Start Interview
            </button>
        </form>
    </div>
    @elseif($activeStep === 5 && $pctStatus === 'active')
    {{-- Interview is Running --}}
    <div class="px-4 py-3 bg-light border-top text-center" style="background-color: #fff4e5 !important;">
        <p class="fw-bold mb-2 text-danger" style="font-size: 0.95rem; animation: pulse 2s infinite;">
            <i class="bi bi-record-circle-fill me-1"></i> Interview is currently running...
        </p>
        <button type="button" class="btn btn-danger btn-sm fw-bold px-4" style="border-radius:8px; box-shadow: 0 0 10px rgba(220,53,69,0.4);" data-bs-toggle="modal" data-bs-target="#stopInterviewModal">
            <i class="bi bi-stop-fill me-1"></i> Stop Interview
        </button>
    </div>
    @elseif($activeStep === 6)
    {{-- Interview completed, record result --}}
    <div class="px-4 py-3 bg-light border-top text-center">
        <p class="fw-semibold mb-2" style="font-size: 0.9rem; color: #2A3F54;">
            <i class="bi bi-question-circle-fill me-1 text-primary"></i>
            Please record the outcome of the scheduled interview:
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
            {{-- PASSED button --}}
            <button type="button"
                class="btn btn-success btn-sm fw-bold px-4"
                style="border-radius:8px;"
                data-bs-toggle="modal" data-bs-target="#passedConfirmModal">
                <i class="bi bi-check-circle-fill me-1"></i>Passed
            </button>
            {{-- NOT PASSED button --}}
            <button type="button"
                class="btn btn-danger btn-sm fw-bold px-4"
                style="border-radius:8px;"
                data-bs-toggle="modal" data-bs-target="#notPassedConfirmModal">
                <i class="bi bi-x-circle-fill me-1"></i>Not Passed
            </button>
        </div>
    </div>
    @endif
    @endif

</div>
@else
{{-- Just the button if no schedule yet --}}
@if(!$isRejected)
<div class="mt-4 mb-4 text-center">
    <button type="button"
        id="btn-open-schedule"
        class="btn btn-outline-primary btn-sm fw-semibold px-4"
        disabled
        data-bs-toggle="modal" data-bs-target="#scheduleInterviewModal"
        style="border-radius:6px;">
        <span id="btn-schedule-icon"></span>
        <span id="btn-schedule-text">Set Schedule</span>
    </button>
</div>
@endif
@endif
@elseif($hasPendingUpdate)
<div class="mt-4 mb-4 text-center">
    <button type="button"
        id="btn-open-schedule"
        class="btn btn-outline-primary btn-sm fw-semibold px-4"
        disabled
        style="border-radius:6px;">
        <span id="btn-schedule-icon"></span>
        <span id="btn-schedule-text">Pending Documents</span>
    </button>
</div>
@endif

{{-- ══ Awaiting Payment Panels ══ --}}
@if($currentStatus === 'Awaiting Payment')
@if($isEvaluator && (!$application->payment || !$application->payment->signed_recommendation_letter))
<div class="ai-card mt-4">
    <div class="ai-card-header">
        <i class="bi bi-file-earmark-pdf fs-5 text-dark"></i>
        <h5>Recommendation Form</h5>
    </div>
    <div class="x_content p-3 mt-2">
        <p class="text-muted small">This application has passed the interview stage. Fill out the recommendation form details and generate the PDF to print.</p>

        <form action="{{ route('admin.hcd.applications.generate_recommendation', $application->id) }}" method="POST" target="_blank" class="mb-3">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Form Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">From Division</label>
                    <input type="text" name="from" class="form-control form-control-sm" value="Health Control Division (HCD)" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold small">To Office</label>
                    <input type="text" name="to" class="form-control form-control-sm" value="Office of the Executive Director (OED)" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Specialization/Industry</label>
                    <input type="text" name="specialization" class="form-control form-control-sm" placeholder="e.g. Construction, General Manufacturing">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Evaluator Name</label>
                    <input type="text" name="evaluator" class="form-control form-control-sm" value="{{ auth()->user()->name }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-semibold small">
                        Interviewers (One per line) <span class="text-muted fw-normal" style="font-size: 0.72rem;">— Press Enter after each name to add each interviewer on a new line.</span>
                    </label>
                    <textarea name="interviewers" class="form-control form-control-sm" rows="3" placeholder="Enter interviewer names, one per line"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Recommended By</label>
                    <input type="text" name="recommended_by" class="form-control form-control-sm" value="MARIA BEATRIZ G. VILLANUEVA, MD" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Approved By</label>
                    <input type="text" name="approved_by" class="form-control form-control-sm" value="ENGR. JOSE MARIA S. BATINO" required>
                </div>
            </div>

            <div class="mt-4 text-center">
                <button type="submit" class="btn btn-primary fw-semibold text-white px-5 py-2" style="border-radius: 8px; font-size: 0.95rem;">
                    <i class="fas fa-file-pdf me-2"></i>Generate Recommendation Form
                </button>
            </div>
        </form>

        {{-- Payment actions removed from Evaluator view --}}
    </div>
</div>
@endif

@if($isVerifier)
<div class="ai-card mb-4" style="border-left: 4px solid #1A4A8A; background-color: #f7f9fc;">
    <div class="ai-card-header">
        <i class="bi bi-shield-check fs-5 text-dark"></i>
        <h5 class="fw-bold text-dark mb-0">Verifier Action: Signed Recommendation & Payment Verification</h5>
    </div>
    <div class="x_content p-3 mt-2">
        <p class="text-muted small">This application is awaiting final verification. As a Verifier, you must upload the signed recommendation letter (signed offline by OED/Division Chief) and evaluate the payment requirements uploaded by the applicant.</p>

        <form id="evaluate-payment-form" action="{{ route('admin.hcd.applications.evaluate_payment', $application->id) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                {{-- Signed recommendation letter upload --}}
                <div class="col-md-12">
                    <div class="p-3 border rounded mb-3 bg-white" style="border-color: #d0ddf7 !important;">
                        <h6 class="fw-bold" style="color: #1A3A6A;"><i class="fas fa-file-signature me-1"></i> Signed Recommendation Letter (PDF) <span class="text-danger">*</span></h6>
                        <div class="mt-2">
                            <input type="file" name="signed_recommendation_letter" class="form-control" accept=".pdf" {{ !($application->payment && $application->payment->signed_recommendation_letter) ? 'required' : '' }}>
                            @if($application->payment && $application->payment->signed_recommendation_letter)
                            <div class="mt-2 text-success fw-semibold small">
                                <i class="fas fa-check-circle"></i> Already uploaded:
                                <a href="{{ route('admin.hcd.payments.view', ['payment' => $application->payment->id, 'fileType' => 'signed_recommendation_letter']) }}" target="_blank" class="text-decoration-underline text-success">
                                    {{ basename($application->payment->signed_recommendation_letter) }}
                                </a>
                            </div>
                            @else
                            <div class="mt-2 text-danger small"><i class="fas fa-exclamation-circle"></i> Missing: Please upload the signed recommendation letter to finalize accreditation.</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Payment Requirements evaluation list --}}
                @php
                $payment = $application->payment;
                $requirements = [
                'proof_of_payment' => 'Proof of Payment',
                ];
                @endphp

                @foreach($requirements as $key => $label)
                @php
                $filePath = $payment ? $payment->$key : null;
                $status = $payment ? $payment->{"{$key}_status"} : 'pending';
                $remarks = $payment ? $payment->{"{$key}_remarks"} : '';
                @endphp
                <div class="col-md-12">
                    <div class="p-3 border rounded bg-white shadow-sm" style="border-color: #dee2e6;">
                        <h6 class="fw-bold text-dark mb-2">{{ $label }}</h6>
                        <div class="mt-2 mb-2 text-center" style="min-height: 50px;">
                            @if($filePath)
                            <a href="{{ route('admin.hcd.payments.view', ['payment' => $payment->id, 'fileType' => $key]) }}" target="_blank" class="btn btn-outline-primary btn-xs mt-2 px-3 py-1 fw-semibold">
                                <i class="fas fa-eye me-1"></i> View {{ $label }}
                            </a>
                            <div class="text-muted small mt-2" style="font-size: 0.72rem; word-break: break-all;">
                                {{ basename($filePath) }}
                            </div>
                            @else
                            <span class="badge bg-secondary mt-3">Not Uploaded Yet</span>
                            @endif
                        </div>

                        @if($filePath)
                        <div class="mt-3 pt-3 border-top">
                            <label class="form-label small fw-semibold d-block mb-2">Status</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="{{ $key }}_status" id="{{ $key }}_app" value="approved" {{ $status === 'approved' ? 'checked' : '' }} required>
                                    <label class="form-check-label text-success small fw-semibold" for="{{ $key }}_app">Approve</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="{{ $key }}_status" id="{{ $key }}_rej" value="rejected" {{ $status === 'rejected' ? 'checked' : '' }} required onclick="document.getElementById('div-rem-{{ $key }}').style.display='block';">
                                    <label class="form-check-label text-danger small fw-semibold" for="{{ $key }}_rej">Reject</label>
                                </div>
                            </div>

                            <div class="mt-2" id="div-rem-{{ $key }}" style="{{ $status === 'rejected' ? '' : 'display:none;' }}">
                                <label class="form-label small text-muted">Rejection Remarks</label>
                                <textarea name="{{ $key }}_remarks" class="form-control form-control-sm" rows="2" placeholder="State reason for rejection...">{{ $remarks }}</textarea>
                            </div>
                        </div>
                        @else
                        <input type="hidden" name="{{ $key }}_status" value="pending">
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary fw-semibold px-4">
                    <span class="btn-text"><i class="fas fa-save me-1"></i> Submit Evaluation</span>
                    <span class="btn-spinner d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span> Submitting...
                    </span>
                </button>
        </form>

        <button type="button" class="btn btn-danger fw-semibold px-4 ms-auto" data-bs-toggle="modal" data-bs-target="#archivePaymentModal">
            <i class="fas fa-archive me-1"></i> Archive Application
        </button>
    </div>
</div>
@endif
@endif

{{-- ══ Certificate Issuance Panel (Step 8) ══ --}}
@if($isAccredited)
    <div class="ai-card mb-4 mt-4" style="border-left: 4px solid #16a34a; background-color: #f0fdf4;">
        <div class="ai-card-header">
            <i class="bi bi-file-earmark-check fs-5 text-dark"></i>
            <h5 class="fw-bold text-dark mb-0">Certificate Issuance</h5>
        </div>
        <div class="x_content p-3 mt-2">
            <p class="text-muted small">
                This application has been approved and accredited. 
                @if($isVerifier)
                    As a Verifier, you must upload the scanned certificate to complete Step 8 (Certificate Issuance) and notify the applicant that it is ready for pickup.
                @else
                    The Verifier will upload the scanned certificate to notify the applicant that it is ready for pickup.
                @endif
            </p>

            @if($application->accreditation->scanned_certificate)
                <div class="p-3 border rounded bg-white shadow-sm" style="border-color: #bbf7d0 !important;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-4"></i>
                            <div>
                                <div class="fw-bold text-success" style="font-size: 0.95rem;">
                                    <i class="fas fa-check-circle"></i> Scanned Certificate Uploaded
                                </div>
                                <small class="text-muted">
                                    File: {{ basename($application->accreditation->scanned_certificate) }}
                                </small>
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('admin.hcd.accreditations.view_scanned', $application->accreditation->id) }}" target="_blank" class="btn btn-outline-success btn-sm fw-semibold px-3">
                                <i class="fas fa-eye me-1"></i> View Scanned Certificate
                            </a>
                        </div>
                    </div>
                </div>
            @else
                @if($isVerifier)
                    <form id="upload-scanned-certificate-form" action="{{ route('admin.hcd.accreditations.upload_scanned', $application->accreditation->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="p-3 border rounded bg-white shadow-sm" style="border-color: #d0ddf7 !important;">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="fw-bold mb-1" style="color: #1A3A6A; font-size: 0.95rem;">
                                        <i class="fas fa-upload me-1"></i> Upload Scanned Certificate (PDF) <span class="text-danger">*</span>
                                    </h6>
                                    <p class="text-muted small mb-0">Upload proof that the certificate is ready for pickup (signed & scanned certificate).</p>
                                </div>
                                <div class="col-md-4 mt-2 mt-md-0">
                                    <input type="file" name="scanned_certificate" class="form-control form-control-sm" accept=".pdf" required>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary fw-semibold px-4">
                                <span class="btn-text"><i class="fas fa-save me-1"></i> Upload Scanned Certificate</span>
                                <span class="btn-spinner d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span> Uploading...
                                </span>
                            </button>
                        </div>
                    </form>
                @else
                    <div class="alert alert-secondary py-2 px-3 border-0 rounded text-center small mb-0">
                        <i class="fas fa-info-circle me-1"></i> Awaiting Scanned Certificate Upload by Verifier.
                    </div>
                @endif
            @endif
        </div>
    </div>
@endif

{{-- ══ Archive Payment Modal ══ --}}
<div class="modal fade" id="archivePaymentModal" tabindex="-1" aria-labelledby="archivePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="archivePaymentModalLabel"><i class="fas fa-exclamation-triangle me-1"></i> Confirm Archive Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <p>Are you sure you want to archive/reject this application? This action is <strong>permanent</strong> and will notify the applicant that they did not pass the requirements.</p>
                <ul class="mb-0">
                    <li><strong>Tracking Number:</strong> {{ $application->tracking_number }}</li>
                    <li><strong>FATPro Name:</strong>
                        @if($isOrg)
                        {{ $org->name ?? 'N/A' }}
                        @else
                        {{ ($ind->first_name ?? '') . ' ' . ($ind->last_name ?? '') }}
                        @endif
                    </li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.hcd.applications.archive_payment', $application->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold">Archive Application</button>
                </form>
            </div>
        </div>
    </div>
</div>


{{-- ══ Schedule Interview Modal ══ --}}
<div class="modal fade" id="scheduleInterviewModal" tabindex="-1"
    aria-labelledby="scheduleInterviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 60px rgba(0,0,0,.18);">

            {{-- Modal Header --}}
            <div class="modal-header"
                style="background:linear-gradient(135deg,#1A4A8A,#0D2B55); border:none; padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-calendar-check-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="scheduleInterviewModalLabel">
                            {{ $interview ? 'Update Interview Schedule' : 'Schedule Interview' }}
                        </h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4" style="background:#f8fafc;">
                <form id="schedule-interview-form"
                    action="{{ route('admin.hcd.applications.schedule_interview', $application->id) }}"
                    method="POST">
                    @csrf

                    {{-- Notice --}}
                    <div class="mb-3 p-2 d-flex align-items-center gap-2"
                        style="background:#eef5ff;border-radius:8px;border-left:4px solid #1A4A8A;">
                        <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                        <small class="text-muted">
                            An email notification with the interview schedule will be sent to the applicant upon saving.
                        </small>
                    </div>

                    <div class="row g-3">
                        {{-- Date --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>Interview Date
                            </label>
                            <input type="date" name="interview_date" id="interview-date" class="form-control"
                                value="{{ $interview?->interview_date?->format('Y-m-d') }}"
                                min="{{ now()->format('Y-m-d') }}" required
                                style="border-radius:8px;border-color:#d0d8e8;">
                        </div>

                        {{-- Time --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;">
                                <i class="bi bi-clock me-1 text-primary"></i>Interview Time
                            </label>
                            <input type="time" name="interview_time" id="interview-time" class="form-control"
                                value="{{ $interview ? \Carbon\Carbon::parse($interview->interview_time)->format('H:i') : '' }}" required
                                style="border-radius:8px;border-color:#d0d8e8;">
                        </div>

                        {{-- Mode --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;">
                                <i class="bi bi-display me-1 text-primary"></i>Interview Mode
                            </label>
                            <select name="mode" id="interview-mode" class="form-select" required
                                style="border-radius:8px;border-color:#d0d8e8;">
                                <option value="">— Select Mode —</option>
                                <option value="online" {{ ($interview?->mode === 'online') ? 'selected' : '' }}>Online</option>
                                <option value="f2f" {{ ($interview?->mode === 'f2f')    ? 'selected' : '' }}>Face-to-Face (F2F)</option>
                            </select>
                        </div>

                        {{-- Venue --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;" id="interview-venue-label">
                                <i class="bi bi-geo-alt me-1 text-primary"></i>Venue
                                <span class="text-muted fw-normal fst-italic" id="venue-note">(F2F only)</span>
                            </label>
                            <input type="text" name="venue" id="interview-venue" class="form-control"
                                placeholder="Venue / meeting link"
                                value="{{ $interview?->venue }}"
                                style="border-radius:8px;border-color:#d0d8e8;" required>
                        </div>

                        {{-- Online Notice --}}
                        <div class="col-12">
                            <div class="form-text mt-2 d-none" id="online-notice" style="font-size:0.75rem; color:#0d4f9e; padding:6px 10px; background:rgba(13,79,158,.08); border-radius:6px; border-left:3px solid #0d4f9e;">
                                <i class="bi bi-info-circle-fill me-1"></i> The meeting link entered above will be included directly in the confirmation email.
                            </div>
                        </div>

                        {{-- Slot Conflict Warning (hidden by default) --}}
                        <div class="col-12">
                            <div id="slot-conflict-warning" class="d-none mt-2 p-2 d-flex align-items-center gap-2"
                                style="background:#fee2e2;border-radius:8px;border-left:4px solid #dc2626;font-size:0.82rem;color:#991b1b;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="slot-conflict-msg">This slot is already taken.</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e4eaf2;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Cancel
                </button>
                <button type="submit" form="schedule-interview-form"
                    id="submit-schedule-btn"
                    class="btn btn-primary fw-bold px-5"
                    style="border-radius:8px; background:linear-gradient(135deg,#1A4A8A,#0D2B55); border:none;">
                    <span id="submit-schedule-text">
                        <i class="bi bi-calendar-check me-2"></i>
                        {{ $interview ? 'Update Schedule' : 'Save Schedule' }}
                    </span>
                    <span id="submit-schedule-spinner" class="d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span> Saving...
                    </span>
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ══ Rejection Confirmation Modal ══ --}}
<div class="modal fade" id="rejectionConfirmModal" tabindex="-1"
    aria-labelledby="rejectionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 60px rgba(0,0,0,.2);">

            {{-- Header --}}
            <div class="modal-header border-0 pb-2"
                style="background:linear-gradient(135deg,#c0392b,#922b21); padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-envelope-exclamation-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="rejectionConfirmModalLabel">
                            Send Rejection Email
                        </h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-4" style="background:#fafafa;">

                {{-- Applicant info --}}
                <div class="d-flex align-items-center gap-3 mb-3 p-3"
                    style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
                    <i class="bi bi-person-circle fs-2 text-danger"></i>
                    <div>
                        <div class="fw-bold" style="color:#2A3F54;font-size:.95rem;">
                            {{ $org?->name ?? ($ind?->full_name ?? 'N/A') }}
                        </div>
                        <small class="text-muted">{{ $org?->email ?? $user->email }}</small>
                    </div>
                </div>

                {{-- Warning notice --}}
                <div class="d-flex align-items-start gap-2 mb-3 p-3"
                    style="background:#fff3cd;border-radius:8px;border-left:4px solid #ffc107;">
                    <i class="bi bi-exclamation-triangle-fill text-warning mt-1"></i>
                    <small class="text-dark">
                        An email will be sent to the applicant listing the rejected documents and remarks. The application status will change to <strong>For Update</strong>.
                    </small>
                </div>

                {{-- Rejected documents list (populated by JS) --}}
                <h6 class="fw-bold mb-2" style="color:#922b21; font-size:.85rem; text-transform:uppercase; letter-spacing:.4px;">
                    <i class="bi bi-x-circle-fill me-1"></i>Rejected Documents
                </h6>
                <div id="rejection-doc-list" class="d-flex flex-column gap-2">
                    {{-- JS fills this dynamically --}}
                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer border-0 pt-2" style="background:#fafafa; padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    Go Back
                </button>
                <button type="button" id="btn-confirm-rejection"
                    class="btn btn-danger fw-bold px-5"
                    style="border-radius:8px;">
                    <i class="bi bi-send-fill me-2"></i>Confirm & Send Email
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ══ Shared variables for modal applicant details partial ══ --}}
@php
$modalInstructors = $user->instructors ?? collect();
$applicantName = $isOrg ? ($org->name ?? 'N/A') : ($user->name ?? 'N/A');
$applicantEmail = $isOrg ? ($org->email ?? $user->email) : $user->email;
$accTypeName = $application->accreditationType->name ?? '—';
@endphp

{{-- ══ Stop Interview Confirmation Modal ══ --}}
<div class="modal fade" id="stopInterviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#c0392b,#922b21);padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px; display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-stop-circle-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold">Stop Interview</h5>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="mb-0 fw-semibold">Are you sure you want to stop the interview?</p>
                <small class="text-muted">The PCT timer for the interview will be completed, and you will be able to record the evaluation result.</small>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('admin.hcd.applications.stop_interview', $application->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-danger fw-bold"><i class="bi bi-stop-fill me-1"></i> Confirm Stop</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ══ Passed Confirmation Modal ══ --}}
<div class="modal fade" id="passedConfirmModal" tabindex="-1"
    aria-labelledby="passedConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.18);">

            <div class="modal-header border-0"
                style="background:linear-gradient(135deg,#1a7a4a,#145c38);padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-patch-check-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="passedConfirmModalLabel">Confirm: Interview Passed</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" style="background:#f9fdf9; max-height:65vh; overflow-y:auto;">
                @include('admin.hcd.partials.modal_applicant_details', ['accentColor' => 'text-success'])

                {{-- Action Notice --}}
                <div class="d-flex align-items-start gap-2 p-3"
                    style="background:#d4edda;border-radius:8px;border-left:4px solid #28a745;">
                    <i class="bi bi-check-circle-fill text-success mt-1"></i>
                    <small class="text-dark">
                        The application will be marked as having <strong>Passed the Interview</strong>. The status will be updated to <strong>Awaiting Payment</strong>, allowing the Evaluator to generate the recommendation form and request payment from the applicant.
                    </small>
                </div>
            </div>

            <div class="modal-footer border-0" style="background:#f9fdf9;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <form id="confirm-approval-form" method="POST" action="{{ route('admin.hcd.applications.interview_result', $application->id) }}">
                    @csrf
                    <input type="hidden" name="result" value="passed">
                    <button type="submit" class="btn btn-success fw-bold px-5" style="border-radius:8px;">
                        <span class="btn-text"><i class="bi bi-check-circle-fill me-2"></i>Confirm Passed</span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span> Updating...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ══ Not Passed Confirmation Modal ══ --}}
<div class="modal fade" id="notPassedConfirmModal" tabindex="-1"
    aria-labelledby="notPassedConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <div class="modal-header border-0"
                style="background:linear-gradient(135deg,#c0392b,#922b21);padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-x-octagon-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="notPassedConfirmModalLabel">Confirm: Application Rejected</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" style="background:#fafafa; max-height:65vh; overflow-y:auto;">
                @include('admin.hcd.partials.modal_applicant_details', ['accentColor' => 'text-danger'])

                {{-- Warning Notice --}}
                <div class="d-flex align-items-start gap-2 p-3"
                    style="background:#f8d7da;border-radius:8px;border-left:4px solid #dc3545;">
                    <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
                    <small class="text-dark">
                        <strong>Warning — this action is permanent and cannot be undone.</strong><br>
                        The application status will be changed to <strong>Rejected</strong> and it will be moved to the <strong>Archived Applications</strong> directory. The applicant will be notified by email.
                    </small>
                </div>
            </div>

            <div class="modal-footer border-0" style="background:#fafafa;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <form id="confirm-reject-form" method="POST" action="{{ route('admin.hcd.applications.interview_result', $application->id) }}">
                    @csrf
                    <input type="hidden" name="result" value="not_passed">
                    <button type="submit" class="btn btn-danger fw-bold px-5" style="border-radius:8px;">
                        <span class="btn-text"><i class="bi bi-archive-fill me-2"></i>Confirm & Archive</span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span> Archiving...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ══ Revoke Accreditation Confirmation Modal ══ --}}
@if($isAccredited && $application->accreditation->status === 'active')
<div class="modal fade" id="revokeAccreditationModal" tabindex="-1"
    aria-labelledby="revokeAccreditationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <div class="modal-header border-0"
                style="background:linear-gradient(135deg,#c0392b,#922b21);padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-shield-x text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="revokeAccreditationModalLabel">Revoke Accreditation</h5>
                        <small class="text-white-50">{{ $application->accreditation->accreditation_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" style="background:#fafafa;">
                <div class="d-flex align-items-center gap-3 mb-3 p-3"
                    style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
                    <i class="bi bi-person-circle fs-2 text-danger"></i>
                    <div>
                        <div class="fw-bold" style="color:#2A3F54;font-size:.95rem;">
                            {{ $org?->name ?? ($ind?->full_name ?? 'N/A') }}
                        </div>
                        <small class="text-muted">{{ $org?->email ?? $user->email }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 mb-1"
                    style="background:#f8d7da;border-radius:8px;border-left:4px solid #dc3545;">
                    <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
                    <small class="text-dark">
                        <strong>Warning — this action will revoke the FATPro's active accreditation.</strong><br>
                        The accreditation status will be changed to <strong>Revoked</strong>. The FATPro will no longer
                        appear in the active directory. This action can only be reversed by issuing a new accreditation.
                    </small>
                </div>
            </div>

            <div class="modal-footer border-0" style="background:#fafafa;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <form method="POST" action="{{ route('admin.hcd.accreditations.revoke', $application->accreditation->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-danger fw-bold px-5" style="border-radius:8px;">
                        <i class="bi bi-shield-x me-2"></i>Confirm Revoke
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══ Request Update Modals (outside main form to avoid nesting issues) ══ --}}
@if($application->user && $application->user->instructors)
@foreach($application->user->instructors as $instructor)
@if($isAccredited && in_array($instructor->update_request_status, ['none', 'completed']))
<div class="modal fade" id="reqModal-inst-{{ $instructor->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('admin.hcd.instructors.request_update', $instructor->id) }}" method="POST" class="modal-content text-start" style="border-radius:12px;overflow:hidden;font-family:'Inter',sans-serif;">
            @csrf
            <div class="modal-header py-2 px-3" style="background:linear-gradient(135deg,#0b3d91,#091e3e);border:none;">
                <div>
                    <h6 class="modal-title text-white mb-0 fw-bold" style="font-size:.88rem;">Request Update</h6>
                    <small class="text-white-50" style="font-size:.75rem;">{{ $instructor->first_name }} {{ $instructor->last_name }}</small>
                </div>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" style="background:#f8fafc;">
                <label class="form-label fw-semibold mb-2" style="font-size:.82rem;color:#2A3F54;">
                    Select Documents &amp; Provide Reason Per Item <span class="text-danger">*</span>
                </label>
                <div class="px-2 py-2" style="background:#fff;border:1px solid #d0d8e8;border-radius:6px;">

                    {{-- Service Agreement --}}
                    <div class="mb-3 border-bottom pb-3">
                        <div class="form-check mb-1">
                            <input class="form-check-input req-chk" type="checkbox"
                                name="fields[service_agreement][requested]" value="1"
                                id="chk-sa-{{ $instructor->id }}"
                                data-target="reason-sa-{{ $instructor->id }}">
                            <label class="form-check-label fw-semibold" for="chk-sa-{{ $instructor->id }}" style="font-size:.85rem;">Service Agreement</label>
                        </div>
                        <div id="reason-sa-{{ $instructor->id }}" class="req-reason-box ps-4" style="display:none;">
                            <input type="text" name="fields[service_agreement][reason]"
                                class="form-control form-control-sm mt-1"
                                placeholder="Reason for this document..."
                                style="font-size:.8rem;border-radius:5px;">
                        </div>
                    </div>

                    {{-- Credentials --}}
                    @foreach($instructor->credentials as $cred)
                    <div class="{{ $loop->last ? '' : 'mb-3 border-bottom pb-3' }}">
                        <div class="form-check mb-1">
                            <input class="form-check-input req-chk" type="checkbox"
                                name="fields[{{ $cred->type }}][requested]" value="1"
                                id="chk-{{ $instructor->id }}-{{ $cred->type }}"
                                data-target="reason-{{ $instructor->id }}-{{ $cred->type }}">
                            <label class="form-check-label fw-semibold" for="chk-{{ $instructor->id }}-{{ $cred->type }}" style="font-size:.85rem;">
                                @if($cred->type === 'EMS') TESDA EMS NC II/III
                                @elseif($cred->type === 'TM1') TESDA TM1
                                @elseif($cred->type === 'NTTC') TESDA NTTC
                                @elseif($cred->type === 'BOSH') BOSH SO1/SO2
                                @else {{ $cred->type }}
                                @endif
                            </label>
                        </div>
                        <div id="reason-{{ $instructor->id }}-{{ $cred->type }}" class="req-reason-box ps-4" style="display:none;">
                            <input type="text" name="fields[{{ $cred->type }}][reason]"
                                class="form-control form-control-sm mt-1"
                                placeholder="Reason for this document..."
                                style="font-size:.8rem;border-radius:5px;">
                        </div>
                    </div>
                    @endforeach

                </div>
            </div>
            <div class="modal-footer py-2 px-3" style="background:#f8fafc;border-top:1px solid #e4eaf2;">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" style="background-color:#0b3d91; border-color:#0b3d91;"><i class="bi bi-send me-1"></i>Send Request</button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
@endif

{{-- ── Certificate Executive Director Name Modal ── --}}
<div class="modal fade" id="certDirectorModal" tabindex="-1" aria-labelledby="certDirectorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:0;box-shadow:0 8px 30px rgba(0,0,0,.15);">
            <div class="modal-header py-3 px-4" style="background:linear-gradient(135deg,#15803d,#166534);border:0;">
                <h6 class="modal-title text-white fw-bold mb-0" id="certDirectorModalLabel">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Generate Certificate
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <small class="text-muted d-block mb-2" style="font-size:.75rem;">Name of signatory on the certificate.</small>
                <input type="text" class="form-control" id="cert-director-name"
                       value="JOSE MARIA S. BATINO"
                       placeholder="Enter Executive Director name"
                       style="border-radius:8px;font-size:.9rem;border:1px solid #ccc;">
                <div class="invalid-feedback">Please enter the Executive Director name.</div>
                <label for="cert-director-name" class="form-label fw-semibold mt-2 mb-0 d-block text-center" style="font-size:.85rem;color:#333;">
                    Executive Director Name
                </label>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                <button type="button" class="btn btn-success btn-sm fw-semibold px-4" id="cert-generate-btn" style="border-radius:8px;background:#15803d;border-color:#166534;" onclick="generateCert()">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Generate PDF
                </button>
            </div>
        </div>
    </div>
</div>
<script>
var _certBaseUrl = '';
function setCertUrl(url) {
    _certBaseUrl = url;
    var nameInput = document.getElementById('cert-director-name');
    if (nameInput) {
        nameInput.value = 'JOSE MARIA S. BATINO';
        nameInput.classList.remove('is-invalid');
    }
}
function generateCert() {
    var nameInput = document.getElementById('cert-director-name');
    var name = nameInput ? nameInput.value.trim() : '';
    if (!name) {
        if (nameInput) nameInput.classList.add('is-invalid');
        return;
    }
    var url = _certBaseUrl + '?executive_director=' + encodeURIComponent(name);
    window.open(url, '_blank');
    
    // Close modal programmatically by clicking the modal's close/cancel button
    var modalEl = document.getElementById('certDirectorModal');
    if (modalEl) {
        var cancelBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');
        if (cancelBtn) {
            cancelBtn.click();
        }
    }
}
document.getElementById('cert-director-name').addEventListener('input', function () {
    this.classList.remove('is-invalid');
});
</script>

@endsection

@push('scripts')
<script>
    window.ARMS = window.ARMS || {};
    window.ARMS.csrfToken = '{{ csrf_token() }}';
    window.ARMS.isScheduled = {{ $isScheduled ? 'true' : 'false' }};
    window.ARMS.hasInterviewRecord = {{ $interview ? 'true' : 'false' }};
    window.ARMS.allApproved = {{ $allApproved ? 'true' : 'false' }};
    window.ARMS.isApproved = {{ $isApproved ? 'true' : 'false' }};
    window.ARMS.isAccredited = {{ $isAccredited ? 'true' : 'false' }};
    window.ARMS.hasPendingUpdate = {{ $hasPendingUpdate ? 'true' : 'false' }};
    window.ARMS.canUpdateSchedule = {{ (!$interview || ($activeStep === 5 && $pctStatus === 'paused')) ? 'true' : 'false' }};
    window.ARMS.checkSlotUrl = '{{ route("admin.hcd.interviews.check_slot") }}';
    window.ARMS.evaluateItemUrl = '{{ route("admin.hcd.applications.evaluate_item", $application->id) }}';
    window.ARMS.applicationStatus = '{{ $currentStatus }}';
    window.ARMS.applicationId = {{ $application->id }};
</script>
<script src="{{ asset('js/evaluation.js') }}?v={{ filemtime(public_path('js/evaluation.js')) }}"></script>
<script>
    // ── Interview Slot Conflict Checker ──────────────────────────────────────
    (function() {
        'use strict';

        const dateInput = document.getElementById('interview-date');
        const timeInput = document.getElementById('interview-time');
        const warningBox = document.getElementById('slot-conflict-warning');
        const warningMsg = document.getElementById('slot-conflict-msg');
        const submitBtn = document.querySelector('#schedule-interview-form button[type="submit"], button[form="schedule-interview-form"]');

        if (!dateInput || !timeInput) return;

        let checkTimeout = null;

        function checkSlot() {
            const date = dateInput.value;
            const time = timeInput.value;

            // Only check when both fields are filled
            if (!date || !time) {
                hideWarning();
                return;
            }

            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(async function() {
                try {
                    const url = new URL(window.ARMS.checkSlotUrl, window.location.origin);
                    url.searchParams.set('date', date);
                    url.searchParams.set('time', time);
                    url.searchParams.set('application_id', window.ARMS.applicationId);

                    const res = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();

                    if (!data.available) {
                        showWarning(data.message);
                    } else {
                        hideWarning();
                    }
                } catch (err) {
                    console.error('Slot check failed:', err);
                    hideWarning();
                }
            }, 350); // debounce 350ms
        }

        function showWarning(msg) {
            if (warningBox) {
                warningMsg.textContent = msg;
                warningBox.classList.remove('d-none');
                warningBox.classList.add('d-flex');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        }

        function hideWarning() {
            if (warningBox) {
                warningBox.classList.add('d-none');
                warningBox.classList.remove('d-flex');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }

        dateInput.addEventListener('change', checkSlot);
        timeInput.addEventListener('change', checkSlot);
    })();
</script>
<script>
    // Request Update modal — show/hide reason input when checkbox is checked
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('req-chk')) {
            const targetId = e.target.dataset.target;
            const box = document.getElementById(targetId);
            if (box) {
                box.style.display = e.target.checked ? 'block' : 'none';
                const input = box.querySelector('input, textarea');
                if (input) input.required = e.target.checked;
            }
        }
    });

    // Approval and Rejection modal submission loaders
    (function() {
        'use strict';
        const forms = ['confirm-approval-form', 'confirm-reject-form', 'evaluate-payment-form', 'upload-scanned-certificate-form'];
        forms.forEach(function(formId) {
            const form = document.getElementById(formId);
            if (form) {
                form.addEventListener('submit', function() {
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.85';
                        btn.style.cursor = 'not-allowed';
                        const textSpan = btn.querySelector('.btn-text');
                        const spinnerSpan = btn.querySelector('.btn-spinner');
                        if (textSpan) textSpan.classList.add('d-none');
                        if (spinnerSpan) spinnerSpan.classList.remove('d-none');
                    }
                });
            }
        });
    })();
</script>
@endpush