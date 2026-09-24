{{--
    List of Instructors Who Conducted the Training — evaluator view.

    Requirement 2 holds no file. The FATPro picks the instructors on the NTC
    and carries them into the report, so what the evaluator judges is a list of
    people, shown here with the credentials that made them eligible.

    They are people rather than roster entries: a renewal re-files the roster,
    and a past training must keep naming the same humans.

    Unlike the Directory, the verdict is on the section as a whole rather than
    derived from per-row decisions, so the ordinary Accept / Decline controls
    apply. A declined roster is put right by re-choosing in the portal, not by
    re-uploading, which is why data-has-file goes false only once declined.

    Expects: $doc (PtrDocument), $postTrainingReport, $isAccepted (bool)
--}}

@php
    $instructors = $postTrainingReport->instructors;
    $declared    = $postTrainingReport->ntcReport?->instructors->pluck('id')->all() ?? [];

    // Credentials are read off the submission filed with the application this
    // training was held under, so the evaluator sees what was current for it.
    $applicationId = $postTrainingReport->accreditation?->application_id;

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
            <i class="bi bi-people text-primary me-1"></i>
            {{ $doc->documentType->name ?? 'List of Instructors' }}
            <div class="ntc-doc-meta">
                Selected in the portal
                &bull; {{ $instructors->count() }} {{ Str::plural('instructor', $instructors->count()) }}
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

    @if($instructors->isEmpty())
        <div class="alert alert-warning py-2 mb-0" style="font-size:.8rem;">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No instructors were recorded for this training.
        </div>
    @else
        <div class="table-responsive w-100">
            <table class="table table-sm align-middle mb-0" style="font-size:.8rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width:2.5rem;">#</th>
                        <th>Instructor</th>
                        <th>Credentials</th>
                        <th style="width:9rem;">On the NTC</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($instructors as $index => $instructor)
                    <tr>
                        <td class="text-muted">{{ $index + 1 }}</td>
                        <td class="fw-semibold">{{ $instructor->listingName() }}</td>
                        <td>
                            @forelse($instructor->credentialLabelsFor($applicationId) as $credential)
                                <span class="badge bg-light text-dark border me-1" style="font-size:.7rem;">
                                    {{ $credential }}
                                </span>
                            @empty
                                <span class="text-muted">None on file</span>
                            @endforelse
                        </td>
                        <td>
                            {{-- Anyone added after the fact is worth flagging: the
                                 NTC is what was cleared in advance. --}}
                            @if(in_array($instructor->id, $declared, true))
                                <span class="badge bg-success-subtle text-success-emphasis" style="font-size:.7rem;">
                                    <i class="bi bi-check2 me-1"></i>Declared
                                </span>
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:.7rem;">
                                    <i class="bi bi-plus-circle me-1"></i>Added later
                                </span>
                            @endif
                        </td>
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
                  placeholder="Explain why this list was declined…"
                  rows="2"
                  {{ $docStatus === 'rejected' ? 'readonly' : '' }}>{{ $doc->remarks }}</textarea>
    </div>
    @endif
</div>
