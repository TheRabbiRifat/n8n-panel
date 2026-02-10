<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - n8n Panel</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #1f2937;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 2.5rem;
            border: 1px solid #e5e7eb;
        }

        .login-logo {
            margin-bottom: 2rem;
            text-align: center;
        }

        .login-logo img {
            max-height: 60px;
            width: auto;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-primary {
            background-color: #3b82f6;
            border-color: #3b82f6;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .form-check-input {
            width: 1.1em;
            height: 1.1em;
            margin-top: 0.1em;
            cursor: pointer;
        }

        .form-check-label {
            cursor: pointer;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .bottom-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .bottom-links a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }

        .bottom-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-logo">
            <img src="{{ asset('images/logo.png') }}" alt="n8n Panel">
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small mb-4 rounded-2 border-0 bg-danger-subtle text-danger">
                 {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="name@company.com">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
            </div>

            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <a href="#" class="small text-decoration-none" style="color: #3b82f6; font-weight: 500;">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary shadow-sm">
                Sign In
            </button>
        </form>

        <div class="bottom-links">
            &copy; {{ date('Y') }} n8n Panel. All rights reserved.
        </div>
    </div>

</body>
</html>
