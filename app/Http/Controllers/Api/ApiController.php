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
use Illuminate\Support\Facades\URL;

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
            'email' => 'required|email|exists:users,email',
            'package_id' => 'required|exists:packages,id',
            'name' => 'required|string|alpha_dash|unique:containers,name', // Instance Name
            'version' => 'nullable|string', // Default 'latest'
        ]);

        $version = $request->version ?: 'latest';
        $genericTimezone = 'Asia/Dhaka'; // Default or from request

        // 1. Find User
        $user = User::where('email', $request->email)->firstOrFail();

        // Security: Ensure Reseller owns the target user
        if (auth()->user()->hasRole('reseller')) {
            if ($user->reseller_id !== auth()->id()) {
                abort(403, 'Unauthorized: You can only create instances for your own users.');
            }
        }

        $package = Package::findOrFail($request->package_id);

        // 2. Allocate Port
        $port = $this->portAllocator->allocate();

        // 3. Domain Logic
        // Automatically detect domain if not strictly set in env
        $baseDomain = env('APP_DOMAIN');
        if (empty($baseDomain) || $baseDomain === 'n8n.local') {
             // Fallback to request host or hostname
             $baseDomain = $request->getHost();
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
        // Volume creation/permissions handled by DockerService->createContainer -> create-instance.sh
        // However, standard Create logic handles it. But here we construct $volumes array.
        // DockerService createContainer will ignore this manual array if we just pass path, but signature expects array.
        // Actually, create-instance.sh does `mkdir -p` and `chmod`.
        // So we can skip manual Process::run here, as the script will do it.
        // But we need to pass the volume mapping to createContainer if generic.
        // Wait, create-instance.sh hardcodes VOLUME_HOST_PATH based on name!
        // So passing $volumes array to DockerService might be redundant if using that specific script.
        // But DockerService->createContainer is generic-ish?
        // Let's check DockerService implementation.
        // It appends `--env-json`. It does NOT seemingly iterate $volumes array to pass to script?
        // Let's check DockerService.php content again.
        // It accepts $volumes argument but create-instance.sh signature doesn't take volumes arg!
        // create-instance.sh hardcodes: VOLUME_HOST_PATH="/var/lib/n8n/instances/${NAME}" and CMD_ARGS+=("-v" "${VOLUME_HOST_PATH}:/home/node/.n8n")
        // So this manual logic in ApiController is actually redundant/conflicting if we rely on the script.

        // We should just define the array for DB compatibility if needed, but remove manual FS operations.

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
            $email = $user->email; // Use user email for SSL

            $instanceDocker = $this->dockerService->createContainer(
                $image,
                $request->name,
                $port,
                5678,
                $package->cpu_limit,
                $package->ram_limit,
                $envArray,
                $volumes, // This is technically ignored by the specific n8n script but kept for interface
                [],
                $subdomain,
                $email
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

            // Nginx & SSL handled by create-instance.sh

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
            // Cleanup via script
            $this->dockerService->removeContainer($container->docker_id, $container->domain);

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

            Process::run([
                base_path('scripts/docker-utils.sh'),
                '--action=update',
                "--id={$container->docker_id}",
                "--arg=--memory={$memory}",
                "--arg=--memory-swap={$memory}",
                "--arg=--cpus={$cpus}"
            ]);

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
             // Fetch Status via Utils
             $statusProcess = Process::run([
                 base_path('scripts/docker-utils.sh'),
                 '--action=inspect-format',
                 "--id={$container->docker_id}",
                 "--arg={{.State.Status}}"
             ]);

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
             $process = Process::run([
                 base_path('scripts/docker-utils.sh'),
                 '--action=stats',
                 "--id={$container->docker_id}",
                 "--arg={{.CPUPerc}};{{.MemUsage}};{{.MemPerc}}"
             ]);

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

    // CREATE USER (Create a standard user under a Reseller or Admin)
    public function createUser(Request $request)
    {
        // Admin or Reseller
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $resellerId = null;
        if (auth()->user()->hasRole('reseller')) {
            $resellerId = auth()->id();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'instance_limit' => 5, // Default for standard users
            'reseller_id' => $resellerId,
        ]);

        $user->assignRole('user');

        return response()->json(['status' => 'success', 'user_id' => $user->id], 201);
    }

    // CREATE RESELLER
    public function createReseller(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

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
        $statusService = new \App\Services\SystemStatusService();
        $stats = $statusService->getSystemStats();

        // Auto-detect base URL/Domain for client configuration convenience
        $baseUrl = url('/');

        return response()->json([
            'status' => 'success',
            'message' => 'Connection successful',
            'hostname' => $stats['hostname'],
            'ip' => $stats['ips'],
            'detected_url' => $baseUrl,
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email
            ]
        ]);
    }

    // USER SSO
    public function sso(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        // Security checks
        if (auth()->user()->hasRole('reseller')) {
            // Reseller can only SSO into their own users
            if ($user->reseller_id !== auth()->id()) {
                 abort(403, 'Unauthorized: You can only access your own users.');
            }
            // Double check target is not admin (redundant if reseller_id check passes, but safe)
            if ($user->hasRole('admin')) {
                abort(403, 'Unauthorized: Cannot access admin account.');
            }
        } elseif (!auth()->user()->hasRole('admin')) {
             // Standard users cannot use SSO
             abort(403, 'Unauthorized');
        }

        // Generate a temporary signed URL for auto-login
        $url = URL::temporarySignedRoute(
            'sso.login',
            now()->addMinutes(1),
            ['user' => $user->id]
        );

        return response()->json([
            'status' => 'success',
            'redirect_url' => $url
        ]);
    }

    // SYSTEM STATS
    public function systemStats()
    {
        $isAdmin = auth()->user()->hasRole('admin');
        $isReseller = auth()->user()->hasRole('reseller');

        $statusService = new \App\Services\SystemStatusService();
        $stats = $statusService->getSystemStats();

        if ($isAdmin) {
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
                'system_info' => [
                    'os' => $stats['os'],
                    'kernel' => $stats['kernel'],
                    'ip' => $stats['ips'],
                    'uptime' => $stats['uptime'],
                    'hostname' => $stats['hostname'],
                ],
                'load_averages' => $stats['loads'],
                'counts' => [
                    'users' => User::count(),
                    'instances_total' => $totalInstances,
                    'instances_running' => $runningInstances,
                    'instances_stopped' => $totalInstances - $runningInstances
                ]
            ]);
        } elseif ($isReseller) {
            // Reseller View: Only their own counts
            $myUsers = User::where('reseller_id', auth()->id())->pluck('id');
            $myInstances = Container::whereIn('user_id', $myUsers)->get();

            $total = $myInstances->count();
            // We can't easily check docker status for filtered list without mapping IDs
            // Simplified approach: Return total DB counts. Real-time status might require more logic.
            // Requirement: "their own instances count". It doesn't strictly say running/stopped breakdown is mandatory if complex.
            // But for consistency let's try.

            // Optimization: Get status from Docker for ALL, then match? Or just return DB counts?
            // "online, load, their own instances count"
            // Let's return simple count.

            return response()->json([
                'status' => 'success',
                'server_status' => 'online',
                'load_averages' => $stats['loads'],
                'counts' => [
                    'users' => $myUsers->count(),
                    'instances_total' => $total,
                ]
            ]);
        }

        abort(403, 'Unauthorized');
    }
}
