{{--
    One practitioner form control.

    Every practitioner input goes through here so the label, the required
    asterisk, the invalid-feedback block and the data-label are written once
    instead of ~90 times. Styles live in landing.css (.prac-*).

    Expects:
      $f        array  — name, label, type, col, required, placeholder, help,
                         hint, options, rows, min, max, step, attrs, accept, allowedExt
                         (type 'ph-address': a Region or City dropdown from the
                         PSGC list — part 'region' | 'city', pair = shared key)
                         (hint: a short muted note on the label's own line, e.g.
                         "(if any)", so the input stays level with its row
                         neighbours; help: a longer note on its own line, or
                         [type id => note] when Practitioner and Consultant
                         read differently — see $pracType in form.blade.php)
      $group    string — name prefix, e.g. "practitioner[education][__IDX__]"
      $idPrefix string — id prefix; may contain __IDX__ for cloned cards
    Optional, when the form comes back pre-filled (tracking page correction):
      $pracValues array — every answer, keyed like correctionValues(); a field
                          in group "practitioner[profile]" or "person" reads
                          $pracValues['profile'] / ['person'][its name]
      $pracRow    array — the row of a repeater card, which wins over the above
    Optional, on the portal renewal form (form.blade.php's $renewal):
      $f['renewalLocked'] — a field the AF-PCR-A2 does not ask: shown with its
                            value, disabled, with a note saying why
      $pracLocked bool    — every field of a step the AF-PCR-A2 does not ask
    A locked field is disabled, so it is neither validated nor sent.
--}}
@php
    if (isset($pracRow) && is_array($pracRow)) {
        $current = $pracRow[$f['name']] ?? null;
    } elseif (isset($pracValues) && preg_match('/^(?:practitioner\[)?(\w+)\]?$/', $group ?? '', $groupKey)) {
        $current = $pracValues[$groupKey[1]][$f['name']] ?? null;
    } else {
        $current = null;
    }
    $current = $current ?? ($f['value'] ?? null);
    if ($current instanceof \DateTimeInterface) {
        $current = $current->format('Y-m-d');
    }
    // Yes/no answers are stored as booleans; the radios carry "1" / "0".
    $currentFlag = is_bool($current) ? ($current ? '1' : '0') : (($current === null || $current === '') ? null : (string) $current);

    $lockedField = ($renewal ?? false) && ($f['renewalLocked'] ?? false);
    $isLocked    = $lockedField || ($pracLocked ?? false);

    $type        = $f['type'] ?? 'text';
    $col         = $f['col'] ?? 'col-md-6';
    $required    = ! $isLocked && ($f['required'] ?? false);
    $placeholder = $f['placeholder'] ?? '';
    $help        = $f['help'] ?? null;
    $hint        = $f['hint'] ?? null;
    $attrs       = ($f['attrs'] ?? '') . ($isLocked ? ' disabled' : '');
    $name        = ($group ?? '') !== '' ? $group . '[' . $f['name'] . ']' : $f['name'];
    $id          = ($idPrefix ?? 'prac') . '_' . $f['name'];
    // Blank optional fields print as "N/A" on the AF-PCN-A1 form (display
    // only — nothing is stored); see App\Support\PractitionerForm.
    $label       = $f['label'];
@endphp

<div class="{{ $col }} prac-field" data-label="{{ $label }}">
    @if($type !== 'yesno')
        <label class="form-label fw-semibold prac-label" for="{{ $id }}">
            {{ $label }}@if($required) <span class="text-danger">*</span>@endif
            @if($hint) <span class="prac-label-hint">{{ $hint }}</span>@endif
            @if($lockedField) <span class="prac-locked-hint"><i class="bi bi-lock-fill"></i> Not on the renewal form</span>@endif
        </label>
    @endif

    @if(is_array($help))
        {{-- One note per type: the matching one when the type is known, else
             all of them for practitioner-form.js to show the selected one. --}}
        @foreach($help as $forType => $text)
            @if(($pracType ?? null) === null)
                <div class="form-text mt-0 mb-2 prac-help" data-prac-for="{{ $forType }}">{{ $text }}</div>
            @elseif((int) $pracType === (int) $forType)
                <div class="form-text mt-0 mb-2 prac-help">{{ $text }}</div>
            @endif
        @endforeach
    @elseif($help)
        <div class="form-text mt-0 mb-2 prac-help">{{ $help }}</div>
    @endif

    @switch($type)

        @case('select')
            <select class="form-select form-select-sm" id="{{ $id }}" name="{{ $name }}"
                    @if($required) required @endif {!! $attrs !!}>
                <option value="" @selected($currentFlag === null) disabled>{{ $placeholder ?: '— Select —' }}</option>
                @foreach($f['options'] as $opt)
                    <option value="{{ $opt }}" @selected($currentFlag === (string) $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('ph-address')
            {{-- Region ($f['part'] 'region') or city ('city') from the PSGC
                 list, filled by ph-fields.js; data-value is the answer on file.
                 The pair shares one key, so the city follows its region. --}}
            @pushOnce('scripts', 'ph-fields')
                <script src="{{ \App\Support\AssetVersion::url('js/ph-fields.js') }}" defer></script>
            @endPushOnce
            <select class="form-select form-select-sm" id="{{ $id }}" name="{{ $name }}"
                    data-ph-address-{{ ($f['part'] ?? 'region') === 'city' ? 'city' : 'region' }}="{{ $f['pair'] ?? 'person' }}"
                    data-value="{{ $current }}"
                    @if($required) required @endif {!! $attrs !!}>
                <option value="" disabled selected>{{ ($f['part'] ?? 'region') === 'city' ? '— Select a region first —' : '— Select region —' }}</option>
            </select>
            @break

        @case('textarea')
            <textarea class="form-control form-control-sm" id="{{ $id }}" name="{{ $name }}"
                      rows="{{ $f['rows'] ?? 2 }}" placeholder="{{ $placeholder }}"
                      @if($required) required @endif {!! $attrs !!}>{{ $current }}</textarea>
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
                    <span class="file-name-text text-muted text-truncate prac-file-name">No file chosen</span>
                </div>
                <div class="invalid-feedback file-invalid-feedback prac-file-feedback">
                    Please select a valid file for {{ $label }}.
                </div>
            </div>
            @break

        @case('yesno')
            <div class="prac-yesno d-flex align-items-start justify-content-between flex-wrap gap-2 p-2 rounded-2">
                <span class="pe-2 prac-yesno-question">
                    {{ $label }}@if($required) <span class="text-danger">*</span>@endif
                </span>
                <div class="d-flex gap-3 flex-shrink-0">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="radio" value="1"
                               id="{{ $id }}_yes" name="{{ $name }}" @checked($currentFlag === '1') @if($required) required @endif @disabled($isLocked) {!! $f['attrs'] ?? '' !!}>
                        <label class="form-check-label prac-yesno-label" for="{{ $id }}_yes">Yes</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="radio" value="0"
                               id="{{ $id }}_no" name="{{ $name }}" @checked($currentFlag === '0') @if($required) required @endif @disabled($isLocked) {!! $f['attrs'] ?? '' !!}>
                        <label class="form-check-label prac-yesno-label" for="{{ $id }}_no">No</label>
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
                   @if($current !== null && $current !== '') value="{{ $current }}" @endif
                   @if($required) required @endif {!! $attrs !!}>
    @endswitch

    @if(! in_array($type, ['file', 'yesno'], true))
        <div class="invalid-feedback prac-feedback">{{ $label }} is required.</div>
    @endif
</div>
