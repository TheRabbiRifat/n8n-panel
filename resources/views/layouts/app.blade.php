<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <title>n8n Panel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa; /* bg-light */
        }

        #wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
        }

        #sidebar {
            min-width: 260px;
            max-width: 260px;
            min-height: 100vh;
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            transition: margin-left 0.3s ease-in-out;
            z-index: 1040;
        }

        #sidebar .brand {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #dee2e6;
            background-color: #fff;
        }

        #sidebar .nav-group-title {
            padding: 1rem 1.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #adb5bd;
            letter-spacing: 0.05em;
        }

        #sidebar .nav-link {
            padding: 0.75rem 1.25rem;
            color: #495057;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }

        #sidebar .nav-link:hover {
            color: #0d6efd;
            background-color: #f8f9fa;
        }

        #sidebar .nav-link.active {
            color: #0d6efd;
            background-color: #e9ecef;
            border-left-color: #0d6efd;
            font-weight: 600;
        }

        #content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        #topbar {
            height: 64px;
            background-color: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Mobile Sidebar */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -260px;
                position: fixed;
                height: 100%;
            }
            #sidebar.show {
                margin-left: 0;
                box-shadow: 0 0 15px rgba(0,0,0,0.1);
            }
            #sidebar-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
            }
            #sidebar-overlay.show { display: block; }
        }

        .main-content {
            padding: 2rem;
            flex: 1;
        }
    </style>
</head>
<body>
    @auth
    <div id="sidebar-overlay"></div>
    <div id="wrapper">
        <!-- Sidebar -->
        <aside id="sidebar" class="d-flex flex-column">
            <div class="brand">
                <a href="{{ route('dashboard') }}" class="text-decoration-none">
                    <img src="{{ asset('images/logo.png') }}" alt="n8n Panel" style="max-height: 40px; width: auto;">
                </a>
            </div>

            <div class="p-3">
                <input type="text" class="form-control search-input bg-light border-0" placeholder="Search menu...">
            </div>

            <nav class="flex-grow-1 overflow-y-auto pb-4" id="sidebar-nav">
                @can('view_system')
                <div class="nav-group-title">Server Information</div>
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Server Status
                </a>
                @endcan

                @can('manage_instances')
                <div class="nav-group-title">Instance Management</div>
                <a href="{{ route('instances.index') }}" class="nav-link {{ request()->routeIs('instances.*') ? 'active' : '' }}">
                    <i class="bi bi-hdd-network"></i> List Instances
                </a>
                <a href="{{ route('instances.create') }}" class="nav-link">
                    <i class="bi bi-plus-circle"></i> Create Instance
                </a>
                @endcan

                <div class="nav-group-title">Packages</div>
                <a href="{{ route('packages.index') }}" class="nav-link {{ request()->routeIs('packages.*') ? 'active' : '' }}">
                    <i class="bi bi-box"></i> Packages
                </a>

                <div class="nav-group-title">Integration</div>
                <a href="{{ route('api-tokens.index') }}" class="nav-link {{ request()->routeIs('api-tokens.*') ? 'active' : '' }}">
                    <i class="bi bi-key"></i> Manage API Tokens
                </a>

                @can('manage_users')
                <div class="nav-group-title">Administration</div>
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Manage Users
                </a>
                @endcan

                @can('manage_settings')
                <a href="{{ route('admin.environment.index') }}" class="nav-link {{ request()->routeIs('admin.environment.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders"></i> Settings
                </a>
                <a href="{{ route('admin.backups.index') }}" class="nav-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">
                    <i class="bi bi-cloud-arrow-up"></i> Backups
                </a>
                <a href="{{ route('containers.orphans') }}" class="nav-link {{ request()->routeIs('containers.orphans') ? 'active' : '' }}">
                    <i class="bi bi-search"></i> Docker Discovery
                </a>
                @endcan

                @can('manage_roles')
                <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock"></i> Roles & Permissions
                </a>
                @endcan

                @can('view_system')
                <a href="{{ route('admin.system.index') }}" class="nav-link {{ request()->routeIs('admin.system.*') ? 'active' : '' }}">
                    <i class="bi bi-hdd-rack"></i> System Settings
                </a>
                @endcan

                @can('view_logs')
                <a href="{{ route('admin.api_logs.index') }}" class="nav-link {{ request()->routeIs('admin.api_logs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> API Logs
                </a>
                @endcan
            </nav>
        </aside>

        <div id="content-wrapper">
            <!-- Top Bar -->
            <header id="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button id="sidebarToggle" class="btn btn-light d-md-none text-secondary">
                        <i class="bi bi-list fs-4"></i>
                    </button>

                    <div class="d-none d-lg-block">
                        <div class="fw-bold text-dark">{{ $serverInfo['hostname'] ?? 'localhost' }}</div>
                        <div class="text-muted small">{{ $serverInfo['ips'] ?? '127.0.0.1' }}</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-none d-xl-flex align-items-center gap-2 small text-secondary">
                        <span class="fw-bold text-uppercase" style="font-size: 0.7rem;">Load:</span>
                        <span class="badge bg-light text-dark border">{{ $serverInfo['loads']['1'] ?? '0.00' }}</span>
                        <span class="badge bg-light text-dark border">{{ $serverInfo['loads']['5'] ?? '0.00' }}</span>
                        <span class="badge bg-light text-dark border">{{ $serverInfo['loads']['15'] ?? '0.00' }}</span>
                    </div>

                    <div class="vr mx-1 d-none d-xl-block"></div>

                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; font-weight: bold;">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                            <span class="d-none d-md-inline fw-medium">{{ Auth::user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i> Password & Security</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="main-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="text-center py-4 text-muted small border-top bg-white">
                &copy; {{ date('Y') }} n8n Host Manager. All rights reserved. <span class="ms-2 badge bg-secondary bg-opacity-10 text-secondary border">v1.0.0</span>
            </footer>
        </div>
    </div>
    @else
    <div class="d-flex min-vh-100 align-items-center justify-content-center bg-light">
         @yield('content')
    </div>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if(toggle && sidebar){
                const close = () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); };
                toggle.addEventListener('click', () => { sidebar.classList.add('show'); overlay.classList.add('show'); });
                overlay.addEventListener('click', close);
            }

            // Sidebar Search
            const searchInput = document.querySelector('.search-input');
            const navLinks = document.querySelectorAll('#sidebar-nav .nav-link');
            const navGroups = document.querySelectorAll('#sidebar-nav .nav-group-title');

            if(searchInput) {
                searchInput.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase();

                    navLinks.forEach(link => {
                        const text = link.textContent.trim().toLowerCase();
                        if(text.includes(term)) {
                            link.style.display = 'flex';
                        } else {
                            link.style.display = 'none';
                        }
                    });

                    // Hide empty groups
                    navGroups.forEach(group => {
                        let hasVisible = false;
                        let next = group.nextElementSibling;
                        while(next && !next.classList.contains('nav-group-title')) {
                            if(next.classList.contains('nav-link') && next.style.display !== 'none') {
                                hasVisible = true;
                                break;
                            }
                            next = next.nextElementSibling;
                        }
                        group.style.display = hasVisible ? 'block' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>
