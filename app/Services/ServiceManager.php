<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class ServiceManager
{
    protected $allowedServices = ['nginx', 'mysql', 'mariadb', 'postgresql', 'docker'];

    public function getStatus(string $service)
    {
        if (!in_array($service, $this->allowedServices)) {
            // Check mysql vs mariadb fallback logic
            if ($service === 'mysql') {
                 // Try mysql
                 $process = Process::run([base_path('scripts/system-manager.sh'), '--action=service-status', "--service=mysql"]);
                 $status = trim($process->output());
                 if ($status === 'active') return 'active';

                 // Try mariadb
                 $process = Process::run([base_path('scripts/system-manager.sh'), '--action=service-status', "--service=mariadb"]);
                 return trim($process->output());
            }
            return 'Unknown';
        }

        $process = Process::run([base_path('scripts/system-manager.sh'), '--action=service-status', "--service={$service}"]);
        return trim($process->output()); // active, inactive, failed
    }

    public function start(string $service)
    {
        return $this->runCommand($service, 'start');
    }

    public function stop(string $service)
    {
        return $this->runCommand($service, 'stop');
    }

    public function restart(string $service)
    {
        return $this->runCommand($service, 'restart');
    }

    protected function runCommand($service, $action)
    {
        if (!in_array($service, $this->allowedServices)) {
            throw new \Exception("Service $service is not managed.");
        }

        // Execute via system-manager.sh with sudo
        $process = Process::run([
            'sudo',
            base_path('scripts/system-manager.sh'),
            '--action=service-action',
            "--service={$service}",
            "--cmd={$action}"
        ]);

        if (!$process->successful()) {
            throw new \Exception("Failed to $action $service: " . $process->errorOutput());
        }

        return true;
    }
}
