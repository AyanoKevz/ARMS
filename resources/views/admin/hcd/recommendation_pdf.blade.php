<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Recommendation Form - {{ $application->tracking_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 18mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-cell {
            width: 65px;
        }
        .title-cell {
            text-align: center;
        }
        .logo-text {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .form-table td {
            padding: 4px 3px;
            vertical-align: top;
        }
        .field-label {
            width: 25%;
            font-weight: bold;
        }
        .field-colon {
            width: 2%;
            font-weight: bold;
        }
        .field-value {
            width: 73%;
            border-bottom: 1px solid #777;
        }
        .box {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #000;
            text-align: center;
            line-height: 13px;
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 9pt;
            margin-right: 4px;
        }
        .box-x {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #000;
            text-align: center;
            line-height: 13px;
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 9pt;
            margin-right: 4px;
        }
        .recommendation-grid {
            width: 100%;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        .recommendation-grid td {
            width: 33.33%;
            padding: 3px;
            vertical-align: middle;
            font-size: 9.5pt;
        }
        .note {
            font-style: italic;
            font-size: 9.5pt;
            margin: 8px 0;
        }
        .signature-section {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .signature-block {
            width: 50%;
            float: right;
            text-align: center;
        }
        .signature-line {
            width: 80%;
            margin: 0 auto;
            border-bottom: 1px solid #000;
            padding-top: 30px;
        }
        .signature-name {
            font-weight: bold;
            margin-top: 3px;
            font-size: 10pt;
        }
        .signature-title {
            font-size: 9pt;
            color: #333;
        }
        .oed-section {
            width: auto;
            border-top: 2px solid #000;
            margin-top: 15px;
            margin-left: -12px;
            margin-right: -12px;
            margin-bottom: -12px;
            padding: 8px 12px 12px 12px;
        }
        .oed-header {
            font-weight: bold;
            font-size: 10pt;
            border-bottom: 1px solid #000;
            padding-bottom: 4px;
            margin-left: -12px;
            margin-right: -12px;
            padding-left: 12px;
            padding-right: 12px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .clear {
            clear: both;
        }
    </style>
</head>
<body>

    <?php
        $logoPath = public_path('images/oshc-logo.png');
        $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';

        $region = null;
        if ($application->user->organizationProfile && $application->user->organizationProfile->region) {
            $region = $application->user->organizationProfile->region;
        } elseif ($application->user->individualProfile && $application->user->individualProfile->region) {
            $region = $application->user->individualProfile->region;
        }

        $accTypeName = $application->accreditationType->name ?? '';
        $accTypeMap = [
            'Practitioners' => 'OSH PRACTITIONER',
            'Consultant' => 'OSH CONSULTANT',
            'Safety Training Organizations' => 'STO',
            'Safety Consultancy Organizations' => 'SCO',
            'Construction Heavy Equipment Testing Organizations' => 'CHETO',
            'Work and Environment Measurement Providers' => 'WEM PROVIDER',
            'First Aid Training Providers' => 'FATPro',
        ];
        $checkedType = isset($accTypeMap[$accTypeName]) ? $accTypeMap[$accTypeName] : '';

        $interviewersList = '';
        if (is_array($interviewers) && count($interviewers) > 0) {
            $interviewersList = implode('<br>', array_map('e', $interviewers));
        }

        $specValue = !empty($specialization) ? $specialization : '';
    ?>


    <div style="border: 2px solid #000; padding: 12px;">

    <div style="width: auto; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; margin-left: -12px; margin-right: -12px; padding-left: 12px; padding-right: 12px;">
        <table style="width: auto; margin: 0 auto; border-collapse: collapse;">
            <tr>
                <td style="vertical-align: middle; padding-right: 12px;">
                    <?php if ($logoBase64): ?>
                        <img src="<?php echo $logoBase64; ?>" alt="OSHC Logo" style="width: 55px; height: auto; display: block;">
                    <?php endif; ?>
                </td>
                <td style="vertical-align: middle; text-align: center;">
                    <div class="logo-text" style="font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2;">Occupational Safety and Health Center</div>
                    <div class="form-title" style="font-size: 10.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-top: 1px;">Recommendation Form</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="form-table">
        <tr>
            <td class="field-label">Date</td>
            <td class="field-colon">:</td>
            <td class="field-value" colspan="3"><?php echo \Carbon\Carbon::parse($date)->format('n/d/Y'); ?></td>
        </tr>
        <tr>
            <td class="field-label">From</td>
            <td class="field-colon">:</td>
            <td class="field-value" colspan="3"><?php echo e($from); ?></td>
        </tr>
        <tr>
            <td class="field-label">To</td>
            <td class="field-colon">:</td>
            <td class="field-value" colspan="3"><?php echo e($to); ?></td>
        </tr>
        <tr>
            <td class="field-label">Name of Applicant</td>
            <td class="field-colon">:</td>
            <td style="border-bottom: 1px solid #777; font-weight: bold; text-transform: uppercase; width: 48%; padding-bottom: 2px;">
                <?php echo e($applicantName); ?>
            </td>
            <td style="width: 10%; text-align: right; font-weight: bold; vertical-align: bottom; padding-bottom: 2px;">Region:</td>
            <td style="border-bottom: 1px solid #777; width: 15%; vertical-align: bottom; text-align: center; padding-bottom: 2px;">
                <?php echo $region ? e($region) : '&nbsp;'; ?>
            </td>
        </tr>
        <tr>
            <td class="field-label">Type of Accreditation</td>
            <td class="field-colon">:</td>
            <td style="padding-bottom: 4px;" colspan="3">
                <span class="box<?php echo $application->application_type === 'new' ? '-x' : ''; ?>"><?php echo $application->application_type === 'new' ? 'X' : ''; ?></span> NEW
                <span style="margin-left: 25px;"></span>
                <span class="box<?php echo $application->application_type === 'renewal' ? '-x' : ''; ?>"><?php echo $application->application_type === 'renewal' ? 'X' : ''; ?></span> RENEWAL
                <span style="margin-left: 25px;"></span>
                <span class="box<?php echo $application->application_type === 'reinstatement' ? '-x' : ''; ?>"><?php echo $application->application_type === 'reinstatement' ? 'X' : ''; ?></span> REINSTATEMENT
            </td>
        </tr>
        <tr>
            <td class="field-label">Recommendation</td>
            <td class="field-colon">:</td>
            <td colspan="3">Approval of application for accreditation as:</td>
        </tr>
    </table>

    <table class="recommendation-grid">
        <tr>
            <td><span class="box"><?php echo $checkedType === 'OSH PRACTITIONER' ? 'X' : ''; ?></span> OSH PRACTITIONER</td>
            <td><span class="box"><?php echo $checkedType === 'OSH CONSULTANT' ? 'X' : ''; ?></span> OSH CONSULTANT</td>
            <td><span class="box"><?php echo $checkedType === 'STO' ? 'X' : ''; ?></span> STO</td>
        </tr>
        <tr>
            <td><span class="box"><?php echo $checkedType === 'SCO' ? 'X' : ''; ?></span> SCO</td>
            <td><span class="box"><?php echo $checkedType === 'CHETO' ? 'X' : ''; ?></span> CHETO</td>
            <td><span class="box"><?php echo $checkedType === 'WEM PROVIDER' ? 'X' : ''; ?></span> WEM PROVIDER</td>
        </tr>
        <tr>
            <td>
                <span class="box"></span> OH PRACTITIONER
                <div style="font-size: 8pt; margin-left: 18px; color: #444;">
                    Physician ___ &nbsp; Nurse ___
                </div>
            </td>
            <td><span class="box"><?php echo $checkedType === 'FATPro' ? 'X' : ''; ?></span> First Aid Training Provider (FATPro)</td>
            <td></td>
        </tr>
    </table>

    <table class="form-table" style="margin-top: 4px;">
        <tr>
            <td class="field-label">Specialization/Industry</td>
            <td class="field-colon">:</td>
            <td class="field-value"><?php echo e($specValue); ?></td>
        </tr>
        <tr>
            <td class="field-label">Evaluator</td>
            <td class="field-colon">:</td>
            <td class="field-value"><?php echo e($evaluator); ?></td>
        </tr>
        <tr>
            <td class="field-label">Interviewer(s)</td>
            <td class="field-colon">:</td>
            <td class="field-value"><?php echo $interviewersList; ?></td>
        </tr>
    </table>

    <div class="note">
        Enclosed are the documents submitted by the applicant for your further review.
    </div>

    <div style="margin-top: 15px; margin-bottom: 10px; width: 100%;">
        <div style="font-weight: bold; margin-bottom: 2px; text-align: left;">Recommended by:</div>
        <div style="width: 60%; margin: 0 auto; text-align: center;">
            <div style="width: 100%; border-bottom: 1px solid #000; padding-top: 35px; margin-bottom: 4px;"></div>
            <div style="font-weight: bold; font-size: 10pt; text-transform: uppercase;"><?php echo e($recommended_by); ?></div>
            <div style="font-size: 9pt; color: #333;">Division Chief</div>
        </div>
    </div>

    <div class="clear"></div>

    <div class="oed-section">
        <div class="oed-header">For use by the Office of the Executive Director</div>
        <table class="form-table" style="border: none; margin-bottom: 0;">
            <tr>
                <td class="field-label" style="width: 15%;">Date</td>
                <td class="field-colon" style="width: 2%;">:</td>
                <td style="width: 83%;"><span style="display: inline-block; width: 50%; border-bottom: 1px solid #777;">&nbsp;</span></td>
            </tr>
            <tr>
                <td class="field-label">To</td>
                <td class="field-colon">:</td>
                <td><span style="display: inline-block; width: 50%; border-bottom: 1px solid #777;">Data Control</span></td>
            </tr>
            <tr>
                <td class="field-label" style="vertical-align: middle;">Approved by</td>
                <td class="field-colon" style="vertical-align: middle;">:</td>
                <td style="padding-top: 25px;">
                    <div style="width: 70%; border-bottom: 1px solid #000; text-align: center; font-weight: bold; margin-bottom: 2px; text-transform: uppercase;">
                        <?php echo e($approved_by); ?>
                    </div>
                    <div style="width: 70%; font-size: 9pt; color: #333; text-align: center;">
                        Executive Director
                    </div>
                </td>
            </tr>
        </table>
    </div>

    </div> <!-- end outer box -->

</body>
</html>
