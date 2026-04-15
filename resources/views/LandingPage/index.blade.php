@extends('layouts.landing')

@section('title', 'ARMS – Accreditation Reporting and Monitoring System')

@section('content')
    {{-- ══ HERO ══ --}}
    <section class="hero">
        <img src="https://oshc-arms.com/wp-content/uploads/2026/02/OSHC_paint1-1024x686.jpg" class="hero-bg-img" alt=""
            aria-hidden="true">
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
                </div>

                <div class="col-lg-5 offset-lg-1 hero-image-side mt-5 mt-lg-0">
                    <div class="hero-btns">
                        <a href="#portals" class="btn-hero-primary">
                            <i class="bi bi-file-earmark-check"></i> Apply for Accreditation
                        </a>
                        <a href="#portals" class="btn-hero-outline">
                            <i class="bi bi-arrow-repeat"></i> Renew Accreditation
                        </a>
                        <a href="{{ route('track') }}" class="btn-hero-primary fw-bold fs-5">
                            <i class="bi bi-search"></i> Track Your Application
                        </a>
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
                @foreach ($stats as $stat)
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
                <p class="section-label">Access Portal</p>
                <h2 class="section-title">OSHC Accreditation Portal</h2>
                <p class="section-desc mx-auto mt-2">
                    One portal for all accreditation types. Log in to manage your existing accreditation
                    or register to start a new application.
                </p>
            </div>

            <div class="row justify-content-center g-4">

                {{-- Single Unified Portal Card --}}
                <div class="col-md-8 col-lg-6">
                    <div class="portal-card text-center">
                        <div class="portal-icon mx-auto mb-4" style="width:72px;height:72px;font-size:2rem;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3 class="mb-2">ARMS Accreditation Portal</h3>
                        <p class="mb-1">
                            For <strong>Practitioners</strong>, <strong>Consultants</strong>, and all
                            <strong>Accredited Organizations</strong> — manage your accreditation in one place.
                        </p>

                        <ul class="portal-sub-list text-start mt-3 mb-4">
                            <li>OSH Practitioners &amp; Consultants</li>
                            <li>Safety Training &amp; Consultancy Organizations</li>
                            <li>Work &amp; Environment Measurement Providers</li>
                            <li>Construction Heavy Equipment Testing Orgs</li>
                            <li>First Aid Training Providers</li>
                        </ul>

                        <div class="portal-actions">
                            <a href="{{ route('login') }}" class="btn-portal-login">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                            <a href="{{ route('register') }}" class="btn-portal-register">
                                <i class="bi bi-person-plus"></i> Register
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ══ CTA BAND ══ --}}
    <section class="cta-band" style="background: url('{{ asset('images/CTA_bg.jpg') }}') center/cover no-repeat;">
        <div style="position: absolute; inset: 0; background: rgba(13, 43, 85, 0.88);"></div>
        <div class="container position-relative" style="z-index:2;">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <p class="section-label" style="color:var(--gold-light);">Get Started Today</p>
                    <h2>Raise the Standard — <br>Be OSHC Accredited</h2>
                    <p class="mt-3">
                        Join thousands of certified OSH professionals and organizations committed to building safer
                        workplaces across the Philippines.
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
