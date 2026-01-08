<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Package;
use App\Models\User;
use App\Services\DockerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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
        // Admins see all, Resellers see theirs
        if ($user->hasRole('admin')) {
             $packages = Package::all();
        } else {
             $packages = Package::where('user_id', $user->id)->get();
        }

        return view('containers.create', compact('versions', 'packages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|alpha_dash',
            'version' => 'required|string',
            'port' => 'required|integer',
            'package_id' => 'required|exists:packages,id',
        ]);

        // Logic to start container
        // Ensure name is unique
        if (Container::where('name', $request->name)->exists()) {
             return back()->withErrors(['name' => 'Container name already exists']);
        }

        $image = 'n8nio/n8n:' . $request->version;
        $package = Package::findOrFail($request->package_id);

        // Check if user is authorized to use this package
        if (!Auth::user()->hasRole('admin') && $package->user_id !== Auth::id()) {
             // For simplicity, resellers can only use their own packages.
             // If requirements imply they can use admin packages, remove this check.
             // "admin own all, reseller own theirs" implies segregation.
             abort(403, 'Unauthorized package.');
        }

        $instance = null;
        DB::beginTransaction();

        try {
            // 1. Create Docker Container
            $instance = $this->dockerService->createContainer(
                $image,
                $request->name,
                $request->port,
                5678,
                $package->cpu_limit,
                $package->ram_limit
            );

            // 2. Create DB Record
            Container::create([
                'user_id' => Auth::id(), // Assign to current user for now, or allow selecting user if Admin
                'package_id' => $package->id,
                'docker_id' => $instance->getShortDockerIdentifier(),
                'name' => $request->name,
                'port' => $request->port,
            ]);

            DB::commit();

            return redirect()->route('dashboard')->with('success', 'Container created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            // 3. Rollback Docker Container if it was created
            if ($instance) {
                try {
                    $this->dockerService->removeContainer($instance->getShortDockerIdentifier());
                } catch (\Exception $dockerException) {
                    // Log that we failed to cleanup orphaned container
                    // Log::error("Failed to remove orphaned container: " . $dockerException->getMessage());
                }
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function start($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        try {
            $this->dockerService->startContainer($container->docker_id);
            return back()->with('success', 'Container started.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function stop($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        try {
            $this->dockerService->stopContainer($container->docker_id);
            return back()->with('success', 'Container stopped.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        try {
            $this->dockerService->removeContainer($container->docker_id);
            $container->delete();
            return back()->with('success', 'Container removed.');
        } catch (\Exception $e) {
             // If docker remove fails (maybe already gone), still delete from DB?
             // Or maybe force delete.
             $container->delete();
             return back()->with('warning', 'Container removed from database but Docker might have failed: ' . $e->getMessage());
        }
    }

    public function orphans()
    {
        // Get all docker containers
        $allContainers = $this->dockerService->listContainers();

        // Get all managed container docker_ids
        $managedIds = Container::pluck('docker_id')->toArray();

        // Filter orphans
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
        ]);

        return redirect()->route('containers.orphans')->with('success', 'Container imported successfully.');
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
