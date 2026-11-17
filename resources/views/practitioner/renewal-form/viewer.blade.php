{{--
    On-screen AF-PCR-A2 viewer: the two form pages behind a tab strip
    (Page 1 / Page 2 / Full Application Form), as the AF-PCN-A1 viewer.
    Returned by the portal renewal page's "Review Details"; tab switching
    lives in practitioner-form.js and the styles in af-pcn-a1.css.
--}}
<div class="af-viewer" data-af-viewer data-af-show="1">
    <div class="af-viewer-banner">
        <span class="af-viewer-kicker"><i class="bi bi-file-earmark-text"></i> Official DOLE-BWC Digital Transceiver</span>
        <span class="af-viewer-title">Renewal Application Form (DOLE-BWC AF-PCR-A2)</span>
        <span class="af-viewer-sub">Review both pages. This is the form that will be attached to your renewal application.</span>
    </div>

    <div class="af-tabs" role="tablist">
        <button type="button" class="af-tab is-active" data-af-tab="1" role="tab">Page 1: Profile &amp; Trainings</button>
        <button type="button" class="af-tab" data-af-tab="2" role="tab">Page 2: Lectures, Skills &amp; Awards</button>
        <button type="button" class="af-tab" data-af-tab="all" role="tab">Full Application Form</button>
    </div>

    <div class="af-form af-screen">
        @include('practitioner.renewal-form.page1', ['pdf' => false])
        @include('practitioner.renewal-form.page2', ['pdf' => false])
    </div>
</div>
