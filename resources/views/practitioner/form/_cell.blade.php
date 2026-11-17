{{--
    One profile cell: the label on top, the value under it, as on the
    AF-PCR-A2 renewal form the profile is laid out after.
    Expects $label, $value. Optional: $span (colspan), $strong (bold label),
    $plain (value keeps its case — e-mail addresses).
--}}
<td @if(($span ?? 1) > 1) colspan="{{ $span }}" @endif>
    <span class="af-label {{ ($strong ?? false) ? 'af-label-strong' : '' }}">{{ $label }}</span>
    <span class="af-value af-block {{ ($plain ?? false) ? 'af-value-plain' : '' }}">{{ $value }}</span>
</td>
