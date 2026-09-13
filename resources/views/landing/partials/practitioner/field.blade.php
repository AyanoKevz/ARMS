{{--
    One practitioner form control.

    Every practitioner input goes through here so the label, the required
    asterisk, the invalid-feedback block and the data-label the review step
    reads are written once instead of ~90 times.

    Expects:
      $f        array  — name, label, type, col, required, placeholder, help,
                         options, rows, min, max, step, attrs, accept, allowedExt
      $group    string — name prefix, e.g. "practitioner[education][__IDX__]"
      $idPrefix string — id prefix; may contain __IDX__ for cloned cards
--}}
@php
    $type        = $f['type'] ?? 'text';
    $col         = $f['col'] ?? 'col-md-6';
    $required    = $f['required'] ?? false;
    $placeholder = $f['placeholder'] ?? '';
    $help        = $f['help'] ?? null;
    $attrs       = $f['attrs'] ?? '';
    $name        = ($group ?? '') !== '' ? $group . '[' . $f['name'] . ']' : $f['name'];
    $id          = ($idPrefix ?? 'prac') . '_' . $f['name'];
    // Blank optional fields are recorded as "N/A" on submission, so the review
    // step and the evaluator never see an empty cell.
    $label       = $f['label'];
@endphp

<div class="{{ $col }} prac-field" data-label="{{ $label }}">
    @if($type !== 'yesno')
        <label class="form-label fw-semibold" for="{{ $id }}" style="font-size:.88rem;">
            {{ $label }}@if($required) <span class="text-danger">*</span>@endif
        </label>
    @endif

    @if($help)
        <div class="form-text mt-0 mb-2" style="font-size:.75rem;line-height:1.2;color:#6c757d;">{{ $help }}</div>
    @endif

    @switch($type)

        @case('select')
            <select class="form-select form-select-sm" id="{{ $id }}" name="{{ $name }}"
                    @if($required) required @endif {!! $attrs !!}>
                <option value="" selected disabled>{{ $placeholder ?: '— Select —' }}</option>
                @foreach($f['options'] as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('textarea')
            <textarea class="form-control form-control-sm" id="{{ $id }}" name="{{ $name }}"
                      rows="{{ $f['rows'] ?? 2 }}" placeholder="{{ $placeholder }}"
                      @if($required) required @endif {!! $attrs !!}></textarea>
            @break

        @case('file')
            {{-- Mirrors the FATPro upload control so both forms behave identically. --}}
            <div class="file-upload-wrapper mt-1">
                <input class="real-file-input visually-hidden" type="file" id="{{ $id }}" name="{{ $name }}"
                       accept="{{ $f['accept'] ?? '.pdf' }}"
                       data-allowed-ext="{{ $f['allowedExt'] ?? 'pdf' }}"
                       @if($required) required @endif {!! $attrs !!}>
                <div class="d-flex align-items-center gap-2">
                    <label for="{{ $id }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                        <i class="bi bi-cloud-upload me-1"></i> Choose File
                    </label>
                    <span class="file-name-text text-muted text-truncate" style="font-size:.8rem;max-width:200px;">No file chosen</span>
                </div>
                <div class="invalid-feedback file-invalid-feedback" style="font-size:.8rem;margin-top:4px;">
                    Please select a valid file for {{ $label }}.
                </div>
            </div>
            @break

        @case('yesno')
            <div class="prac-yesno d-flex align-items-start justify-content-between flex-wrap gap-2 p-2 rounded-2">
                <span class="pe-2" style="font-size:.88rem;line-height:1.45;">
                    {{ $label }}@if($required) <span class="text-danger">*</span>@endif
                </span>
                <div class="d-flex gap-3 flex-shrink-0">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="radio" value="1"
                               id="{{ $id }}_yes" name="{{ $name }}" @if($required) required @endif>
                        <label class="form-check-label" for="{{ $id }}_yes" style="font-size:.85rem;">Yes</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="radio" value="0"
                               id="{{ $id }}_no" name="{{ $name }}" @if($required) required @endif>
                        <label class="form-check-label" for="{{ $id }}_no" style="font-size:.85rem;">No</label>
                    </div>
                </div>
            </div>
            @break

        @default
            <input type="{{ $type }}" class="form-control form-control-sm" id="{{ $id }}" name="{{ $name }}"
                   placeholder="{{ $placeholder }}"
                   @isset($f['min']) min="{{ $f['min'] }}" @endisset
                   @isset($f['max']) max="{{ $f['max'] }}" @endisset
                   @isset($f['step']) step="{{ $f['step'] }}" @endisset
                   @isset($f['value']) value="{{ $f['value'] }}" @endisset
                   @if($required) required @endif {!! $attrs !!}>
    @endswitch

    @if(! in_array($type, ['file', 'yesno'], true))
        <div class="invalid-feedback" style="font-size:.78rem;">{{ $label }} is required.</div>
    @endif
</div>
