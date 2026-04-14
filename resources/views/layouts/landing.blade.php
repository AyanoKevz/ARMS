<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ARMS – Accreditation Reporting and Monitoring System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    @stack('styles')
</head>
<body>

{{-- ══ NAVBAR ══ --}}
<nav class="navbar navbar-arms navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">
            <img src="{{ asset('images/oshc-logo.png') }}" class="brand-logo" alt="OSHC Logo">
            <div class="brand-text">
                <span class="brand-title">OSHC – ARMS</span>
                <span class="brand-sub">Accreditation Reporting and Monitoring System </span>
            </div>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <i class="bi bi-list text-white fs-4"></i>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="https://oshc.dole.gov.ph/about-us/" target="_blank">About OSHC</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="https://oshc.dole.gov.ph/contact-us-2/" target="_blank">Contact Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('track') }}">Track Application</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn-nav-ext" href="https://oshc.dole.gov.ph/" target="_blank">
                        OSHC Website <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.75rem;"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

{{-- ══ MAIN CONTENT ══ --}}
<main>
    @yield('content')
</main>

{{-- ══ FOOTER ══ --}}
<footer class="footer">
    <div class="footer-top">
        <div class="container">
            <div class="row g-5">

                <div class="col-lg-4">
                    <div class="footer-logo">
                        <img src="{{ asset('images/oshc-logo.png') }}" alt="OSHC Logo">
                    </div>
                    <p class="footer-brand-name">Occupational Safety and Health Center</p>
                    <address>
                        North Avenue corner Sen. Miriam P. Defensor-Santiago Avenue,<br>
                        Diliman, Quezon City, Philippines 1105
                    </address>
                    <div class="mt-3">
                        <div class="footer-contact-item">
                            <i class="bi bi-telephone-fill"></i>
                            <span>(02) 8-929-6036</span>
                        </div>
                        <div class="footer-contact-item">
                            <i class="bi bi-envelope-fill"></i>
                            <a href="mailto:oed@oshc.dole.gov.ph" style="color:rgba(255,255,255,.55);text-decoration:none;">oed@oshc.dole.gov.ph</a>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2 offset-lg-1">
                    <p class="footer-heading">Mandate</p>
                    <ul class="footer-links">
                        <li><a href="#">Training</a></li>
                        <li><a href="#">Research</a></li>
                        <li><a href="#">Technical Services</a></li>
                        <li><a href="#">Information Campaign</a></li>
                    </ul>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <p class="footer-heading">Events</p>
                    <ul class="footer-links">
                        <li><a href="#">Gawad Kaligtasan at Kalusugan (GKK)</a></li>
                        <li><a href="#">National OSH Congress</a></li>
                        <li><a href="#">Occupational Medicine Week</a></li>
                    </ul>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <p class="footer-heading">Portals</p>
                    <ul class="footer-links">
                        <li><a href="#">OSH Professionals Login</a></li>
                        <li><a href="#">OSH Professionals Register</a></li>
                        <li><a href="#">Technical Service Providers</a></li>
                        <li><a href="#">Training Orgs Login</a></li>
                        <li><a href="#">Training Orgs Register</a></li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <span>Copyright &copy; {{ date('Y') }} Occupational Safety and Health Center. All rights reserved.</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
