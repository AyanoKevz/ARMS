<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificate of Accreditation – {{ $accreditation->accreditation_number }}</title>
    <style>
        /* ─── Page ──────────────────────────────────────────────────────────── */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Times New Roman", Times, serif;
            background: #ffffff;
            color: #000000; /* Plain black text */
            width: 210mm;
            height: 297mm;
        }

        /* ─── Signature block – fixed to page bottom-right ─────────────────── */
        .signature-block {
            position: fixed;
            bottom: 22mm;
            right: 22mm;
            width: 68mm;
            text-align: center;
        }

        .sig-name {
            font-size: 10.5pt;
            font-weight: bold;
            color: #000000;
            letter-spacing: 0.2px;
        }

        .sig-title {
            font-size: 8.5pt;
            color: #000000;
            margin-top: 1.5mm;
        }

        .sig-org {
            font-size: 8pt;
            font-style: italic;
            color: #000000;
            margin-top: 0.8mm;
        }

        /* ─── Main content (normal document flow, padded for top margin) ── */
        .cert-content {
            /* Adjust top padding since header is removed to keep it roughly vertically centered */
            padding: 50mm 22mm 0 22mm;
            text-align: center;
        }

        /* ─── Title block ────────────────────────────────────────────────── */
        .cert-preamble {
            font-size: 8pt;
            font-style: italic;
            color: #000000;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .cert-title {
            font-size: 21pt;
            font-weight: bold;
            color: #000000;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.15;
            margin-bottom: 1.5mm;
        }

        .cert-as {
            font-size: 10pt;
            font-style: italic;
            color: #000000;
            margin-bottom: 1.2mm;
        }

        .cert-type {
            font-size: 13.5pt;
            font-weight: bold;
            color: #000000;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 8mm; /* Added margin to replace divider */
        }

        /* ─── Info box ───────────────────────────────────────────────────── */
        .info-box {
            /* Removed background and border, kept padding/margins */
            padding: 3.5mm 10mm;
            margin: 0 14mm 5mm 14mm;
        }

        .info-row {
            font-size: 10pt;
            color: #000000;
            margin: 1.5mm 0;
        }

        .info-label {
            font-style: italic;
        }

        .info-value {
            font-weight: bold;
        }

        /* ─── Body copy ──────────────────────────────────────────────────── */
        .certifies-line {
            font-size: 11pt;
            font-style: italic;
            color: #000000;
            margin: 5mm 0 2mm 0;
        }

        .fatpro-name {
            font-size: 19pt;
            font-weight: bold;
            color: #000000;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 8mm; /* Increased margin to replace the rule */
        }

        .body-copy {
            font-size: 10.5pt;
            line-height: 1.75;
            color: #000000;
            margin: 0 8mm;
            text-align: justify;
        }

        .validity-statement {
            margin-top: 15pt;
        }

        .given-text {
            font-size: 10.5pt;
            color: #000000;
            margin-top: 3mm;
            line-height: 1.6;
            text-align: left;
        }
    </style>
</head>
<body>

@php
    $accNumber      = $accreditation->accreditation_number;
    $validUntil     = $accreditation->validity_date->format('F d, Y');
    $dateIssued     = $accreditation->date_of_accreditation;
    $dayNum         = (int) $dateIssued->format('j');
    $suffix = match(true) {
        in_array($dayNum % 100, [11, 12, 13]) => 'th',
        ($dayNum % 10) === 1                  => 'st',
        ($dayNum % 10) === 2                  => 'nd',
        ($dayNum % 10) === 3                  => 'rd',
        default                               => 'th',
    };
    $givenDay       = $dayNum . $suffix;
    $givenMonthYear = $dateIssued->format('F Y');
@endphp

{{-- ── Signature block (fixed bottom-right) ───────────────────────────────── --}}
<div class="signature-block">
    <div class="sig-name">JOSE MARIA S. BATINO</div>
    <div class="sig-title">Executive Director</div>
    <div class="sig-org">Occupational Safety and Health Center</div>
</div>

{{-- ── Main certificate content (normal flow) ──────────────────────────────── --}}
<div class="cert-content">

    {{-- Title --}}
    <div class="cert-preamble">Certificate of Accreditation</div>
    <div class="cert-as">as</div>
    <div class="cert-type">First Aid Training Provider</div>

    {{-- Accreditation info box --}}
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">No. </span>
            <span class="info-value">{{ $accNumber }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Valid Until: </span>
            <span class="info-value">{{ $validUntil }}</span>
        </div>
    </div>

    {{-- Certifies body --}}
    <div class="certifies-line">This certifies that</div>

    <div class="fatpro-name">{{ $fatproName }}</div>

    <div class="body-copy">
        is hereby duly accredited as a <strong>First Aid Training Provider</strong> by the <strong>Occupational Safety and Health Center (OSHC)</strong> 
        by virtue of <strong>DOLE Department Order No. 235, Series of 2022 </strong>, to conduct in the Philippines the <strong>1-day Emergency First Aid</strong>, 
        <strong>2-day Occupational</strong>, and <strong>4-day Standard First Aid training Courses</strong> within the validity period. 
        <div class="validity-statement">
            This accreditation shall be valid until <strong>{{ $validUntil }}</strong>, unless otherwise suspended or revoked in accordance with Order.
        </div>
        <div class="given-text">
        Given this <strong>{{ $givenDay }}</strong> day of <strong>{{ $givenMonthYear }}</strong> at Quezon City, Philippines.
        </div>
    </div>

</div>

</body>
</html>
