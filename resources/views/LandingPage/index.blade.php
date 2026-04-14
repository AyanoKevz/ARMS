@extends('layouts.landing')

@section('title', 'ARMS – Accreditation Reporting and Monitoring System')

@section('content')
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

            <div class="col-lg-5 offset-lg-1 hero-image-side d-none d-lg-block text-center pt-5">
                <a href="{{ route('track') }}" class="btn btn-warning btn-lg px-4 py-3 fw-bold shadow-lg" style="border-radius: 50px;">
                    <i class="bi bi-search me-2"></i> Track Your Application
                </a>
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
@endsection
