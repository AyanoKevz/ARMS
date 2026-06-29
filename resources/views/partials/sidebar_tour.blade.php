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

{{-- Re-launch button (visible after user dismisses the tour) --}}
<button id="arms-tour-trigger" aria-label="Relaunch sidebar tour" title="Quick Tour">
    <span>
        <i class="fas fa-map-signs"></i>
        Quick Tour
    </span>
</button>

{{-- Bootstrap the tour for the given role --}}
<script>
    window.ARMSTour && window.ARMSTour.init('{{ $tourType }}', '{{ session()->getId() }}');
</script>
