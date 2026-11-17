<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Certificate of Accreditation – {{ $accreditation->accreditation_number }}</title>
    {{-- OSHC OSH Practitioner templates (NEW / RENEWAL - OSH PRACTITIONER FRONT and BACK);
         shares the certificate stylesheet with FATPro's (admin/accreditation_certificate). --}}
    <link rel="stylesheet" href="{{ public_path('css/accreditation-certificate.css') }}">
</head>

<body>

    @php
        // From PractitionerController::certificate: $practitionerName ("Juan D. Cruz"),
        // $honorific (Mr. / Ms. / null), $field (Type of Industry), $certType, $bodyCopy,
        // $footerNote, $executiveDirector, $isRenewal, $backSignatory, $backSignatoryTitle.
        $accNumber  = $accreditation->accreditation_number;
        $validUntil = $accreditation->validity_date->format('F j, Y');
        $dateIssued = $accreditation->date_of_accreditation;
        $dayNum     = (int) $dateIssued->format('j');
        $daySuffix  = match (true) {
            in_array($dayNum % 100, [11, 12, 13]) => 'th',
            ($dayNum % 10) === 1 => 'st',
            ($dayNum % 10) === 2 => 'nd',
            ($dayNum % 10) === 3 => 'rd',
            default => 'th',
        };
        $givenMonthYear = $dateIssued->format('F Y');

        // The template's 36 pt name and 32 pt field, a step smaller when long.
        $nameLength  = mb_strlen($practitionerName);
        $nameSize    = $nameLength > 80 ? 'cert-name-sm' : ($nameLength > 40 ? 'cert-name-md' : '');
        $fieldLength = mb_strlen((string) $field);
        $fieldSize   = $fieldLength > 40 ? 'cert-prac-field-sm' : ($fieldLength > 20 ? 'cert-prac-field-md' : '');

        $holder = trim(($honorific ? $honorific . ' ' : '') . $practitionerName);
    @endphp

    {{-- ── Front ─────────────────────────────────────────────────────────── --}}
    <div class="cert-front">
        <div class="cert-title">CERTIFICATE OF ACCREDITATION</div>
        <div class="cert-number">No. {{ $accNumber }}</div>
        <div class="cert-valid">Valid until: {{ $validUntil }}</div>

        <div class="cert-prac-type">{{ $certType }}</div>
        <div class="cert-prac-in-field">in the field of</div>
        <div class="cert-prac-field {{ $fieldSize }}">
            @if(filled($field)){{ $field }}@else<span class="cert-prac-field-blank"></span>@endif
        </div>

        <div class="cert-certifies">This certifies that &ndash;</div>

        <div class="cert-name {{ $nameSize }}">{{ $practitionerName }}</div>

        <div class="cert-body">
            <p>{{ $bodyCopy }}</p>
            <p class="cert-given">Given this {{ $dayNum }}<sup>{{ $daySuffix }}</sup> day of {{ $givenMonthYear }} at Quezon City, Philippines.</p>
        </div>

        <div class="cert-signatory">
            <div class="cert-signatory-name">{{ $executiveDirector }}</div>
            <div class="cert-signatory-title">Executive Director</div>
            <div class="cert-signatory-title">Occupational Safety and Health Center</div>
        </div>

        <div class="cert-footer-note cert-prac-footer-note">{{ $footerNote }}</div>
    </div>

    {{-- ── Back ──────────────────────────────────────────────────────────── --}}
    <div class="cert-back cert-prac-back">
        <p>I hereby agree to assume the following duties and responsibilities in connection with my accreditation as <strong><u>{{ $certType }}.</u></strong></p>

        <table class="cert-prac-list">
            <tr><td class="cert-prac-marker">1.</td><td>To assist, advise or guide the employer in complying with the provisions of the OSHS including the development of safety and health programs and submission of DOLE reporting requirements.</td></tr>
            <tr><td class="cert-prac-marker">2.</td><td>To make at least a quarterly appraisal of safety programs and safety performance of my company and submit the same to OSHC on a quarterly basis.</td></tr>
            <tr><td class="cert-prac-marker">3.</td><td>To be present during scheduled safety inspection by authorized government agents and during health and safety committee meetings.</td></tr>
            <tr><td class="cert-prac-marker">4.</td><td>To renew my accreditation within the prescriptive period of 30 calendar days prior to expiration by complying with the following requirements for renewal.</td></tr>
        </table>
        <table class="cert-prac-list cert-prac-sublist">
            <tr><td class="cert-prac-marker">a.)</td><td>updated resume using the prescribed form, 2 copies with 1x1 ID picture (red background).</td></tr>
            <tr><td class="cert-prac-marker">b.)</td><td>summary of individual OSH accomplishments duly noted by the employer.</td></tr>
            <tr><td class="cert-prac-marker">c.)</td><td>original certification of community service/s rendered on safety and health.</td></tr>
            <tr><td class="cert-prac-marker">d.)</td><td>photocopy of additional OSH trainings attended.</td></tr>
        </table>

        <p>I affix my signature to perform the above stated duties and responsibilities with integrity and honesty by avoiding illegal and unlawful acts.</p>

        {{-- The practitioner signs here (CONFORME) --}}
        <div class="cert-prac-conforme">
            <div class="cert-prac-sign-line"></div>
            <div class="cert-prac-sign-caption">(Signature over printed name)</div>
            <div class="cert-prac-date {{ $isRenewal ? 'is-renewal' : '' }}">Date <span class="cert-prac-date-line"></span></div>
        </div>

        <div class="cert-prac-certification {{ $isRenewal ? 'is-renewal' : '' }}">
            <div class="cert-prac-heading">CERTIFICATION</div>

            <p class="cert-prac-single">This hereby certifies that <strong>{{ $holder }}</strong> has satisfactorily complied with all the requirements {{ $isRenewal ? 'for the renewal of accreditation' : 'for accreditation' }} as Occupational Safety and Health Practitioner per the following;</p>

            <table class="cert-prac-list cert-prac-bullets">
                <tr><td class="cert-prac-marker">&bull;</td><td>DOLE Department Order No. 16, series of 2001 &ndash; Rule 1030 Training and Accreditation of Personnel on Occupational Safety and Health of the Occupational Safety and Health Standards (OSHS), and</td></tr>
                <tr><td class="cert-prac-marker">&bull;</td><td>DOLE A.O. No. 407 Series of 2017 &ndash; Transferring the Function of Accreditation of OSH Practitioners and Accreditation and Performance Audit of Testing Organization for Construction Heavy Equipment from the BWC and DOLE Regional Offices to the OSHC.</td></tr>
            </table>

            @if($isRenewal)
                <p class="cert-prac-single cert-prac-active"><strong>{{ $holder }}</strong> has been an active OSH Practitioner for more than three (3) years prior to this renewal.</p>
            @endif
        </div>

        <div class="cert-prac-signatory {{ $isRenewal ? 'is-renewal' : '' }}">
            <div class="cert-prac-signatory-line"></div>
            <div class="cert-prac-signatory-name">{{ $backSignatory }}</div>
            <div>{{ $backSignatoryTitle }}</div>
        </div>
    </div>

</body>

</html>
