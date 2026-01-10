<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Package;
use App\Models\GlobalSetting;
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

    public function __construct(
        DockerService $dockerService,
        NginxService $nginxService,
        PortAllocator $portAllocator
    ) {
        $this->dockerService = $dockerService;
        $this->nginxService = $nginxService;
        $this->portAllocator = $portAllocator;
    }

    public function index()
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            $instances = Container::with('package', 'user')->get();
        } else {
            $instances = Container::with('package')->where('user_id', $user->id)->get();
        }

        // Populate status using DockerService
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
        $versions = [
            'latest' => 'Stable',
            'next' => 'Beta',
        ];

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
            'name' => 'required|string|alpha_dash|unique:containers,name',
            'version' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'generic_timezone' => 'required|string',
        ]);

        // Check Instance Limit
        $user = Auth::user();
        if (!$user->hasRole('admin')) {
            $count = $user->instances()->count();
            if ($count >= $user->instance_limit) {
                return back()->with('error', "Instance limit reached ({$user->instance_limit}). Please contact admin to increase limit.");
            }
        }

        $package = Package::findOrFail($request->package_id);

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

        // Ensure volume exists and has correct permissions (n8n runs as node:1000)
        \Illuminate\Support\Facades\Process::run("sudo mkdir -p $volumeHostPath");
        \Illuminate\Support\Facades\Process::run("sudo chown -R 1000:1000 $volumeHostPath");

        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        // Generate Instance Specific Envs (Important for n8n persistence)
        $instanceEnv = [
            'N8N_ENCRYPTION_KEY' => Str::random(32),
            'GENERIC_TIMEZONE' => $request->generic_timezone ?: 'Asia/Dhaka',
            'N8N_BLOCK_ENV_ACCESS_IN_NODE' => 'true',
        ];
        $envArray = array_merge($envArray, $instanceEnv);

        $image = 'n8nio/n8n:' . $request->version;

        DB::beginTransaction();
        $instance = null;

        try {
            // Docker Create
            $instance = $this->dockerService->createContainer(
                $image,
                $request->name,
                $port,
                5678,
                $package->cpu_limit,
                $package->ram_limit,
                $envArray,
                $volumes
            );

            // Create DB Record
            $container = Container::create([
                'user_id' => Auth::id(),
                'package_id' => $package->id,
                'docker_id' => $instance->getShortDockerIdentifier(),
                'name' => $request->name,
                'port' => $port,
                'domain' => $subdomain,
                'image_tag' => $request->version,
                'environment' => json_encode($instanceEnv),
            ]);

            // Nginx & Certbot
            $this->nginxService->createVhost($subdomain, $port);

            // Attempt to secure via Certbot (Best effort)
            $sslSuccess = $this->nginxService->secureVhost($subdomain);

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
            // Remove Nginx Vhost
            if ($container->domain) {
                $this->nginxService->removeVhost($container->domain);
            }

            // Remove Container
            $this->dockerService->removeContainer($container->docker_id);

            // DELETE VOLUME (Permanent removal as requested)
            $volumePath = "/var/lib/n8n/instances/{$container->name}";
            if (Str::startsWith($volumePath, '/var/lib/n8n/instances/') && strlen($volumePath) > 23) {
                 \Illuminate\Support\Facades\Process::run("sudo rm -rf $volumePath");
            }

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
