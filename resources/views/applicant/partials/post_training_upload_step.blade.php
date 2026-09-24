{{--
    One attachment step of the submission wizard.

    Five of the six requirements are plain PDF uploads and differ only by name,
    so they share this body rather than being spelled out one by one.

    Expects: $docType (PtrDocumentType)
--}}

@php $inputId = 'ptr_' . $docType->inputName(); @endphp

<div class="ptr-file-drop-zone" data-extensions="{{ $docType->accepted_extensions }}">
    <div class="state-empty">
        <i class="fas fa-cloud-upload-alt ptr-file-icon"></i>
        <p class="ptr-file-label">Drag &amp; drop or <span class="ptr-browse-link">browse</span></p>
        <p class="ptr-file-selected">No file selected</p>
    </div>

    <div class="state-selected d-none">
        <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
        <p class="fw-bold text-success mb-1">File ready to upload</p>
        <p class="selected-file-info mb-2 text-dark font-monospace ptr-text-78"></p>
        <button type="button"
                class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3 ptr-pill-sm">
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
