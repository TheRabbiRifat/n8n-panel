<!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>n8n Control Panel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script>
        /*!
         * Color mode toggler for Bootstrap's docs (https://getbootstrap.com/)
         * Copyright 2011-2023 The Bootstrap Authors
         * Licensed under the Creative Commons Attribution 3.0 Unported License.
         */
        (() => {
            'use strict'
            const getStoredTheme = () => localStorage.getItem('theme')
            const setStoredTheme = theme => localStorage.setItem('theme', theme)

            const getPreferredTheme = () => {
                const storedTheme = getStoredTheme()
                if (storedTheme) {
                    return storedTheme
                }
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

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                const storedTheme = getStoredTheme()
                if (storedTheme !== 'light' && storedTheme !== 'dark') {
                    setTheme(getPreferredTheme())
                }
            })

            window.addEventListener('DOMContentLoaded', () => {
                // Expose globally for toggle button
                window.toggleTheme = () => {
                    const current = getStoredTheme() || 'auto';
                    const next = current === 'dark' ? 'light' : 'dark';
                    setStoredTheme(next);
                    setTheme(next);
                }
            })
        })()
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bs-body-bg);
            overflow-x: hidden;
        }

        #wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        #sidebar {
            width: 280px;
            background-color: var(--bs-body-bg);
            border-right: 1px solid var(--bs-border-color);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease-in-out;
            z-index: 1040;
        }

        [data-bs-theme="dark"] #sidebar {
            background-color: #1a1d20; /* Slightly lighter than pure black */
        }

        #sidebar .sidebar-brand {
            padding: 1.5rem;
            color: var(--bs-body-color);
            font-size: 1.25rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--bs-border-color);
        }

        #sidebar .nav-link {
            padding: 0.85rem 1.5rem;
            color: var(--bs-secondary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        #sidebar .nav-link:hover {
            color: var(--bs-primary);
            background-color: var(--bs-tertiary-bg);
        }

        #sidebar .nav-link.active {
            color: var(--bs-primary);
            background-color: var(--bs-primary-bg-subtle);
            border-left-color: var(--bs-primary);
        }

        #sidebar .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid var(--bs-border-color);
        }

        #content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--bs-tertiary-bg); /* Slight contrast with white/dark cards */
        }

        .main-content {
            padding: 2rem;
            flex-grow: 1;
            max-width: 1600px; /* Prevent overly wide screens */
            width: 100%;
        }

        /* Mobile handling */
        @media (max-width: 768px) {
            #sidebar {
                position: fixed;
                height: 100%;
                transform: translateX(-100%);
            }
            #sidebar.show {
                transform: translateX(0);
            }

            /* Overlay for mobile sidebar */
            #sidebar-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
            }
            #sidebar-overlay.show {
                display: block;
            }
        }

        .card {
            border: 1px solid var(--bs-border-color-translucent);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            border-radius: 0.75rem;
            background-color: var(--bs-body-bg);
        }

        .btn-toggle-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--bs-body-color);
            padding: 0;
            cursor: pointer;
        }

        /* Avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--bs-secondary-bg);
            color: var(--bs-body-color);
        }
    </style>
</head>
<body>
    @auth
    <!-- Mobile Overlay -->
    <div id="sidebar-overlay"></div>

    <div id="wrapper">
        <!-- Sidebar -->
        <aside id="sidebar">
            <a href="{{ route('dashboard') }}" class="sidebar-brand">
                <div class="bg-primary text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bi bi-boxes"></i>
                </div>
                n8n Panel
            </a>

            <nav class="d-flex flex-column mt-3 flex-grow-1 overflow-y-auto">
                <div class="px-3 mb-2 text-uppercase text-secondary small fw-bold" style="font-size: 0.75rem;">Platform</div>

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
                <div class="px-3 mt-4 mb-2 text-uppercase text-secondary small fw-bold" style="font-size: 0.75rem;">Administration</div>

                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Users
                </a>
                <a href="{{ route('admin.environment.index') }}" class="nav-link {{ request()->routeIs('admin.environment.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders"></i> Environment
                </a>
                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear-wide-connected"></i> Panel Settings
                </a>
                <a href="{{ route('containers.orphans') }}" class="nav-link {{ request()->routeIs('containers.orphans') ? 'active' : '' }}">
                    <i class="bi bi-search"></i> Instance Discovery
                </a>
                @endrole
            </nav>

            <div class="sidebar-footer">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold border">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div style="overflow: hidden;">
                        <div class="fw-bold text-truncate">{{ Auth::user()->name }}</div>
                        <div class="small text-muted text-truncate">{{ ucfirst(Auth::user()->roles->first()->name ?? 'User') }}</div>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-2">
                    <button onclick="window.toggleTheme()" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Toggle Theme">
                         <i class="bi bi-moon-stars"></i> Theme
                    </button>
                    <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary btn-sm" title="Profile">
                        <i class="bi bi-person-gear"></i>
                    </a>
                </div>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Content Wrapper -->
        <div id="content-wrapper">
            <!-- Mobile Header -->
            <header class="d-md-none p-3 bg-body border-bottom d-flex align-items-center justify-content-between sticky-top">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn-toggle-sidebar" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="fw-bold">n8n Panel</span>
                </div>
                <div class="bg-primary text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                    <i class="bi bi-boxes"></i>
                </div>
            </header>

            <main class="main-content">
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
            </main>

            <footer class="text-center py-4 text-muted small border-top bg-body">
                &copy; {{ date('Y') }} n8n Control Panel.
            </footer>
        </div>
    </div>
    @else
    <!-- Login Layout is now handled by a separate view structure if needed, or yields here -->
    <!-- Ideally, auth views should not extend this layout if they differ significantly, but if they do: -->
    <div class="d-flex min-vh-100 align-items-center justify-content-center bg-body-tertiary">
         @yield('content')
    </div>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Sidebar Toggle Logic
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if(toggleBtn && sidebar && overlay){
                function closeSidebar() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }

                function openSidebar() {
                    sidebar.classList.add('show');
                    overlay.classList.add('show');
                }

                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if(sidebar.classList.contains('show')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });

                overlay.addEventListener('click', closeSidebar);

                // Close on escape
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                        closeSidebar();
                    }
                });
            }
        });
    </script>
</body>
</html>
