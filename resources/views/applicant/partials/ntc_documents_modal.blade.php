{{--
    One NTC's documents, and the re-upload form for any the evaluator declined.

    Lifted out of the table cell it used to fill: a rejected document brings a
    remarks box and a drop zone with it, which no column can hold. The cell now
    carries a button and this opens beside it.

    One dialog per submission rather than one retargeted by script, because the
    re-upload form posts per NTC and its drop zones are wired per document —
    there is nothing here a shared shell could usefully carry.

    The markup is unchanged from the cell, so the drop zones and the submit
    guard in ntc.js keep binding to it exactly as before.

    Expects: $ntc (NtcReport)
--}}

@php
    // Declined means the evaluator rejected it AND the file was wiped, which
    // is what distinguishes "owes a re-upload" from "already re-uploaded".
    $rejectedDocsForBatch = $ntc->documents->filter(fn ($d) => $d->status === 'rejected' && !$d->file_path);
    $declinedCount        = $rejectedDocsForBatch->count();
@endphp

<div class="modal fade" id="ntcDocsModal-{{ $ntc->id }}" tabindex="-1"
     aria-labelledby="ntcDocsModalLabel-{{ $ntc->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered portal-scroll-modal">
        <div class="modal-content ntc-modal-surface">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold ntc-heading-navy" id="ntcDocsModalLabel-{{ $ntc->id }}">
                    <i class="fas fa-folder-open ntc-icon-gold me-2"></i>
                    NTC Documents
                    <span class="text-secondary fw-normal ms-1">{{ $ntc->reference_number }}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @if($declinedCount > 0)
                    <div class="alert alert-danger py-2 ntc-text-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        {{ $declinedCount }} {{ Str::plural('document', $declinedCount) }}
                        {{ $declinedCount === 1 ? 'was' : 'were' }} declined. Re-upload
                        {{ $declinedCount === 1 ? 'it' : 'them' }} below to send the submission back for review.
                    </div>
                @endif

            @if($rejectedDocsForBatch->isNotEmpty())
            <form method="POST"
                  action="{{ route('applicant.ntc.reupload_batch', $ntc->id) }}"
                  enctype="multipart/form-data" class="ntc-reupload-form" novalidate>
                @csrf
            @endif

            @foreach($ntc->documents as $doc)
            @php
                $isTrueRejected = ($doc->status === 'rejected' && !$doc->file_path);
                $isReturned = ($doc->status === 'returned');
                
                if ($isTrueRejected) {
                    $docBadge = ['badge-premium-danger', 'Rejected'];
                } elseif ($isReturned) {
                    $docBadge = ['badge-premium-warning', 'Awaiting Review'];
                } elseif ($doc->status === 'approved') {
                    $docBadge = ['badge-premium-success', 'Approved'];
                } else {
                    $docBadge = ['badge-premium-secondary', 'Under Review'];
                }
            @endphp
            <div class="ntc-doc-item">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1 flex-wrap">
                    @if($doc->file_path)
                    <a href="{{ route('applicant.ntc.document.view', $doc->id) }}"
                       target="_blank"
                       class="btn btn-xs ntc-doc-btn fw-bold px-2 py-1 d-inline-flex align-items-center gap-1 ntc-btn-xs">
                        <i class="far fa-file-pdf text-dark"></i>
                        {{ $doc->documentType->code ?? 'DOC' }}
                    </a>
                    @else
                    <span class="text-danger fw-semibold ntc-text-75">
                        <i class="fas fa-trash-alt me-1"></i>File removed ({{ $doc->documentType->code ?? 'DOC' }})
                    </span>
                    @endif
                    <span class="badge {{ $docBadge[0] }} ntc-badge-doc">{{ $docBadge[1] }}</span>
                </div>
                @if($isTrueRejected || $isReturned)
                    @if($doc->remarks)
                    <div class="mt-2 ntc-remarks-box">
                        <i class="fas fa-comment me-1"></i><strong>Remarks:</strong> {{ $doc->remarks }}
                    </div>
                    @endif
                    @if($isTrueRejected)
                    <div class="ntc-compact-drop-zone mt-2"
                         id="dropZoneReject-{{ $doc->id }}"
                         data-input="file-reject-{{ $doc->id }}">
                        <div class="file-info text-secondary">
                            <i class="fas fa-cloud-upload-alt text-muted fs-6"></i>
                            <span>Click or drag file to re-upload...</span>
                        </div>
                        <button type="button" class="btn-clear d-none no-trigger">Clear</button>
                        <input type="file"
                               id="file-reject-{{ $doc->id }}"
                               name="files[{{ $doc->id }}]"
                               class="d-none ntc-file-input"
                               accept=".pdf,.doc,.docx"
                               >
                    </div>
                    @elseif($isReturned)
                    <div class="mt-2 text-warning d-flex align-items-center gap-1 fw-semibold ntc-awaiting-note">
                        <i class="fas fa-hourglass-half spinner-border-sm"></i> Awaiting admin re-evaluation
                    </div>
                    @endif
                @endif
            </div>
            @endforeach

            @if($rejectedDocsForBatch->isNotEmpty())
                <button type="submit"
                        class="btn btn-danger btn-sm fw-bold w-100 mt-2 d-inline-flex align-items-center justify-content-center gap-1 ntc-btn-reupload">
                    <i class="fas fa-cloud-upload-alt"></i> Submit Re-uploaded Documents
                </button>
            </form>
            @endif
            </div>
        </div>
    </div>
</div>
