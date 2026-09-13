{{--
    A repeatable practitioner CV section.

    Renders only the header and a <template>; practitioner-form.js clones the
    template `data-initial` times on first reveal and on every "Add" click,
    rewriting __IDX__ the same way landing.js does for instructor cards. Inputs
    inside a <template> are inert, so the hidden copy never reaches
    checkValidity() or the submitted FormData.

    Expects: $key, $title, $addLabel, $fields
    Optional: $icon, $itemLabel, $initial, $min, $note
--}}
@php
    $icon      = $icon      ?? 'bi-collection-fill';
    $itemLabel = $itemLabel ?? 'Entry';
    $initial   = $initial   ?? 1;
    $min       = $min       ?? 1;
@endphp

<div class="prac-section mb-4"
     data-repeater="{{ $key }}"
     data-initial="{{ $initial }}"
     data-min="{{ $min }}"
     data-item-label="{{ $itemLabel }}"
     data-section-title="{{ $title }}">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h6 class="prac-section-heading mb-0">
            <i class="bi {{ $icon }} me-2"></i>{{ $title }}
        </h6>
        <button type="button" class="btn btn-outline-primary btn-sm fw-semibold px-3"
                style="border-radius:8px;" data-repeater-add="{{ $key }}">
            <i class="bi bi-plus-circle me-1"></i>{{ $addLabel }}
        </button>
    </div>

    @isset($note)
        <div class="prac-note mb-3">
            <i class="bi bi-info-circle-fill me-1"></i><span>{!! $note !!}</span>
        </div>
    @endisset

    <div class="prac-cards" data-repeater-cards="{{ $key }}"></div>

    <template data-repeater-template="{{ $key }}">
        <div class="prac-card border rounded-3 bg-white shadow-sm p-3 mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold prac-card-label">{{ $itemLabel }} #1</span>
                <button type="button" class="btn btn-sm btn-outline-danger prac-remove-btn d-none">
                    <i class="bi bi-trash me-1"></i>Remove
                </button>
            </div>
            <div class="row g-3">
                @foreach($fields as $f)
                    @include('landing.partials.practitioner.field', [
                        'f'        => $f,
                        'group'    => "practitioner[{$key}][__IDX__]",
                        'idPrefix' => "prac_{$key}___IDX__",
                    ])
                @endforeach
            </div>
        </div>
    </template>
</div>
