{{--
    The submission wizard: one requirement per step.

    The requirements and the deadline sit together in a single card at the top,
    the way the registration page states its document checklist before the form
    begins. The card doubles as the progress indicator — each item ticks once it
    is satisfied and can be clicked to jump back.

    Remarks closes the wizard as a step of its own, reached by Next from the
    last requirement. It is deliberately absent from the checklist above: that
    card lists what is REQUIRED, and remarks never are.

    Expects: $ptrDocumentTypes
--}}

@php
    $requiredCount = $ptrDocumentTypes->count();
    $totalSteps    = $requiredCount + 1;   // + the closing Remarks step
@endphp

<div class="ptr-wizard" id="ptrWizard" data-total="{{ $totalSteps }}">

    {{-- ── Requirements and instructions ──────────────────────────────── --}}
    <section class="ptr-reqs" aria-label="Post Training Report requirements">
        <div class="ptr-reqs-head">
            <span class="ptr-reqs-icon"><i class="fas fa-clipboard-check"></i></span>
            <div>
                <h6 class="ptr-reqs-title">Required Items &amp; Instructions</h6>
                <p class="ptr-reqs-lead">
                    Due within
                    <strong>{{ \App\Models\NtcReport::POST_TRAINING_DEADLINE_DAYS }} working days</strong>
                    of the last training day (OSHC MC 04 Series 2025).
                    All {{ $requiredCount }} numbered items below are required — complete them one step
                    at a time, then add any remarks at the end.
                    The Directory of Participants is encoded in this form, the instructors
                    carry over from your Notice to Conduct and the training video is a link;
                    the other {{ $ptrDocumentTypes->where('entry_type', 'file')->count() }}
                    are PDF uploads of up to <strong>25 MB</strong> each.
                </p>
            </div>
        </div>

        <div class="ptr-reqs-grid">
            @foreach($ptrDocumentTypes as $index => $docType)
            <button type="button"
                    class="ptr-req{{ $index === 0 ? ' is-current' : '' }}"
                    data-goto="{{ $index + 1 }}"
                    aria-current="{{ $index === 0 ? 'step' : 'false' }}">
                <span class="ptr-req-num">{{ $index + 1 }}</span>
                <span class="ptr-req-text">
                    <span class="ptr-req-name">{{ $docType->name }}</span>
                    <span class="ptr-req-meta">
                        @if($docType->isEncoded())
                            Encoded here &mdash; ID picture 5 MB
                        @elseif($docType->isRoster())
                            Selected here &mdash; from your NTC
                        @elseif($docType->isLink())
                            A link &mdash; not an upload
                        @else
                            {{ $docType->formatLabel() }} &mdash; 25 MB
                        @endif
                    </span>
                </span>
                <i class="fas fa-check ptr-req-tick" aria-hidden="true"></i>
            </button>
            @endforeach
        </div>
    </section>

    {{-- ── Step panels ────────────────────────────────────────────────── --}}
    <div class="ptr-steps">
        @foreach($ptrDocumentTypes as $index => $docType)
        <section class="ptr-step-panel{{ $index === 0 ? ' is-active' : '' }}"
                 data-step="{{ $index + 1 }}"
                 data-kind="{{ $docType->stepKind() }}"
                 data-doc-type-id="{{ $docType->id }}"
                 data-input="{{ $docType->isFile() ? $docType->inputName() : '' }}"
                 data-name="{{ $docType->name }}"
                 {{ $index === 0 ? '' : 'hidden' }}>

            <header class="ptr-step-head">
                <p class="ptr-step-eyebrow">Step {{ $index + 1 }} of {{ $totalSteps }}</p>
                <h6 class="ptr-step-title">{{ $docType->name }} <span class="text-danger">*</span></h6>
                <p class="ptr-step-hint">
                    @if($docType->isEncoded())
                        Encode every participant below. Each one needs an ID picture of up to
                        <strong>5 MB</strong> (JPG or PNG).
                    @elseif($docType->isRoster())
                        Already selected on your Notice to Conduct. Amend the list if someone
                        else conducted the training, or simply continue.
                    @elseif($docType->isLink())
                        Upload the recording wherever you keep it, then paste the link here.
                        A full session is far too large to upload through this form.
                    @else
                        Accepted format: <code>{{ $docType->formatLabel() }}</code> &mdash; up to
                        <strong>25 MB</strong>.
                    @endif
                </p>
            </header>

            <div class="ptr-step-body">
                @if($docType->isEncoded())
                    @include('applicant.partials.post_training_directory')
                @elseif($docType->isRoster())
                    @include('applicant.partials.post_training_instructors')
                @elseif($docType->isLink())
                    @include('applicant.partials.post_training_video_step')
                @else
                    @include('applicant.partials.post_training_upload_step', ['docType' => $docType])
                @endif
            </div>
        </section>
        @endforeach

        {{-- The closing step. data-kind="remarks" tells the wizard it is
             always satisfied, so Next and Submit are never blocked on it. --}}
        <section class="ptr-step-panel"
                 data-step="{{ $totalSteps }}"
                 data-kind="remarks"
                 data-input=""
                 data-name="Remarks"
                 hidden>

            <header class="ptr-step-head">
                <p class="ptr-step-eyebrow">Step {{ $totalSteps }} of {{ $totalSteps }}</p>
                <h6 class="ptr-step-title">
                    Remarks <span class="text-muted fw-normal ptr-text-80">(optional)</span>
                </h6>
                <p class="ptr-step-hint">
                    Anything the evaluator should know about this submission. Leave it
                    blank if there is nothing to add, then submit.
                </p>
            </header>

            <div class="ptr-step-body">
                <textarea id="ptr_applicant_remarks"
                          name="applicant_remarks"
                          class="form-control"
                          rows="4"
                          maxlength="1000"
                          placeholder="Anything the evaluator should know about this submission…"></textarea>
            </div>
        </section>
    </div>
</div>
