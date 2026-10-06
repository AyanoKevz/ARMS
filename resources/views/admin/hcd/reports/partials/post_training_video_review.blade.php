{{--
    Link to the Training Video — evaluator view.

    Requirement 7 holds no file: a full session is far too large to upload, so
    the FATPro hosts it and files the address. Like the Directory and the
    roster it still gets a ptr_documents row, which is what carries the verdict
    and the remarks; the link itself is a column on the report.

    A declined link is put right by entering a new one, not by re-uploading,
    which is why data-has-file goes false only once declined.

    Expects: $doc (PtrDocument), $postTrainingReport, $isAccepted (bool)
--}}

@php
    $link = $postTrainingReport->training_video_url;

    $docStatus  = $doc->status ?? 'pending';
    $badgeClass = match($docStatus) {
        'approved' => 'ntc-badge-approved',
        'rejected' => 'ntc-badge-rejected',
        'returned' => 'ntc-badge-returned',
        default    => 'ntc-badge-pending',
    };
    $badgeLabel = match($docStatus) {
        'approved' => 'Accepted',
        'rejected' => 'Declined — Awaiting New Link',
        'returned' => 'Updated — Awaiting Review',
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
            <i class="bi bi-play-btn text-dark me-1"></i>
            {{ $doc->documentType->name ?? 'Link to the Training Video' }}
            <div class="ntc-doc-meta">
                Filed as a link
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

    @if($link)
        {{-- FATPro-supplied, so it opens in a new tab and carries no referrer. --}}
        <a href="{{ $link }}"
           target="_blank"
           rel="noopener noreferrer"
           class="btn btn-outline-primary btn-xs px-2 py-1"
           style="font-size:.78rem;word-break:break-all;">
            <i class="bi bi-box-arrow-up-right me-1"></i>{{ $link }}
        </a>
    @else
        <div class="alert alert-warning py-2 mb-0" style="font-size:.8rem;">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No link was recorded for this training.
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
        <span class="badge bg-secondary" style="font-size:.75rem;">Awaiting a new link from the FATPro</span>
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
                  placeholder="Explain why this link was declined — unreachable, wrong training, private…"
                  rows="2"
                  {{ $docStatus === 'rejected' ? 'readonly' : '' }}>{{ $doc->remarks }}</textarea>
    </div>
    @endif
</div>
