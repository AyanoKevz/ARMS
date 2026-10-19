{{--
    Directory of Participants — evaluator view.

    The Directory holds no file: it is encoded row by row in the portal. Like
    the instructor list, it is judged as a whole — one Accept / Decline for
    the entire Directory, with one set of remarks if declined. The verdict is
    copied onto every participant server-side, so a declined Directory opens
    every row for the FATPro to correct.

    Expects: $doc (PtrDocument), $postTrainingReport, $isAccepted (bool)
--}}

@php
    $participants = $postTrainingReport->participants;

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
    $evalStatus = in_array($docStatus, ['approved', 'rejected', 'returned']) ? $docStatus : 'pending';
@endphp

<div class="ntc-doc-row d-block" id="ntc-doc-row-{{ $doc->id }}">
    <input type="hidden" name="evaluations[{{ $doc->id }}][id]" value="{{ $doc->id }}">
    <input type="hidden" name="evaluations[{{ $doc->id }}][status]"
           id="ntc-status-input-{{ $doc->id }}"
           value="{{ $evalStatus }}"
           data-db-status="{{ $doc->status }}"
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
                    <th class="column-title">First Name</th>
                    <th class="column-title">Middle Name</th>
                    <th class="column-title">Last Name</th>
                    <th class="column-title">Suffix</th>
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
                    <th class="column-title">Catch Up</th>
                    <th class="column-title">Training Start Date</th>
                    <th class="column-title">Training End Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($participants as $participant)
                <tr id="ptr-participant-row-{{ $participant->id }}">
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
                    <td>{{ $participant->first_name }}</td>
                    <td>{{ $participant->middle_name ?: '—' }}</td>
                    <td>{{ $participant->last_name }}</td>
                    <td>{{ $participant->suffix ?: '—' }}</td>
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
                    <td>
                        @if($participant->is_catch_up)
                            <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:.72rem;">Catch Up</span>
                        @else
                            No
                        @endif
                    </td>
                    <td data-order="{{ $participant->catch_up_start_date?->format('Y-m-d') }}">
                        {{ $participant->catch_up_start_date?->format('M d, Y') ?? '—' }}
                        @if($participant->catchUpSource)
                            <div style="font-size:.72rem;color:#888;">{{ $participant->catchUpSource->reference_number }}</div>
                        @endif
                    </td>
                    <td>{{ $participant->catch_up_end_date?->format('M d, Y') ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!$isAccepted && $docStatus !== 'rejected')
    <div class="doc-eval-actions mt-2" id="ntc-eval-actions-{{ $doc->id }}">
        <button type="button"
                class="btn-eval btn-approve {{ $evalStatus === 'approved' ? 'active' : '' }}"
                id="btn-approve-{{ $doc->id }}"
                onclick="setNtcDocStatus({{ $doc->id }}, 'approved')">
            <i class="bi bi-check-circle-fill"></i> Accept
        </button>
        <button type="button"
                class="btn-eval btn-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}"
                id="btn-reject-{{ $doc->id }}"
                onclick="setNtcDocStatus({{ $doc->id }}, 'rejected')">
            <i class="bi bi-x-circle-fill"></i> Decline
        </button>
    </div>
    @elseif(!$isAccepted)
    <div class="doc-eval-actions mt-2" id="ntc-eval-actions-{{ $doc->id }}">
        <span class="badge bg-secondary" style="font-size:.75rem;">Awaiting correction from FATPro</span>
    </div>
    @endif

    @if(!$isAccepted)
    <div class="reject-panel w-100" id="ntc-reject-panel-{{ $doc->id }}"
         style="{{ $evalStatus === 'rejected' ? '' : 'display:none;' }}">
        <label class="reject-remarks-label">
            <i class="bi bi-pencil-square me-1"></i>Decline Remarks <span class="text-muted">(optional)</span>
        </label>
        <textarea class="reject-remarks-input w-100"
                  name="evaluations[{{ $doc->id }}][remarks]"
                  id="ntc-remarks-{{ $doc->id }}"
                  placeholder="Explain what the FATPro needs to correct in the Directory…"
                  rows="2"
                  {{ $docStatus === 'rejected' ? 'readonly' : '' }}>{{ $doc->remarks }}</textarea>
    </div>
    @endif
</div>
