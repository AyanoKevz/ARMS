{{--
    Directory of Participants — evaluator view.

    The Directory holds no file: it is encoded row by row, so the evaluator
    judges participants individually. The section's own verdict is derived
    server-side from those rows (one declined participant declines the
    section), which is why there are no Accept/Decline buttons for the
    document itself — only the single set of remarks that covers the whole
    Directory.

    Expects: $doc (PtrDocument), $postTrainingReport, $isAccepted (bool)
--}}

@php
    $participants = $postTrainingReport->participants;
    $approvedRows = $participants->where('status', 'approved')->count();
    $rejectedRows = $participants->where('status', 'rejected')->count();

    $docStatus  = $doc->status ?? 'pending';
    $badgeClass = match($docStatus) {
        'approved' => 'ntc-badge-approved',
        'rejected' => 'ntc-badge-rejected',
        'returned' => 'ntc-badge-returned',
        default    => 'ntc-badge-pending',
    };
    $badgeLabel = match($docStatus) {
        'approved' => 'Accepted',
        'rejected' => 'Declined — Awaiting Correction',
        'returned' => 'Corrected — Awaiting Review',
        default    => 'Pending',
    };
@endphp

<div class="ntc-doc-row d-block" id="ntc-doc-row-{{ $doc->id }}">
    {{-- The section's status is derived from the rows, so it is submitted as a
         read-only passenger rather than something the evaluator toggles. --}}
    <input type="hidden" name="evaluations[{{ $doc->id }}][id]" value="{{ $doc->id }}">
    <input type="hidden" name="evaluations[{{ $doc->id }}][status]"
           id="ntc-status-input-{{ $doc->id }}"
           value="{{ in_array($docStatus, ['approved','rejected','returned']) ? $docStatus : 'pending' }}"
           data-db-status="{{ $doc->status }}"
           data-ptr-directory="1"
           data-doc-id="{{ $doc->id }}"
           data-evaluate-base="{{ url('admin/hcd/reports/post-training/participants') }}"
           data-has-file="{{ $docStatus === 'rejected' ? 'false' : 'true' }}">

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 w-100 mb-2">
        <div class="ntc-doc-name">
            <i class="bi bi-table text-dark me-1"></i>
            {{ $doc->documentType->name ?? 'Directory of Participants' }}
            <div class="ntc-doc-meta">
                Encoded in the portal
                &bull; {{ $participants->count() }} {{ Str::plural('participant', $participants->count()) }}
                @if($doc->evaluatedByUser)
                    &bull; Evaluated by {{ $doc->evaluatedByUser->name }} on {{ $doc->evaluated_at?->format('M d, Y') }}
                @endif
            </div>
            @if($docStatus === 'rejected' && $doc->remarks)
            <div class="ntc-doc-remark-box">
                <i class="bi bi-chat-left-text me-1"></i>
                <strong>Remarks:</strong> {{ $doc->remarks }}
            </div>
            @endif
        </div>

        <span class="badge {{ $badgeClass }} px-2 py-1"
              style="font-size:.75rem;border-radius:20px;white-space:nowrap;"
              id="ntc-badge-{{ $doc->id }}">{{ $badgeLabel }}</span>
    </div>

    @if($participants->isEmpty())
        <div class="alert alert-secondary mb-0">No participants were encoded for this report.</div>
    @else

    <div class="ptr-dir-summary">
        <span><strong id="ptrDirApprovedCount">{{ $approvedRows }}</strong> accepted</span>
        <span><strong id="ptrDirRejectedCount">{{ $rejectedRows }}</strong> declined</span>
        <span><strong id="ptrDirPendingCount">{{ $participants->count() - $approvedRows - $rejectedRows }}</strong> pending</span>
        @if(!$isAccepted)
        <span class="ms-auto text-muted" style="font-size:.74rem;">
            <i class="bi bi-info-circle me-1"></i>
            One declined participant declines the whole Directory.
        </span>
        @endif
    </div>

    {{-- Verdict inputs live outside the table: DataTables detaches rows that are
         paged or filtered out, and the form and the section tally must still
         see every participant. --}}
    @if(!$isAccepted)
    <div class="d-none">
        @foreach($participants as $participant)
        <input type="hidden"
               name="participants[{{ $participant->id }}][id]"
               value="{{ $participant->id }}">
        <input type="hidden"
               name="participants[{{ $participant->id }}][status]"
               id="ptr-participant-status-{{ $participant->id }}"
               value="{{ $participant->status ?? 'pending' }}">
        @endforeach
    </div>
    @endif

    <div class="table-responsive">
        <table id="ptr_directory_table"
               class="table table-striped table-bordered jambo_table table-compact ptr-dir-review-table"
               data-order="[]"
               style="width:100%">
            <thead>
                <tr class="headings">
                    <th class="column-title">#</th>
                    <th class="column-title no-sort no-export">ID Picture</th>
                    <th class="column-title">Certificate Number</th>
                    <th class="column-title">Participant</th>
                    <th class="column-title">Sex</th>
                    <th class="column-title">Age</th>
                    <th class="column-title">Company</th>
                    <th class="column-title">Position</th>
                    <th class="column-title">Region</th>
                    <th class="column-title">City / Municipality</th>
                    <th class="column-title">Industry</th>
                    <th class="column-title">Total Workers</th>
                    <th class="column-title">Company Email</th>
                    <th class="column-title">Personal Email</th>
                    <th class="column-title">Mobile No.</th>
                    <th class="column-title">Company Landline</th>
                    <th class="column-title">Mode of Training</th>
                    <th class="column-title">Batch No.</th>
                    @if(!$isAccepted)<th class="column-title no-sort no-export">Verdict</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($participants as $participant)
                @php $pStatus = $participant->status ?? 'pending'; @endphp
                <tr id="ptr-participant-row-{{ $participant->id }}"
                    class="{{ $pStatus === 'approved' ? 'is-approved' : ($pStatus === 'rejected' ? 'is-rejected' : '') }}">
                    <td>{{ $participant->row_no }}</td>
                    <td>
                        @if($participant->hasIdPicture())
                            <a href="{{ route('admin.hcd.reports.post_training.participant.photo', $participant->id) }}"
                               data-file-modal
                               data-file-title="ID Picture — {{ $participant->fullName() }}">
                                <img src="{{ route('admin.hcd.reports.post_training.participant.photo', $participant->id) }}"
                                     alt="ID picture of {{ $participant->fullName() }}"
                                     class="ptr-dir-review-photo"
                                     loading="lazy">
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $participant->certificate_number }}</td>
                    <td>{{ $participant->fullName() }}</td>
                    <td>{{ $participant->sex }}</td>
                    <td>{{ $participant->age }}</td>
                    <td>{{ $participant->company }}</td>
                    <td>{{ $participant->position }}</td>
                    <td>{{ $participant->company_region }}</td>
                    <td>{{ $participant->company_city }}</td>
                    <td>{{ $participant->industry }}</td>
                    <td>{{ $participant->total_workers !== null ? number_format($participant->total_workers) : '—' }}</td>
                    <td>{{ $participant->company_email ?: '—' }}</td>
                    <td>{{ $participant->personal_email ?: '—' }}</td>
                    <td>{{ $participant->mobile_no }}</td>
                    <td>{{ $participant->company_landline ?: '—' }}</td>
                    <td>{{ $participant->mode_of_training }}</td>
                    <td>{{ $participant->batch_no ?: '—' }}</td>
                    @if(!$isAccepted)
                    <td>
                        <span class="ptr-dir-verdict" data-participant="{{ $participant->id }}">
                            <button type="button"
                                    class="ptr-dir-verdict-approve {{ $pStatus === 'approved' ? 'is-active-approve' : '' }}"
                                    title="Accept this participant">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button"
                                    class="ptr-dir-verdict-reject {{ $pStatus === 'rejected' ? 'is-active-reject' : '' }}"
                                    title="Decline this participant">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </span>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(!$isAccepted)
    {{-- One set of remarks for the entire Directory, by design: the FATPro gets
         a single explanation rather than one note per participant. --}}
    <div class="reject-panel w-100 mt-2" id="ptr-directory-remarks-panel"
         style="{{ $rejectedRows > 0 ? '' : 'display:none;' }}">
        <label class="reject-remarks-label" for="ptr-directory-remarks">
            <i class="bi bi-pencil-square me-1"></i>
            Decline Remarks for the Directory <span class="text-muted">(applies to all declined participants)</span>
        </label>
        <textarea class="reject-remarks-input w-100"
                  name="directory_remarks"
                  id="ptr-directory-remarks"
                  placeholder="Explain what the FATPro needs to correct in the declined participant rows…"
                  rows="2">{{ $doc->remarks }}</textarea>
    </div>
    @endif

    @endif
</div>
