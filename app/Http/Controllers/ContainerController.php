<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Package;
use App\Models\User;
use App\Models\GlobalSetting;
use App\Services\DockerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContainerController extends Controller
{
    protected $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    public function create()
    {
        $versions = [
            'latest',
            '1.25.1',
            '1.24.1',
            '1.22.6',
            '1.21.1',
            '0.236.3'
        ];

        $user = Auth::user();
        if ($user->hasRole('admin')) {
             $packages = Package::all();
        } else {
             $packages = Package::where('user_id', $user->id)->get();
        }

        return view('containers.create', compact('versions', 'packages'));
    }

    public function store(Request $request)
    {
        // ... (Legacy store, omitted for brevity but keeping structure if needed or just minimal)
        // Since create uses InstanceController, this might be unused.
        // I will focus on update/show/destroy which are used by "Manage" view.
        return abort(404);
    }

    public function start($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        try {
            $this->dockerService->startContainer($container->docker_id);
            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Container started.']);
            }
            return back()->with('success', 'Container started.');
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function stop($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        try {
            $this->dockerService->stopContainer($container->docker_id);
            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Container stopped.']);
            }
            return back()->with('success', 'Container stopped.');
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        try {
            $this->dockerService->removeContainer($container->docker_id);

            // DELETE VOLUME
            $volumePath = "/var/lib/n8n/instances/{$container->name}";
            if (Str::startsWith($volumePath, '/var/lib/n8n/instances/') && strlen($volumePath) > 23) {
                 \Illuminate\Support\Facades\Process::run("sudo rm -rf $volumePath");
            }

            $container->delete();
            return back()->with('success', 'Container and volume removed.');
        } catch (\Exception $e) {
             $container->delete();
             return back()->with('warning', 'Container removed from database but Docker might have failed: ' . $e->getMessage());
        }
    }

    public function orphans()
    {
        $allContainers = $this->dockerService->listContainers();
        $managedIds = Container::pluck('docker_id')->toArray();
        $orphans = array_filter($allContainers, function($c) use ($managedIds) {
            return !in_array($c['id'], $managedIds);
        });

        $users = User::all();
        $packages = Package::all();

        return view('containers.orphans', compact('orphans', 'users', 'packages'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'docker_id' => 'required|string|unique:containers,docker_id',
            'name' => 'required|string',
            'user_id' => 'required|exists:users,id',
            'package_id' => 'nullable|exists:packages,id',
            'port' => 'required|integer',
        ]);

        Container::create([
            'user_id' => $request->user_id,
            'package_id' => $request->package_id,
            'docker_id' => $request->docker_id,
            'name' => $request->name,
            'port' => $request->port,
            'image_tag' => 'latest',
        ]);

        return redirect()->route('containers.orphans')->with('success', 'Container imported successfully.');
    }

    public function show($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $stats = $this->dockerService->getContainer($container->docker_id);
        $logs = $this->dockerService->getContainerLogs($container->docker_id);

        $versions = ['latest', '1.25.1', '1.24.1', '1.22.6'];

        // Fetch packages for dropdown
        $user = Auth::user();
        if ($user->hasRole('admin')) {
             $packages = Package::all();
        } else {
             $packages = Package::where('user_id', $user->id)->get();
        }

        return view('containers.show', compact('container', 'stats', 'logs', 'versions', 'packages'));
    }

    public function restart($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        try {
            $this->dockerService->restartContainer($container->docker_id);
            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Container restarted.']);
            }
            return back()->with('success', 'Container restarted.');
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function logs($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $logs = $this->dockerService->getContainerLogs($container->docker_id);
        return response()->json(['logs' => $logs]);
    }

    public function update(Request $request, $id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $request->validate([
            'image_tag' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'custom_env' => 'nullable|string', // Key=Value format
        ]);

        // 1. Prepare Configuration
        // Global Env
        $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
        $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];

        // Essential Envs
        if ($container->domain) {
            $envArray['N8N_HOST'] = $container->domain;
            $envArray['N8N_PORT'] = 5678;
            $envArray['N8N_PROTOCOL'] = 'https';
            $envArray['WEBHOOK_URL'] = "https://{$container->domain}/";
        }

        // Preserve critical envs from existing record (e.g. Encryption Key)
        $existingEnv = $container->environment ? json_decode($container->environment, true) : [];
        $criticalKeys = ['N8N_ENCRYPTION_KEY', 'GENERIC_TIMEZONE'];
        $preservedEnv = array_intersect_key($existingEnv, array_flip($criticalKeys));

        // Custom Envs (Merge and Override)
        $customEnvArray = [];
        if ($request->custom_env) {
            $lines = explode("\n", $request->custom_env);
            foreach ($lines as $line) {
                if (str_contains($line, '=')) {
                    list($k, $v) = explode('=', trim($line), 2);
                    $customEnvArray[trim($k)] = trim($v);
                }
            }
        }

        // Final Instance Env (Preserved + Custom)
        $finalInstanceEnv = array_merge($preservedEnv, $customEnvArray);

        // Merge with Global for Docker creation
        $envArray = array_merge($envArray, $finalInstanceEnv);

        // Volume Path
        $volumeHostPath = "/var/lib/n8n/instances/{$container->name}";
        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        // Package
        $package = Package::findOrFail($request->package_id);
        // Verify ownership if needed
        if (!Auth::user()->hasRole('admin') && $package->user_id !== Auth::id()) {
             abort(403);
        }

        DB::beginTransaction();
        try {
            // 2. Stop and Remove old container
            try {
                $this->dockerService->removeContainer($container->docker_id);
            } catch (\Exception $e) {
                // Ignore if already gone
            }

            // 3. Create New Container
            $image = 'n8nio/n8n:' . $request->image_tag;

            $instance = $this->dockerService->createContainer(
                $image,
                $container->name,
                $container->port,
                5678,
                $package->cpu_limit,
                $package->ram_limit,
                $envArray,
                $volumes
            );

            // 4. Update DB
            $container->update([
                'image_tag' => $request->image_tag,
                'docker_id' => $instance->getShortDockerIdentifier(),
                'package_id' => $package->id,
                'environment' => $finalInstanceEnv ? json_encode($finalInstanceEnv) : null,
            ]);

            DB::commit();
            return back()->with('success', 'Instance updated and recreated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function deleteOrphan(Request $request)
    {
        $request->validate(['docker_id' => 'required|string']);
        try {
            $this->dockerService->removeContainer($request->docker_id);
            return back()->with('success', 'Orphan container removed.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function authorizeAccess(Container $container)
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($container->user_id === $user->id) {
            return true;
        }
        abort(403);
    }
}
