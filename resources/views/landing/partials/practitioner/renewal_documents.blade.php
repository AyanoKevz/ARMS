{{--
    The renewal's documentary requirements — the last step of the portal
    renewal form (form.blade.php with $renewal), the same for OSH Practitioner
    and OSH Consultant (PractitionerApplicationService::RENEWAL_DOCUMENTS):

      1      the most recent 1" x 1" picture, red background for a
             Practitioner and blue for a Consultant — a form field, printed
             on the AF-PCR-A2 as on a new application
      2 – 5  always
      3      the last Certificate of Accreditation the Accreditation division
             uploaded is used when there is one ($certificateOnFile)
      6 – 7  only after a change of employer or position: shown, enabled and
             required on Yes by practitioner-renewal.js

    Expects $pracType, $typeIds, $certificateOnFile.
--}}
@php
    $renewalDocs = \App\Services\PractitionerApplicationService::RENEWAL_DOCUMENTS;
    $item        = fn (string $code) => (int) substr($code, -2);
@endphp

<div class="row g-3">
    <div class="col-12">
        <div class="alert alert-info rounded-3 prac-upload-info mb-0">
            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Document Upload Instructions</h6>
            <p class="mb-0 prac-upload-info-text">
                Upload each document in <strong>PDF format only</strong> (maximum <strong>15 MB</strong> per file), and the picture as a JPG or PNG.
                Scans must be clear and fully readable.
            </p>
        </div>
    </div>

    <div class="col-12">
        <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
            <h6 class="fw-bold mb-3 prac-doc-heading">
                <span class="badge me-2 prac-doc-badge">1</span>Renewal Documentary Requirements
            </h6>
            <div class="row g-3">
                @include('landing.partials.practitioner.field', [
                    'f' => ['name' => 'photo', 'label' => '1. Most Recent 1" x 1" Picture', 'type' => 'file', 'col' => 'col-md-6 mb-2', 'required' => true,
                            'help' => [
                                $typeIds['practitioner'] => 'In a RED background. Printed on your renewal application form. JPG or PNG, max 15 MB.',
                                $typeIds['consultant']   => 'In a BLUE background. Printed on your renewal application form. JPG or PNG, max 15 MB.',
                            ],
                            'accept' => 'image/jpeg,image/png', 'allowedExt' => 'jpg,jpeg,png', 'attrs' => 'data-prac-photo-input="1"'],
                    'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile',
                ])

                @foreach($renewalDocs as $code => [$title, $note, $when])
                    @continue($when !== 'always')

                    @if($code === 'PCR_03' && $certificateOnFile)
                        {{-- Already in the system: nothing to upload. --}}
                        <div class="col-md-6 mb-2 prac-field" data-label="{{ $item($code) }}. {{ $title }}" data-prac-document
                             data-on-file="On file — issued by the Accreditation Division">
                            <span class="form-label fw-bold mb-0 prac-label d-block">{{ $item($code) }}. {{ $title }}</span>
                            <div class="form-text mt-0 mb-2 prac-help">Your last certificate is already on file, so there is nothing to upload.</div>
                            <div class="prac-on-file">
                                <i class="bi bi-file-earmark-check-fill"></i>
                                <span>On file</span>
                                <a href="{{ route('applicant.practitioner.renewal.certificate') }}" target="_blank" rel="noopener"
                                   class="btn btn-outline-primary btn-sm fw-semibold ms-auto">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </div>
                        </div>
                    @else
                        @include('landing.partials.practitioner.document_upload', [
                            'code' => $code, 'title' => $item($code) . '. ' . $title, 'note' => $note, 'required' => true,
                        ])
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-12">
        @include('landing.partials.practitioner.field', [
            'f' => ['name' => 'employer_changed', 'type' => 'yesno', 'col' => 'w-100', 'required' => true,
                    'label' => 'Is there a change of employer or position since your last accreditation?',
                    'attrs' => 'data-employer-changed="1"'],
            'group' => 'renewal', 'idPrefix' => 'renewal',
        ])
    </div>

    {{-- Shown on Yes. Disabled while hidden: neither required nor sent. --}}
    <div class="col-12" data-renewal-employer-docs hidden>
        <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
            <h6 class="fw-bold mb-3 prac-doc-heading">
                <span class="badge me-2 prac-doc-badge">2</span>When There Is a Change of Employer / Position
            </h6>
            <div class="row g-3">
                @foreach($renewalDocs as $code => [$title, $note, $when])
                    @continue($when !== 'employer_changed')
                    @include('landing.partials.practitioner.document_upload', [
                        'code' => $code, 'title' => $item($code) . '. ' . $title, 'note' => $note, 'required' => true, 'disabled' => true,
                    ])
                @endforeach
            </div>
        </div>
    </div>
</div>
