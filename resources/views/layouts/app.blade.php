<!doctype html>
<html lang="en">
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
            background-color: #f8f9fa;
            color: #1f2937;
            font-size: 0.925rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: #fff;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.06);
            padding: 0.75rem 0;
            z-index: 1000;
        }

        .navbar-brand img {
            height: 36px;
            width: auto;
        }

        .nav-link {
            font-weight: 500;
            color: #4b5563 !important;
            padding: 0.5rem 1rem !important;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .nav-link:hover, .nav-link.active {
            color: #111827 !important;
            background-color: #f3f4f6;
        }

        .nav-link.active {
            color: #2563eb !important;
            background-color: #eff6ff;
        }

        .nav-link i {
            margin-right: 0.4rem;
            font-size: 1.1em;
            vertical-align: -0.15em;
        }

        .dropdown-menu {
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: #374151;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
            color: #111827;
        }

        .dropdown-item.active, .dropdown-item:active {
            background-color: #2563eb;
            color: white;
        }

        main {
            flex: 1;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            background-color: #fff;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: #111827;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Utility for server info badges */
        .server-stat-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    @auth
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand me-4" href="{{ route('dashboard') }}">
                <img src="{{ asset('images/logo.png') }}" alt="n8n Panel">
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @can('view_system')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    @endcan

                    @can('manage_instances')
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('instances.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-hdd-network"></i> Instances
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('instances.index') }}">List Instances</a></li>
                            <li><a class="dropdown-item" href="{{ route('instances.create') }}">Create New Instance</a></li>
                        </ul>
                    </li>
                    @endcan

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('packages.*') ? 'active' : '' }}" href="{{ route('packages.index') }}">
                            <i class="bi bi-box"></i> Packages
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('api-tokens.*') ? 'active' : '' }}" href="{{ route('api-tokens.index') }}">
                            <i class="bi bi-key"></i> API Tokens
                        </a>
                    </li>

                    @canany(['manage_users', 'manage_roles', 'manage_settings', 'view_system', 'view_logs'])
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*') || request()->routeIs('admin.*') || request()->routeIs('roles.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i> Administration
                        </a>
                        <ul class="dropdown-menu">
                            @can('manage_users')
                            <li><h6 class="dropdown-header text-uppercase small fw-bold">User Management</h6></li>
                            <li><a class="dropdown-item" href="{{ route('users.index') }}">Users</a></li>
                            @endcan

                            @can('manage_roles')
                            <li><a class="dropdown-item" href="{{ route('roles.index') }}">Roles & Permissions</a></li>
                            @endcan

                            @if(auth()->user()->can('manage_users') || auth()->user()->can('manage_roles'))
                            <li><hr class="dropdown-divider"></li>
                            @endif

                            @can('manage_settings')
                            <li><h6 class="dropdown-header text-uppercase small fw-bold">Configuration</h6></li>
                            <li><a class="dropdown-item" href="{{ route('admin.environment.index') }}">Environment Settings</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.backups.index') }}">Backups</a></li>
                            <li><a class="dropdown-item" href="{{ route('containers.orphans') }}">Docker Discovery</a></li>
                            @endcan

                            @if(auth()->user()->can('manage_settings') && (auth()->user()->can('view_system') || auth()->user()->can('view_logs')))
                            <li><hr class="dropdown-divider"></li>
                            @endif

                            @if(auth()->user()->can('view_system') || auth()->user()->can('view_logs'))
                            <li><h6 class="dropdown-header text-uppercase small fw-bold">System</h6></li>
                            @can('view_system')
                            <li><a class="dropdown-item" href="{{ route('admin.system.index') }}">System Settings</a></li>
                            @endcan
                            @can('view_logs')
                            <li><a class="dropdown-item" href="{{ route('admin.api_logs.index') }}">API Logs</a></li>
                            @endcan
                            @endif
                        </ul>
                    </li>
                    @endcanany
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <!-- Server Stats (Hidden on mobile) -->
                    <div class="d-none d-xl-flex align-items-center gap-2">
                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Load:</span>
                        <span class="server-stat-badge" title="1 min load">{{ $serverInfo['loads']['1'] ?? '0.00' }}</span>
                        <span class="server-stat-badge" title="5 min load">{{ $serverInfo['loads']['5'] ?? '0.00' }}</span>
                        <span class="server-stat-badge" title="15 min load">{{ $serverInfo['loads']['15'] ?? '0.00' }}</span>
                    </div>

                    <div class="vr mx-1 d-none d-xl-block text-muted"></div>

                    <!-- User Menu -->
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-reset text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 34px; height: 34px; font-weight: 600;">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                            <div class="d-none d-md-block lh-1 text-start">
                                <div class="fw-bold small">{{ Auth::user()->name }}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">{{ Auth::user()->email }}</div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li><div class="dropdown-header">Signed in as <strong>{{ Auth::user()->name }}</strong></div></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i> Profile & Security</a></li>
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
            </div>
        </div>
    </nav>

    <main class="py-4">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="py-4 mt-auto border-top bg-white">
        <div class="container text-center">
             <div class="text-muted small">
                &copy; {{ date('Y') }} n8n Host Manager. All rights reserved.
                <span class="mx-2">|</span>
                <span class="badge bg-light text-secondary border">v1.0.0</span>
            </div>
        </div>
    </footer>

    @else
    <div class="d-flex min-vh-100 align-items-center justify-content-center bg-body-tertiary">
         @yield('content')
    </div>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
