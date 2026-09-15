{{--
    The Post Training Report cell of the Training Submissions table.

    A training only owes a report once it has been held, so this cell stays
    inert until then and only offers the submit action when it is genuinely
    available.

    Expects: $ntc (NtcReport)
--}}

@php
    $ptr        = $ntc->postTrainingReport;
    $deadline   = $ntc->postTrainingDeadlineDate();
    $daysAllowed = $ntc->postTrainingDaysAllowed();
    $concluded  = $ntc->hasTrainingConcluded();
    $isOverdue  = $ntc->isPostTrainingReportOverdue();
    $daysLeft   = $ntc->postTrainingDaysRemaining();
@endphp

@if($ntc->status !== 'acknowledged')
    {{-- Nothing is owed until the NTC itself is acknowledged. --}}
    <span class="ptr-muted-line d-block">
        <i class="fas fa-minus text-muted me-1"></i> Available once the NTC is acknowledged
    </span>

@elseif(!$concluded)
    {{-- Training has not been held yet. --}}
    <span class="badge-ptr-secondary d-inline-block mb-1" style="font-size:.7rem;">Not yet due</span>
    <div class="ptr-muted-line">
        Opens after {{ $ntc->training_end_date?->format('F d, Y') ?? 'the training' }}
    </div>
    <div class="ptr-muted-line">
        Due by {{ $deadline?->format('F d, Y') ?? 'N/A' }}
        ({{ $daysAllowed }} working {{ Str::plural('day', $daysAllowed) }})
    </div>

@elseif(!$ptr)
    {{-- Held, nothing filed: this is the action state. --}}
    @php
        $pillClass = $isOverdue
            ? 'ptr-deadline-over'
            : (($daysLeft !== null && $daysLeft <= 1) ? 'ptr-deadline-soon' : 'ptr-deadline-ok');

        if ($isOverdue) {
            $pillText = 'Overdue by ' . abs($daysLeft) . ' ' . Str::plural('day', abs($daysLeft));
        } elseif ($daysLeft === 0) {
            $pillText = 'Due today';
        } else {
            $pillText = $daysLeft . ' ' . Str::plural('day', $daysLeft) . ' left';
        }
    @endphp

    <div class="mb-1">
        <span class="ptr-deadline-pill {{ $pillClass }}">
            <i class="fas {{ $isOverdue ? 'fa-exclamation-circle' : 'fa-clock' }}"></i>
            {{ $pillText }}
        </span>
    </div>
    <div class="ptr-muted-line mb-2">
        Deadline: <strong>{{ $deadline?->format('F d, Y') ?? 'N/A' }}</strong>
        &mdash; {{ $daysAllowed }} working {{ Str::plural('day', $daysAllowed) }} allowed
    </div>

    {{-- Label stays short: the column header already says what this is. --}}
    <button type="button"
            class="btn btn-sm ptr-submit-btn w-100 btn-ptr-submit-open"
            data-action="{{ route('applicant.post_training.store', $ntc->id) }}"
            data-ntc-ref="{{ $ntc->reference_number }}"
            data-training-type="{{ $ntc->trainingType->name ?? 'N/A' }}"
            data-training-period="{{ ($ntc->training_start_date?->format('F d, Y') ?? 'N/A') . ' — ' . ($ntc->training_end_date?->format('F d, Y') ?? 'N/A') }}"
            data-deadline="{{ $deadline?->format('F d, Y') ?? 'N/A' }}"
            data-overdue="{{ $isOverdue ? '1' : '0' }}">
        <i class="fas fa-cloud-upload-alt me-1"></i> Submit Report
    </button>

@else
    {{-- Filed: show where it stands, and re-open only what was declined. --}}
    @php
        $declined  = $ptr->declinedDocuments();
        $approved  = $ptr->documents->where('status', 'approved')->count();
        $totalDocs = $ptr->documents->count();
    @endphp

    <div class="mb-1">
        @if($ptr->isAccepted())
            <span class="badge-ptr-success">Accepted</span>
        @elseif($declined->isNotEmpty())
            <span class="badge-ptr-danger">Requires Re-submission</span>
        @elseif($ptr->documents->contains('status', 'returned'))
            <span class="badge-ptr-warning">Awaiting Re-evaluation</span>
        @else
            <span class="badge-ptr-info">Under Review</span>
        @endif
    </div>

    <div class="ptr-ref-link" style="font-size:.8rem;">{{ $ptr->reference_number }}</div>
    <div class="ptr-muted-line">
        Submitted {{ $ptr->submitted_at?->format('F d, Y') ?? 'N/A' }}
        @if($ptr->wasSubmittedLate())
            <span class="ptr-deadline-pill ptr-deadline-over ms-1" style="font-size:.65rem;">Late</span>
        @endif
    </div>
    <div class="ptr-muted-line mb-2">{{ $approved }} / {{ $totalDocs }} documents accepted</div>

    {{-- Compact chips: one per attachment, colour-coded by outcome --}}
    <div class="d-flex flex-wrap gap-1 mb-2">
        @foreach($ptr->documents->sortBy(fn($d) => $d->documentType->sort_order ?? 0) as $doc)
            @php
                $isDeclined = ($doc->status === 'rejected' && !$doc->file_path);
                $chipClass = match(true) {
                    $isDeclined                   => 'badge-ptr-danger',
                    $doc->status === 'approved'   => 'badge-ptr-success',
                    $doc->status === 'returned'   => 'badge-ptr-warning',
                    default                       => 'badge-ptr-secondary',
                };
            @endphp
            @if($doc->file_path)
                <a href="{{ route('applicant.post_training.document.view', $doc->id) }}"
                   target="_blank"
                   class="{{ $chipClass }} text-decoration-none"
                   style="font-size:.65rem; padding:3px 7px;"
                   title="{{ $doc->documentType->name ?? 'Document' }}">
                    {{ $doc->documentType->code ?? 'DOC' }}
                </a>
            @else
                <span class="{{ $chipClass }}"
                      style="font-size:.65rem; padding:3px 7px;"
                      title="{{ $doc->documentType->name ?? 'Document' }} — declined, awaiting re-upload">
                    <i class="fas fa-times"></i> {{ $doc->documentType->code ?? 'DOC' }}
                </span>
            @endif
        @endforeach
    </div>

    @if($declined->isNotEmpty())
        <button type="button"
                class="btn btn-danger btn-sm fw-bold w-100 d-inline-flex align-items-center justify-content-center gap-1"
                style="font-size:.72rem; padding: 7px 10px; border-radius:6px; border:none; background:#e11d48;"
                data-bs-toggle="modal"
                data-bs-target="#ptrReuploadModal-{{ $ptr->id }}">
            <i class="fas fa-cloud-upload-alt"></i>
            Re-upload {{ $declined->count() }} Declined {{ Str::plural('Document', $declined->count()) }}
        </button>
    @elseif($ptr->isAccepted())
        <div class="ptr-muted-line">
            <i class="fas fa-check-circle text-success me-1"></i>
            Accepted {{ $ptr->accepted_at?->format('F d, Y') ?? '' }}
        </div>
    @endif
@endif
