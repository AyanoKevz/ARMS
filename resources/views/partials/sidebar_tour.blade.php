{{--
    Sidebar Quick Tour Partial
    ─────────────────────────
    Usage: @include('partials.sidebar_tour', ['tourType' => 'applicant|evaluator|verifier'])

    This partial injects:
      1. The re-launch "Quick Tour" trigger button (HTML only, hidden by default — shown by JS)
      2. A one-liner script tag that bootstraps the tour via window.ARMSTour.init()

    All CSS  → public/css/portal.css   (SIDEBAR QUICK TOUR section)
    All JS   → public/js/portal.js     (ARMS Tour logic)
    Library  → Intro.js (loaded via CDN in portal.blade.php)
--}}


{{-- Bootstrap the tour for the given role.

     portal.js is loaded with `defer`, so it has NOT executed yet while this inline
     script runs during parsing — window.ARMSTour is still undefined at this point.
     Calling init() directly here silently did nothing. Leave the request in a global
     instead; portal.js picks it up as soon as it evaluates. The direct call is kept
     for the case where portal.js somehow ran first. --}}
<script>
    window.__armsTourRequest = ['{{ $tourType }}', '{{ session()->getId() }}'];
    if (window.ARMSTour) {
        window.ARMSTour.init.apply(null, window.__armsTourRequest);
    }
</script>
