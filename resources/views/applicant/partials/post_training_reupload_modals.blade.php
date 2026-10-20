{{--
    One corrections modal per report the evaluator sent back.

    A report is declined in several ways and the FATPro should not have to find
    several places to fix them: a wiped attachment is re-uploaded, the rejected
    participants are edited in place, a refused instructor list is re-chosen and
    declined test scores are re-entered. Whichever are outstanding appear here
    together, and they post as one submission.

    Sections already accepted are deliberately absent — there is nothing to do
    to them, and showing them would invite re-filing work that was already
    approved.

    Expects: $ptrDeclined (Collection<PostTrainingReport>), $instructorRoster
--}}

@foreach($ptrDeclined as $report)
@php
    $declinedDocs     = $report->declinedDocuments()->sortBy(fn ($d) => $d->documentType->sort_order ?? 0);
    $needsDirectory   = $report->hasDirectoryCorrections();
    $needsInstructors = $report->hasInstructorCorrections();
    $needsScores      = $report->hasScoreCorrections();
    $needsVideo       = $report->hasVideoCorrections();
    $declaredIds      = $report->ntcReport?->instructors->pluck('id')->all() ?? [];
    $chosenIds        = $report->instructors->pluck('id')->all();
@endphp

<div class="modal fade" id="ptrReuploadModal-{{ $report->id }}" tabindex="-1"
     aria-labelledby="ptrReuploadModalLabel-{{ $report->id }}" aria-hidden="true">
    <div class="modal-dialog modal-xl portal-scroll-modal">
        <div class="modal-content ptr-modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold ptr-heading-navy" id="ptrReuploadModalLabel-{{ $report->id }}">
                    Correct Declined Items &mdash; {{ $report->reference_number }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST"
                  action="{{ route('applicant.post_training.corrections', $report->id) }}"
                  enctype="multipart/form-data"
                  class="ptr-corrections-form"
                  data-report-id="{{ $report->id }}"
                  data-photo-url="{{ route('applicant.post_training.participant_photo') }}"
                  novalidate>
                @csrf

                <div class="modal-body">
                    <div class="ptr-notice ptr-notice-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Put right the items below and submit them together.
                        Everything the evaluator already accepted is unaffected.
                    </div>

                    {{-- ── Declined attachments ─────────────────────────── --}}
                    @foreach($declinedDocs as $doc)
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

                    {{-- ── Declined participants ────────────────────────── --}}
                    @if($needsDirectory)
                    <div class="ptr-upload-group">
                        <div class="ptr-upload-group-title">Directory of Participants</div>
                        <div class="ptr-upload-group-hint">
                            The Directory was declined. Review the participants below and correct
                            whatever the evaluator's remarks point out.
                        </div>

                        @if($report->directoryDocument()?->remarks)
                        <div class="ptr-doc-remarks mb-2 mt-0">
                            <i class="fas fa-comment me-1"></i><strong>Evaluator remarks:</strong>
                            {{ $report->directoryDocument()->remarks }}
                        </div>
                        @endif

                        @include('applicant.partials.post_training_correction_participants', ['report' => $report])

                        {{-- Serialised by post-training-corrections.js on submit. --}}
                        <input type="hidden" name="participants" data-correction-payload value="[]">
                    </div>
                    @endif

                    {{-- ── Declined instructor roster ───────────────────── --}}
                    @if($needsInstructors)
                    <div class="ptr-upload-group">
                        <div class="ptr-upload-group-title">List of Instructors Who Conducted the Training</div>
                        <div class="ptr-upload-group-hint">
                            Amend who actually conducted the training, then submit.
                        </div>

                        @if($report->instructorsDocument()?->remarks)
                        <div class="ptr-doc-remarks mb-2 mt-0">
                            <i class="fas fa-comment me-1"></i><strong>Evaluator remarks:</strong>
                            {{ $report->instructorsDocument()->remarks }}
                        </div>
                        @endif

                        <div class="ptr-instructor-list" data-correction-instructors role="group">
                            @foreach($instructorRoster as $instructor)
                                @php
                                    $personId = $instructor->instructor_person_id;
                                    // Anyone the NTC declared stays selectable whatever has
                                    // since happened to their credentials — they taught it.
                                    $wasDeclared = $personId && in_array($personId, $declaredIds, true);
                                    $reason      = $wasDeclared ? null : $instructor->ineligibility_reason;
                                    $blocked     = !$personId || $reason;
                                @endphp
                                <label class="ptr-instructor-option{{ $blocked ? ' is-ineligible' : '' }}">
                                    <input type="checkbox"
                                           class="form-check-input ptr-instructor-check"
                                           name="instructor_ids[]"
                                           value="{{ $personId }}"
                                           {{ $personId && in_array($personId, $chosenIds, true) && !$blocked ? 'checked' : '' }}
                                           {{ $blocked ? 'disabled' : '' }}>
                                    <span class="ptr-instructor-body">
                                        <span class="ptr-instructor-name">{{ $instructor->listingName() }}</span>
                                        <span class="ptr-instructor-meta{{ $reason ? '' : ' d-none' }}">{{ $reason }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- ── Declined test scores ─────────────────────────── --}}
                    @if($needsScores)
                    <div class="ptr-upload-group">
                        <div class="ptr-upload-group-title">Pre- and Post-Test Summary Evaluation</div>
                        <div class="ptr-upload-group-hint">
                            Correct the scores below. Every participant needs both a pre-test and a
                            post-test score, written as the score over the total (e.g. <strong>7/10</strong>);
                            the ones you entered before are filled in.
                        </div>

                        @if($report->scoresDocument()?->remarks)
                        <div class="ptr-doc-remarks mb-2 mt-0">
                            <i class="fas fa-comment me-1"></i><strong>Evaluator remarks:</strong>
                            {{ $report->scoresDocument()->remarks }}
                        </div>
                        @endif

                        <div class="ptr-dir-scroll">
                            <table class="ptr-dir-table ptr-scores-table" data-correction-scores>
                                <thead>
                                    <tr>
                                        <th class="ptr-dir-col-no">#</th>
                                        <th class="ptr-scores-w-name">Full Name</th>
                                        <th class="ptr-scores-w-score">Pre-Test <span class="ptr-dir-req" aria-hidden="true">*</span></th>
                                        <th class="ptr-scores-w-score">Post-Test <span class="ptr-dir-req" aria-hidden="true">*</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report->participants->sortBy('row_no') as $participant)
                                    <tr data-score-participant="{{ $participant->id }}">
                                        <td class="ptr-dir-rowno">{{ $participant->row_no }}</td>
                                        <td class="ptr-scores-name">{{ $participant->scoreSheetName() }}</td>
                                        <td>
                                            <input type="text" class="ptr-dir-input" maxlength="9" placeholder="e.g. 7/10"
                                                   data-score="pre_test_score"
                                                   value="{{ $participant->pre_test_score }}"
                                                   aria-label="Pre-test score for {{ $participant->scoreSheetName() }}">
                                        </td>
                                        <td>
                                            <input type="text" class="ptr-dir-input" maxlength="9" placeholder="e.g. 7/10"
                                                   data-score="post_test_score"
                                                   value="{{ $participant->post_test_score }}"
                                                   aria-label="Post-test score for {{ $participant->scoreSheetName() }}">
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Serialised by post-training-corrections.js on submit:
                             one JSON field rather than two inputs per participant. --}}
                        <input type="hidden" name="test_scores" data-correction-scores-payload value="{}">
                    </div>
                    @endif

                    {{-- ── Declined video link ──────────────────────────── --}}
                    @if($needsVideo)
                    <div class="ptr-upload-group">
                        <div class="ptr-upload-group-title">Link to the Training Video</div>
                        <div class="ptr-upload-group-hint">
                            Enter a link DOLE-OSHC can open. The previous one is filled in below.
                        </div>

                        @if($report->videoDocument()?->remarks)
                        <div class="ptr-doc-remarks mb-2 mt-0">
                            <i class="fas fa-comment me-1"></i><strong>Evaluator remarks:</strong>
                            {{ $report->videoDocument()->remarks }}
                        </div>
                        @endif

                        <input type="url"
                               name="training_video_url"
                               class="form-control"
                               value="{{ $report->training_video_url }}"
                               placeholder="https://drive.google.com/…"
                               maxlength="500"
                               data-correction-video
                               required>
                    </div>
                    @endif

                    <div class="ptr-field-error d-none" data-correction-error></div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4 ptr-btn-round">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Submit Corrections
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
