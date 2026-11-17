{{--
    One PDF upload of the portal renewal checklist (renewal_documents).
    Expects $code, $title, $note, $required. Optional $disabled (items 6 – 7
    until a change of employer is answered Yes).
--}}
<div class="col-md-6 mb-2 prac-field" data-label="{{ $title }}" data-prac-document>
    <label class="form-label fw-bold mb-0 prac-label" for="doc_{{ $code }}">
        {{ $title }} @if($required) <span class="text-danger">*</span> @endif
    </label>
    <div class="form-text mt-0 mb-2 prac-help">{{ $note }}</div>
    <div class="file-upload-wrapper mt-1">
        <input class="real-file-input visually-hidden" type="file"
               name="documents[{{ $code }}]" id="doc_{{ $code }}"
               accept=".pdf" data-allowed-ext="pdf"
               @if($required) required @endif @disabled($disabled ?? false)>
        <div class="d-flex align-items-center gap-2">
            <label for="doc_{{ $code }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                <i class="bi bi-cloud-upload me-1"></i> Choose File
            </label>
            <span class="file-name-text text-muted text-truncate prac-file-name">No file chosen</span>
        </div>
        <div class="invalid-feedback file-invalid-feedback prac-file-feedback">Please select a valid PDF file.</div>
    </div>
</div>
