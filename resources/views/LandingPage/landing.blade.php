<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMS – Accreditation Reporting and Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body>

{{-- ══ NAVBAR ══ --}}
<nav class="navbar navbar-arms navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="#">
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
                    <a class="nav-link btn-nav-ext" href="https://oshc.dole.gov.ph/" target="_blank">
                        OSHC Website <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.75rem;"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

{{-- ══ HERO ══ --}}
<section class="hero">
    <img
        src="https://oshc-arms.com/wp-content/uploads/2026/02/OSHC_paint1-1024x686.jpg"
        class="hero-bg-img"
        alt=""
        aria-hidden="true"
    >
    <div class="hero-overlay"></div>

    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 hero-content">
                <div class="hero-eyebrow">
                    Occupational Safety and Health Center
                </div>
                <h1 class="hero-title">
                    Advocate a <em>Safe</em> and Healthy Workplace
                </h1>
                <p class="hero-lead">
                    The Accreditation Reporting and Monitoring System (ARMS) is the official
                    digital platform for OSHC accreditation — raising standards and protecting
                    Filipino workers nationwide.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#portals" class="btn-hero-primary">
                        <i class="bi bi-file-earmark-check"></i> Apply for Accreditation
                    </a>
                    <a href="#portals" class="btn-hero-outline">
                        <i class="bi bi-arrow-repeat"></i> Renew Accreditation
                    </a>
                </div>
            </div>

            <div class="col-lg-5 offset-lg-1 hero-image-side d-none d-lg-block">
                <div class="hero-card-mockup">
                    <p style="font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:1rem;">Accreditation Portals</p>
                    <div class="card-icon-row">
                        <div class="card-badge">
                            <i class="bi bi-person-badge"></i>
                            <strong style="text-align: center;">OSH Professionals</strong>
                        </div>
                        <div class="card-badge">
                            <i class="bi bi-building-gear"></i>
                            <strong style="text-align: center;">Technical Service Provider</strong>
                        </div>
                        <div class="card-badge">
                            <i class="bi bi-mortarboard"></i>
                            <strong style="text-align: center;">Safety Training and Consultancy Organizations</strong>
                        </div>
                    </div>
                    <div style="border-top:1px solid rgba(255,255,255,.1);padding-top:1rem;">
                        <p style="font-size:.8rem;color:rgba(255,255,255,.5);margin:0;">
                            <i class="bi bi-check-circle-fill" style="color:var(--gold-light);"></i>
                            &nbsp;Official accreditation platform for all OSH professionals and organizations in the Philippines.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══ STATS BAND ══ --}}
<section class="stats-band">
    <div class="container">
        <div class="row g-0">
            @php
                $stats = [
                    ['num' => '800+', 'label' => 'Practitioners'],
                    ['num' => '800+', 'label' => 'Consultants'],
                    ['num' => '800+', 'label' => 'Safety Training Orgs'],
                    ['num' => '800+', 'label' => 'Safety Consultancy Orgs'],
                    ['num' => '800+', 'label' => 'First Aid Training Providers'],
                    ['num' => '800+', 'label' => 'WEM Providers'],
                    ['num' => '800+', 'label' => 'CHETO'],
                ];
            @endphp
            @foreach($stats as $stat)
            <div class="col stat-item">
                <span class="stat-num">{{ $stat['num'] }}</span>
                <span class="stat-label">{{ $stat['label'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══ PORTALS ══ --}}
<section id="portals" class="section-py bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">Access Portals</p>
            <h2 class="section-title">Choose Your Accreditation Category</h2>
            <p class="section-desc mx-auto mt-2">
                Select the portal that corresponds to your role or organization type to log in or apply for OSHC accreditation.
            </p>
        </div>

        <div class="row g-4">

            {{-- Portal 1 --}}
            <div class="col-md-4">
                <div class="portal-card">
                    <div class="portal-icon"><i class="bi bi-person-badge-fill"></i></div>
                    <h3>OSH Professionals</h3>
                    <p>Individual occupational safety and health practitioners and consultants seeking official OSHC accreditation.</p>
                    <ul class="portal-sub-list">
                        <li>OSH Practitioners</li>
                        <li>OSH Consultants</li>
                    </ul>
                    <div class="portal-actions">
                        <a href="#" class="btn-portal-login">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        <a href="#" class="btn-portal-register">
                            <i class="bi bi-person-plus"></i> Register
                        </a>
                    </div>
                </div>
            </div>

            {{-- Portal 2 --}}
            <div class="col-md-4">
                <div class="portal-card">
                    <div class="portal-icon"><i class="bi bi-building-gear"></i></div>
                    <h3>Technical Service Providers</h3>
                    <p>Organizations providing technical work environment measurement and heavy equipment testing services.</p>
                    <ul class="portal-sub-list">
                        <li>Work and  Environment Measurement Providers</li>
                        <li>Construction Heavy Equipment Testing Orgs</li>
                    </ul>
                    <div class="portal-actions">
                        <a href="#" class="btn-portal-login">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        <a href="#" class="btn-portal-register">
                            <i class="bi bi-building-add"></i> Register
                        </a>
                    </div>
                </div>
            </div>

            {{-- Portal 3 --}}
            <div class="col-md-4">
                <div class="portal-card">
                    <div class="portal-icon"><i class="bi bi-mortarboard-fill"></i></div>
                    <h3>Safety Training and Consultancy Organizations</h3>
                    <p>Organizations delivering safety training programs, consultancy services, and first aid training to workplaces.</p>
                    <ul class="portal-sub-list">
                        <li>Safety Training Organizations</li>
                        <li>Safety Consultancy Organizations</li>
                        <li>First Aid Training Providers</li>
                    </ul>
                    <div class="portal-actions">
                        <a href="#" class="btn-portal-login">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        <a href="#" class="btn-portal-register">
                            <i class="bi bi-building-add"></i> Register
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ══ CTA BAND ══ --}}
<section class="cta-band">
    <div class="container position-relative" style="z-index:2;">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="section-label" style="color:var(--gold-light);">Get Started Today</p>
                <h2>Raise the Standard — <br>Be OSHC Accredited</h2>
                <p class="mt-3">
                    Join thousands of certified OSH professionals and organizations committed to building safer workplaces across the Philippines.
                </p>
            </div>
            <div class="col-lg-5 d-flex flex-wrap gap-3 justify-content-lg-end">
                <a href="#portals" class="btn-cta">
                    <i class="bi bi-file-earmark-check"></i> Apply Now
                </a>
                <a href="https://oshc.dole.gov.ph/" target="_blank" class="btn-cta-ghost">
                    Learn About OSHC <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

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

</body>
</html>