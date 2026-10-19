{{--
    Pre- and Post-Test Summary Evaluation — evaluator view.

    Requirement 5 holds no file: the FATPro keys a pre-test and a post-test
    score in against each participant of the Directory, so the scores are read
    off the participant rows. Judged as a whole, like the instructor list and
    the Directory — one Accept / Decline with one set of remarks.

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
            <i class="bi bi-clipboard-data text-dark me-1"></i>
            {{ $doc->documentType->name ?? 'Pre- and Post-Test Summary Evaluation' }}
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
        <div class="table-responsive w-100">
            <table class="table table-sm table-bordered align-middle mb-0" style="font-size:.8rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width:3rem;">#</th>
                        <th>Full Name</th>
                        <th style="width:9rem;">Pre-Test</th>
                        <th style="width:9rem;">Post-Test</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($participants as $participant)
                    <tr>
                        <td class="text-muted">{{ $participant->row_no }}</td>
                        <td class="fw-semibold">{{ $participant->scoreSheetName() }}</td>
                        <td>{{ $participant->pre_test_score ?: '—' }}</td>
                        <td>{{ $participant->post_test_score ?: '—' }}</td>
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
                  placeholder="Explain which scores the FATPro needs to correct…"
                  rows="2"
                  {{ $docStatus === 'rejected' ? 'readonly' : '' }}>{{ $doc->remarks }}</textarea>
    </div>
    @endif
</div>
