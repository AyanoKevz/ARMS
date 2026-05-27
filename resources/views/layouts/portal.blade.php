<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ARMS') | Portal</title>
    <link rel="icon" href="{{ asset('images/oshc-icon.ico') }}" type="image/x-icon">

    <!-- Gentelella CSS Base -->
    <link rel="stylesheet" href="{{ asset('gentelella/assets/init-Cvid-qA8.css') }}">

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
                            <img src="{{ asset(Auth::user()->user_photo ?? 'images/profile_picture/default_photo.jpg') }}" alt="..." class="img-circle profile_img" loading="lazy" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">
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
                    <div class="sidebar-footer hidden-small d-flex flex-column align-items-center justify-content-center" style="background: #091e3e; border-top: 1px solid var(--portal-gold);">
                        <div id="real-time-date" style="font-size: 0.65rem; font-weight: 500; color: var(--portal-gold); text-transform: uppercase; letter-spacing: 0.05em; line-height: 1;"></div>
                        <div id="real-time-clock" style="font-size: 0.8rem; font-weight: 700; color: #fff; line-height: 1.2;"></div>
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
                                <a href="#" class="dropdown-toggle info-number" id="navbarDropdown1" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-bell"></i>
                                    @if(auth()->user()->unreadNotifications->count() > 0)
                                        <span class="badge bg-danger rounded-pill" style="position: absolute; top: 0px; right: 0px; font-size: 0.6rem;">
                                            {{ auth()->user()->unreadNotifications->count() }}
                                        </span>
                                    @endif
                                </a>
                                <ul class="dropdown-menu list-unstyled msg_list dropdown-menu-end shadow" role="menu" aria-labelledby="navbarDropdown1" style="min-width: 300px; padding: 0;">
                                    <li class="p-2 border-bottom bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold ms-2">Notifications</span>
                                            <form action="{{ url('admin/notifications/mark-all-read') }}" method="POST" class="m-0 p-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-link text-decoration-none m-0 p-0 me-2" style="font-size: 0.8rem;">Mark all as read</button>
                                            </form>
                                        </div>
                                    </li>
                                    @forelse(auth()->user()->unreadNotifications->take(5) as $notification)
                                        <li class="border-bottom p-2" style="background: transparent;">
                                            <a class="dropdown-item d-flex flex-column text-wrap" href="{{ url('admin/notifications/' . $notification->id . '/read') }}" style="white-space: normal; line-height: 1.4; padding: 6px 12px; background: transparent;">
                                                <span class="text-muted" style="font-size: 0.72rem; display: block; margin-bottom: 3px; font-weight: normal;">{{ $notification->created_at->diffForHumans() }}</span>
                                                <span class="text-dark" style="font-size: 0.84rem; display: block; font-weight: 500; white-space: normal;">
                                                    {{ $notification->data['message'] ?? 'You have a new notification.' }}
                                                </span>
                                            </a>
                                        </li>
                                    @empty
                                        <li class="p-3 text-center text-muted small">
                                            No new notifications
                                        </li>
                                    @endforelse
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a href="#" role="button" class="user-profile dropdown-toggle" aria-haspopup="true" id="navbarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="{{ asset(Auth::user()->user_photo ?? 'images/profile_picture/default_photo.jpg') }}" alt="" loading="lazy" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">{{ Auth::user()->name ?? 'User' }}
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
            // Prevent Gentelella's JS height calculation feedback loop from stretching the page
            const rightCol = document.querySelector('.right_col');
            if (rightCol) {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.attributeName === 'style') {
                            if (rightCol.style.minHeight && rightCol.style.minHeight !== 'auto') {
                                observer.disconnect();
                                rightCol.style.minHeight = 'auto';
                                observer.observe(rightCol, { attributes: true, attributeFilter: ['style'] });
                            }
                        }
                    });
                });
                observer.observe(rightCol, { attributes: true, attributeFilter: ['style'] });
                rightCol.style.minHeight = 'auto';
            }

            // Prevent Gentelella from overriding the fixed sidebar height
            const leftCol = document.querySelector('.col-md-3.left_col');
            if (leftCol) {
                leftCol.style.height = '100vh';
                leftCol.style.minHeight = '100vh';
                const scrollView = leftCol.querySelector('.left_col.scroll-view');
                if (scrollView) {
                    scrollView.style.height = '100%';
                    scrollView.style.minHeight = '0';
                }
                const sidebarObserver = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.attributeName === 'style') {
                            sidebarObserver.disconnect();
                            leftCol.style.height = '100vh';
                            leftCol.style.minHeight = '100vh';
                            leftCol.style.position = 'fixed';
                            if (scrollView) {
                                scrollView.style.height = '100%';
                                scrollView.style.minHeight = '0';
                            }
                            sidebarObserver.observe(leftCol, { attributes: true, attributeFilter: ['style'] });
                        }
                    });
                });
                sidebarObserver.observe(leftCol, { attributes: true, attributeFilter: ['style'] });
            }

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
