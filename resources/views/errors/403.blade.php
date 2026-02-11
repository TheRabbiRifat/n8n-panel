<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Inter', sans-serif; }
        .error-container { text-align: center; }
        .error-code { font-size: calc(1.5rem + 4.5vw); font-weight: 700; color: #dc3545; }
        .error-message { font-size: 1.25rem; color: #6c757d; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">403</div>
        <div class="error-message">Access Forbidden</div>
        <p class="text-muted">You do not have permission to access this resource.</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-3">Back to Home</a>
    </div>
</body>
</html>
