@extends('layouts.applicant')

@section('title', 'Renewal of Accreditation')

{{--
    OSH Practitioner / OSH Consultant renewal (DOLE-BWC AF-PCR-A2), in the
    practitioner portal (PractitionerRenewalController).

    The new-application form (landing/partials/practitioner/form) in its
    renewal mode, filled in with the answers of the application that earned
    the accreditation. What the AF-PCR-A2 does not ask again is shown locked.
    "Review Details" renders the AF-PCR-A2 from the form; it cannot be
    submitted yet.
--}}
@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/af-pcn-a1.css') }}">
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/practitioner-renewal.css') }}">
@endpush
@push('scripts')
<script src="{{ \App\Support\AssetVersion::url('js/practitioner-form.js') }}" defer></script>
<script src="{{ \App\Support\AssetVersion::url('js/practitioner-renewal.js') }}" defer></script>
@endpush

@section('content')
<div class="page-title">
    <div class="title_left">
        <h3>Renewal of Accreditation</h3>
    </div>
</div>
<div class="clearfix"></div>

@if(! $accreditation)
    <div class="x_panel">
        <div class="x_content">
            <p class="mb-0 text-muted">No OSH Practitioner or OSH Consultant accreditation is on record for this account.</p>
        </div>
    </div>
@elseif(! $renewable)
    <div class="x_panel">
        <div class="x_content">
            <p class="mb-0 text-muted">
                Accreditation No. <strong>{{ $accreditation->accreditation_number }}</strong> is
                <strong>{{ $accreditation->status }}</strong>, so it cannot be renewed online.
                Please contact the Accreditation Division.
            </p>
        </div>
    </div>
@else
<div class="prac-renewal">
    <div class="pr-soon">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Online submission of renewals is not yet available.</strong>
            You can already fill in your renewal form and review it as the DOLE-BWC AF-PCR-A2 application form it
            will generate. Submitting it opens soon.
        </div>
    </div>

    {{-- The box beside the renewal choice on the AF-PCR-A2. --}}
    <div class="x_panel">
        <div class="x_title">
            <h2><i class="fas fa-award me-2 pr-gold"></i> Accreditation to Renew</h2>
            <div class="clearfix"></div>
        </div>
        <div class="x_content">
            <div class="row text-center text-md-start">
                <div class="col-md mb-2 mb-md-0 border-end">
                    <p class="pr-label">Renewal of Accreditation as</p>
                    <p class="pr-value">{{ $accreditation->application?->accreditationTitle() ?? 'OSH Practitioner' }}</p>
                </div>
                <div class="col-md mb-2 mb-md-0 border-end">
                    <p class="pr-label">Accreditation No.</p>
                    <p class="pr-value is-number">{{ $details['number'] }}</p>
                </div>
                <div class="col-md mb-2 mb-md-0 border-end">
                    <p class="pr-label">Date Accredited</p>
                    <p class="pr-value">{{ $details['accredited'] }}</p>
                </div>
                <div class="col-md mb-2 mb-md-0 border-end">
                    <p class="pr-label">Date of Last Renewal</p>
                    <p class="pr-value">{{ $details['last_renewal'] }}</p>
                </div>
                <div class="col-md">
                    <p class="pr-label">Validity</p>
                    <p class="pr-value">{{ $details['validity'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="x_panel">
        <div class="x_title">
            <h2><i class="fas fa-file-signature me-2 pr-gold"></i> Renewal Application Form <small>DOLE-BWC AF-PCR-A2</small></h2>
            <div class="clearfix"></div>
        </div>
        <div class="x_content">
            <div class="pr-intro">
                <i class="bi bi-pencil-square"></i>
                <span>
                    Filled in with what you submitted in your last application. Change anything that is no longer
                    correct. Fields marked <span class="prac-locked-hint"><i class="bi bi-lock-fill"></i> Not on the renewal form</span>
                    and the locked steps are not asked again and are kept as they are.
                </span>
            </div>

            <form id="practitionerRenewalForm" novalidate>
                @csrf

                <p class="form-section-title">Step 1 &mdash; Personal Information</p>
                <div class="row g-3">
                    @foreach([
                        ['name' => 'first_name',  'label' => 'First Name',          'type' => 'text',   'col' => 'col-md-4', 'required' => true],
                        ['name' => 'middle_name', 'label' => 'Middle Name',         'type' => 'text',   'col' => 'col-md-3', 'placeholder' => '(optional)'],
                        ['name' => 'last_name',   'label' => 'Last Name',           'type' => 'text',   'col' => 'col-md-3', 'required' => true],
                        ['name' => 'suffix',      'label' => 'Suffix',              'type' => 'text',   'col' => 'col-md-2', 'placeholder' => 'Jr., Sr., III', 'attrs' => 'maxlength="20"'],
                        ['name' => 'sex',         'label' => 'Sex',                 'type' => 'select', 'col' => 'col-md-4', 'required' => true, 'options' => ['Male', 'Female']],
                        ['name' => 'birthday',    'label' => 'Date of Birth',       'type' => 'date',   'col' => 'col-md-4', 'required' => true, 'renewalLocked' => true],
                        ['name' => 'region',      'label' => 'Region',              'type' => 'ph-address', 'part' => 'region', 'col' => 'col-md-4', 'required' => true],
                        ['name' => 'city',        'label' => 'City / Municipality', 'type' => 'ph-address', 'part' => 'city',   'col' => 'col-md-6', 'required' => true],
                        ['name' => 'address',     'label' => 'City / Mailing Address', 'type' => 'text', 'col' => 'col-md-6', 'required' => true, 'placeholder' => 'No. & Street, Barangay'],
                    ] as $f)
                        @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'person', 'idPrefix' => 'prac_person', 'renewal' => true])
                    @endforeach

                    {{-- On the AF-PCR-A2, but it is the account's own address. --}}
                    <div class="col-md-6 prac-field">
                        <label class="form-label fw-semibold prac-label" for="renewal_email">E-mail</label>
                        <input type="email" class="form-control form-control-sm" id="renewal_email" value="{{ $email }}" readonly>
                        <div class="form-text prac-help">The e-mail address of your account.</div>
                    </div>
                </div>

                @include('landing.partials.practitioner.form', [
                    'renewal'    => true,
                    'pracType'   => $accreditation->accreditation_type_id,
                    'previewUrl' => route('applicant.practitioner.renewal.preview'),
                ])

                <div class="pr-actions">
                    <button type="button" class="btn btn-primary fw-semibold px-4" data-renewal-review>
                        <i class="bi bi-eye me-1"></i> Review Details
                    </button>
                    <span class="d-inline-block" tabindex="0" title="Online submission of renewals is not yet available.">
                        <button type="button" class="btn btn-success fw-semibold px-4" disabled>
                            <i class="bi bi-send me-1"></i> Submit Renewal Application
                        </button>
                    </span>
                    <span class="pr-actions-note">Submission is not yet available.</span>
                </div>
            </form>
        </div>
    </div>

    {{-- Review Details: the AF-PCR-A2 rendered from the form (practitioner-renewal.js). --}}
    <div class="x_panel" id="renewalReview" hidden>
        <div class="x_title">
            <h2><i class="fas fa-file-alt me-2 pr-gold"></i> Review Details</h2>
            <button type="button" class="btn btn-outline-secondary btn-sm float-end" data-renewal-back>
                <i class="bi bi-arrow-left me-1"></i> Back to the form
            </button>
            <div class="clearfix"></div>
        </div>
        <div class="x_content" data-renewal-review-body></div>
    </div>
</div>
@endif
@endsection
