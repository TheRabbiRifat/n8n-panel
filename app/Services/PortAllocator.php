<?php

namespace App\Services;

use App\Models\Container;

class PortAllocator
{
    public function allocate(): int
    {
        // Simple linear search for free port starting from 5678
        // In production, we might want a more robust range or tracking table.
        $startPort = 5678;
        $maxPort = 65000;

        $usedPorts = Container::pluck('port')->toArray();

        for ($port = $startPort; $port <= $maxPort; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }

        throw new \Exception("No free ports available.");
    }
}
