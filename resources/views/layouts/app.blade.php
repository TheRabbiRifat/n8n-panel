<!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>n8n Panel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script>
        // Theme Toggler
        (() => {
            'use strict'
            const getStoredTheme = () => localStorage.getItem('theme')
            const setStoredTheme = theme => localStorage.setItem('theme', theme)
            const getPreferredTheme = () => {
                const storedTheme = getStoredTheme()
                if (storedTheme) { return storedTheme }
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
            }
            const setTheme = theme => {
                if (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark')
                } else {
                    document.documentElement.setAttribute('data-bs-theme', theme)
                }
            }
            setTheme(getPreferredTheme())
            window.toggleTheme = () => {
                const current = getStoredTheme() || 'auto';
                const next = current === 'dark' ? 'light' : 'dark';
                setStoredTheme(next);
                setTheme(next);
            }
        })()
    </script>

    <style>
        :root {
            --whm-sidebar-bg: #2c3e50;
            --whm-sidebar-text: #ecf0f1;
            --whm-sidebar-hover: #34495e;
            --whm-accent: #ff6c2c; /* Jupiter Orange Accent */
            --whm-header-bg: #ffffff;
            --whm-header-text: #333333;
        }

        [data-bs-theme="dark"] {
            --whm-sidebar-bg: #1a1d20;
            --whm-sidebar-text: #adb5bd;
            --whm-sidebar-hover: #212529;
            --whm-header-bg: #212529;
            --whm-header-text: #ffffff;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background-color: var(--bs-body-bg);
            font-size: 0.9rem;
        }

        #wrapper { display: flex; min-height: 100vh; }

        /* Sidebar */
        #sidebar {
            width: 260px;
            background-color: var(--whm-sidebar-bg);
            color: var(--whm-sidebar-text);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            z-index: 1040;
        }

        #sidebar .brand {
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
            background-color: rgba(0,0,0,0.1);
            text-decoration: none;
        }

        #sidebar .search-wrapper {
            padding: 1rem;
        }

        #sidebar .search-input {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 4px;
            padding: 0.4rem 0.8rem;
            width: 100%;
        }
        #sidebar .search-input::placeholder { color: rgba(255,255,255,0.5); }

        #sidebar .nav-group-title {
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 1rem 1.5rem 0.5rem;
            opacity: 0.6;
            letter-spacing: 0.5px;
        }

        #sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.6rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        #sidebar .nav-link:hover {
            background-color: var(--whm-sidebar-hover);
            color: #fff;
        }
        #sidebar .nav-link.active {
            background-color: var(--whm-sidebar-hover);
            color: #fff;
            border-left-color: var(--whm-accent);
        }

        /* Header */
        #topbar {
            height: 60px;
            background-color: var(--whm-header-bg);
            border-bottom: 1px solid var(--bs-border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            color: var(--whm-header-text);
        }

        /* Content */
        #content-wrapper { flex-grow: 1; display: flex; flex-direction: column; }
        .main-content { padding: 2rem; flex-grow: 1; }

        /* Card override for WHM look */
        .card {
            border: 1px solid var(--bs-border-color);
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--bs-border-color);
            font-weight: 600;
        }

        /* Mobile */
        @media (max-width: 768px) {
            #sidebar { position: fixed; height: 100%; transform: translateX(-100%); transition: transform 0.3s; }
            #sidebar.show { transform: translateX(0); }
            #sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1030; }
            #sidebar-overlay.show { display: block; }
        }
    </style>
</head>
<body>
    @auth
    <div id="sidebar-overlay"></div>
    <div id="wrapper">
        <!-- Sidebar -->
        <aside id="sidebar">
            <a href="{{ route('dashboard') }}" class="brand">
                n8n Panel
            </a>

            <div class="search-wrapper">
                <input type="text" class="search-input" placeholder="Search features...">
            </div>

            <nav class="flex-grow-1 overflow-y-auto pb-4">
                <div class="nav-group-title">Platform Information</div>
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Server Status
                </a>

                <div class="nav-group-title">Account Functions</div>
                <a href="{{ route('instances.index') }}" class="nav-link {{ request()->routeIs('instances.*') ? 'active' : '' }}">
                    <i class="bi bi-hdd-network"></i> List Accounts
                </a>
                <a href="{{ route('instances.create') }}" class="nav-link">
                    <i class="bi bi-plus-circle"></i> Create New Account
                </a>

                <div class="nav-group-title">Packages</div>
                <a href="{{ route('packages.index') }}" class="nav-link {{ request()->routeIs('packages.*') ? 'active' : '' }}">
                    <i class="bi bi-box"></i> Feature Manager
                </a>

                @role('admin')
                <div class="nav-group-title">System Administration</div>
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Manage Users
                </a>
                <a href="{{ route('admin.environment.index') }}" class="nav-link {{ request()->routeIs('admin.environment.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders"></i> Tweak Settings
                </a>
                <a href="{{ route('containers.orphans') }}" class="nav-link {{ request()->routeIs('containers.orphans') ? 'active' : '' }}">
                    <i class="bi bi-search"></i> Orphan Discovery
                </a>
                <a href="{{ route('admin.api_logs.index') }}" class="nav-link {{ request()->routeIs('admin.api_logs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> API Logs
                </a>
                @endrole
            </nav>
        </aside>

        <div id="content-wrapper">
            <!-- Top Bar -->
            <header id="topbar">
                <div class="d-flex align-items-center gap-3 overflow-hidden">
                    <button id="sidebarToggle" class="btn btn-link p-0 text-decoration-none d-md-none text-reset me-2">
                        <i class="bi bi-list fs-4"></i>
                    </button>

                    <div class="d-none d-lg-flex flex-column small lh-1">
                        <div class="fw-bold">{{ $serverInfo['hostname'] ?? 'localhost' }}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">{{ $serverInfo['ips'] ?? '127.0.0.1' }}</div>
                    </div>

                    <div class="vr mx-2 d-none d-lg-block"></div>

                    <div class="d-none d-md-block small text-muted">
                        <span class="fw-bold">Uptime:</span> {{ $serverInfo['uptime'] ?? 'Unknown' }}
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-none d-xl-flex align-items-center gap-2 small">
                        <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Load Averages</span>
                        <span class="badge bg-light text-dark border">{{ $serverInfo['loads']['1'] ?? '0.00' }}</span>
                        <span class="badge bg-light text-dark border">{{ $serverInfo['loads']['5'] ?? '0.00' }}</span>
                        <span class="badge bg-light text-dark border">{{ $serverInfo['loads']['15'] ?? '0.00' }}</span>
                    </div>

                    <div class="vr mx-1 d-none d-xl-block"></div>

                    <button onclick="window.toggleTheme()" class="btn btn-link text-reset p-1" title="Toggle Theme">
                         <i class="bi bi-moon-stars"></i>
                    </button>

                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-reset text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: bold;">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                            <span class="d-none d-md-inline small fw-bold">{{ Auth::user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Password & Security</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="dropdown-item text-danger">Log Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="main-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show mb-4">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="text-center py-3 text-muted small border-top">
                &copy; {{ date('Y') }} n8n Host Manager. All rights reserved.
            </footer>
        </div>
    </div>
    @else
    <div class="d-flex min-vh-100 align-items-center justify-content-center bg-body-tertiary">
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
        });
    </script>
</body>
</html>
