{{--
    One card of a repeatable practitioner CV section. Rendered as the
    <template> practitioner-form.js clones ($idx "__IDX__", no row), and once
    per stored row when the form comes back pre-filled.

    Expects: $key, $itemLabel, $fields, $idx, $pracRow (array, may be empty)
--}}
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
                'group'    => "practitioner[{$key}][{$idx}]",
                'idPrefix' => "prac_{$key}_{$idx}",
                'pracRow'  => $pracRow,
            ])
        @endforeach
    </div>
</div>
