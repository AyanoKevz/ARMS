<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Recommendation Form - {{ $application->tracking_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .logo-text {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-title {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .form-table td {
            padding: 6px 4px;
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
        .checkbox-container {
            margin: 10px 0;
        }
        .checkbox-row {
            margin-bottom: 5px;
        }
        .box {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #000;
            text-align: center;
            line-height: 14px;
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 10pt;
            margin-right: 5px;
        }
        .box.checked {
            background-color: #fff;
        }
        .box.checked::after {
            content: "X";
        }
        .recommendation-grid {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .recommendation-grid td {
            width: 33.33%;
            padding: 4px;
            vertical-align: middle;
        }
        .note {
            font-style: italic;
            font-size: 10pt;
            margin: 15px 0;
        }
        .signature-section {
            width: 100%;
            margin-top: 25px;
            margin-bottom: 25px;
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
            padding-top: 40px;
        }
        .signature-name {
            font-weight: bold;
            margin-top: 5px;
        }
        .signature-title {
            font-size: 10pt;
            color: #333;
        }
        .oed-section {
            width: 100%;
            border: 2px solid #000;
            margin-top: 30px;
            padding: 10px;
            box-sizing: border-box;
        }
        .oed-header {
            font-weight: bold;
            font-size: 11pt;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .clear {
            clear: both;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo-text">Occupational Safety and Health Center</div>
        <div class="form-title">Recommendation Form</div>
    </div>

    <table class="form-table">
        <tr>
            <td class="field-label">Date</td>
            <td class="field-colon">:</td>
            <td class="field-value">{{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</td>
        </tr>
        <tr>
            <td class="field-label">From</td>
            <td class="field-colon">:</td>
            <td class="field-value">{{ $from }}</td>
        </tr>
        <tr>
            <td class="field-label">To</td>
            <td class="field-colon">:</td>
            <td class="field-value">{{ $to }}</td>
        </tr>
        <tr>
            <td class="field-label">Name of Applicant</td>
            <td class="field-colon">:</td>
            <td class="field-value" style="font-weight: bold; text-transform: uppercase;">{{ $applicantName }}</td>
        </tr>
        <tr>
            <td class="field-label">Type of Accreditation</td>
            <td class="field-colon">:</td>
            <td style="padding-bottom: 6px;">
                <span class="box {{ $application->application_type === 'new' ? 'checked' : '' }}"></span> NEW
                <span style="margin-left: 30px;"></span>
                <span class="box {{ $application->application_type === 'renewal' ? 'checked' : '' }}"></span> RENEWAL
                <span style="margin-left: 30px;"></span>
                <span class="box {{ $application->application_type === 'reinstatement' ? 'checked' : '' }}"></span> REINSTATEMENT
            </td>
        </tr>
        <tr>
            <td class="field-label">Recommendation</td>
            <td class="field-colon">:</td>
            <td>Approval of application for accreditation as:</td>
        </tr>
    </table>

    <table class="recommendation-grid">
        <tr>
            <td><span class="box"></span> OSH PRACTITIONER</td>
            <td><span class="box"></span> OSH CONSULTANT</td>
            <td><span class="box"></span> STO</td>
        </tr>
        <tr>
            <td><span class="box"></span> SCO</td>
            <td><span class="box"></span> CHETO</td>
            <td><span class="box"></span> WEM PROVIDER</td>
        </tr>
        <tr>
            <td>
                <span class="box"></span> OH PRACTITIONER
                <div style="font-size: 9pt; margin-left: 20px; color: #444;">
                    [ ] Physician ___ [ ] Nurse ___
                </div>
            </td>
            <td><span class="box checked"></span> First Aid Training Provider (FATPro)</td>
            <td></td>
        </tr>
    </table>

    <table class="form-table" style="margin-top: 10px;">
        <tr>
            <td class="field-label">Specialization/Industry</td>
            <td class="field-colon">:</td>
            <td class="field-value">{{ $specialization ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Evaluator</td>
            <td class="field-colon">:</td>
            <td class="field-value">{{ $evaluator }}</td>
        </tr>
        <tr>
            <td class="field-label">Interviewer(s)</td>
            <td class="field-colon">:</td>
            <td class="field-value">
                @if(is_array($interviewers) && count($interviewers) > 0)
                    {{ implode(', ', $interviewers) }}
                @else
                    N/A
                @endif
            </td>
        </tr>
    </table>

    <div class="note">
        Enclosed are the documents submitted by the applicant for your further review.
    </div>

    <div class="signature-section">
        <div class="signature-block">
            <div>Recommended by:</div>
            <div class="signature-line"></div>
            <div class="signature-name">{{ $recommended_by }}</div>
            <div class="signature-title">Division Chief</div>
        </div>
    </div>
    
    <div class="clear"></div>

    <div class="oed-section">
        <div class="oed-header">For use by the Office of the Executive Director</div>
        <table class="form-table" style="border: none; margin-bottom: 0;">
            <tr>
                <td class="field-label" style="width: 15%;">Date</td>
                <td class="field-colon" style="width: 2%;">:</td>
                <td class="field-value" style="width: 83%;">&nbsp;</td>
            </tr>
            <tr>
                <td class="field-label">To</td>
                <td class="field-colon">:</td>
                <td class="field-value">Data Control</td>
            </tr>
            <tr>
                <td class="field-label">Approved by</td>
                <td class="field-colon">:</td>
                <td style="padding-top: 25px;">
                    <div style="width: 70%; border-bottom: 1px solid #000; text-align: center; font-weight: bold; margin-bottom: 2px;">
                        {{ $approved_by }}
                    </div>
                    <div style="font-size: 9pt; color: #555; text-align: left; padding-left: 20px;">
                        Executive Director
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
