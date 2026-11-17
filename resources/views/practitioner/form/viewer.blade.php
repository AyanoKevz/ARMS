{{--
    On-screen AF-PCN-A1 viewer: the three form pages behind a tab strip
    (Page 1 / Page 2 / Page 3 / Full Application Form). Returned by the
    "Review Details" preview endpoint; tab switching lives in
    practitioner-form.js and the styles in af-pcn-a1.css.
--}}
<div class="af-viewer" data-af-viewer data-af-show="1">
    <div class="af-viewer-banner">
        <span class="af-viewer-kicker"><i class="bi bi-file-earmark-text"></i> Official DOLE-BWC Digital Transceiver</span>
        <span class="af-viewer-title">Application Form (DOLE-BWC AF-PCN-A1)</span>
        <span class="af-viewer-sub">Review every page. This is the form that will be attached to your application.</span>
    </div>

    <div class="af-tabs" role="tablist">
        <button type="button" class="af-tab is-active" data-af-tab="1" role="tab">Page 1: Profile &amp; Education</button>
        <button type="button" class="af-tab" data-af-tab="2" role="tab">Page 2: Trainings &amp; Skills</button>
        <button type="button" class="af-tab" data-af-tab="3" role="tab">Page 3: Affiliation &amp; Declarations</button>
        <button type="button" class="af-tab" data-af-tab="all" role="tab">Full Application Form</button>
    </div>

    <div class="af-form af-screen">
        @include('practitioner.form.page1', ['pdf' => false])
        @include('practitioner.form.page2', ['pdf' => false])
        @include('practitioner.form.page3', ['pdf' => false])
    </div>
</div>
