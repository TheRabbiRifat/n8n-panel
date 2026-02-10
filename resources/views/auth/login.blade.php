<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>n8n Panel Login</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa; /* bg-light */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); /* shadow-sm */
            padding: 3rem 2.5rem;
            border: 1px solid #dee2e6;
        }

        .login-logo {
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-control {
            border-radius: 4px;
            padding: 0.6rem 0.8rem;
        }

        .btn-login {
            font-weight: 600;
            padding: 0.7rem;
            border-radius: 4px;
            width: 100%;
        }

        .bottom-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
        }
        .bottom-links a {
            color: #6c757d;
            text-decoration: none;
        }
        .bottom-links a:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-logo">
            <img src="{{ asset('images/logo.png') }}" alt="n8n Panel" style="max-width: 180px; height: auto;">
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small mb-4 rounded-1">
                 {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label small fw-bold text-secondary">Username</label>
                <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="Enter your username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label small fw-bold text-secondary">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
            </div>

            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label small text-secondary" for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary btn-login shadow-sm">
                Log in
            </button>
        </form>

        <div class="bottom-links">
            <a href="#">Reset Password</a>
            <span class="mx-2 text-muted">&bull;</span>
            <span class="text-muted">v1.0.0</span>
        </div>
    </div>

</body>
</html>
