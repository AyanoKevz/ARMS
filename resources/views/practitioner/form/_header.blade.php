{{--
    Page header, as printed: page 1 carries the instructions and the 1"x1"
    photo box; the other pages only the title block. Expects $form, $page,
    $pdf.

    Shared by the AF-PCN-A1 (new applicant, the defaults below) and the
    AF-PCR-A2 renewal form, which passes its own $sheet: kind (the line
    under the title), code, revision, pages and instructions.
--}}
@php
    $firstPage = (int) $page === 1;
    $sheet = ($sheet ?? []) + [
        'kind'         => 'New Applicant',
        'code'         => 'AF-PCN-A1',
        'revision'     => '0803-0',
        'pages'        => 3,
        'instructions' => 'Fill in all the data needed. Use block/printed letters or use a typewriter. Write N.A. if the blanks are not applicable. Please sign in all pages of the form.',
    ];
@endphp
<table class="af-header {{ $firstPage ? '' : 'af-header-cont' }}">
    <tr>
        <td class="af-header-brand">
            <table class="af-brand">
                <tr>
                    <td class="af-brand-logo">
                        <img class="af-logo" alt="OSHC"
                             src="{{ $pdf ? public_path('images/oshc-logo.png') : asset('images/oshc-logo.png') }}">
                    </td>
                    <td class="af-brand-name">Occupational Safety<br>and Health Center</td>
                </tr>
            </table>
        </td>
        <td class="af-header-title">
            @if($firstPage)
                OSH Practitioner/<br>Consultant<br>Application Form<br><span>({{ $sheet['kind'] }})</span>
            @else
                OSH Practitioner/Consultant<br>Application Form<br><span>({{ $sheet['kind'] }})</span>
            @endif
        </td>
        <td class="af-header-code">
            <span class="af-code-agency">DOLE-BWC</span>
            <span class="af-code-form">{{ $sheet['code'] }}</span>
            <span>Revision Code: {{ $sheet['revision'] }}</span>
            <span>Page {{ $page }} of {{ $sheet['pages'] }}</span>
        </td>
        @if($firstPage)
            <td class="af-header-photo" rowspan="2">
                @if($form['photo_src'] ?? null)
                    <img class="af-photo" src="{{ $form['photo_src'] }}" alt="1x1 ID photo">
                @else
                    @unless($pdf)
                        {{-- The preview fills this from the chosen file (practitioner-form.js). --}}
                        <img class="af-photo" alt="" data-af-photo hidden>
                    @endunless
                    <span data-af-photo-placeholder>
                        <strong>Please attach your<br>1" x 1" picture</strong><br>
                        SC: blue background<br>
                        SP: red background<br>
                        <strong>2 COPIES<br>signed at the back</strong>
                    </span>
                @endif
            </td>
        @endif
    </tr>
    @if($firstPage)
        <tr>
            <td class="af-header-instructions" colspan="3">
                <strong>Instructions:</strong>
                {{ $sheet['instructions'] }}
            </td>
        </tr>
    @endif
</table>
