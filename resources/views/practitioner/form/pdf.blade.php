{{--
    The AF-PCN-A1 application form as a standalone document for dompdf.
    Generated on submission (PractitionerApplicationService) and attached to
    the application for the Accreditation admins.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>AF-PCN-A1 {{ $form['tracking_number'] }}</title>
    <link rel="stylesheet" href="{{ public_path('css/af-pcn-a1.css') }}">
</head>
<body class="af-pdf">
    <div class="af-form">
        @include('practitioner.form.page1', ['pdf' => true])
        @include('practitioner.form.page2', ['pdf' => true])
        @include('practitioner.form.page3', ['pdf' => true])
    </div>
</body>
</html>
