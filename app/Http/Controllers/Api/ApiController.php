<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Container;
use App\Models\User;
use App\Models\Package;
use App\Models\GlobalSetting;
use App\Services\DockerService;
use App\Services\NginxService;
use App\Services\PortAllocator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    protected $dockerService;
    protected $nginxService;
    protected $portAllocator;

    public function __construct(
        DockerService $dockerService,
        NginxService $nginxService,
        PortAllocator $portAllocator
    ) {
        $this->dockerService = $dockerService;
        $this->nginxService = $nginxService;
        $this->portAllocator = $portAllocator;
    }

    // CREATE
    public function create(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'package_id' => 'required|exists:packages,id',
            'name' => 'required|string|alpha_dash|unique:containers,name', // Instance Name
            'version' => 'nullable|string', // Default 'latest'
            'password' => 'nullable|string', // For new user creation
        ]);

        $version = $request->version ?: 'latest';
        $genericTimezone = 'Asia/Dhaka'; // Default or from request

        // 1. Find or Create User
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            $user = User::create([
                'name' => explode('@', $request->email)[0],
                'email' => $request->email,
                'password' => Hash::make($request->password ?: Str::random(12)),
                'instance_limit' => 5,
            ]);
            $user->assignRole('client');
        }

        $package = Package::findOrFail($request->package_id);

        // 2. Allocate Port
        $port = $this->portAllocator->allocate();

        // 3. Domain Logic
        $baseDomain = env('APP_DOMAIN');
        if (empty($baseDomain) || $baseDomain === 'n8n.local') {
             $hostname = gethostname();
             $baseDomain = $hostname ?: 'n8n.local';
        }
        $subdomain = Str::slug($request->name) . '.' . $baseDomain;

        // 4. Env Prep
        $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
        $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];
        $envArray['N8N_HOST'] = $subdomain;
        $envArray['N8N_PORT'] = 5678;
        $envArray['N8N_PROTOCOL'] = 'https';
        $envArray['WEBHOOK_URL'] = "https://{$subdomain}/";

        // 5. Volume
        $volumeHostPath = "/var/lib/n8n/instances/{$request->name}";
        Process::run("sudo mkdir -p $volumeHostPath");
        Process::run("sudo chown -R 1000:1000 $volumeHostPath");
        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        $instanceEnv = [
            'N8N_ENCRYPTION_KEY' => Str::random(32),
            'GENERIC_TIMEZONE' => $genericTimezone,
            'N8N_BLOCK_ENV_ACCESS_IN_NODE' => 'true',
        ];
        $envArray = array_merge($envArray, $instanceEnv);

        $image = 'n8nio/n8n:' . $version;

        DB::beginTransaction();
        $instanceDocker = null;

        try {
            $instanceDocker = $this->dockerService->createContainer(
                $image,
                $request->name,
                $port,
                5678,
                $package->cpu_limit,
                $package->ram_limit,
                $envArray,
                $volumes
            );

            $container = Container::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'docker_id' => $instanceDocker->getShortDockerIdentifier(),
                'name' => $request->name,
                'port' => $port,
                'domain' => $subdomain,
                'image_tag' => $version,
                'environment' => json_encode($instanceEnv),
            ]);

            $this->nginxService->createVhost($subdomain, $port);
            $this->nginxService->secureVhost($subdomain);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'instance_id' => $container->id,
                'domain' => $subdomain,
                'user_id' => $user->id
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($instanceDocker) {
                try { $this->dockerService->removeContainer($instanceDocker->getShortDockerIdentifier()); } catch (\Exception $e) {}
            }
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // TERMINATE
    public function terminate($id)
    {
        $container = Container::findOrFail($id);

        try {
            if ($container->domain) {
                $this->nginxService->removeVhost($container->domain);
            }

            try {
                $this->dockerService->removeContainer($container->docker_id);
            } catch (\Exception $e) {
                // Ignore if container not found
            }

            $volumePath = "/var/lib/n8n/instances/{$container->name}";
            if (Str::startsWith($volumePath, '/var/lib/n8n/instances/') && strlen($volumePath) > 23) {
                 Process::run("sudo rm -rf $volumePath");
            }

            $container->delete();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // START
    public function start($id)
    {
        $container = Container::findOrFail($id);

        // If suspended, do not allow start?
        if ($container->is_suspended) {
             return response()->json(['status' => 'error', 'message' => 'Service is suspended.'], 403);
        }

        try {
            $this->dockerService->startContainer($container->docker_id);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // STOP
    public function stop($id)
    {
        $container = Container::findOrFail($id);
        try {
            $this->dockerService->stopContainer($container->docker_id);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // SUSPEND
    public function suspend($id)
    {
        $container = Container::findOrFail($id);

        try {
            $this->dockerService->stopContainer($container->docker_id);
            $container->is_suspended = true;
            $container->save();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // UNSUSPEND
    public function unsuspend($id)
    {
        $container = Container::findOrFail($id);

        try {
            $container->is_suspended = false;
            $container->save();

            $this->dockerService->startContainer($container->docker_id);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // UPGRADE PACKAGE
    public function upgrade(Request $request, $id)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $container = Container::findOrFail($id);
        $package = Package::findOrFail($request->package_id);

        try {
            // Update DB
            $container->package_id = $package->id;
            $container->save();

            // Apply limits immediately via Docker Update
            // Converting RAM to bytes or string format as needed by DockerService or CLI
            // DockerService->create uses '512m' or '1g'. Package stores 'ram_limit' as int/float (GB usually).
            // Let's assume standard 'docker update' accepts --memory and --cpus

            // Logic similar to DockerService creation but updating
            $memory = intval($package->ram_limit * 1024) . 'm'; // Convert GB to MB
            $cpus = $package->cpu_limit;

            Process::run("sudo docker update --memory={$memory} --memory-swap={$memory} --cpus={$cpus} " . $container->docker_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Package updated and resources applied.',
                'new_package' => $package->name
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // STATS
    public function stats($id)
    {
        $container = Container::findOrFail($id);

        // Return resource usage
        try {
             // Fetch Status
             $statusProcess = Process::run("sudo docker inspect --format '{{.State.Status}}' " . $container->docker_id);
             $status = 'unknown';
             if ($statusProcess->successful()) {
                 $rawStatus = trim($statusProcess->output());
                 // Map docker status to user friendly status
                 $status = match ($rawStatus) {
                     'running' => 'running',
                     'exited' => 'stopped',
                     'paused' => 'paused',
                     'restarting' => 'restarting',
                     default => $rawStatus,
                 };
             }

             // Fetch stats: CPU%, MemUsage, MemPerc
             $process = Process::run("sudo docker stats --no-stream --format \"{{.CPUPerc}};{{.MemUsage}};{{.MemPerc}}\" " . $container->docker_id);

             if ($process->successful()) {
                 $output = trim($process->output());
                 // Output example: 0.10%; 150MiB / 1GiB; 14.65%

                 $parts = explode(';', $output);
                 if (count($parts) === 3) {
                     $cpu = floatval(str_replace('%', '', $parts[0]));
                     $memParts = explode('/', $parts[1]);
                     $memUsage = trim($memParts[0] ?? '0B');
                     $memLimit = trim($memParts[1] ?? '0B');
                     $memPerc = floatval(str_replace('%', '', $parts[2]));

                     return response()->json([
                         'status' => 'success',
                         'domain' => $container->domain,
                         'instance_status' => $status,
                         'cpu_percent' => $cpu,
                         'memory_usage' => $memUsage,
                         'memory_limit' => $memLimit,
                         'memory_percent' => $memPerc
                     ]);
                 }

                 return response()->json([
                     'status' => 'success',
                     'domain' => $container->domain,
                     'instance_status' => $status,
                     'raw' => $output
                 ]);
             }

             // If stats failed (e.g. container stopped), still return status and domain
             return response()->json([
                 'status' => 'success',
                 'domain' => $container->domain,
                 'instance_status' => $status,
                 'cpu_percent' => 0,
                 'memory_usage' => '0B',
                 'memory_limit' => '0B',
                 'memory_percent' => 0
             ]);

        } catch (\Exception $e) {
             return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // GET PACKAGE
    public function getPackage($id)
    {
        // $id can be package ID or we might want to list packages.
        // Assuming getting details of a specific package by ID
        $package = Package::find($id);
        if (!$package) {
            return response()->json(['status' => 'error', 'message' => 'Package not found'], 404);
        }
        return response()->json(['status' => 'success', 'package' => $package]);
    }

    // LIST PACKAGES (Helper for WHMCS config)
    public function listPackages()
    {
        return response()->json(['status' => 'success', 'packages' => Package::all()]);
    }

    // CREATE RESELLER
    public function createReseller(Request $request)
    {
        // Admin only (enforced by route middleware)
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'instance_limit' => 10, // Higher limit for resellers
        ]);

        $user->assignRole('reseller');

        return response()->json(['status' => 'success', 'user_id' => $user->id], 201);
    }

    // CONNECTION TEST
    public function testConnection()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Connection successful',
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email
            ]
        ]);
    }

    // SYSTEM STATS
    public function systemStats()
    {
        $statusService = new \App\Services\SystemStatusService();
        $stats = $statusService->getSystemStats();

        // Calculate instance counts
        $totalInstances = Container::count();
        $runningInstances = 0;

        // This is heavy if many containers, but fine for now or could be cached/optimized via DockerService
        $allContainers = $this->dockerService->listContainers();
        foreach($allContainers as $c) {
            if (str_contains(strtolower($c['status']), 'up')) {
                $runningInstances++;
            }
        }

        return response()->json([
            'status' => 'success',
            'server_status' => 'online',
            'load_averages' => $stats['loads'],
            'counts' => [
                'users' => User::count(),
                'instances_total' => $totalInstances,
                'instances_running' => $runningInstances,
                'instances_stopped' => $totalInstances - $runningInstances
            ]
        ]);
    }
}
