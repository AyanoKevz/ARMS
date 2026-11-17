{{--
    The AF-PCR-A2 renewal form as a standalone document for dompdf. Styled by
    af-pcn-a1.css with the AF-PCN-A1 it shares its partials with.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>AF-PCR-A2 {{ $form['accreditation']['number'] }}</title>
    <link rel="stylesheet" href="{{ public_path('css/af-pcn-a1.css') }}">
</head>
<body class="af-pdf">
    <div class="af-form">
        @include('practitioner.renewal-form.page1', ['pdf' => true])
        @include('practitioner.renewal-form.page2', ['pdf' => true])
    </div>
</body>
</html>
