{{--
    Requirement 7: the link to the training video.

    A URL rather than an upload. A full session runs to gigabytes, which no
    form post is going to carry, so the FATPro hosts it and gives us the
    address instead.

    Like the Directory and the instructor roster it gets a ptr_documents row
    with no file — the section the evaluator acts on, and where its remarks
    live — while the link itself is a column on the report.
--}}

<div class="ptr-video-step">
    <input type="url"
           id="ptr_training_video_url"
           name="training_video_url"
           class="form-control"
           placeholder="https://drive.google.com/…"
           maxlength="500"
           required>

    <p class="ptr-video-hint">
        <i class="fas fa-info-circle me-1"></i>
        Paste the link to where you uploaded the recording — Google Drive,
        OneDrive, YouTube or similar. Make sure DOLE-OSHC can open it.
    </p>

    <div class="ptr-field-error d-none" id="error_ptr_training_video_url">
        Please provide a valid link to the training video.
    </div>
</div>
