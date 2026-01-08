<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Services\DockerService;
use App\Services\SystemStatusService;
use App\Services\ServiceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected $dockerService;
    protected $systemStatusService;
    protected $serviceManager;

    public function __construct(
        DockerService $dockerService,
        SystemStatusService $systemStatusService,
        ServiceManager $serviceManager
    ) {
        $this->dockerService = $dockerService;
        $this->systemStatusService = $systemStatusService;
        $this->serviceManager = $serviceManager;
    }

    public function index()
    {
        $user = Auth::user();
        $systemStats = null;
        $nginxStatus = 'Unknown';

        if ($user->hasRole('admin')) {
            $systemStats = $this->systemStatusService->getSystemStats();
            // Add counts
            $systemStats['container_count'] = Container::count();
            $systemStats['user_count'] = \App\Models\User::count();

            $nginxStatus = $this->serviceManager->getStatus('nginx');
        }

        // Fetch all docker containers once
        $dockerContainers = $this->dockerService->listContainers();
        // Convert to collection keyed by ID (short or long, let's normalize to matching what we stored)
        // DockerService listContainers returns IDs.
        $dockerMap = [];
        foreach ($dockerContainers as $dc) {
            // Store by partial ID (12 chars) as that is what we likely store or can match
            // Or store by full ID if available.
            // In DockerService::listContainers we return ID from `docker ps`. `docker ps --format "{{.ID}}"` usually returns short ID (12 chars).
            // Let's assume keys are IDs.
            $dockerMap[$dc['id']] = $dc;
        }

        if ($user->hasRole('admin')) {
             $dbContainers = Container::all();
        } elseif ($user->hasRole('reseller')) {
             $dbContainers = Container::where('user_id', $user->id)->get();
        } else {
             return abort(403);
        }

        // Merge Data
        $containers = $dbContainers->map(function ($dbContainer) use ($dockerMap) {
            // Find matching docker container.
            // We stored `docker_id` in DB.
            // Check if we have a match in $dockerMap
            // Note: DB `docker_id` might be 12 chars. `docker ps` usually gives 12 chars by default or full.
            // Our DockerService uses `{{.ID}}` which is short ID usually.

            // We need a fuzzy match or exact match.
            // Let's try exact match first.
            $dockerInfo = null;
            foreach ($dockerMap as $dId => $info) {
                if (str_starts_with($dId, $dbContainer->docker_id) || str_starts_with($dbContainer->docker_id, $dId)) {
                    $dockerInfo = $info;
                    break;
                }
            }

            return [
                'id' => $dbContainer->id, // Database ID for actions
                'docker_id' => $dbContainer->docker_id,
                'name' => $dbContainer->name,
                'port' => $dbContainer->port,
                'image' => $dockerInfo['image'] ?? 'Unknown',
                'status' => $dockerInfo['status'] ?? 'Stopped/Unknown',
                'state' => $dockerInfo['state'] ?? 'unknown',
                'managed' => true,
            ];
        });

        return view('dashboard.index', compact('containers', 'systemStats', 'nginxStatus'));
    }
}
