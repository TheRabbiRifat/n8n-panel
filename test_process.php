<?php

use Illuminate\Support\Facades\Process;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$outputFile = 'test_redirect.txt';
$command = "echo 'hello world' > {$outputFile}";

echo "Running command: $command\n";

try {
    $result = Process::run($command);
    echo "Exit Code: " . $result->exitCode() . "\n";
    echo "Output: " . $result->output() . "\n";
    echo "Error Output: " . $result->errorOutput() . "\n";

    if (file_exists($outputFile)) {
        echo "File created successfully. Content: " . file_get_contents($outputFile) . "\n";
        unlink($outputFile);
    } else {
        echo "File NOT created.\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
