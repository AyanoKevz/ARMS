{{--
    AF-PCR-A2 page 2 — 5. Lectures conducted, 6. Skills, 7. Awards, and the
    certification with the signature and right thumb mark. Expects $form, $pdf.
--}}
<div class="af-page af-page-last" data-af-page="2">
    <div class="af-sheet af-sheet-spaced">
        @include('practitioner.form._header', ['page' => 2, 'sheet' => \App\Support\PractitionerForm::RENEWAL_SHEET])

        @include('practitioner.form._section', [
            'number' => 5, 'title' => 'OSH Related Lectures / Seminars / Trainings Conducted',
            'lead'   => '( As Resource Speaker )',
            'note'   => '(Since last issuance of accreditation. Please attach photocopy of certificate/recognition received.)',
        ])
        @include('practitioner.form._table', [
            'columns' => [['Title / Topic', 30], ['From', 9, 'af-center'], ['To', 8, 'af-center'], ["No. of\nHours", 10, 'af-center'], ['Conducted By', 24], ['Venue', 19]],
            'rows'    => $form['tables']['lectures'],
            'group'   => ['Time / Duration', 1, 2],
            'total'   => [3, $form['totals']['lectures']],
        ])

        @include('practitioner.form._section', [
            'number' => 6, 'title' => 'OSH Skills / Expertise / Specialization Acquired',
            'note'   => '(Use additional sheet if necessary)',
        ])
        @include('practitioner.form._table', [
            'columns' => [['Trade / Occupation', 29], ['Field of Expertise', 24], ['Brief Description', 30], ["Years of\nExperience", 17, 'af-center']],
            'rows'    => $form['tables']['skills'],
        ])

        @include('practitioner.form._section', [
            'number' => 7, 'title' => 'OSH Awards / Achievements / Recognition Received',
            'note'   => '(Use additional sheet if necessary). Attach photocopy of certificate of award/recognition',
        ])
        @include('practitioner.form._table', [
            'columns' => [['Title', 36], ['Brief Description', 28], ['Issued By', 22], ['Date issued', 14, 'af-center']],
            'rows'    => $form['tables']['awards'],
        ])

        <table class="af-certify af-certify-renewal">
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

    @include('practitioner.form._footer', ['reproduce' => 'can'])
</div>
