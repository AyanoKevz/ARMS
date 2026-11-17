{{--
    AF-PCN-A1 page 3 — 8. Examinations, 9. Memberships, 10. Character
    references, then the declarations with their "give details" lines and the
    certification, laid out as printed. Expects $form, $pdf.
--}}
@php
    $answers = $form['declarations'];
    $details = $form['declaration_details'];
    $yesNo   = fn (string $key) => [
        ['checked' => ($answers[$key] ?? null) === true,  'label' => 'Yes'],
        ['checked' => ($answers[$key] ?? null) === false, 'label' => 'No'],
    ];
@endphp
<div class="af-page af-page-last" data-af-page="3">
    <div class="af-sheet af-sheet-spaced">
        @include('practitioner.form._header', ['page' => 3])

        @include('practitioner.form._section', [
            'number' => 8, 'title' => 'OSH Examinations / Eligibilities Passed',
            'aside'  => '(if any)',
            'note'   => '(Use additional sheet if necessary). Please attach photocopy of ID, license or certification',
        ])
        @include('practitioner.form._table', [
            'columns' => [['Title', 36], ['Year Taken', 27, 'af-center'], ['Given by', 22], ['Rating', 15, 'af-center']],
            'rows'    => $form['tables']['examinations'],
        ])

        @include('practitioner.form._section', ['number' => 9, 'title' => 'Memberships / Affiliations Related to OSH'])
        @include('practitioner.form._table', [
            'columns' => [['Organization / Institution / Agency', 38], ['Designation / Position', 42], ['Validity', 20, 'af-center']],
            'rows'    => $form['tables']['memberships'],
        ])

        @include('practitioner.form._section', ['number' => 10, 'title' => 'Character References', 'lead' => '(give at least 3)'])
        @include('practitioner.form._table', [
            'columns' => [['Name', 22], ['Position / Occupation', 26], ['Company / Address', 32], ['Contact Number/s', 20, 'af-center']],
            'rows'    => $form['tables']['references'],
        ])

        <div class="af-decl">
            <div class="af-decl-line">
                Do you have any pending &nbsp;a) administrative case
                @foreach($yesNo('has_pending_admin_case') as $check) @include('practitioner.form._check', $check) @endforeach
                b) criminal case?
                @foreach($yesNo('has_pending_criminal_case') as $check) @include('practitioner.form._check', $check) @endforeach
            </div>
            <table class="af-line af-decl-line">
                <tr>
                    <td class="af-line-label">If you have any, give details of the offense</td>
                    <td class="af-line-value af-value">{{ $details['pending_case_details'] }}</td>
                </tr>
            </table>

            <div class="af-decl-line">
                Have you been convicted of any crime or violation of any law, decree, ordinance or regulations by any court or tribunal?
            </div>
            <table class="af-line af-decl-line">
                <tr>
                    @foreach($yesNo('has_crime_conviction') as $check)
                        <td class="af-line-label">@include('practitioner.form._check', $check)</td>
                    @endforeach
                    <td class="af-line-label">If yes, give details</td>
                    <td class="af-line-value af-value">{{ $details['crime_conviction_details'] }}</td>
                </tr>
            </table>

            <div class="af-decl-line">
                Have you ever been convicted of any administrative offense?
                @foreach($yesNo('has_admin_offense_conviction') as $check) @include('practitioner.form._check', $check) @endforeach
            </div>
            <table class="af-line af-decl-line">
                <tr>
                    <td class="af-line-label">If your answer is "YES", give details of the offense</td>
                    <td class="af-line-value af-value">{{ $details['admin_offense_details'] }}</td>
                </tr>
            </table>

            <div class="af-decl-line">
                Have you ever been retired, forced to resign or dropped from employment in the public and private sector?
            </div>
            <table class="af-line af-decl-line">
                <tr>
                    @foreach($yesNo('has_forced_separation') as $check)
                        <td class="af-line-label">@include('practitioner.form._check', $check)</td>
                    @endforeach
                    <td class="af-line-label">If yes, give reasons</td>
                    <td class="af-line-value af-value">{{ $details['forced_separation_reasons'] }}</td>
                </tr>
            </table>
        </div>

        <table class="af-certify">
            <tr>
                <td class="af-certify-text" colspan="2">I certify that the information stated above are true and correct.</td>
                <td class="af-certify-gap" rowspan="2"></td>
                <td class="af-thumb" rowspan="2">RIGHT THUMB<br>MARK</td>
            </tr>
            <tr>
                <td class="af-sign">
                    <span class="af-sign-value">{{ $form['signature'] }}</span>
                    <span class="af-sign-label">SIGNATURE</span>
                </td>
                <td class="af-date">Date: <span class="af-blank af-value">{{ $form['signed_date'] }}</span></td>
            </tr>
        </table>
    </div>

    @include('practitioner.form._footer')
</div>
