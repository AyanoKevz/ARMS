{{--
    The rejected AF-PCN-A1, back as the form itself (tracking page).

    Steps 3–13 of the practitioner registration, filled in with the answers on
    file ($pracValues, from PractitionerApplicationService::correctionValues()).
    Submitted with the rest of the resubmission; TrackingController::resubmitAll()
    saves the corrected answers and generates the form again from them.

    The Step 3 person fields live in register.blade.php's #individualFields
    there; here they are written through the shared field partial under
    person[...], away from the resubmission's own field names.

    Expects: $document (the rejected PRAC_00 / CONS_00 application document),
    $pracValues, $application
--}}
@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/track-correction.css') }}">
@endpush
@push('scripts')
<script src="{{ \App\Support\AssetVersion::url('js/practitioner-form.js') }}" defer></script>
@endpush

<div class="prac-correction">
    <div class="prac-correction-head">
        <div class="prac-correction-title">
            <i class="bi bi-pencil-square me-1"></i>{{ $document->documentField?->name ?? 'Application Form' }}
        </div>
        @if($document->remarks)
            <div class="prac-correction-remarks">
                <i class="bi bi-chat-left-text me-1"></i>{{ $document->remarks }}
            </div>
        @endif
        <p class="prac-correction-hint mb-0">
            Correct your answers below — they are filled in with what you submitted. Your application form
            is generated again from them when you resubmit, so there is nothing to upload.
        </p>
    </div>

    <p class="form-section-title">Step 3 &mdash; Personal Information</p>
    <div class="row g-3">
        @foreach([
            ['name' => 'first_name',  'label' => 'First Name',          'type' => 'text',   'col' => 'col-md-4', 'required' => true],
            ['name' => 'middle_name', 'label' => 'Middle Name',         'type' => 'text',   'col' => 'col-md-3', 'placeholder' => '(optional)'],
            ['name' => 'last_name',   'label' => 'Last Name',           'type' => 'text',   'col' => 'col-md-3', 'required' => true],
            ['name' => 'suffix',      'label' => 'Suffix',              'type' => 'text',   'col' => 'col-md-2', 'placeholder' => 'Jr., Sr., III'],
            ['name' => 'sex',         'label' => 'Sex',                 'type' => 'select', 'col' => 'col-md-4', 'required' => true, 'options' => ['Male', 'Female']],
            ['name' => 'birthday',    'label' => 'Date of Birth',       'type' => 'date',   'col' => 'col-md-4', 'required' => true],
            ['name' => 'region',      'label' => 'Region',              'type' => 'ph-address', 'part' => 'region', 'col' => 'col-md-4', 'required' => true],
            ['name' => 'city',        'label' => 'City / Municipality', 'type' => 'ph-address', 'part' => 'city',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'address',     'label' => 'Full Address',        'type' => 'text',   'col' => 'col-md-6', 'required' => true, 'placeholder' => 'Street, Barangay'],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'person', 'idPrefix' => 'prac_person'])
        @endforeach
    </div>

    {{-- The application's own type: Practitioner and Consultant notes differ (photo background). --}}
    @include('landing.partials.practitioner.form', ['correcting' => true, 'pracType' => $application->accreditation_type_id])
</div>
