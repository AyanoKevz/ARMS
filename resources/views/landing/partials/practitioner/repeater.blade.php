{{--
    A repeatable practitioner CV section.

    Renders only the header and a <template>; practitioner-form.js clones the
    template `data-initial` times on first reveal and on every "Add" click,
    rewriting __IDX__ the same way landing.js does for instructor cards. Inputs
    inside a <template> are inert, so the hidden copy never reaches
    checkValidity() or the submitted FormData.

    Expects: $key, $title, $addLabel, $fields
    Optional: $icon, $itemLabel, $initial, $min, $note
    When the form comes back pre-filled, the rows in $pracValues['sections']
    are rendered as cards here; the script numbers new ones after them.

    $pracLocked (portal renewal, a step the AF-PCR-A2 does not ask): the rows
    on file only — no Add / Remove, every field disabled (field.blade.php),
    and practitioner-form.js adds no blank cards.
--}}
@php
    $icon       = $icon      ?? 'bi-collection-fill';
    $itemLabel  = $itemLabel ?? 'Entry';
    $initial    = $initial   ?? 1;
    $min        = $min       ?? 1;
    $rows       = array_values($pracValues['sections'][$key] ?? []);
    $pracLocked = $pracLocked ?? false;
@endphp

<div class="prac-section mb-4"
     data-repeater="{{ $key }}"
     data-initial="{{ $initial }}"
     data-min="{{ $min }}"
     data-next-index="{{ count($rows) }}"
     data-item-label="{{ $itemLabel }}"
     data-section-title="{{ $title }}"
     @if($pracLocked) data-locked="1" @endif>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h6 class="prac-section-heading mb-0">
            <i class="bi {{ $icon }} me-2"></i>{{ $title }}
        </h6>
        @unless($pracLocked)
            <button type="button" class="btn btn-outline-primary btn-sm fw-semibold px-3 prac-add-btn"
                    data-repeater-add="{{ $key }}">
                <i class="bi bi-plus-circle me-1"></i>{{ $addLabel }}
            </button>
        @endunless
    </div>

    @if(isset($note) && ! $pracLocked)
        <div class="prac-note mb-3">
            <i class="bi bi-info-circle-fill me-1"></i><span>{!! $note !!}</span>
        </div>
    @endif

    <div class="prac-cards" data-repeater-cards="{{ $key }}">
        @foreach($rows as $i => $row)
            @include('landing.partials.practitioner.repeater_card', ['idx' => $i, 'pracRow' => $row])
        @endforeach
        @if($pracLocked && $rows === [])
            <p class="prac-locked-empty mb-0">None on record.</p>
        @endif
    </div>

    <template data-repeater-template="{{ $key }}">
        @include('landing.partials.practitioner.repeater_card', ['idx' => '__IDX__', 'pracRow' => []])
    </template>
</div>
