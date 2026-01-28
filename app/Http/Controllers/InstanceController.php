<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Package;
use App\Models\GlobalSetting;
use App\Services\BackupService;
use App\Services\DockerService;
use App\Services\NginxService;
use App\Services\PortAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstanceController extends Controller
{
    protected $dockerService;
    protected $nginxService;
    protected $portAllocator;
    protected $backupService;

    public function __construct(
        DockerService $dockerService,
        NginxService $nginxService,
        PortAllocator $portAllocator,
        BackupService $backupService
    ) {
        $this->dockerService = $dockerService;
        $this->nginxService = $nginxService;
        $this->portAllocator = $portAllocator;
        $this->backupService = $backupService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Container::with('package');

        if ($user->hasRole('admin')) {
            $query->with('user');
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhere('docker_id', 'like', "%{$search}%")
                  ->orWhere('image_tag', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $instances = $query->paginate(20)->withQueryString();

        // Populate status using DockerService (Optimization: fetch mostly relevant?)
        // Fetching ALL containers is potentially expensive if thousands exist,
        // but for now consistent with previous logic.
        $statsMap = [];
        $allContainers = $this->dockerService->listContainers();

        foreach ($allContainers as $c) {
            // Normalize ID to 12 characters to match database storage
            $shortId = substr($c['id'], 0, 12);
            $statsMap[$shortId] = $c;
        }

        foreach ($instances as $instance) {
            // Ensure db ID is also normalized (it should be, but safe to clamp)
            $dbShortId = substr($instance->docker_id, 0, 12);
            $instance->docker_status = $statsMap[$dbShortId]['status'] ?? 'Unknown';
            $instance->docker_state = $statsMap[$dbShortId]['state'] ?? 'stopped';
        }

        return view('instances.index', compact('instances'));
    }

    public function create()
    {
        $versions = ['stable', 'latest', 'beta'];

        $user = Auth::user();
        if ($user->hasRole('admin')) {
             $packages = Package::all();
        } else {
             $packages = Package::where('user_id', $user->id)->get();
        }

        return view('instances.create', compact('versions', 'packages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'alpha_dash', 'max:64', 'unique:containers,name'],
            'version' => ['required', 'string', 'in:stable,latest,beta'],
            'package_id' => ['required', 'exists:packages,id'],
            'generic_timezone' => ['required', 'string', 'max:100'],
        ]);

        // Check Instance Limit
        $user = Auth::user();
        $package = Package::instance()->findOrFail($request->package_id);

        if (!$user->hasRole('admin')) {
            // Count Check
            $count = $user->instances()->count();
            if ($count >= $user->instance_limit) {
                return back()->with('error', "Instance limit reached ({$user->instance_limit}). Please contact admin to increase limit.");
            }

            // Reseller Resource Check
            if ($user->hasRole('reseller') && $user->package) {
                $existingInstances = $user->instances()->with('package')->get();
                $totalCpu = $existingInstances->sum(fn($i) => $i->package->cpu_limit ?? 0);
                $totalRam = $existingInstances->sum(fn($i) => $i->package->ram_limit ?? 0);

                $newCpu = $package->cpu_limit ?? 0;
                $newRam = $package->ram_limit ?? 0;

                if (($totalCpu + $newCpu) > $user->package->cpu_limit) {
                     return back()->with('error', "CPU limit exceeded. Plan allows {$user->package->cpu_limit} CPUs.");
                }

                if (($totalRam + $newRam) > $user->package->ram_limit) {
                     return back()->with('error', "RAM limit exceeded. Plan allows {$user->package->ram_limit} GB RAM.");
                }
            }
        }

        // 1. Assign Port
        $port = $this->portAllocator->allocate();

        // 2. Generate Domain
        // Fully automated hostname detection if APP_DOMAIN is not explicitly set
        $baseDomain = env('APP_DOMAIN');
        if (empty($baseDomain) || $baseDomain === 'n8n.local') {
             $hostname = gethostname();
             $baseDomain = $hostname ?: 'n8n.local';
        }
        $subdomain = Str::slug($request->name) . '.' . $baseDomain;

        // 3. Global Environment
        $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
        $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];

        // Add specific envs
        $envArray['N8N_HOST'] = $subdomain;
        $envArray['N8N_PORT'] = 5678;
        $envArray['N8N_PROTOCOL'] = 'https';
        $envArray['WEBHOOK_URL'] = "https://{$subdomain}/";

        // 4. Volume Path
        $volumeHostPath = "/var/lib/n8n/instances/{$request->name}";
        // FS operations are handled by the create-instance.sh script now.
        // We define volumes array for compatibility if needed, but script hardcodes the path.
        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        // Generate Instance Specific Envs (Important for n8n persistence)
        $instanceEnv = [
            'N8N_ENCRYPTION_KEY' => Str::random(32),
            'GENERIC_TIMEZONE' => $request->generic_timezone ?: 'Asia/Dhaka',
            'N8N_BLOCK_ENV_ACCESS_IN_NODE' => 'true',
        ];
        $envArray = array_merge($envArray, $instanceEnv);

        $image = 'n8nio/n8n:' . $request->version;

        // DB Credentials
        // Use lowercase, strip special chars but append random string to ensure uniqueness
        $safeName = preg_replace('/[^a-z0-9]/', '', strtolower($request->name)) . '_' . Str::random(4);
        $dbConfig = [
            'host' => '172.17.0.1', // Default Docker Gateway
            'port' => 5432,
            'database' => "n8n_{$safeName}",
            'username' => "n8n_{$safeName}",
            'password' => Str::random(16),
        ];

        DB::beginTransaction();
        $instance = null;

        try {
            // Create DB Record FIRST to get the ID for persistent volume path
            $container = Container::create([
                'user_id' => Auth::id(),
                'package_id' => $package->id,
                'docker_id' => 'pending_' . Str::random(8), // Placeholder
                'name' => $request->name,
                'port' => $port,
                'domain' => $subdomain,
                'image_tag' => $request->version,
                'environment' => json_encode($instanceEnv),
                'db_host' => $dbConfig['host'],
                'db_port' => $dbConfig['port'],
                'db_database' => $dbConfig['database'],
                'db_username' => $dbConfig['username'],
                'db_password' => $dbConfig['password'],
            ]);

            // Docker Create
            // Pass domain and email for Nginx/Certbot setup inside the script
            $email = env('MAIL_FROM_ADDRESS', 'admin@example.com');

            $panelDbUser = config('database.connections.pgsql.username');

            $instance = $this->dockerService->createContainer(
                $image,
                $request->name,
                $port,
                5678,
                $package->cpu_limit,
                $package->ram_limit,
                $envArray,
                $volumes,
                [],
                $subdomain,
                $email,
                $container->id, // Pass DB ID for volume path
                $dbConfig,
                $panelDbUser
            );

            // Update DB Record with real Docker ID
            $container->update([
                'docker_id' => $instance->getShortDockerIdentifier(),
            ]);

            // Nginx & Certbot handled by script via createContainer
            $sslSuccess = true; // Assumed success or non-critical failure in script

            DB::commit();

            // Send Email
            try {
                \Illuminate\Support\Facades\Mail::to(Auth::user()->email)->send(new \App\Mail\InstanceCreated($container));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send creation email: " . $e->getMessage());
            }

            $msg = "Instance created. Domain: https://{$subdomain}";
            if (!$sslSuccess) {
                $msg .= " (SSL Certificate could not be obtained automatically, please check logs or DNS).";
            }

            return redirect()->route('instances.index')->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            // Cleanup Docker
            if ($instance) {
                try { $this->dockerService->removeContainer($instance->getShortDockerIdentifier()); } catch (\Exception $e) {}
            }
            return back()->with('error', 'Creation failed: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $container = Container::findOrFail($id);

        if (!Auth::user()->hasRole('admin') && $container->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            // Remove Container & Associated Resources (Nginx, Volume) via Script
            // We pass the domain to allow Nginx cleanup
            // Pass DB ID for volume cleanup
            // Pass DB Config for database cleanup
            $dbConfig = [
                'database' => $container->db_database,
                'username' => $container->db_username,
            ];
            $this->dockerService->removeContainer($container->docker_id, $container->domain, $container->id, $dbConfig);

            // Attempt to remove backups
            $this->backupService->deleteBackup($container->name);

            // Remove DB
            $containerName = $container->name;
            $container->delete();

            // Send Email
            try {
                \Illuminate\Support\Facades\Mail::to(Auth::user()->email)->send(new \App\Mail\InstanceDeleted($containerName));
            } catch (\Exception $e) {}

            return redirect()->route('instances.index')->with('success', 'Instance deleted.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
