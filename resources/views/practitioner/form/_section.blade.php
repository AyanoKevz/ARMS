{{--
    Numbered section heading, as printed: the bold title, then the form's own
    wording. Expects $number, $title. Optional: $lead (bold), $aside (plain),
    $note (italic).
--}}
<div class="af-section">
    <span class="af-section-title">{{ $number }}. {{ $title }}</span>
    @isset($lead)<span class="af-section-lead">{{ $lead }}</span>@endisset
    @isset($aside)<span>{{ $aside }}</span>@endisset
    @isset($note)<span class="af-section-note">{{ $note }}</span>@endisset
</div>
