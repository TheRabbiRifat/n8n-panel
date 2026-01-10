<!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | n8n Panel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script>
        /*!
         * Color mode toggler
         */
        (() => {
            'use strict'
            const getStoredTheme = () => localStorage.getItem('theme')
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
        })()
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bs-tertiary-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--bs-border-color-translucent);
            border-radius: 1rem;
            background-color: var(--bs-body-bg);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            padding: 0.75rem 1rem;
        }
        .btn-login {
            padding: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="bg-white bg-opacity-25 rounded p-2 d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                <i class="bi bi-boxes fs-3 text-white"></i>
            </div>
            <h4 class="fw-bold mb-1">Welcome Back</h4>
            <p class="mb-0 opacity-75 small">Sign in to your control panel</p>
        </div>

        <div class="login-body">
            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <div class="small">
                        {{ $errors->first() }}
                    </div>
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body-tertiary border-end-0 text-secondary"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" required autofocus placeholder="name@example.com">
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                        <!-- <a href="#" class="small text-decoration-none">Forgot password?</a> -->
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-body-tertiary border-end-0 text-secondary"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" required placeholder="••••••••">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-login shadow-sm">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
