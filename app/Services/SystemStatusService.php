<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class SystemStatusService
{
    public function getSystemStats()
    {
        return [
            'docker' => $this->getDockerStatus(),
            'uptime' => $this->getUptime(),
            'cpu' => $this->getCpuLoad(),
            'ram' => $this->getRamUsage(),
            'disk' => $this->getDiskUsage(),
        ];
    }

    protected function getDockerStatus()
    {
        // Simple check if docker info command runs successfully
        $process = Process::run('docker info');
        return $process->successful() ? 'Running' : 'Stopped';
    }

    protected function getUptime()
    {
        $uptime = Process::run('uptime -p');
        return $uptime->successful() ? trim(str_replace('up ', '', $uptime->output())) : 'Unknown';
    }

    protected function getCpuLoad()
    {
        // Load average for last 1 minute
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? 0;
        }
        return 0;
    }

    protected function getRamUsage()
    {
        // Parse /proc/meminfo or use shell command
        $total = 0;
        $free = 0;

        $process = Process::run('free -m');
        if ($process->successful()) {
            $output = $process->output();
            // Expected format:
            //               total        used        free      shared  buff/cache   available
            // Mem:           7933        1234        5000         123        1700        6400

            $lines = explode("\n", $output);
            if (isset($lines[1])) {
                $parts = preg_split('/\s+/', $lines[1]);
                if (count($parts) >= 3) {
                    $total = intval($parts[1]);
                    $used = intval($parts[2]); // This is 'used' column, but better to check 'available' if present

                    // Let's stick to basic: Used / Total
                    return [
                        'used' => $used,
                        'total' => $total,
                        'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0
                    ];
                }
            }
        }

        return ['used' => 0, 'total' => 0, 'percent' => 0];
    }

    protected function getDiskUsage()
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;

        return [
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0
        ];
    }
}
