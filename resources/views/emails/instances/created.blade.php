<!DOCTYPE html>
<html>
<body>
    <h2>Your n8n Instance is Ready!</h2>
    <p>Hello,</p>
    <p>Your new n8n instance <strong>{{ $container->name }}</strong> has been successfully created.</p>

    <p><strong>Details:</strong></p>
    <ul>
        <li>Domain: <a href="https://{{ $container->domain }}">https://{{ $container->domain }}</a></li>
        <li>Version: {{ $container->image_tag }}</li>
    </ul>

    <p>You can manage your instance from the <a href="{{ route('instances.index') }}">Control Panel</a>.</p>

    <p>Regards,<br>{{ config('app.name') }}</p>
</body>
</html>
