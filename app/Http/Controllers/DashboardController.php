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

        // Fetch all docker containers once
        $dockerContainers = $this->dockerService->listContainers();

        $dockerMap = [];
        foreach ($dockerContainers as $dc) {
            $shortId = substr($dc['id'], 0, 12);
            $dockerMap[$shortId] = $dc;
        }

        if ($user->hasRole('admin')) {
            $systemStats = $this->systemStatusService->getSystemStats();
            $systemStats['container_count'] = Container::count();
            $systemStats['user_count'] = \App\Models\User::count();
            $systemStats['panel_version'] = '1.0.0';

            $nginxStatus = $this->serviceManager->getStatus('nginx');
            $postgresStatus = $this->serviceManager->getStatus('postgresql');
            $dockerStatus = $this->serviceManager->getStatus('docker');
        } else {
            $postgresStatus = 'Unknown';
            $dockerStatus = 'Unknown';
        }

        if ($user->hasRole('admin')) {
             $dbContainers = Container::with(['user', 'package'])->get();
        } elseif ($user->hasRole('reseller')) {
             $dbContainers = Container::with(['user', 'package'])->where('user_id', $user->id)->get();
        } else {
             return abort(403);
        }

        $containers = $dbContainers->map(function ($dbContainer) use ($dockerMap) {
            $shortId = substr($dbContainer->docker_id, 0, 12);
            $dockerInfo = $dockerMap[$shortId] ?? null;

            return [
                'id' => $dbContainer->id,
                'docker_id' => $dbContainer->docker_id,
                'name' => $dbContainer->name,
                'port' => $dbContainer->port,
                'domain' => $dbContainer->domain,
                'user' => $dbContainer->user ? $dbContainer->user->name : 'System',
                'package' => $dbContainer->package ? $dbContainer->package->name : 'Default',
                'created_at' => $dbContainer->created_at,
                'image' => $dockerInfo['image'] ?? 'Unknown',
                'status' => $dockerInfo['status'] ?? 'Stopped/Unknown',
                'state' => $dockerInfo['state'] ?? 'unknown',
                'managed' => true,
            ];
        });

        return view('dashboard.index', compact('containers', 'systemStats', 'nginxStatus', 'postgresStatus', 'dockerStatus'));
    }
}
