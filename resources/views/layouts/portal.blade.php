<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ARMS') | Portal</title>

    <!-- Gentelella CSS Base -->
    <link rel="stylesheet" href="{{ asset('gentelella/assets/init-Cvid-qA8.css') }}">
    <link rel="stylesheet" href="{{ asset('gentelella/assets/leaflet-CIGW-MKW.css') }}">
    <link rel="stylesheet" href="{{ asset('gentelella/assets/choices-CINgOKWX.css') }}">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    @stack('styles')
</head>

<body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <!-- Sidebar Navigation -->
            <div class="col-md-3 left_col" aria-label="Sidebar navigation">
                <div class="left_col scroll-view">
                    <div class="navbar nav_title border-0">
                        <a href="{{ url('/') }}" class="site_title">
                            <i class="fas fa-paw"></i> <span>ARMS Portal</span>
                        </a>
                    </div>

                    <div class="clearfix"></div>

                    <!-- menu profile quick info -->
                    <div class="profile clearfix">
                        <div class="profile_pic">
                            <img src="{{ asset('gentelella/images/img.jpg') }}" alt="..." class="img-circle profile_img" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">
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
                    <div class="sidebar-footer hidden-small">
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Settings">
                            <span class="fas fa-cog" aria-hidden="true"></span>
                        </a>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="FullScreen">
                            <span class="fas fa-expand" aria-hidden="true"></span>
                        </a>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Lock">
                            <span class="fas fa-eye-slash" aria-hidden="true"></span>
                        </a>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Logout" href="{{ route('login') }}">
                            <span class="fas fa-power-off" aria-hidden="true"></span>
                        </a>
                    </div>
                    <!-- /menu footer buttons -->
                </div>
            </div>

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
                                    <img src="{{ asset('gentelella/images/img.jpg') }}" alt="" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">{{ Auth::user()->name ?? 'User' }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-usermenu dropdown-menu-sm" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="javascript:;"> Profile</a>
                                    <a class="dropdown-item" href="javascript:;">
                                        <span class="badge bg-red float-end">50%</span>
                                        <span>Settings</span>
                                    </a>
                                    <a class="dropdown-item" href="javascript:;">Help</a>
                                    <a class="dropdown-item" href="{{ route('login') }}"><i class="fas fa-sign-out-alt float-end"></i> Log Out</a>
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
                    ARMS Portal
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
    @stack('scripts')
</body>
</html>
