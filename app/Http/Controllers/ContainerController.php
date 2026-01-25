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
        $versions = ['stable', 'latest', 'beta'];

        // Resellers can use any package (according to: "resellers can only use the packages")
        // assuming "use" means using Admin packages to create instances.
        $packages = Package::all();

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
                 \Illuminate\Support\Facades\Process::run("rm -rf $volumePath");
            }

            $containerName = $container->name;
            $container->delete();

            try {
                \Illuminate\Support\Facades\Mail::to(Auth::user()->email)->send(new \App\Mail\InstanceDeleted($containerName));
            } catch (\Exception $e) {}

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
            // Filter out managed containers
            if (in_array($c['id'], $managedIds)) {
                return false;
            }
            // Only show n8n containers (images containing 'n8n')
            if (!str_contains($c['image'], 'n8nio/n8n')) {
                return false;
            }
            return true;
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
            'package_id' => 'required|exists:packages,id', // Package is mandatory
            'port' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            // 1. Inspect the existing orphan container to capture ENV and Image Tag
            $orphanStats = $this->dockerService->getContainer($request->docker_id);

            // Extract Image Tag
            $imageParts = explode(':', $orphanStats['Config']['Image'] ?? 'n8nio/n8n:latest');
            $imageTag = $imageParts[1] ?? 'latest';

            // Extract Existing Envs (Convert "KEY=VALUE" array to associative)
            $existingEnvList = $orphanStats['Config']['Env'] ?? [];
            $existingEnvs = [];
            foreach ($existingEnvList as $envStr) {
                $parts = explode('=', $envStr, 2);
                if (count($parts) === 2) {
                    $existingEnvs[$parts[0]] = $parts[1];
                }
            }

            // Detect existing volume source for /home/node/.n8n
            $volumeHostPath = "/var/lib/n8n/instances/{$request->name}"; // Default
            $mounts = $orphanStats['Mounts'] ?? [];
            foreach ($mounts as $mount) {
                if (($mount['Destination'] ?? '') === '/home/node/.n8n') {
                    $volumeHostPath = $mount['Source'] ?? $volumeHostPath;
                    break;
                }
            }

            // 2. Stop and Remove the Orphan Container
            // Standard removal to clear name/port binding
            $this->dockerService->removeContainer($request->docker_id);

            // 3. Prepare for Recreation
            $package = Package::findOrFail($request->package_id);

            // Generate Domain
            $baseDomain = env('APP_DOMAIN');
            if (empty($baseDomain) || $baseDomain === 'n8n.local') {
                 $hostname = gethostname();
                 $baseDomain = $hostname ?: 'n8n.local';
            }
            $subdomain = Str::slug($request->name) . '.' . $baseDomain;

            // Prepare Environment Variables
            $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
            $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];

            // System/Fixed Envs (These override everything)
            $systemEnvs = [
                'N8N_HOST' => $subdomain,
                'N8N_PORT' => 5678,
                'N8N_PROTOCOL' => 'https',
                'WEBHOOK_URL' => "https://{$subdomain}/",
                'N8N_BLOCK_ENV_ACCESS_IN_NODE' => 'true',
            ];

            // Preserve ALL existing envs except system overrides
            // Filter out internal Docker keys if necessary (usually not needed as we merge)
            $preservedEnvs = array_diff_key($existingEnvs, $systemEnvs);

            // Ensure encryption key exists
            if (!isset($preservedEnvs['N8N_ENCRYPTION_KEY'])) {
                $preservedEnvs['N8N_ENCRYPTION_KEY'] = Str::random(32);
            }
            // Ensure timezone exists
            if (!isset($preservedEnvs['GENERIC_TIMEZONE'])) {
                $preservedEnvs['GENERIC_TIMEZONE'] = 'UTC';
            }

            // Merge: Global -> Preserved (User) -> System (Overrides)
            // Order implies: Global defaults < User Existing < System Enforced
            $finalEnv = array_merge($envArray, $preservedEnvs, $systemEnvs);

            // Volume Config
            $volumes = [$volumeHostPath => '/home/node/.n8n'];

            // DB Credentials (Import new DB if needed, or maybe we should reuse if we could extract?)
            // For import, we generate new postgres credentials to enforce the policy "use database postgresql".
            // If the user wants to keep SQLite data, they'd need to migrate.
            // But if we just inject envs, n8n might respect them and switch DB.
            $safeName = preg_replace('/[^a-z0-9]/', '', $request->name);
            $dbConfig = [
                'host' => '172.17.0.1',
                'port' => 5432,
                'database' => "n8n_{$safeName}",
                'username' => "n8n_{$safeName}",
                'password' => Str::random(16),
            ];

            // 4. Create DB Record (to get ID)
            $container = Container::create([
                'user_id' => $request->user_id,
                'package_id' => $package->id,
                'docker_id' => 'pending_' . Str::random(8),
                'name' => $request->name,
                'port' => $request->port,
                'domain' => $subdomain,
                'image_tag' => $imageTag,
                'environment' => json_encode($preservedEnvs),
                'db_host' => $dbConfig['host'],
                'db_port' => $dbConfig['port'],
                'db_database' => $dbConfig['database'],
                'db_username' => $dbConfig['username'],
                'db_password' => $dbConfig['password'],
            ]);

            // 5. Create New Container (with Nginx + SSL)
            $email = env('MAIL_FROM_ADDRESS', 'admin@example.com');
            $image = "n8nio/n8n:" . $imageTag;

            $panelDbUser = config('database.connections.pgsql.username');

            $instance = $this->dockerService->createContainer(
                $image,
                $request->name,
                $request->port,
                5678,
                $package->cpu_limit,
                $package->ram_limit,
                $finalEnv,
                $volumes,
                [],
                $subdomain,
                $email,
                $container->id,
                $dbConfig,
                $panelDbUser
            );

            // Update DB with real ID
            $container->update([
                'docker_id' => $instance->getShortDockerIdentifier(),
            ]);

            DB::commit();
            return redirect()->route('containers.orphans')->with('success', "Container imported, recreated, and setup with SSL at https://{$subdomain}");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $stats = $this->dockerService->getContainer($container->docker_id);
        $logs = $this->dockerService->getContainerLogs($container->docker_id);

        $versions = ['stable', 'latest', 'beta'];

        // Add current tag if not in list (handles imports of specific versions)
        if (!in_array($container->image_tag, $versions)) {
            $versions[] = $container->image_tag;
        }

        // Fetch packages for dropdown
        // Resellers can use any package
        $packages = Package::all();

        $timezones = \DateTimeZone::listIdentifiers();

        return view('containers.show', compact('container', 'stats', 'logs', 'versions', 'packages', 'timezones'));
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

    public function stats($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $stats = $this->dockerService->getContainerStats($container->docker_id);
        return response()->json($stats);
    }

    public function downloadLogs($id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $logs = $this->dockerService->getContainerLogs($container->docker_id);
        $filename = "instance-{$container->name}-logs.txt";

        return response()->streamDownload(function () use ($logs) {
            echo $logs;
        }, $filename);
    }

    public function update(Request $request, $id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $request->validate([
            'image_tag' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'generic_timezone' => 'required|string',
        ]);

        // 1. Prepare Configuration
        // Global Env
        $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
        $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];

        // Fixed & Dynamic Envs (Not editable by user)
        $fixedAndDynamic = [
            'N8N_HOST' => $container->domain,
            'N8N_PORT' => 5678,
            'N8N_PROTOCOL' => 'https',
            'WEBHOOK_URL' => "https://{$container->domain}/",
            'N8N_SECURE_COOKIE' => 'false',
            'N8N_VERSION_NOTIFICATIONS_ENABLED' => 'false',
            'N8N_TELEMETRY_ENABLED' => 'false',
            'EXECUTIONS_PROCESS' => 'main',
            'N8N_BLOCK_ENV_ACCESS_IN_NODE' => 'true',
        ];

        $envArray = array_merge($envArray, $fixedAndDynamic);

        // User Configurable: Timezone + Preserve/Update Encryption Key
        $existingEnv = $container->environment ? json_decode($container->environment, true) : [];
        $userEnv = [
            'GENERIC_TIMEZONE' => $request->generic_timezone,
        ];

        // Allow updating encryption key if provided, otherwise preserve existing
        if ($request->filled('N8N_ENCRYPTION_KEY')) {
             $userEnv['N8N_ENCRYPTION_KEY'] = $request->input('N8N_ENCRYPTION_KEY');
        } elseif (isset($existingEnv['N8N_ENCRYPTION_KEY'])) {
            $userEnv['N8N_ENCRYPTION_KEY'] = $existingEnv['N8N_ENCRYPTION_KEY'];
        }

        // Merge with Global for Docker creation
        $envArray = array_merge($envArray, $userEnv);

        // Volume Path
        $volumeHostPath = "/var/lib/n8n/instances/{$container->name}";
        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        // Package
        $package = Package::findOrFail($request->package_id);
        // Resellers can use any package, no ownership check needed anymore.

        // Retrieve or Generate DB Credentials
        $dbConfig = [];
        if ($container->db_database && $container->db_username) {
            $dbConfig = [
                'host' => $container->db_host,
                'port' => $container->db_port,
                'database' => $container->db_database,
                'username' => $container->db_username,
                'password' => $container->db_password, // Decrypted by model cast
            ];
        } else {
            // Legacy/Missing - Generate
            $safeName = preg_replace('/[^a-z0-9]/', '', $container->name);
            $dbConfig = [
                'host' => '172.17.0.1',
                'port' => 5432,
                'database' => "n8n_{$safeName}",
                'username' => "n8n_{$safeName}",
                'password' => Str::random(16),
            ];
        }

        DB::beginTransaction();
        try {
            // 2. Stop and Remove old container
            try {
                // Do NOT pass dbConfig here, we don't want to drop the DB on update/recreation!
                $this->dockerService->removeContainer($container->docker_id);
            } catch (\Exception $e) {
                // Ignore if already gone
            }

            // 3. Create New Container
            $image = 'n8nio/n8n:' . $request->image_tag;

            $email = Auth::user()->email ?? env('MAIL_FROM_ADDRESS', 'admin@example.com');

            $panelDbUser = config('database.connections.pgsql.username');

            $instance = $this->dockerService->createContainer(
                $image,
                $container->name,
                $container->port,
                5678,
                $package->cpu_limit,
                $package->ram_limit,
                $envArray,
                $volumes,
                [], // labels
                $container->domain,
                $email,
                $container->id,
                $dbConfig,
                $panelDbUser
            );

            // 4. Update DB
            $container->update([
                'image_tag' => $request->image_tag,
                'docker_id' => $instance->getShortDockerIdentifier(),
                'package_id' => $package->id,
                'environment' => json_encode($userEnv),
                // Ensure legacy containers get updated DB fields if generated
                'db_host' => $dbConfig['host'],
                'db_port' => $dbConfig['port'],
                'db_database' => $dbConfig['database'],
                'db_username' => $dbConfig['username'],
                'db_password' => $dbConfig['password'],
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

    public function transferOwnership(Request $request, $id)
    {
        // Admin only
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $container = Container::findOrFail($id);

        $request->validate([
            'new_user_id' => 'required|exists:users,id',
        ]);

        $newUser = User::findOrFail($request->new_user_id);

        // Prevent transfer if it exceeds new user's limit?
        // Logic: if new user isn't admin and (count >= limit), fail?
        // Let's implement check if not admin.
        if (!$newUser->hasRole('admin')) {
            if ($newUser->instances()->count() >= $newUser->instance_limit) {
                 return back()->with('error', 'New owner has reached their instance limit.');
            }
        }

        $container->user_id = $newUser->id;
        $container->save();

        return back()->with('success', 'Instance ownership transferred successfully to ' . $newUser->name);
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

    public function exportDatabase($id)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $container = Container::findOrFail($id);

        if (!$container->db_database || !$container->db_username) {
            return back()->with('error', 'This instance does not use an external PostgreSQL database.');
        }

        $dbName = $container->db_database;
        $dbUser = $container->db_username; // Used for context if needed, but we execute as postgres superuser usually
        // Or better, execute as the panel user who now has access?
        // But the script is PHP. We can use `sudo -u postgres pg_dump`

        $filename = "backup-{$container->name}-" . date('Y-m-d-H-i') . ".sql";

        return response()->streamDownload(function () use ($dbName) {
            $script = base_path('scripts/db-manager.sh');
            $cmd = "sudo {$script} --action=export --db-name={$dbName}";
            $fp = popen($cmd, 'r');
            while (!feof($fp)) {
                echo fread($fp, 1024);
            }
            pclose($fp);
        }, $filename);
    }

    public function importDatabase(Request $request, $id)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $container = Container::findOrFail($id);

        $request->validate([
            'sql_file' => 'required|file|mimes:sql,txt|max:102400', // 100MB max
        ]);

        if (!$container->db_database) {
            return back()->with('error', 'Instance has no configured database.');
        }

        $dbName = $container->db_database;
        $dbUser = $container->db_username;
        $file = $request->file('sql_file');
        $path = $file->getRealPath();

        try {
            // Stop container first to prevent locks/issues
            $this->dockerService->stopContainer($container->docker_id);

            // Execute Import via Script
            $script = base_path('scripts/db-manager.sh');
            $process = \Illuminate\Support\Facades\Process::run([
                'sudo',
                $script,
                '--action=import',
                "--db-name={$dbName}",
                "--db-user={$dbUser}",
                "--file={$path}"
            ]);

            if (!$process->successful()) {
                throw new \Exception("Import Script Failed: " . $process->errorOutput());
            }

            // Restart container
            $this->dockerService->startContainer($container->docker_id);

            return back()->with('success', 'Database imported successfully.');
        } catch (\Exception $e) {
            // Try to restart anyway
            try { $this->dockerService->startContainer($container->docker_id); } catch (\Exception $ex) {}
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
