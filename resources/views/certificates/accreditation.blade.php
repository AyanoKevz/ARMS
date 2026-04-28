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
            color: #1a1a1a;
            width: 210mm;
            height: 297mm;
        }

        /* ─── Single decorative border (position:fixed so DomPDF pins it reliably) ── */
        .page-border {
            position: fixed;
            top: 9mm;
            left: 9mm;
            right: 9mm;
            bottom: 9mm;
            border: 3px solid #9B870C;
        }

        /* ─── Watermark logo (centred behind content) ──────────────────────── */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 80mm;
            height: 80mm;
            margin-top: -40mm;
            margin-left: -40mm;
            opacity: 0.05;
        }

        /* ─── Signature block – fixed to page bottom-right ─────────────────── */
        .signature-block {
            position: fixed;
            bottom: 22mm;
            right: 22mm;
            width: 68mm;
            text-align: center;
        }

        .sig-line {
            border-top: 1.2px solid #1a1a1a;
            margin-bottom: 2mm;
        }

        .sig-name {
            font-size: 10.5pt;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 0.2px;
        }

        .sig-title {
            font-size: 8.5pt;
            color: #333;
            margin-top: 1.5mm;
        }

        .sig-org {
            font-size: 8pt;
            font-style: italic;
            color: #555;
            margin-top: 0.8mm;
        }

        /* ─── Footer strip – fixed to page bottom-centre ───────────────────── */
        .footer-strip {
            position: fixed;
            bottom: 12mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 6.5pt;
            color: #bbb;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        /* ─── Main content (normal document flow, padded inside the border) ── */
        .cert-content {
            padding: 20mm 22mm 0 22mm;
            text-align: center;
        }

        /* ─── Letterhead ─────────────────────────────────────────────────── */
        .logo {
            width: 20mm;
            height: auto;
            margin-bottom: 2.5mm;
        }

        .republic-text {
            font-size: 7.5pt;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: #2c2c2c;
            margin-bottom: 1mm;
        }

        .dept-text {
            font-size: 7pt;
            color: #3a3a3a;
            margin-bottom: 0.8mm;
        }

        .oshc-name {
            font-size: 10.5pt;
            font-weight: bold;
            color: #8B1A1A;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        /* ─── Dividers ──────────────────────────────────────────────────── */
        .divider-gold {
            border: none;
            border-top: 1.8px solid #9B870C;
            margin: 4.5mm 8mm;
        }

        .divider-thin {
            border: none;
            border-top: 0.7px solid #9B870C;
            margin: 3mm 18mm;
        }

        /* ─── Title block ────────────────────────────────────────────────── */
        .cert-preamble {
            font-size: 8pt;
            font-style: italic;
            color: #666;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .cert-title {
            font-size: 21pt;
            font-weight: bold;
            color: #8B1A1A;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.15;
            margin-bottom: 1.5mm;
        }

        .cert-as {
            font-size: 10pt;
            font-style: italic;
            color: #555;
            margin-bottom: 1.2mm;
        }

        .cert-type {
            font-size: 13.5pt;
            font-weight: bold;
            color: #2A3F54;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 4mm;
        }

        /* ─── Info box ───────────────────────────────────────────────────── */
        .info-box {
            background: #fdf8f0;
            border: 1px solid #d4af37;
            padding: 3.5mm 10mm;
            margin: 0 14mm 5mm 14mm;
        }

        .info-row {
            font-size: 10pt;
            color: #2c2c2c;
            margin: 1.5mm 0;
        }

        .info-label {
            font-style: italic;
            color: #7a5f00;
        }

        .info-value {
            font-weight: bold;
        }

        /* ─── Body copy ──────────────────────────────────────────────────── */
        .certifies-line {
            font-size: 11pt;
            font-style: italic;
            color: #555;
            margin: 5mm 0 2mm 0;
        }

        .fatpro-name {
            font-size: 19pt;
            font-weight: bold;
            color: #1a1a2e;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .name-rule {
            display: block;
            margin: 0 auto;
            width: 62%;
            border-top: 1.8px solid #9B870C;
            margin-bottom: 4.5mm;
            height: 0;
        }

        .body-copy {
            font-size: 10.5pt;
            line-height: 1.75;
            color: #333;
            margin: 0 8mm;
            text-align: justify;
        }

        .validity-statement {
            margin-top: 15pt;
        }

        .given-text {
            font-size: 10.5pt;
            color: #333;
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
    $logoBase64     = base64_encode(file_get_contents(public_path('images/oshc-logo.png')));
    $logoSrc        = 'data:image/png;base64,' . $logoBase64;
@endphp

{{-- ── Fixed page decoration ────────────────────────────────────────────── --}}
<div class="page-border"></div>

{{-- ── Watermark ─────────────────────────────────────────────────────────── --}}
<img class="watermark" src="{{ $logoSrc }}" alt="">

{{-- ── Signature block (fixed bottom-right) ───────────────────────────────── --}}
<div class="signature-block">
    <div class="sig-line"></div>
    <div class="sig-name">JOSE MARIA S. BATINO</div>
    <div class="sig-title">Executive Director</div>
    <div class="sig-org">Occupational Safety and Health Center</div>
</div>

{{-- ── Footer strip (fixed bottom-centre) ──────────────────────────────────── --}}
<div class="footer-strip">
  Accreditation is valid per requirements set forth in d.o no. 
  235-22 and ra no. 11058 and its irr d.o no. 198-18
</div>

{{-- ── Main certificate content (normal flow) ──────────────────────────────── --}}
<div class="cert-content">

    {{-- Letterhead --}}
    <img class="logo" src="{{ $logoSrc }}" alt="OSHC Logo">
    <div class="republic-text">Republic of the Philippines</div>
    <div class="dept-text">Department of Labor and Employment</div>
    <div class="oshc-name">Occupational Safety and Health Center</div>

    <hr class="divider-gold">

    {{-- Title --}}
    <div class="cert-preamble">This is to certify that</div>
    <div class="cert-title">Certificate of Accreditation</div>
    <div class="cert-as">as</div>
    <div class="cert-type">First Aid Training Provider</div>

    <hr class="divider-thin">

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
    <div class="name-rule"></div>

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
