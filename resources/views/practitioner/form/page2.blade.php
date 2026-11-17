{{--
    AF-PCN-A1 page 2 — 4. Trainings attended, 5. Lectures conducted,
    6. Skills, 7. Awards. Expects $form, $pdf.
--}}
<div class="af-page" data-af-page="2">
    <div class="af-sheet af-sheet-spaced">
        @include('practitioner.form._header', ['page' => 2])

        @include('practitioner.form._section', [
            'number' => 4, 'title' => 'OSH Related Trainings / Seminars Attended',
            'lead'   => '(As Participant) -',
            'note'   => '(Use additional sheet if necessary) Please attach photocopy of certificate. Original copies of certificates to be presented to authorized DOLE staff for certification.',
        ])
        @include('practitioner.form._table', [
            'columns' => [["Title\n(Start from recent to previous)", 30], ['From', 9, 'af-center'], ['To', 8, 'af-center'], ["No. of\nHours", 11, 'af-center'], ['Conducted by', 23], ['Venue', 19]],
            'rows'    => $form['tables']['trainings'],
            'group'   => ['Time / Duration', 1, 2],
        ])

        @include('practitioner.form._section', [
            'number' => 5, 'title' => 'OSH Related Lectures / Seminars / Trainings Conducted',
            'lead'   => '(As Resource Speaker)',
            'note'   => '(Use additional sheet if necessary) Please attach photocopy of certificate/recognition received.',
        ])
        @include('practitioner.form._table', [
            'columns' => [["Title/Topic\n(Start from recent to previous)", 30], ['From', 9, 'af-center'], ['To', 8, 'af-center'], ["No. of\nHours", 11, 'af-center'], ['Conducted by', 23], ['Venue', 19]],
            'rows'    => $form['tables']['lectures'],
            'group'   => ['Time / Duration', 1, 2],
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
            'columns' => [['Title', 41], ['Issued by', 31], ['Date Issued', 28, 'af-center']],
            'rows'    => $form['tables']['awards'],
        ])
    </div>

    @include('practitioner.form._footer')
</div>
