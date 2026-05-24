<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Certificate of Accreditation – {{ $accreditation->accreditation_number }}</title>
    <style>
        /* ─── Page ──────────────────────────────────────────────────────────── */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Times New Roman", Times, serif;
            background: #ffffff;
            color: #1a1a1a;
            width: 210mm;
            height: 297mm;
        }

        /* ─── Signature block – aligned inside bottom table ─────────────────── */
        .signature-block {
            width: 100%;
            text-align: center;
        }

        .sig-name {
            font-family: "Times New Roman", Times, serif;
            font-size: 13pt;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 0.3px;
        }

        .sig-title {
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            color: #1a1a1a;
            margin-top: 1mm;
        }

        .sig-org {
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            color: #1a1a1a;
            margin-top: 0.5mm;
        }

        /* ─── Main content ────────────────────────────────────────────────── */
        .cert-content {
            padding: 32mm 25mm 0 25mm;
            text-align: center;
        }

        /* ─── "CERTIFICATE OF ACCREDITATION" – serif small-caps ──────────── */
        .cert-title {
            font-family: "Copperplate Gothic Bold", "Copperplate", "Copperplate Gothic", serif;
            font-size: 24pt;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 2px;
            margin-bottom: 2mm;
        }

        /* ─── "as" ───────────────────────────────────────────────────────── */
        .cert-as {
            font-family: "Calibri", "Calibri (Body)", "Carlito", sans-serif;
            font-size: 14pt;
            color: #1a1a1a;
            margin-bottom: 1px;
        }

        /* ─── "FIRST AID TRAINING PROVIDER" – heavy black sans-serif ─────── */
        .cert-type {
            font-family: "Arial Black", "Helvetica Neue", Arial, sans-serif;
            font-size: 24pt;
            font-weight: 900;
            color: #1a1a1a;
            letter-spacing: 1px;
            text-transform: uppercase;
            line-height: 1.15;
            margin-bottom: 2mm;
        }

        /* ─── "No." and "Valid until:" ────────────────────────────────────── */
        .info-section {
            margin-bottom: 4mm;
        }

        .info-row {
            font-family: "Times New Roman", Times, serif;
            font-size: 16pt;
            color: #1a1a1a;
            margin: 1mm 0;
        }

        .info-row-valid {
            font-family: "Times New Roman", Times, serif;
            font-size: 14pt;
            color: #1a1a1a;
            margin: 1mm 0;
        }

        /* ─── "This certifies that –" ────────────────────────────────────── */
        .certifies-line {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            font-style: italic;
            color: #1a1a1a;
            text-align: left;
            margin-bottom: 4mm;
        }

        /* Font size for certifies-line kept at default; no specific pt given – italicized per spec */

        /* ─── Company name (large bold serif, centered) ──────────────────── */
        .fatpro-name {
            font-family: "Bodoni MT", "Bodoni 72", "Bodoni", "Didot", serif;
            font-size: 32pt;
            font-weight: bold;
            color: #1a1a1a;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 6mm;
            text-align: center;
        }

        /* ─── Body paragraphs – Script MT Bold ────────────────────────────── */
        .body-copy {
            font-family: "Script MT Bold", "Brush Script MT", cursive;
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.45;
            color: #1a1a1a;
            text-align: justify;
        }

        .validity-statement {
            margin-top: 3mm;
        }

        .given-text {
            font-family: "Script MT Bold", "Brush Script MT", cursive;
            font-size: 14pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-top: 3mm;
            line-height: 1.45;
            text-align: left;
        }

        /* ─── Footer note ────────────────────────────────────────────────── */
        .footer-note {
            position: fixed;
            bottom: 10mm;
            left: 0;
            width: 100%;
            text-align: center;
            font-family: "Arial Narrow", "Arial", Helvetica, sans-serif;
            font-size: 7.5pt;
            color: #333;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>

    @php
    $accNumber = $accreditation->accreditation_number;
    $validUntil = $accreditation->validity_date->format('F d, Y');
    $dateIssued = $accreditation->date_of_accreditation;
    $dayNum = (int) $dateIssued->format('j');
    $suffix = match(true) {
    in_array($dayNum % 100, [11, 12, 13]) => 'th',
    ($dayNum % 10) === 1 => 'st',
    ($dayNum % 10) === 2 => 'nd',
    ($dayNum % 10) === 3 => 'rd',
    default => 'th',
    };
    $givenDay = $dayNum . $suffix;
    $givenMonthYear = $dateIssued->format('F Y');
    @endphp

    {{-- ── Main certificate content ──────────────────────────────────────────── --}}
    <div class="cert-content">

        {{-- Title --}}
        <div class="cert-title">Certificate of Accreditation</div>
        <div class="cert-as">as</div>
        <div class="cert-type">First Aid Training Provider</div>

        {{-- Accreditation info --}}
        <div class="info-section">
            <div class="info-row">No. {{ $accNumber }}</div>
            <div class="info-row-valid">Valid until: {{ $validUntil }}</div>
        </div>

        {{-- Certifies body --}}
        <div class="certifies-line">This certifies that &ndash;</div>

        <div class="fatpro-name">{{ $fatproName }}</div>

        <div class="body-copy">
            has been accredited by the Occupational Safety and Health Center (OSHC)
            by virtue of DOLE Department Order No. 235, Series of 2022, to conduct in the
            Philippines the 1-day Emergency First Aid, 2-day Occupational First Aid, and
            4-day Standard First Aid Training Courses within the validity period.
            <div class="validity-statement">
                This accreditation shall be valid until {{ $validUntil }}, unless otherwise
                suspended or revoked in accordance with the Order.
            </div>
        </div>

        <div class="given-text">
            Given this {{ $givenDay }} day of {{ $givenMonthYear }} at Quezon City, Philippines.
        </div>

        {{-- ── Bottom layout table (Signature Block pushed to the right side) ── --}}
        <table style="width: 100%; margin-top: 12mm; border: none; border-collapse: collapse;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%; text-align: center; vertical-align: top; padding: 0;">
                    <div class="signature-block">
                        <div class="sig-name">JOSE MARIA S. BATINO</div>
                        <div class="sig-title">Executive Director</div>
                        <div class="sig-org">Occupational Safety and Health Center</div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

    <div class="footer-note">
        (Accreditation is valid per requirements set forth in D.O. No. 235-22 and RA No. 11058 and its IRR D.O. No. 198-18.)
    </div>

</body>

</html>