<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class ServiceManager
{
    protected $allowedServices = ['nginx'];

    public function getStatus(string $service)
    {
        if (!in_array($service, $this->allowedServices)) {
            return 'Unknown';
        }

        $process = Process::run("systemctl is-active $service");
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

        // This requires sudo permissions without password for the web user
        $process = Process::run("sudo systemctl $action $service");

        if (!$process->successful()) {
            throw new \Exception("Failed to $action $service: " . $process->errorOutput());
        }

        return true;
    }
}
