<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new App\Services\SystemStatusService();
$stats = $service->getSystemStats();

echo "Kernel Version: " . $stats['kernel'] . "\n";
echo "OS: " . $stats['os'] . "\n";
