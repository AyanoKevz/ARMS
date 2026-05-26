{{-- Accent color for section icons: pass $accentColor = 'text-success' or 'text-danger' --}}
@php $accent = $accentColor ?? 'text-primary'; @endphp

{{-- ── Applicant Header ── --}}
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
    <i class="bi bi-person-circle fs-2 {{ $accent }}"></i>
    <div class="flex-grow-1">
        <div class="fw-bold" style="color:#2A3F54;font-size:.95rem;">{{ $applicantName }}</div>
        <small class="text-muted">{{ $applicantEmail }}</small>
    </div>
    <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:.75rem;">{{ $accTypeName }}</span>
</div>

{{-- ── Individual Profile (non-org) ── --}}
@if(!$isOrg && $ind)
<div class="mb-3 p-3" style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-person-vcard {{ $accent }}" style="font-size:1rem;"></i>
        <span class="fw-bold" style="color:#2A3F54;font-size:.85rem;text-transform:uppercase;letter-spacing:.3px;">Individual Profile</span>
    </div>
    <div class="row g-2" style="font-size:.83rem;">
        <div class="col-sm-4"><span class="text-muted">First Name:</span> <span class="fw-semibold">{{ $ind->first_name }}</span></div>
        <div class="col-sm-4"><span class="text-muted">Middle Name:</span> <span class="fw-semibold">{{ $ind->middle_name ?? '—' }}</span></div>
        <div class="col-sm-4"><span class="text-muted">Last Name:</span> <span class="fw-semibold">{{ $ind->last_name }}</span></div>
        <div class="col-sm-4"><span class="text-muted">Sex:</span> <span class="fw-semibold">{{ $ind->sex ?? '—' }}</span></div>
        <div class="col-sm-4"><span class="text-muted">Birthday:</span> <span class="fw-semibold">{{ $ind->birthday ? $ind->birthday->format('M d, Y') : '—' }}</span></div>
        <div class="col-sm-4"><span class="text-muted">Region:</span> <span class="fw-semibold">{{ $ind->region ?? '—' }}</span></div>
        <div class="col-sm-4"><span class="text-muted">City:</span> <span class="fw-semibold">{{ $ind->city ?? '—' }}</span></div>
        <div class="col-sm-8"><span class="text-muted">Address:</span> <span class="fw-semibold">{{ $ind->address ?? '—' }}</span></div>
    </div>
</div>
@endif

{{-- ── Organization Details ── --}}
@if($isOrg && $org)
<div class="mb-3 p-3" style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-building {{ $accent }}" style="font-size:1rem;"></i>
        <span class="fw-bold" style="color:#2A3F54;font-size:.85rem;text-transform:uppercase;letter-spacing:.3px;">Organization</span>
    </div>
    <div class="row g-2" style="font-size:.83rem;">
        <div class="col-sm-6"><span class="text-muted">Name:</span> <span class="fw-semibold">{{ $org->name }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Head:</span> <span class="fw-semibold">{{ $org->head_name ?? '—' }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Designation:</span> <span class="fw-semibold">{{ $org->designation ?? '—' }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Email:</span> <span class="fw-semibold">{{ $org->email ?? '—' }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Telephone:</span> <span class="fw-semibold">{{ $org->telephone ?? '—' }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Fax:</span> <span class="fw-semibold">{{ $org->fax ?? '—' }}</span></div>
        <div class="col-12"><span class="text-muted">Address:</span> <span class="fw-semibold">{{ $org->address ?? '—' }}</span></div>
    </div>
</div>
@endif

{{-- ── Authorized Representative(s) ── --}}
@if($isOrg && $reps->isNotEmpty())
<div class="mb-3 p-3" style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-person-badge {{ $accent }}" style="font-size:1rem;"></i>
        <span class="fw-bold" style="color:#2A3F54;font-size:.85rem;text-transform:uppercase;letter-spacing:.3px;">Authorized Representative</span>
    </div>
    @foreach($reps as $rep)
    <div class="row g-2 {{ !$loop->last ? 'mb-2 pb-2' : '' }}" style="font-size:.83rem;{{ !$loop->last ? 'border-bottom:1px dashed #e4eaf2;' : '' }}">
        <div class="col-sm-6"><span class="text-muted">Name:</span> <span class="fw-semibold">{{ $rep->full_name }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Position:</span> <span class="fw-semibold">{{ $rep->position ?? '—' }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Contact:</span> <span class="fw-semibold">{{ $rep->contact_number ?? '—' }}</span></div>
        <div class="col-sm-6"><span class="text-muted">Email:</span> <span class="fw-semibold">{{ $rep->email ?? '—' }}</span></div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Instructors with Credentials ── --}}
@if($modalInstructors->isNotEmpty())
<div class="mb-3 p-3" style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-people-fill {{ $accent }}" style="font-size:1rem;"></i>
        <span class="fw-bold" style="color:#2A3F54;font-size:.85rem;text-transform:uppercase;letter-spacing:.3px;">Instructors</span>
        <span class="badge bg-secondary ms-auto" style="font-size:.72rem;">{{ $modalInstructors->count() }}</span>
    </div>
    <div class="d-flex flex-column gap-2">
        @foreach($modalInstructors as $mi)
        <div class="p-2" style="background:#f8fafc;border-radius:8px;border:1px solid #eef1f6;">
            {{-- Instructor name row --}}
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-mortarboard-fill text-muted"></i>
                <span class="fw-semibold" style="font-size:.85rem;">{{ $mi->first_name }} {{ $mi->middle_name ? $mi->middle_name . ' ' : '' }}{{ $mi->last_name }}</span>
                @if($mi->status === 'approved')
                    <span class="badge bg-success bg-opacity-10 text-success ms-1" style="font-size:.68rem;">Approved</span>
                @elseif($mi->status === 'rejected')
                    <span class="badge bg-danger bg-opacity-10 text-danger ms-1" style="font-size:.68rem;">Rejected</span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning ms-1" style="font-size:.68rem;">Pending</span>
                @endif
            </div>
            {{-- Credentials --}}
            @if($mi->credentials->isNotEmpty())
            <div class="ps-4 mt-1">
                @foreach($mi->credentials as $cred)
                <div class="d-flex flex-wrap gap-3 {{ !$loop->last ? 'mb-1 pb-1' : '' }}" style="font-size:.78rem;{{ !$loop->last ? 'border-bottom:1px dotted #dde3ef;' : '' }}">
                    <span><span class="text-muted">Type:</span> <span class="fw-semibold">{{ $cred->type }}</span></span>
                    @if($cred->number)<span><span class="text-muted">No:</span> <span class="fw-semibold">{{ $cred->number }}</span></span>@endif
                    @if($cred->issued_date)<span><span class="text-muted">Issued:</span> <span class="fw-semibold">{{ \Carbon\Carbon::parse($cred->issued_date)->format('M d, Y') }}</span></span>@endif
                    @if($cred->validity_date)<span><span class="text-muted">Valid Until:</span> <span class="fw-semibold">{{ \Carbon\Carbon::parse($cred->validity_date)->format('M d, Y') }}</span></span>@endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif
