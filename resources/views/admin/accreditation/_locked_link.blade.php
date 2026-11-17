{{--
    A sidebar entry for a section that is not open yet: visible, but with no
    link and no route behind it. Expects $icon, $label.
--}}
<li class="nav-locked" title="Not available yet">
    <a aria-disabled="true" tabindex="-1"><i class="fas {{ $icon }}"></i> {{ $label }} <span class="fas fa-lock nav-lock-icon"></span></a>
</li>
