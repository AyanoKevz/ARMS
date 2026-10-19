{{--
    Requirement 5: the Pre- and Post-Test Summary Evaluation.

    Encoded rather than uploaded. Its rows are not added here: they mirror the
    Directory of Participants (requirement 1). A participant appears once every
    required (*) column of their Directory row is filled in, named by first,
    middle and last name; only the two scores are typed on this step.

    The scores are kept on the Directory row they belong to and ride in the
    same `participants` JSON, so a draft, a reorder or a removed participant
    can never leave a score pointing at the wrong person.

    post-training.js (window.ptrScores) builds the rows each time the step is
    opened. The step frame supplies the heading; this partial is only the body.
--}}

<div class="ptr-scores-step">
    <div class="ptr-dir-scroll">
        <table class="ptr-dir-table ptr-scores-table" id="ptrScoresTable">
            <thead>
                <tr>
                    <th class="ptr-dir-col-no">#</th>
                    <th class="ptr-scores-w-name">Full Name</th>
                    <th class="ptr-scores-w-score">Pre-Test <span class="ptr-dir-req" aria-hidden="true">*</span></th>
                    <th class="ptr-scores-w-score">Post-Test <span class="ptr-dir-req" aria-hidden="true">*</span></th>
                </tr>
            </thead>
            <tbody id="ptrScoresBody"></tbody>
        </table>
    </div>

    <div class="ptr-dir-empty" id="ptrScoresEmpty">
        <i class="fas fa-info-circle me-1"></i>
        No participants yet. A participant is listed here once every required
        (<span class="ptr-dir-req">*</span>) column of their row in the
        <strong>Directory of Participants</strong> is filled in.
    </div>

    <p class="ptr-video-hint d-none" id="ptrScoresPending">
        <i class="fas fa-exclamation-circle me-1"></i>
        <span id="ptrScoresPendingCount">0</span> more
        <span id="ptrScoresPendingNoun">participants</span> will appear once their Directory
        rows are complete.
    </p>

    <div class="ptr-field-error d-none" id="error_ptr_scores">
        Enter both the pre-test and the post-test score for every participant.
    </div>
</div>
