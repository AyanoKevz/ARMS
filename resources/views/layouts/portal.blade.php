<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ARMS') | Portal</title>
    <link rel="icon" href="{{ asset('images/oshc-icon.ico') }}" type="image/x-icon">

    <!-- Gentelella CSS Base -->
    <link rel="stylesheet" href="{{ asset('gentelella/assets/init-Cvid-qA8.css') }}">
    <link rel="stylesheet" href="{{ asset('gentelella/assets/leaflet-CIGW-MKW.css') }}">
    <link rel="stylesheet" href="{{ asset('gentelella/assets/choices-CINgOKWX.css') }}">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Portal UI Overrides -->
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">

    @stack('styles')
</head>

<body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <!-- Sidebar Navigation -->
            <div class="col-md-3 left_col" aria-label="Sidebar navigation">
                <div class="left_col scroll-view">
                    <div class="navbar nav_title border-0">
                        <a href="{{ url('/') }}" class="site_title" style="display:flex;align-items:center;gap:.6rem;text-decoration:none;">
                            <img src="{{ asset('images/oshc-logo.png') }}" alt="OSHC" style="height:34px;width:auto;flex-shrink:0;">
                            <span style="font-family:'Poppins',sans-serif;font-size:.9rem;font-weight:700;color:#fff;line-height:1.1;">
                                OSHC-ARMS
                                <span style="color:#D4AC4B;font-weight:500;font-size:.7rem;display:block;letter-spacing:.1em;text-transform:uppercase;">
                                    @yield('sidebar_subheading')
                                </span>
                            </span>
                        </a>
                    </div>

                    <div class="clearfix"></div>

                    <!-- menu profile quick info -->
                    <div class="profile clearfix">
                        <div class="profile_pic">
                            <img src="{{ asset(Auth::user()->user_photo ?? 'gentelella/images/img.jpg') }}" alt="..." class="img-circle profile_img" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">
                        </div>
                        <div class="profile_info">
                            <span>Welcome,</span>
                            <h2>{{ Auth::user()->name ?? 'User' }}</h2>
                        </div>
                    </div>
                    <!-- /menu profile quick info -->

                    <br />

                    <!-- sidebar menu -->
                    <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
                        <div class="menu_section">
                            <h3>General</h3>
                            <ul class="nav side-menu">
                                @yield('sidebar')
                            </ul>
                        </div>
                    </div>
                    <!-- /sidebar menu -->

                    <!-- /menu footer buttons -->
                    <div class="sidebar-footer hidden-small d-flex flex-column align-items-center justify-content-center py-2" style="background: #091e3e; border-top: 1px solid var(--portal-gold);">
                        <div id="real-time-date" style="font-size: 0.7rem; font-weight: 500; color: var(--portal-gold); text-transform: uppercase; letter-spacing: 0.05em;"></div>
                        <div id="real-time-clock" style="font-size: 0.85rem; font-weight: 700; color: #fff; line-height: 1.2;"></div>
                    </div>
                    <!-- /menu footer buttons -->
                </div>
            </div>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>

            <script>
                function updateClock() {
                    const now = new Date();
                    const optionsDate = { month: 'short', day: '2-digit', year: 'numeric' };
                    const optionsTime = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
                    
                    document.getElementById('real-time-date').innerText = now.toLocaleDateString('en-US', optionsDate);
                    document.getElementById('real-time-clock').innerText = now.toLocaleTimeString('en-US', optionsTime);
                }
                setInterval(updateClock, 1000);
                updateClock(); // Initial call
            </script>

            <!-- top navigation -->
            <div class="top_nav">
                <div class="nav_menu d-flex align-items-center justify-content-between">
                    <div class="nav toggle">
                        <a id="menu_toggle"><i class="fas fa-bars"></i></a>
                    </div>
                    <nav class="nav navbar-nav ms-auto">
                        <ul class="navbar-right d-flex align-items-center gap-3 pe-3">
                            <li class="nav-item dropdown">
                                <a href="#" role="button" class="user-profile dropdown-toggle" aria-haspopup="true" id="navbarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="{{ asset(Auth::user()->user_photo ?? 'gentelella/images/img.jpg') }}" alt="" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">{{ Auth::user()->name ?? 'User' }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-usermenu dropdown-menu-sm" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('profile.index') }}" style="color: #222 !important;"> Profile</a>
                                    <a class="dropdown-item" href="#" style="color: #222 !important;" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="fas fa-sign-out-alt float-end" style="color: #222 !important;"></i> Log Out
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- /top navigation -->

            <!-- page content -->
            <main class="right_col" role="main" aria-label="Main content">
                @yield('content')
            </main>
            <!-- /page content -->

            <!-- footer content -->
            <footer>
                <div class="pull-right">
                 Copyright &copy; {{ date('Y') }} Occupational Safety and Health Center. All rights reserved.
                </div>
                <div class="clearfix"></div>
            </footer>
            <!-- /footer content -->
        </div>
    </div>

    <!-- Gentelella Base JS (Core dependencies and initialization) -->
    <script type="module" src="{{ asset('gentelella/js/vendor-core-BT4uIdWA.js') }}"></script>
    <script type="module" src="{{ asset('gentelella/js/vendor-forms-35DJolKh.js') }}"></script>
    <script type="module" src="{{ asset('gentelella/js/purify.es-BRHCahJ2.js') }}"></script>
    <script type="module" src="{{ asset('gentelella/js/security-ChJmw7QZ.js') }}"></script>
    <script type="module" src="{{ asset('gentelella/js/init-CkBYjM5e.js') }}"></script>
    <script type="module" src="{{ asset('gentelella/js/main-minimal-Ba_GM_Ws.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert:not(.alert-important)');
                alerts.forEach(alert => {
                    // Use Bootstrap's own close method if possible, or just fade out
                    if (window.bootstrap && bootstrap.Alert) {
                        const alertInstance = bootstrap.Alert.getOrCreateInstance(alert);
                        if (alertInstance) alertInstance.close();
                    } else {
                        alert.style.transition = "opacity 0.6s ease";
                        alert.style.opacity = "0";
                        setTimeout(() => alert.remove(), 600);
                    }
                });
            }, 5000);
        });
    </script>
    @stack('scripts')
</body>
</html>
