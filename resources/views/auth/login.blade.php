<!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WHM Login</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <script>
        // Theme Toggler
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
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(135deg, #29388c 0%, #1e2a69 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        [data-bs-theme="dark"] body {
            background: #121416;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background-color: var(--bs-body-bg);
            border-radius: 4px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            overflow: hidden;
            padding: 3rem 2.5rem;
        }

        .login-logo {
            font-size: 2rem;
            font-weight: 700;
            color: #ff6c2c; /* WHM Orange */
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-control {
            border-radius: 3px;
            padding: 0.6rem 0.8rem;
            border: 1px solid var(--bs-border-color);
        }

        .btn-login {
            background-color: #007cf7; /* cPanel Blue */
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.7rem;
            border-radius: 3px;
            width: 100%;
        }
        .btn-login:hover {
            background-color: #0062c4;
        }

        .bottom-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
        }
        .bottom-links a {
            color: var(--bs-secondary-color);
            text-decoration: none;
        }
        .bottom-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-logo">
            <span style="color: var(--bs-body-color);">n8n</span> Host Manager
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

            <button type="submit" class="btn btn-login shadow-sm">
                Log in
            </button>
        </form>

        <div class="bottom-links">
            <a href="#">Reset Password</a>
            <span class="mx-2 text-muted">&bull;</span>
            <span class="text-muted">v110.0.12 (Jupiter)</span>
        </div>
    </div>

</body>
</html>
