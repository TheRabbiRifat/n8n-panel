<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Package;
use App\Models\GlobalSetting;
use App\Services\DockerService;
use App\Services\PortAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstanceController extends Controller
{
    protected $dockerService;
    protected $portAllocator;

    public function __construct(
        DockerService $dockerService,
        PortAllocator $portAllocator
    ) {
        $this->dockerService = $dockerService;
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
            $statsMap[$c['id']] = $c;
        }

        foreach ($instances as $instance) {
            $instance->docker_status = $statsMap[$instance->docker_id]['status'] ?? 'Unknown';
            $instance->docker_state = $statsMap[$instance->docker_id]['state'] ?? 'stopped';
        }

        return view('instances.index', compact('instances'));
    }

    public function create()
    {
        $versions = ['latest', '1.25.1', '1.24.1', '1.22.6'];

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
        ]);

        $package = Package::findOrFail($request->package_id);

        // 1. Assign Port
        $port = $this->portAllocator->allocate();

        // 2. Generate Domain
        // Assume APP_DOMAIN from env, default to local
        $baseDomain = env('APP_DOMAIN', 'n8n.local');
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
        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        // 5. Traefik Labels
        // Sanitize name for router usage (remove special chars if any, but slug is safe)
        $cleanName = Str::slug($request->name);
        // Traefik router name should be unique. Using cleanName + random or just cleanName if unique.
        // Since name is unique in DB, cleanName should be unique enough.
        $routerName = "n8n-" . $cleanName;

        $labels = [
            'traefik.enable' => 'true',
            "traefik.http.routers.{$routerName}.rule" => "Host(`{$subdomain}`)",
            "traefik.http.routers.{$routerName}.entrypoints" => 'websecure',
            "traefik.http.routers.{$routerName}.tls.certresolver" => 'le',
        ];

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
                $volumes,
                $labels
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
                'environment' => null, // Managed globally
            ]);

            // No Nginx steps needed

            DB::commit();
            return redirect()->route('instances.index')->with('success', "Instance created. Domain: https://{$subdomain} (Propagation may take time)");

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
            // Remove Container (Traefik automatically detects removal)
            $this->dockerService->removeContainer($container->docker_id);

            // Remove DB
            $container->delete();

            return redirect()->route('instances.index')->with('success', 'Instance deleted.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
