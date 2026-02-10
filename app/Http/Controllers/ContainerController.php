<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Package;
use App\Models\User;
use App\Models\GlobalSetting;
use App\Services\DockerService;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContainerController extends Controller
{
    protected $dockerService;
    protected $backupService;

    public function __construct(DockerService $dockerService, BackupService $backupService)
    {
        $this->dockerService = $dockerService;
        $this->backupService = $backupService;
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
            $dbConfig = [
                'database' => $container->db_database,
                'username' => $container->db_username,
            ];

            // Remove Container, Nginx, Volume, DB
            $this->dockerService->removeContainer($container->docker_id, $container->domain, $container->id, $dbConfig);

            // Remove Backups
            $this->backupService->deleteBackup($container->name);

            $containerName = $container->name;
            $container->delete();

            try {
                \Illuminate\Support\Facades\Mail::to(Auth::user()->email)->send(new \App\Mail\InstanceDeleted($containerName));
            } catch (\Exception $e) {}

            return back()->with('success', 'Instance and all associated resources (DB, Volume, Backups) removed.');
        } catch (\Exception $e) {
             // If docker/script fails, we still try to delete the DB record, or maybe we should keep it?
             // Usually better to force delete via script if possible, but if script fails we might leave artifacts.
             // If we delete the record, the user can't retry.
             // But existing logic deleted it in catch block?
             // "return back()->with('warning', 'Container removed from database but Docker might have failed..."
             // I'll keep the behavior of deleting the record if it fails, but maybe log it.
             $container->delete();
             return back()->with('warning', 'Instance removed from database but resource cleanup might have failed: ' . $e->getMessage());
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

            // Remove SMTP keys (ensure clean slate on import)
            foreach (Container::SMTP_ENV_KEYS as $key) {
                unset($finalEnv[$key]);
            }

            // Volume Config
            $volumes = [$volumeHostPath => '/home/node/.n8n'];

            // DB Credentials (Import new DB if needed, or maybe we should reuse if we could extract?)
            // For import, we generate new postgres credentials to enforce the policy "use database postgresql".
            // If the user wants to keep SQLite data, they'd need to migrate.
            // But if we just inject envs, n8n might respect them and switch DB.
            $safeName = preg_replace('/[^a-z0-9]/', '', strtolower($request->name)) . '_' . Str::random(4);
            $dbConfig = [
                'host' => $this->dockerService->getDockerGatewayIp(),
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

        // Fetch available backups for this instance
        $backups = [];
        if (Auth::user()->hasRole('admin')) {
            try {
                $backups = $this->backupService->listBackupsForInstance($container->name);
            } catch (\Exception $e) { /* ignore config errors */ }
        }

        return view('containers.show', compact('container', 'stats', 'logs', 'versions', 'packages', 'timezones', 'backups'));
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

        $package = Package::findOrFail($request->package_id);

        // User Configurable Envs (Timezone + Encryption Key)
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

        // We update the DB object with new user preferences BEFORE recreation logic uses it?
        // Actually, we should pass these explicit values to our helper.

        DB::beginTransaction();
        try {
            // Update the container record with user intent (but not Docker ID yet)
            $container->fill([
                'image_tag' => $request->image_tag,
                'package_id' => $package->id,
                'environment' => json_encode($userEnv),
            ]);
            $container->save();

            // Perform Recreation
            $this->recreateContainer($container);

            DB::commit();
            return back()->with('success', 'Instance updated and recreated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function toggleRecovery(Request $request, $id)
    {
        $container = Container::findOrFail($id);
        $this->authorizeAccess($container);

        $isRecovery = $request->boolean('recovery_mode');

        // Toggle state
        $container->is_recovery_mode = $isRecovery;
        $container->save();

        try {
            $this->recreateContainer($container);
            $status = $isRecovery ? 'enabled' : 'disabled';
            return back()->with('success', "Recovery mode {$status}. Instance recreated.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to toggle recovery mode: ' . $e->getMessage());
        }
    }

    /**
     * Shared logic to destroy and recreate a container with current DB settings.
     */
    private function recreateContainer(Container $container)
    {
        // 1. Prepare Configuration
        // Global Env
        $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
        $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];

        // Fixed & Dynamic Envs
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

        // User Configurable Envs (from DB)
        $userEnv = $container->environment ? json_decode($container->environment, true) : [];
        $envArray = array_merge($envArray, $userEnv);

        // Remove SMTP keys if present (only injected in recovery mode)
        // We remove them after merging global and user envs to ensure they are cleared
        // unless explicitly re-added by the recovery block below.
        foreach (Container::SMTP_ENV_KEYS as $key) {
            unset($envArray[$key]);
        }

        // Recovery Mode - Inject SMTP
        if ($container->is_recovery_mode) {
            $smtpEnv = [
                'N8N_EMAIL_MODE' => 'smtp',
                'N8N_SMTP_HOST' => config('mail.mailers.smtp.host'),
                'N8N_SMTP_PORT' => config('mail.mailers.smtp.port'),
                'N8N_SMTP_USER' => config('mail.mailers.smtp.username'),
                'N8N_SMTP_PASS' => config('mail.mailers.smtp.password'),
                'N8N_SMTP_SENDER' => config('mail.from.address'),
                'N8N_SMTP_SSL' => (config('mail.mailers.smtp.scheme') === 'tls') ? 'true' : 'false',
            ];
            // Filter out nulls just in case
            $smtpEnv = array_filter($smtpEnv, fn($v) => !is_null($v));
            $envArray = array_merge($envArray, $smtpEnv);
        }

        // Volume Path
        $volumeHostPath = "/var/lib/n8n/instances/{$container->name}";
        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        // Retrieve or Generate DB Credentials
        $dbConfig = [];
        if ($container->db_database && $container->db_username) {
            $dbConfig = [
                'host' => $container->db_host,
                'port' => $container->db_port,
                'database' => $container->db_database,
                'username' => $container->db_username,
                'password' => $container->db_password,
            ];
        } else {
             // Generate if missing (Legacy support)
            $safeName = preg_replace('/[^a-z0-9]/', '', strtolower($container->name)) . '_' . Str::random(4);
            $dbConfig = [
                'host' => $this->dockerService->getDockerGatewayIp(),
                'port' => 5432,
                'database' => "n8n_{$safeName}",
                'username' => "n8n_{$safeName}",
                'password' => Str::random(16),
            ];
        }

        // 2. Stop and Remove old container
        try {
            $this->dockerService->removeContainer($container->docker_id);
        } catch (\Exception $e) {
            // Ignore if already gone
        }

        // 3. Create New Container
        $image = 'n8nio/n8n:' . ($container->image_tag ?? 'latest');
        $email = Auth::user()->email ?? env('MAIL_FROM_ADDRESS', 'admin@example.com');
        $panelDbUser = config('database.connections.pgsql.username');
        $package = $container->package; // Relations should be loaded or lazy loaded

        $instance = $this->dockerService->createContainer(
            $image,
            $container->name,
            $container->port,
            5678,
            $package->cpu_limit ?? 1,
            $package->ram_limit ?? 1,
            $envArray,
            $volumes,
            [], // labels
            $container->domain,
            $email,
            $container->id,
            $dbConfig,
            $panelDbUser
        );

        // 4. Update DB details (Docker ID and potentially DB creds if generated)
        $container->update([
            'docker_id' => $instance->getShortDockerIdentifier(),
            'db_host' => $dbConfig['host'],
            'db_port' => $dbConfig['port'],
            'db_database' => $dbConfig['database'],
            'db_username' => $dbConfig['username'],
            'db_password' => $dbConfig['password'],
        ]);
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
            $cmd = "sudo {$script} --action=export --db-name='{$dbName}'";
            $fp = popen($cmd, 'r');
            while (!feof($fp)) {
                echo fread($fp, 1024);
            }
            pclose($fp);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
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

        return $this->performDatabaseRestore($container, $path);
    }

    public function restoreBackup(Request $request, $id)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $container = Container::findOrFail($id);
        $request->validate(['backup_path' => 'required|string']);

        $backupPath = $request->backup_path;

        try {
            // Ensure disk is configured
            if (!$this->backupService->configureDisk()) {
                 throw new \Exception("Backup system not configured.");
            }

            // Download to temp file
            $tempPath = storage_path('app/temp/restore_' . Str::random(10) . '.sql');
            if (!file_exists(dirname($tempPath))) mkdir(dirname($tempPath), 0755, true);

            $stream = \Illuminate\Support\Facades\Storage::disk('backup')->readStream($backupPath);
            $out = fopen($tempPath, 'w');
            while (!feof($stream)) {
                fwrite($out, fread($stream, 8192));
            }
            fclose($stream);
            fclose($out);

            // Execute Restore
            $result = $this->performDatabaseRestore($container, $tempPath);

            // Cleanup
            @unlink($tempPath);

            return $result;

        } catch (\Exception $e) {
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    private function performDatabaseRestore(Container $container, string $filePath)
    {
        $dbName = $container->db_database;
        $dbUser = $container->db_username;

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
                "--file={$filePath}"
            ]);

            if (!$process->successful()) {
                throw new \Exception("Import Script Failed: " . $process->errorOutput());
            }

            // Restart container
            $this->dockerService->startContainer($container->docker_id);

            return back()->with('success', 'Database restored successfully.');
        } catch (\Exception $e) {
            // Try to restart anyway
            try { $this->dockerService->startContainer($container->docker_id); } catch (\Exception $ex) {}
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }
}
