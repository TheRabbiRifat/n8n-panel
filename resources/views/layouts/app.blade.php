<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>n8n Control Panel</title>

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
            background-color: #f3f4f6;
            overflow-x: hidden;
        }

        #wrapper {
            display: flex;
            min-height: 100vh;
        }

        #sidebar {
            width: 260px;
            background-color: #111827; /* Dark sidebar */
            color: #d1d5db;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        #sidebar .sidebar-brand {
            padding: 1.5rem;
            color: #fff;
            font-size: 1.25rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #1f2937;
        }

        #sidebar .nav-link {
            padding: 0.75rem 1.5rem;
            color: #9ca3af;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }

        #sidebar .nav-link:hover {
            color: #fff;
            background-color: #1f2937;
        }

        #sidebar .nav-link.active {
            color: #fff;
            background-color: #2563eb; /* Primary blue */
        }

        #sidebar .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid #1f2937;
        }

        #content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            padding: 2rem;
            flex-grow: 1;
        }

        /* Mobile handling */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -260px;
                position: fixed;
                height: 100%;
                z-index: 1000;
            }
            #sidebar.active {
                margin-left: 0;
            }
        }

        .card {
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 0.75rem;
        }

        .btn-toggle-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #111827;
            padding: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
    @auth
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar">
            <a href="{{ route('dashboard') }}" class="sidebar-brand">
                <div class="bg-primary text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bi bi-boxes"></i>
                </div>
                n8n Panel
            </a>

            <nav class="d-flex flex-column mt-3">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>

                <a href="{{ route('instances.index') }}" class="nav-link {{ request()->routeIs('instances.*') ? 'active' : '' }}">
                    <i class="bi bi-hdd-network"></i> Instances
                </a>

                <a href="{{ route('packages.index') }}" class="nav-link {{ request()->routeIs('packages.*') ? 'active' : '' }}">
                    <i class="bi bi-box"></i> Packages
                </a>

                @role('admin')
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Users
                </a>
                <a href="{{ route('admin.environment.index') }}" class="nav-link {{ request()->routeIs('admin.environment.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders"></i> Global Settings
                </a>
                <a href="{{ route('containers.orphans') }}" class="nav-link {{ request()->routeIs('containers.orphans') ? 'active' : '' }}">
                    <i class="bi bi-search"></i> Instance Discovery
                </a>
                @endrole
            </nav>

            <div class="sidebar-footer">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="bg-gray-700 rounded-circle d-flex align-items-center justify-content-center text-white fw-bold border border-secondary" style="width: 40px; height: 40px; background: #374151;">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div style="overflow: hidden;">
                        <div class="fw-bold text-white text-truncate">{{ Auth::user()->name }}</div>
                        <div class="small text-muted text-truncate">{{ ucfirst(Auth::user()->roles->first()->name ?? 'User') }}</div>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div id="content-wrapper">
            <!-- Mobile Toggle (visible only on mobile) -->
            <div class="d-md-none p-3 bg-white shadow-sm d-flex align-items-center justify-content-between">
                <span class="fw-bold">n8n Panel</span>
                <button class="btn-toggle-sidebar" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <div class="main-content">
                <!-- Alerts -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5"></i>
                        {{ session('warning') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </div>

            <div class="text-center py-3 text-muted small border-top bg-white">
                &copy; {{ date('Y') }} n8n Control Panel.
            </div>
        </div>
    </div>
    @else
    <!-- Login Layout (No Sidebar) -->
    <div class="container main-content d-flex align-items-center justify-content-center min-vh-100">
         @yield('content')
    </div>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Simple toggle for mobile sidebar
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        if(toggleBtn){
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
