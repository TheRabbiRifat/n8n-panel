<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 Page Expired</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Inter', sans-serif; }
        .error-container { text-align: center; }
        .error-code { font-size: 6rem; font-weight: 700; color: #fd7e14; }
        .error-message { font-size: 1.5rem; color: #6c757d; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">419</div>
        <div class="error-message">Page Expired</div>
        <p class="text-muted">Your session has expired. Please refresh and try again.</p>
        <a href="{{ url('/login') }}" class="btn btn-primary mt-3">Back to Login</a>
    </div>
</body>
</html>
