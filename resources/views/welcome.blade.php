<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome - {{ config('app.name', 'n8n Panel') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .hero-section {
            padding: 5rem 0;
            background: #fff;
            border-bottom: 1px solid #dee2e6;
        }
        .feature-icon {
            width: 4rem;
            height: 4rem;
            border-radius: .75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            font-size: 1.75rem;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                n8n Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav gap-2">
                    @if (Route::has('login'))
                        @auth
                            <li class="nav-item">
                                <a href="{{ url('/dashboard') }}" class="btn btn-outline-secondary">Dashboard</a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a href="{{ route('login') }}" class="btn btn-outline-primary px-4">Log in</a>
                            </li>
                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a href="{{ route('register') }}" class="btn btn-primary px-4">Register</a>
                                </li>
                            @endif
                        @endauth
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <header class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3 text-dark">Manage your n8n instances with ease</h1>
                    <p class="lead text-secondary mb-5">
                        A powerful, simple, and secure dashboard to deploy, monitor, and scale your n8n workflows.
                    </p>
                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg px-5">Go to Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-5 shadow-sm">Get Started</a>
                            <a href="https://n8n.io" target="_blank" class="btn btn-outline-secondary btn-lg px-5">Learn about n8n</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Features -->
    <div class="container py-5">
        <div class="row g-5 py-5">
            <div class="col-md-4">
                <div class="feature-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-hdd-network"></i>
                </div>
                <h3 class="fw-bold mb-3">Instance Management</h3>
                <p class="text-secondary">Deploy isolated n8n instances in seconds. Manage resources, environments, and versions with a few clicks.</p>
            </div>
            <div class="col-md-4">
                <div class="feature-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h3 class="fw-bold mb-3">Secure & Scalable</h3>
                <p class="text-secondary">Built with security in mind. Isolated containers, role-based access control, and automated backups.</p>
            </div>
            <div class="col-md-4">
                <div class="feature-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <h3 class="fw-bold mb-3">Real-time Monitoring</h3>
                <p class="text-secondary">Keep an eye on your server health, resource usage, and instance performance from a centralized dashboard.</p>
            </div>
        </div>
    </div>

    <footer class="py-4 border-top bg-white">
        <div class="container text-center">
            <p class="text-muted small mb-0">&copy; {{ date('Y') }} n8n Host Manager. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
