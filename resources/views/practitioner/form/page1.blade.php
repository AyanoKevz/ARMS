{{--
    AF-PCN-A1 page 1 — application type, 1. Profile, 2. Educational
    Attainment, 3. Work Experience. Expects $form, $pdf.

    Every field of the new-applicant form, laid out the way the AF-PCR-A2
    renewal form is: the application type in a double-ruled box on one line,
    and the profile in plain full-width rows (no cell spans two rows) with
    the label on top of each cell. The rows share one six-column grid (the
    sizer row: 28 / 28 / 11 / 11 / 11 / 11) so the rules line up down the
    sheet.
--}}
@php $f = $form['field']; @endphp
<div class="af-page" data-af-page="1">
    <div class="af-sheet">
        @include('practitioner.form._header', ['page' => 1])

        <div class="af-apply">
            <table>
                <tr>
                    <td class="af-apply-label">I would like to apply for Accreditation as:</td>
                    <td class="af-apply-choice">@include('practitioner.form._check', ['checked' => $form['applying_as'] === 'consultant', 'label' => 'OSH Consultant'])</td>
                    <td class="af-apply-choice">@include('practitioner.form._check', ['checked' => $form['applying_as'] === 'practitioner', 'label' => 'OSH Practitioner'])</td>
                </tr>
            </table>
        </div>

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
            {{-- Personal details --}}
            <tr>
                @include('practitioner.form._cell', ['label' => 'Date of Birth:', 'value' => $f['birthday']])
                @include('practitioner.form._cell', ['label' => 'Citizenship:', 'value' => $f['citizenship']])
                @include('practitioner.form._cell', ['label' => 'Height (cm):', 'value' => $f['height'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Weight (kg):', 'value' => $f['weight'], 'span' => 2])
            </tr>
            <tr>
                @include('practitioner.form._cell', ['label' => 'Religion:', 'value' => $f['religion']])
                @include('practitioner.form._cell', ['label' => 'Blood Type:', 'value' => $f['blood_type']])
                @include('practitioner.form._cell', ['label' => 'TIN No.:', 'value' => $f['tin'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'PRC No. (if any):', 'value' => $f['prc_no'], 'span' => 2])
            </tr>

            {{-- Addresses and contact numbers --}}
            <tr>
                @include('practitioner.form._cell', ['label' => 'City Address (Number & Street, Town/City, Province, Zip Code)', 'value' => $f['city_address'], 'span' => 6])
            </tr>
            <tr>
                @include('practitioner.form._cell', ['label' => 'Home/Provincial Address', 'value' => $f['home_address'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Home No.:', 'value' => $f['home_tel'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Cellular Phone No. (if any):', 'value' => $f['cellular'], 'span' => 2])
            </tr>
            <tr>
                @include('practitioner.form._cell', ['label' => 'Business Address', 'value' => $f['business_address'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Co. Tel No.:', 'value' => $f['co_tel'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'Fax No.:', 'value' => $f['fax'], 'span' => 2])
            </tr>
            <tr>
                @include('practitioner.form._cell', ['label' => 'SSS/GSIS No.:', 'value' => $f['sss'], 'span' => 2])
                @include('practitioner.form._cell', ['label' => 'E-mail:', 'value' => $f['email'], 'span' => 4, 'plain' => true])
            </tr>

            {{-- Workplace --}}
            <tr>
                @include('practitioner.form._cell', ['label' => 'Nature of Business / Specific Product / Type of Service:', 'value' => $f['nature'], 'span' => 2, 'strong' => true])
                @include('practitioner.form._cell', ['label' => 'PSIC Code:', 'value' => $f['psic'], 'span' => 4, 'strong' => true])
            </tr>
            <tr>
                <td colspan="2">
                    <span class="af-label af-label-strong">Workplace:</span>
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
                @include('practitioner.form._cell', ['label' => 'GEO Code:', 'value' => $f['geo'], 'span' => 2, 'strong' => true])
                @include('practitioner.form._cell', ['label' => 'Zip Code:', 'value' => $f['zip'], 'span' => 2, 'strong' => true])
            </tr>
        </table>

        @include('practitioner.form._section', [
            'number' => 2, 'title' => 'Educational Attainment',
            'lead'   => '- indicate only tertiary education: Masteral, doctoral.',
            'note'   => 'Please attach photocopy of diploma / transcript of records.',
        ])
        @include('practitioner.form._table', [
            'columns' => [['Degree/units Earned', 34], ["School / Address\n(Last attended)", 33], ['Inclusive dates', 17, 'af-center'], ['Awards/ Honors', 16]],
            'rows'    => $form['tables']['education'],
            'tall'    => true,
        ])
        <div class="af-license">
            <table class="af-line">
                <tr>
                    <td class="af-line-label">Type of Professional License received:</td>
                    <td class="af-line-value af-value af-w-41">{{ $f['license_type'] }}</td>
                    <td></td>
                </tr>
            </table>
            <table class="af-line">
                <tr>
                    <td class="af-line-label">PRC License No.:</td>
                    <td class="af-line-value af-value af-w-24">{{ $f['license_no'] }}</td>
                    <td class="af-line-label">Date Issued:</td>
                    <td class="af-line-value af-value af-w-17">{{ $f['license_issued'] }}</td>
                    <td class="af-line-label">Validity:</td>
                    <td class="af-line-value af-value af-w-17">{{ $f['license_validity'] }}</td>
                    <td></td>
                </tr>
            </table>
        </div>

        <table class="af-section-split">
            <tr>
                <td>
                    @include('practitioner.form._section', [
                        'number' => 3, 'title' => 'Work Experience',
                        'note'   => '(Use additional sheet if necessary). Please attach original certificate of employment and job description duly certified by the Personnel Manager/ employer/or authorized company official using official company letter head; and proof of practice (safety report/programs prepared/implemented).',
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
            'columns' => [["Position\n(From recent to present)", 24], ['From', 9, 'af-center'], ['To', 7, 'af-center'], ["Length\nof service", 10, 'af-center'], ["Status of\nAppointment", 17, 'af-center'], ['Company', 33]],
            'rows'    => $form['tables']['work'],
            'group'   => ['Inclusive Dates', 1, 2],
            'tall'    => true,
        ])
    </div>

    @include('practitioner.form._footer')
</div>
