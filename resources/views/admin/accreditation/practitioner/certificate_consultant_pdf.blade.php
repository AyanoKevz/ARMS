<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Certificate of Accreditation – {{ $accreditation->accreditation_number }}</title>
    {{-- OSHC OSH Consultant templates (NEW / RENEWAL - OSH CONSULTANT FRONT and BACK):
         one front (the NEW one) for both, the back as in each template. Shares the
         certificate stylesheet with the FATPro and OSH Practitioner certificates. --}}
    <link rel="stylesheet" href="{{ public_path('css/accreditation-certificate.css') }}">
</head>

<body>

    @php
        // From PractitionerController::certificate: $practitionerName ("Romeo A. Espino, Jr."),
        // $honorific (Mr. / Ms. / null), $certType ("OSH Consultant"), $bodyCopy, $footerNote,
        // $executiveDirector, $isRenewal, $backSignatory, $backSignatoryTitle.
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

        // The template's 38 pt name, a step smaller when long.
        $nameLength = mb_strlen($practitionerName);
        $nameSize   = $nameLength > 80 ? 'cert-name-sm' : ($nameLength > 40 ? 'cert-name-md' : '');

        $holder = trim(($honorific ? $honorific . ' ' : '') . $practitionerName);
    @endphp

    {{-- ── Front ─────────────────────────────────────────────────────────── --}}
    <div class="cert-front cert-cons-front">
        <div class="cert-title">CERTIFICATE OF ACCREDITATION</div>
        <div class="cert-number">No. {{ $accNumber }}</div>
        <div class="cert-valid">Valid until: {{ $validUntil }}</div>

        <div class="cert-cons-osh">OCCUPATIONAL SAFETY AND HEALTH</div>
        <div class="cert-cons-type">CONSULTANT</div>

        <div class="cert-certifies">This certifies that &ndash;</div>

        <div class="cert-name {{ $nameSize }}">{{ $practitionerName }}</div>

        <div class="cert-body">
            <p>{{ $bodyCopy }}</p>
            <p class="cert-given">Given this {{ $dayNum }}<sup>{{ $daySuffix }}</sup> day of {{ $givenMonthYear }} at Quezon City, Philippines.</p>
        </div>

        <div class="cert-cons-signatory">
            <div class="cert-cons-signatory-name">{{ $executiveDirector }}</div>
            <div class="cert-cons-signatory-title">Executive Director</div>
            <div class="cert-cons-signatory-title">Occupational Safety and Health Center</div>
        </div>

        <div class="cert-footer-note cert-cons-footer-note">{{ $footerNote }}</div>
    </div>

    {{-- ── Back ──────────────────────────────────────────────────────────── --}}
    <div class="cert-back cert-cons-back {{ $isRenewal ? 'is-renewal' : '' }}">
        <p class="cert-cons-indent">I hereby agree to assume the following duties and responsibilities in connection with my accreditation as <strong><u>{{ $certType }}</u></strong>.</p>

        <table class="cert-prac-list cert-cons-list">
            <tr><td class="cert-prac-marker">1.</td><td>To assist, advise or guide the employer in complying with the provisions of the OSHS including the development of safety and health programs and submission of DOLE reporting requirements.</td></tr>
            <tr><td class="cert-prac-marker">2.</td><td>To make at least a quarterly appraisal of safety programs and safety performance of my company and submit the same to OSHC on a quarterly basis.</td></tr>
            <tr><td class="cert-prac-marker">3.</td><td>To be present during scheduled safety inspection by authorized government agents and during health and safety committee meetings.</td></tr>
            <tr><td class="cert-prac-marker">4.</td><td>To renew my accreditation within the prescriptive period of 30 calendar days prior to expiration by complying with the following requirements for renewal.</td></tr>
        </table>
        <table class="cert-prac-list cert-cons-sublist">
            <tr><td class="cert-prac-marker">a)</td><td>updated resume using DOLE prescribed form (2 copies with 1x1 ID picture in blue background).</td></tr>
            <tr><td class="cert-prac-marker">b)</td><td>summary of individual OSH accomplishments duly noted by the employer.</td></tr>
            <tr><td class="cert-prac-marker">c)</td><td>original certification of community service/s rendered on safety and health.</td></tr>
            <tr><td class="cert-prac-marker">d)</td><td>photocopy of additional OSH trainings attended.</td></tr>
        </table>

        <p class="cert-cons-indent">I affix my signature to perform the above stated duties and responsibilities with integrity and honesty by avoiding illegal and unlawful acts.</p>

        {{-- The consultant signs here --}}
        <div class="cert-cons-conforme">
            <div class="cert-cons-sign-line"></div>
            <div class="cert-cons-sign-caption">(Signature Over Printed Name)</div>
            <div class="cert-cons-date"><span class="cert-cons-date-label">Date:</span><span class="cert-cons-date-line"></span></div>
        </div>

        <div class="cert-cons-heading">CERTIFICATION</div>

        <p class="cert-cons-certification">This hereby certifies that <strong>{{ $holder }}</strong> has satisfactorily complied with all the requirements {{ $isRenewal ? 'for the renewal of accreditation' : 'for accreditation' }} as Occupational Safety and Health Consultant per the Occupational Safety and Health Standards (OSHS) and DOLE Department Order No. 16, series of 2001 &ndash; Rule 1030 Training and Accreditation of Personnel on Occupational Safety and Health.</p>

        @if($isRenewal)
            <p class="cert-cons-active"><strong>{{ $holder }}</strong> has been an active {{ $certType }} for more than three (3) years prior to this renewal.</p>
        @endif

        {{-- OIC-Deputy Executive Director: a signature line on the renewal template only --}}
        <div class="cert-cons-back-signatory">
            @if($isRenewal)
                <div class="cert-cons-back-line"></div>
            @endif
            <div class="cert-cons-back-name">{{ $backSignatory }}</div>
            <div>{{ $backSignatoryTitle }}</div>
        </div>
    </div>

</body>

</html>
