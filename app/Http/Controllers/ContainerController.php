<?php

namespace App\Http\Controllers;

use App\Models\Container;
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
        return view('containers.create', compact('versions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|alpha_dash',
            'version' => 'required|string',
            'port' => 'required|integer',
        ]);

        // Logic to start container
        // Ensure name is unique
        if (Container::where('name', $request->name)->exists()) {
             return back()->withErrors(['name' => 'Container name already exists']);
        }

        $image = 'n8nio/n8n:' . $request->version;

        $instance = null;
        DB::beginTransaction();

        try {
            // 1. Create Docker Container
            $instance = $this->dockerService->createContainer(
                $image,
                $request->name,
                $request->port
            );

            // 2. Create DB Record
            Container::create([
                'user_id' => Auth::id(), // Assign to current user for now, or allow selecting user if Admin
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
