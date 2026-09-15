{{--
    Post Training Report — the parts that cannot live inside a table cell.

    The per-training status and actions are rendered in the Training Submissions
    table itself (see post_training_cell). What remains here is the submission
    modal, one re-upload modal per report with declined documents, and the
    deadline rules.

    Expects: $ntcReports, $ptrPending, $ptrSubmitted, $ptrDocumentTypes
--}}

@php
    $ptrOverdueCount = $ptrPending->filter(fn($n) => $n->isPostTrainingReportOverdue())->count();
    $ptrDeclined     = $ptrSubmitted->filter(fn($r) => $r->declinedDocuments()->isNotEmpty());
@endphp

{{-- ══ Submission modal (one, retargeted per training) ══ --}}
<div class="modal fade" id="ptrSubmitModal" tabindex="-1" aria-labelledby="ptrSubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg portal-scroll-modal">
        <div class="modal-content ptr-modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="ptrSubmitModalLabel" style="color: #2A3F54;">
                    <i class="fas fa-flag-checkered text-warning me-2"></i> Submit Post Training Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" id="ptrSubmitForm" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="modal-body">

                    <div id="ptrModalOverdue" class="ptr-notice ptr-notice-danger" hidden>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>This report is overdue.</strong> The submission deadline for this training has already passed.
                    </div>

                    <div class="ptr-notice ptr-notice-info">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="text-uppercase fw-bold" style="font-size:.7rem; letter-spacing:.4px;">NTC Reference</div>
                                <div class="fw-semibold" id="ptrModalNtcRef">—</div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="text-uppercase fw-bold" style="font-size:.7rem; letter-spacing:.4px;">Training Type</div>
                                <div class="fw-semibold" id="ptrModalTrainingType">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-uppercase fw-bold" style="font-size:.7rem; letter-spacing:.4px;">Training Period</div>
                                <div class="fw-semibold" id="ptrModalTrainingPeriod">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-uppercase fw-bold" style="font-size:.7rem; letter-spacing:.4px;">Submission Deadline</div>
                                <div class="fw-semibold" id="ptrModalDeadline">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="ptr-notice ptr-notice-gold">
                        <div class="mb-1">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Deadline (OSHC MC 04 Series 2025):</strong>
                        </div>
                        <p class="mb-1">Counted in working days from the last training day —
                            Emergency First Aid <strong>1 day</strong>,
                            Occupational First Aid <strong>2 days</strong>,
                            Standard First Aid <strong>4 days</strong>.
                        </p>
                        <p class="mb-0">All six documents below are <strong>required</strong>, each up to <strong>25 MB</strong>.</p>
                    </div>

                    @foreach($ptrDocumentTypes as $index => $docType)
                    @php $inputId = 'ptr_' . $docType->inputName(); @endphp
                    <div class="ptr-upload-group">
                        <div class="d-flex align-items-start">
                            <span class="ptr-step-number">{{ $index + 1 }}</span>
                            <div class="flex-grow-1">
                                <div class="ptr-upload-group-title">
                                    {{ $docType->name }} <span class="text-danger">*</span>
                                </div>
                                <div class="ptr-upload-group-hint">
                                    Accepted format: <code>{{ $docType->formatLabel() }}</code> &mdash; Max 25 MB
                                </div>

                                <div class="ptr-file-drop-zone" data-extensions="{{ $docType->accepted_extensions }}">
                                    <div class="state-empty">
                                        <i class="fas fa-cloud-upload-alt ptr-file-icon"></i>
                                        <p class="ptr-file-label">Drag &amp; drop or <span class="ptr-browse-link">browse</span></p>
                                        <p class="ptr-file-selected">No file selected</p>
                                    </div>
                                    <div class="state-selected d-none">
                                        <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                        <p class="fw-bold text-success mb-1">File ready to upload</p>
                                        <p class="selected-file-info mb-2 text-dark font-monospace" style="font-size: 0.78rem;"></p>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3"
                                                style="font-size: 0.72rem; border-radius: 20px;">
                                            <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                        </button>
                                    </div>
                                    <input type="file"
                                           id="{{ $inputId }}"
                                           name="{{ $docType->inputName() }}"
                                           class="d-none ptr-file-input"
                                           accept="{{ $docType->acceptAttribute() }}">
                                </div>

                                <div class="ptr-field-error d-none" id="error_{{ $inputId }}">
                                    Please upload the {{ $docType->name }}.
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn ptr-submit-btn px-4">
                        <i class="fas fa-paper-plane me-1"></i> Submit Post Training Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══ Re-upload modals — one per report that has declined documents ══ --}}
@foreach($ptrDeclined as $report)
<div class="modal fade" id="ptrReuploadModal-{{ $report->id }}" tabindex="-1"
     aria-labelledby="ptrReuploadModalLabel-{{ $report->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg portal-scroll-modal">
        <div class="modal-content ptr-modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="ptrReuploadModalLabel-{{ $report->id }}" style="color: #2A3F54;">
                    <i class="fas fa-cloud-upload-alt text-danger me-2"></i>
                    Re-upload Declined Documents &mdash; {{ $report->reference_number }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST"
                  action="{{ route('applicant.post_training.reupload_batch', $report->id) }}"
                  enctype="multipart/form-data"
                  class="ptr-reupload-form"
                  novalidate>
                @csrf
                <div class="modal-body">
                    <div class="ptr-notice ptr-notice-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        The evaluator declined the {{ Str::plural('document', $report->declinedDocuments()->count()) }} below.
                        Correct {{ $report->declinedDocuments()->count() === 1 ? 'it' : 'them' }} and upload again —
                        your accepted documents are unaffected.
                    </div>

                    @foreach($report->declinedDocuments()->sortBy(fn($d) => $d->documentType->sort_order ?? 0) as $doc)
                    <div class="ptr-upload-group">
                        <div class="ptr-upload-group-title">{{ $doc->documentType->name ?? 'Document' }}</div>
                        <div class="ptr-upload-group-hint">
                            Accepted format: <code>{{ $doc->documentType->formatLabel() }}</code> &mdash; Max 25 MB
                        </div>

                        @if($doc->remarks)
                        <div class="ptr-doc-remarks mb-2 mt-0">
                            <i class="fas fa-comment me-1"></i><strong>Evaluator remarks:</strong> {{ $doc->remarks }}
                        </div>
                        @endif

                        <div class="ptr-compact-drop-zone"
                             data-extensions="{{ $doc->documentType->accepted_extensions ?? 'pdf' }}">
                            <div class="file-info text-secondary">
                                <i class="fas fa-cloud-upload-alt text-muted"></i>
                                <span>Click or drag to re-upload ({{ $doc->documentType->formatLabel() }})…</span>
                            </div>
                            <button type="button" class="btn-clear d-none no-trigger">Clear</button>
                            <input type="file"
                                   id="ptr-file-reject-{{ $doc->id }}"
                                   name="files[{{ $doc->id }}]"
                                   class="d-none ptr-file-input"
                                   accept="{{ $doc->documentType->acceptAttribute() }}">
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4" style="border-radius:6px;">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Submit Re-uploaded Documents
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
