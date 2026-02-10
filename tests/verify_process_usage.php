<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

function testProcessArguments($args) {
    $process = new Process($args);
    echo "Command: " . $process->getCommandLine() . "\n";
}

echo "Testing BackupService rm fix:\n";
$tempFile = '/tmp/file with spaces.sql';
testProcessArguments(['rm', '-f', $tempFile]);

echo "\nTesting ContainerController exportDatabase fix:\n";
$script = '/path/to/script.sh';
$dbName = "db_name_with_chars; echo 'hacked'";
// Arguments used in ContainerController:
// ['sudo', $script, '--action=export', "--db-name={$dbName}"]
testProcessArguments(['sudo', $script, '--action=export', "--db-name={$dbName}"]);
