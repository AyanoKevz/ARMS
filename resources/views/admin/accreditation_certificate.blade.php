<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Certificate of Accreditation – {{ $accreditation->accreditation_number }}</title>
    {{-- Layout copied from the DOLE-OSHC Word templates; see the stylesheet's header. --}}
    <link rel="stylesheet" href="{{ public_path('css/accreditation-certificate.css') }}">
</head>

<body>

    @php
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

        // FATPro wording unless the caller passes its own (OSH Practitioner /
        // Consultant — Admin\Accreditation\PractitionerController::certificate).
        $certType   = $certType ?? 'First Aid Training Provider';
        $bodyCopy   = $bodyCopy ?? 'has been accredited by the Occupational Safety and Health Center (OSHC) by virtue of DOLE Department Order No. 235, Series of 2022, to conduct in the Philippines the 1-day Emergency First Aid, 2-day Occupational First Aid, and 4-day Standard First Aid Training Courses within the validity period.';
        $footerNote = $footerNote ?? '(Accreditation is valid per requirements set forth in D.O. No. 235-22 and RA No. 11058 and its IRR D.O. No. 198-18.)';

        // Page 2 — FATPro only: 'new' or 'renewal' (FATPRO TEMPLATE NEW / RENEWAL (BACK)).
        $backPage = $backPage ?? null;

        // The template's 36 pt name, a step smaller for a long one.
        $nameLength = mb_strlen($fatproName);
        $nameSize   = $nameLength > 80 ? 'cert-name-sm' : ($nameLength > 40 ? 'cert-name-md' : '');
    @endphp

    {{-- ── Front ─────────────────────────────────────────────────────────── --}}
    <div class="cert-front">
        <div class="cert-title">CERTIFICATE OF ACCREDITATION</div>
        <div class="cert-as">as</div>
        <div class="cert-type">{{ $certType }}</div>
        <div class="cert-number">No. {{ $accNumber }}</div>
        <div class="cert-valid">Valid until: {{ $validUntil }}</div>

        <div class="cert-certifies">This certifies that &ndash;</div>

        <div class="cert-name {{ $nameSize }}">{{ $fatproName }}</div>

        <div class="cert-body">
            <p>{{ $bodyCopy }}</p>
            <p>This accreditation shall be valid until {{ $validUntil }}, unless otherwise suspended or revoked in accordance with the Order.</p>
            <p class="cert-given">Given this {{ $dayNum }}<sup>{{ $daySuffix }}</sup> day of {{ $givenMonthYear }} at Quezon City, Philippines.</p>
        </div>

        <div class="cert-signatory">
            <div class="cert-signatory-name">{{ $executiveDirector }}</div>
            <div class="cert-signatory-title">Executive Director</div>
            <div class="cert-signatory-title">Occupational Safety and Health Center</div>
        </div>

        <div class="cert-footer-note">{{ $footerNote }}</div>
    </div>

    {{-- ── Back (FATPro) ─────────────────────────────────────────────────── --}}
    @if($backPage)
        <div class="cert-back cert-fatpro-back">
            <div class="cert-back-heading">CONDITIONS</div>

            <p>The Occupational Safety and Health Center (OSHC) shall conduct observation on its conduct of first-aid training courses to determine compliance with the criteria set forth by the OSHC and to assess the quality and effectiveness of its training staff, systems, and methods of training.</p>

            <p>Renewal of accreditation will be based on the results of observation/evaluation on the actual conduct of OSH trainings and upon compliance with the minimum requirements stated in the First Aid Training Provider&rsquo;s Manual of Procedures and in Section 3, Rule III of Department Order 235 series 2022 - &ldquo;Rules on the Accreditation of First Aiders and the Accreditation of First Aid Training Providers&rdquo;.</p>

            <p>Accreditation may be suspended or cancelled pursuant to the provisions of Sections 10 to 13, Rule III of Department Order 235-22.</p>

            <div class="cert-conforme-label">CONFORME:</div>
            <div class="cert-conforme">
                <div class="cert-sign-line"></div>
                <div>(Signature over printed name)</div>
            </div>

            <div class="cert-certification">
                <div class="cert-back-heading cert-back-heading-lg">CERTIFICATION</div>

                <p>This hereby certifies that <strong>{{ $fatproName }}</strong> has satisfactorily complied with all the requirements for the {{ $backPage === 'renewal' ? 'renewal of ' : '' }}accreditation as First Aid Training Provider (FATPro) per the Department Order 235 series 2022 - &ldquo;Rules on the Accreditation of First Aiders and the Accreditation of First Aid Training Providers&rdquo;.</p>
            </div>

            {{-- Each signatory's name on the template's bold line under the signature
                 line — typed in the pop-up, or blank to write by hand. --}}
            <div class="cert-hcd-signatories">
                <div class="cert-hcd-line"></div>
                <div class="cert-hcd-name">{{ $hcdSupervisor ?? '' }}</div>
                <div>Supervising Occupational Health Officer</div>

                <div class="cert-hcd-gap"></div>

                <div class="cert-hcd-line"></div>
                <div class="cert-hcd-name">{{ $hcdChief ?? '' }}</div>
                <div>Chief, Health Control Division</div>
            </div>
        </div>
    @endif

</body>

</html>
