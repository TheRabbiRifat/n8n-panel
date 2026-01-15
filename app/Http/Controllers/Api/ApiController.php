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
use Illuminate\Support\Facades\Auth;

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

    /**
     * Helper to find container with scope permissions.
     */
    private function findContainer(string $name): Container
    {
        $query = Container::where('name', $name);
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            // Admin sees all
        } elseif ($user->hasRole('reseller')) {
            // Reseller sees own + customers
            $customerIds = User::where('reseller_id', $user->id)->pluck('id');
            $allowedIds = $customerIds->push($user->id);
            $query->whereIn('user_id', $allowedIds);
        } else {
            // Standard user sees only own
            $query->where('user_id', $user->id);
        }

        return $query->firstOrFail();
    }

    // CREATE
    public function create(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'name' => 'required|string|alpha_dash|unique:containers,name', // Instance Name
            'version' => 'nullable|string', // Default 'latest'
        ]);

        $targetUser = Auth::user();

        // Check Instance Limit
        if ($targetUser->instances()->count() >= $targetUser->instance_limit) {
            abort(403, 'Instance limit reached for this user.');
        }

        $version = $request->version ?: 'latest';
        $genericTimezone = 'Asia/Dhaka'; // Default or from request

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
            $email = $targetUser->email; // Use user email for SSL

            // Create DB Record FIRST
            $container = Container::create([
                'user_id' => $targetUser->id,
                'package_id' => $package->id,
                'docker_id' => 'pending_' . Str::random(8),
                'name' => $request->name,
                'port' => $port,
                'domain' => $subdomain,
                'image_tag' => $version,
                'environment' => json_encode($instanceEnv),
            ]);

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
                $email,
                $container->id
            );

            $container->update([
                'docker_id' => $instanceDocker->getShortDockerIdentifier(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'instance_id' => $container->id,
                'domain' => $subdomain,
                'user_id' => $targetUser->id
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($instanceDocker) {
                $dbId = isset($container) ? $container->id : null;
                try { $this->dockerService->removeContainer($instanceDocker->getShortDockerIdentifier(), '', $dbId); } catch (\Exception $e) {}
            }
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // TERMINATE
    public function terminate($name)
    {
        $container = $this->findContainer($name);

        try {
            // Cleanup via script
            $this->dockerService->removeContainer($container->docker_id, $container->domain, $container->id);

            $container->delete();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // START
    public function start($name)
    {
        $container = $this->findContainer($name);

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
    public function stop($name)
    {
        $container = $this->findContainer($name);
        try {
            $this->dockerService->stopContainer($container->docker_id);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // SUSPEND
    public function suspend($name)
    {
        $container = $this->findContainer($name);

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
    public function unsuspend($name)
    {
        $container = $this->findContainer($name);

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
    public function upgrade(Request $request, $name)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $container = $this->findContainer($name);
        $package = Package::findOrFail($request->package_id);

        try {
            // Update DB
            $container->package_id = $package->id;
            $container->save();

            // Apply limits immediately via Docker Update
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
    public function stats($name)
    {
        $container = $this->findContainer($name);

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

    // RESELLER CRUD
    public function indexResellers()
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        $resellers = User::role('reseller')->get(['id', 'username', 'name', 'email', 'instance_limit', 'created_at']);
        return response()->json(['status' => 'success', 'resellers' => $resellers]);
    }

    public function showReseller($username)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        $user = User::role('reseller')->where('username', $username)->firstOrFail();
        return response()->json(['status' => 'success', 'reseller' => $user]);
    }

    public function updateReseller(Request $request, $username)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        $user = User::role('reseller')->where('username', $username)->firstOrFail();

        $request->validate([
            'name' => 'nullable|string',
            'username' => 'nullable|string|alpha_dash|unique:users,username,' . $user->id,
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'instance_limit' => 'nullable|integer|min:1',
        ]);

        if ($request->filled('name')) $user->name = $request->name;
        if ($request->filled('username')) $user->username = $request->username;
        if ($request->filled('email')) $user->email = $request->email;
        if ($request->filled('password')) $user->password = Hash::make($request->password);
        if ($request->filled('instance_limit')) $user->instance_limit = $request->instance_limit;

        $user->save();
        return response()->json(['status' => 'success', 'message' => 'Reseller updated.']);
    }

    public function suspendReseller(Request $request, $username)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        $user = User::role('reseller')->where('username', $username)->firstOrFail();

        $user->is_suspended = true;
        $user->save();

        // Stop all instances owned by reseller
        $containers = Container::where('user_id', $user->id)->get();
        foreach ($containers as $container) {
            try {
                $this->dockerService->stopContainer($container->docker_id);
                // Optionally mark container as suspended if desired, but request said "stop"
                // Let's also update suspended status to prevent auto-start on reboot if system handles it
                $container->is_suspended = true;
                $container->save();
            } catch (\Exception $e) {
                Log::error("Failed to stop container {$container->name} for suspended reseller: " . $e->getMessage());
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Reseller suspended and instances stopped.']);
    }

    public function unsuspendReseller(Request $request, $username)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        $user = User::role('reseller')->where('username', $username)->firstOrFail();

        $user->is_suspended = false;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Reseller unsuspended.']);
    }

    public function destroyReseller($username)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        $user = User::role('reseller')->where('username', $username)->firstOrFail();

        // Terminate all instances first
        $containers = Container::where('user_id', $user->id)->get();
        foreach ($containers as $container) {
            try {
                $this->dockerService->removeContainer($container->docker_id, $container->domain, $container->id);
                $container->delete();
            } catch (\Exception $e) {
                Log::error("Failed to terminate container {$container->name} during reseller deletion: " . $e->getMessage());
            }
        }

        $user->delete();
        return response()->json(['status' => 'success', 'message' => 'Reseller and all instances deleted.']);
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
            'username' => 'required|string|alpha_dash|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'instance_limit' => 'nullable|integer|min:1',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'instance_limit' => $request->instance_limit ?: 10, // Default to 10 if not set
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
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $isReseller = $user->hasRole('reseller');

        // Only fetch system stats if admin (expensive and sensitive)
        // Standard users/resellers don't need kernel info/load averages usually.
        // But the previous code shared some info.
        // User request: "API return unnecessary and unauthorized data.... like entire server instance count"
        // So we strictly limit what is returned.

        if ($isAdmin) {
             $statusService = new \App\Services\SystemStatusService();
             $stats = $statusService->getSystemStats();

            // Calculate instance counts
            $totalInstances = Container::count();
            $runningInstances = 0;

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
            $myUsers = User::where('reseller_id', $user->id)->pluck('id');
            // Include themselves
            $myUsers->push($user->id);

            $myInstances = Container::whereIn('user_id', $myUsers)->get();
            $total = $myInstances->count();

            return response()->json([
                'status' => 'success',
                'server_status' => 'online',
                'counts' => [
                    'users' => $myUsers->count(), // Count of their customers + themselves
                    'instances_total' => $total,
                ]
            ]);
        } else {
            // Standard User View
            $total = Container::where('user_id', $user->id)->count();
            return response()->json([
                'status' => 'success',
                'counts' => [
                    'instances_total' => $total,
                ]
            ]);
        }
    }
}
