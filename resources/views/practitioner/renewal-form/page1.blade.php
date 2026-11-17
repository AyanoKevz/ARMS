{{--
    AF-PCR-A2 page 1 — the renewal type and the accreditation being renewed,
    1. Profile, 2. Work experience, 3. Summary of accomplishments and
    4. Trainings attended. Expects $form, $pdf.

    Laid out after OSH-PRACTITIONER-CONSULTANT-RENEWAL.pdf on the AF-PCN-A1
    partials (resources/views/practitioner/form), with the profile on the same
    six-column grid (28 / 28 / 11 / 11 / 11 / 11).
--}}
@php
    $f    = $form['field'];
    $accr = $form['accreditation'];
@endphp
<div class="af-page" data-af-page="1">
    <div class="af-sheet">
        @include('practitioner.form._header', ['page' => 1, 'sheet' => \App\Support\PractitionerForm::RENEWAL_SHEET])

        <table class="af-renew-head">
            <tr>
                <td class="af-renew-apply">
                    <div class="af-renew-box">
                        <div class="af-renew-apply-label">I would like to apply for renewal of my accreditation as:</div>
                        <div class="af-renew-apply-choices">
                            @include('practitioner.form._check', ['checked' => $form['applying_as'] === 'consultant', 'label' => 'OSH CONSULTANT'])
                            @include('practitioner.form._check', ['checked' => $form['applying_as'] === 'practitioner', 'label' => 'OSH PRACTITIONER'])
                        </div>
                    </div>
                </td>
                <td class="af-renew-gap"></td>
                <td class="af-renew-accr">
                    <div class="af-renew-box">
                        <table class="af-line af-renew-lines">
                            <tr>
                                <td class="af-line-label af-renew-number-label">Accreditation No:</td>
                                <td class="af-line-value af-value">{{ $accr['number'] }}</td>
                            </tr>
                        </table>
                        <table class="af-line af-renew-lines">
                            <tr>
                                <td class="af-line-label">Date accredited:</td>
                                <td class="af-line-value af-value">{{ $accr['accredited'] }}</td>
                            </tr>
                        </table>
                        <table class="af-line af-renew-lines">
                            <tr>
                                <td class="af-line-label">Date of last renewal:</td>
                                <td class="af-line-value af-value">{{ $accr['last_renewal'] }}</td>
                            </tr>
                        </table>
                        <table class="af-line af-renew-lines">
                            <tr>
                                <td class="af-line-label">Validity:</td>
                                <td class="af-line-value af-value">{{ $accr['validity'] }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        @include('practitioner.form._section', ['number' => 1, 'title' => 'Profile'])
        <table class="af-fields">
            <tr class="af-sizer">
                <td class="af-w-28"></td><td class="af-w-28"></td><td class="af-w-11"></td><td class="af-w-11"></td><td class="af-w-11"></td><td class="af-w-11"></td>
            </tr>
            <tr class="af-band">
                <td colspan="2">
                    <table class="af-name">
                        <tr>
                            <td class="af-name-last"><span class="af-label af-label-strong">Last Name</span></td>
                            <td class="af-name-first"><span class="af-label af-label-strong">First Name</span></td>
                            <td class="af-name-middle"><span class="af-label af-label-strong">Middle Name</span></td>
                            <td class="af-name-suffix"><span class="af-label af-label-strong">Suffix</span></td>
                        </tr>
                    </table>
                </td>
                <td><span class="af-label">Sex:</span></td>
                <td colspan="3"><span class="af-label">Civil Status:</span></td>
            </tr>
            <tr class="af-band-after">
                <td colspan="2">
                    <table class="af-name">
                        <tr>
                            <td class="af-name-last"><span class="af-value">{{ $form['name']['last'] }}</span></td>
                            <td class="af-name-first"><span class="af-value">{{ $form['name']['first'] }}</span></td>
                            <td class="af-name-middle"><span class="af-value">{{ $form['name']['middle'] }}</span></td>
                            <td class="af-name-suffix"><span class="af-value">{{ $form['name']['suffix'] }}</span></td>
                        </tr>
                    </table>
                </td>
                <td class="af-center af-sex">
                    @include('practitioner.form._check', ['checked' => $form['sex'] === 'Male', 'label' => 'M'])
                    @include('practitioner.form._check', ['checked' => $form['sex'] === 'Female', 'label' => 'F'])
                </td>
                <td colspan="3">
                    <table class="af-checks">
                        <tr>
                            <td>@include('practitioner.form._check', ['checked' => $form['civil_status'] === 'Single', 'label' => 'Single'])</td>
                            <td>@include('practitioner.form._check', ['checked' => $form['civil_status'] === 'Widow/er', 'label' => 'Widower/Widow'])</td>
                        </tr>
                        <tr>
                            <td>@include('practitioner.form._check', ['checked' => $form['civil_status'] === 'Married', 'label' => 'Married'])</td>
                            <td>@include('practitioner.form._check', ['checked' => $form['civil_status'] === 'Separated', 'label' => 'Separated'])</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                @include('practitioner.form._cell', ['label' => 'City/Mailing Address (No. & Street, Town/City, Province, Zip Code)', 'value' => $f['city_address'], 'span' => 6])
            </tr>
            <tr>
                @include('practitioner.form._cell', ['label' => 'Home/Provincial Address', 'value' => $f['home_address'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Home No.:', 'value' => $f['home_tel'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Cellular Phone No. (if any):', 'value' => $f['cellular'], 'span' => 2])
            </tr>
            {{-- The business address runs down beside the company numbers and the e-mail, as printed. --}}
            <tr>
                <td colspan="2" rowspan="2">
                    <span class="af-label">Business Address</span>
                    <span class="af-value af-block">{{ $f['business_address'] }}</span>
                </td>
                @include('practitioner.form._cell', ['label' => 'Co. Tel No.:', 'value' => $f['co_tel'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Fax No.:', 'value' => $f['fax'], 'span' => 2])
            </tr>
            <tr>
                @include('practitioner.form._cell', ['label' => 'E-mail:', 'value' => $f['email'], 'span' => 4, 'plain' => true])
            </tr>

            {{-- Workplace --}}
            <tr>
                @include('practitioner.form._cell', ['label' => 'Nature of Business / Specific Product/Service:', 'value' => $f['nature'], 'span' => 2, 'strong' => true])
                @include('practitioner.form._cell', ['label' => 'PSIC Code:', 'value' => $f['psic'], 'span' => 4])
            </tr>
            <tr>
                <td colspan="2">
                    <span class="af-label af-label-strong">Type of Workplace:</span>
                    <span class="af-workplace">
                        @include('practitioner.form._check', ['checked' => $form['hazard_level'] === 'Hazardous', 'label' => 'Hazardous'])
                        @include('practitioner.form._check', ['checked' => $form['hazard_level'] === 'Non-Hazardous', 'label' => 'Non-hazardous'])
                    </span>
                </td>
                <td colspan="4">
                    <span class="af-label af-label-strong">Employment Size:</span>
                    <span class="af-employment">
                        <span class="af-emp">MALE: <span class="af-blank af-value">{{ $f['male'] }}</span></span>
                        <span class="af-emp">FEMALE: <span class="af-blank af-value">{{ $f['female'] }}</span></span>
                        <span class="af-emp">TOTAL: <span class="af-blank af-value">{{ $f['total'] }}</span></span>
                    </span>
                </td>
            </tr>
            <tr class="af-shade">
                @include('practitioner.form._cell', ['label' => 'Region:', 'value' => $f['region'], 'span' => 2, 'strong' => true])
                @include('practitioner.form._cell', ['label' => 'GEO Code:', 'value' => $f['geo'], 'span' => 4, 'strong' => true])
            </tr>
        </table>

        <table class="af-section-split">
            <tr>
                <td>
                    @include('practitioner.form._section', [
                        'number' => 2, 'title' => 'Work Experience',
                        'note'   => 'since last issuance of accreditation',
                    ])
                </td>
                <td class="af-years">
                    <table class="af-years-box">
                        <tr>
                            <td class="af-years-value">{{ $form['osh_years'] }}</td>
                            <td class="af-years-label">Years of OSH<br>Experience</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        @include('practitioner.form._table', [
            'columns' => [["Position\n(From recent to previous)", 26], ['From', 12, 'af-center'], ['To', 12, 'af-center'], ["Length of\nservice", 10, 'af-center'], ["Status of\nAppointment", 14, 'af-center'], ['Name of Company', 26]],
            'rows'    => $form['tables']['work'],
            'group'   => ['Inclusive Dates', 1, 2],
            'tall'    => true,
        ])

        @include('practitioner.form._section', [
            'number' => 3, 'title' => 'Summary of Accomplishments on OSH',
            'aside'  => '(Please attach original summary of accomplishments duly certified by your immediate supervisor and employer using company letterhead. Photocopy of supporting documents should be attached (i.e. report on inspection, accident investigation, OSH programs/activities)',
        ])

        @include('practitioner.form._section', [
            'number' => 4, 'title' => 'OSH Related Trainings / Seminars Attended',
            'lead'   => '( As Participant ) -',
            'note'   => 'Since last issuance of accreditation. Please attach photocopy of certificate. Original copies of certificates to be presented to authorized DOLE staff for authentication.',
        ])
        @include('practitioner.form._table', [
            'columns' => [["Title\n(Start from latest)", 30], ['From', 10, 'af-center'], ['To', 10, 'af-center'], ["No. of\nHours", 10, 'af-center'], ['Conducted by', 17], ['Venue', 23]],
            'rows'    => $form['tables']['trainings'],
            'group'   => ['Time / Duration', 1, 2],
            'total'   => [3, $form['totals']['trainings']],
        ])
    </div>

    @include('practitioner.form._footer', ['reproduce' => 'can'])
</div>
