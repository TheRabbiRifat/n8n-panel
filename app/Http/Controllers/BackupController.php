<?php

namespace App\Http\Controllers;

use App\Models\BackupSetting;
use App\Models\Container;
use App\Models\Package;
use App\Models\GlobalSetting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DockerService;
use App\Services\PortAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Rules\CronExpression;

class BackupController extends Controller
{
    protected $backupService;
    protected $dockerService;
    protected $portAllocator;

    public function __construct(
        BackupService $backupService,
        DockerService $dockerService,
        PortAllocator $portAllocator
    ) {
        $this->backupService = $backupService;
        $this->dockerService = $dockerService;
        $this->portAllocator = $portAllocator;
    }

    public function index()
    {
        $setting = BackupSetting::first();
        $backups = [];
        try {
            $backups = $this->backupService->listBackups();
        } catch (\Exception $e) {
            // Config might be invalid
        }

        return view('admin.backups.index', compact('setting', 'backups'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'driver' => 'required|in:local,ftp,s3',
            'host' => 'nullable|required_if:driver,ftp|string|max:255',
            'username' => 'nullable|required_if:driver,ftp,s3|string|max:255',
            'password' => 'nullable|required_if:driver,ftp,s3|string|max:255',
            'bucket' => 'nullable|required_if:driver,s3|string|max:255',
            'region' => 'nullable|required_if:driver,s3|string|max:100',
            'endpoint' => 'nullable|url|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'path' => 'nullable|string|max:255',
            'cron_expression' => 'nullable|string|max:255',
        ]);

        if ($request->filled('cron_expression')) {
            $parts = explode(' ', $request->cron_expression);
            if (count($parts) < 5) {
                return back()->with('error', 'Invalid cron expression format.')->withInput();
            }
        }

        try {
            $this->backupService->testConnection($request->all());
        } catch (\Exception $e) {
            return back()->with('error', 'Connection test failed: ' . $e->getMessage())->withInput();
        }

        $setting = BackupSetting::firstOrNew();
        $setting->fill($request->all());
        $setting->enabled = $request->has('enabled');
        $setting->save();

        return back()->with('success', 'Backup settings saved.');
    }

    public function run()
    {
        try {
            Artisan::call('backup:run');
            $output = Artisan::output();
            return back()->with('success', 'Backup process started. Output: ' . $output);
        } catch (\Exception $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function download(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        return $this->backupService->downloadBackup($request->path);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'folders' => 'required|array',
            'folders.*' => 'string'
        ]);

        $folders = $request->folders;
        $results = [];
        $errors = [];

        // Ensure disk configured
        if (!$this->backupService->configureDisk()) {
            return back()->with('error', 'Backup storage not configured.');
        }

        $adminUser = Auth::user();
        if (!$adminUser || !$adminUser->hasRole('admin')) {
             // Fallback/Safety
             $adminUser = User::role('admin')->first();
        }

        foreach ($folders as $instanceName) {
            try {
                // Check if instance exists
                $container = Container::where('name', $instanceName)->first();
                $encryptionKey = null;

                // 1. Retrieve Key from Backup if available
                $keyPath = "{$instanceName}/key.txt";
                if (Storage::disk('backup')->exists($keyPath)) {
                    $encryptionKey = trim(Storage::disk('backup')->get($keyPath));
                }

                // 2. Create Instance if missing
                if (!$container) {
                    $package = Package::first();
                    if (!$package) {
                        throw new \Exception("No packages available to create instance.");
                    }

                    // Allocation
                    $port = $this->portAllocator->allocate();
                    $baseDomain = env('APP_DOMAIN');
                    if (empty($baseDomain) || $baseDomain === 'n8n.local') {
                        $hostname = gethostname();
                        $baseDomain = $hostname ?: 'n8n.local';
                    }
                    $subdomain = Str::slug($instanceName) . '.' . $baseDomain;

                    // Environment
                    $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
                    $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];
                    foreach (Container::SMTP_ENV_KEYS as $key) {
                        unset($envArray[$key]);
                    }

                    $envArray['N8N_HOST'] = $subdomain;
                    $envArray['N8N_PORT'] = 5678;
                    $envArray['N8N_PROTOCOL'] = 'https';
                    $envArray['WEBHOOK_URL'] = "https://{$subdomain}/";

                    $instanceEnv = [
                        'N8N_ENCRYPTION_KEY' => $encryptionKey ?: Str::random(32),
                        'GENERIC_TIMEZONE' => 'Asia/Dhaka', // Default
                        'N8N_BLOCK_ENV_ACCESS_IN_NODE' => 'true',
                    ];
                    $envArray = array_merge($envArray, $instanceEnv);

                    // DB Creds
                    $safeName = preg_replace('/[^a-z0-9]/', '', strtolower($instanceName)) . '_' . Str::random(4);
                    $dbConfig = [
                        'host' => $this->dockerService->getDockerGatewayIp(),
                        'port' => 5432,
                        'database' => "n8n_{$safeName}",
                        'username' => "n8n_{$safeName}",
                        'password' => Str::random(16),
                    ];

                    $volumeHostPath = "/var/lib/n8n/instances/{$instanceName}";
                    $volumes = [$volumeHostPath => '/home/node/.n8n'];
                    $image = 'n8nio/n8n:latest'; // Default
                    $email = env('MAIL_FROM_ADDRESS', 'admin@example.com');
                    $panelDbUser = config('database.connections.pgsql.username');

                    DB::beginTransaction();
                    try {
                         $container = Container::create([
                            'user_id' => $adminUser->id,
                            'package_id' => $package->id,
                            'docker_id' => 'pending_' . Str::random(8),
                            'name' => $instanceName,
                            'port' => $port,
                            'domain' => $subdomain,
                            'image_tag' => 'latest',
                            'environment' => json_encode($instanceEnv),
                            'db_host' => $dbConfig['host'],
                            'db_port' => $dbConfig['port'],
                            'db_database' => $dbConfig['database'],
                            'db_username' => $dbConfig['username'],
                            'db_password' => $dbConfig['password'],
                        ]);

                        $instance = $this->dockerService->createContainer(
                            $image, $instanceName, $port, 5678, $package->cpu_limit, $package->ram_limit,
                            $envArray, $volumes, [], $subdomain, $email, $container->id, $dbConfig, $panelDbUser
                        );

                        $container->update(['docker_id' => $instance->getShortDockerIdentifier()]);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                } else {
                     // Existing instance: Update Key if provided
                     if ($encryptionKey) {
                         $currentEnv = json_decode($container->environment, true) ?? [];
                         // Only update if different
                         if (($currentEnv['N8N_ENCRYPTION_KEY'] ?? '') !== $encryptionKey) {
                             $currentEnv['N8N_ENCRYPTION_KEY'] = $encryptionKey;
                             $container->environment = json_encode($currentEnv);
                             $container->save();

                             // We should ideally restart/recreate to apply env, but here we focus on DB restore.
                             // The user can restart manually or we can trigger restart.
                         }
                     }
                }

                // 3. Restore Database
                // Find latest SQL
                $files = Storage::disk('backup')->files($instanceName);
                $latestSql = null;
                $latestTime = 0;

                foreach ($files as $file) {
                    if (str_ends_with($file, '.sql')) {
                        // try getting timestamp
                        try {
                            $time = Storage::disk('backup')->lastModified($file);
                        } catch (\Exception $e) { $time = 0; }

                        if ($time > $latestTime) {
                            $latestTime = $time;
                            $latestSql = $file;
                        }
                    }
                }

                if ($latestSql) {
                    // Download to temp
                    $tempPath = storage_path('app/temp/restore_' . Str::random(10) . '.sql');
                    if (!file_exists(dirname($tempPath))) mkdir(dirname($tempPath), 0755, true);

                    $stream = Storage::disk('backup')->readStream($latestSql);
                    $out = fopen($tempPath, 'w');
                    while (!feof($stream)) {
                        fwrite($out, fread($stream, 8192));
                    }
                    fclose($stream);
                    fclose($out);

                    $this->performDatabaseRestore($container, $tempPath);
                    @unlink($tempPath);
                }

                $results[] = $instanceName;

            } catch (\Exception $e) {
                $errors[] = "$instanceName: " . $e->getMessage();
                Log::error("Restore failed for $instanceName: " . $e->getMessage());
            }
        }

        if (count($errors) > 0) {
            return back()->with('warning', "Restored: " . implode(', ', $results) . ". Errors: " . implode('; ', $errors));
        }

        return back()->with('success', "Successfully restored " . count($results) . " instances.");
    }

    private function performDatabaseRestore(Container $container, string $filePath)
    {
        $dbName = $container->db_database;
        $dbUser = $container->db_username;

        // Stop container first
        try {
            $this->dockerService->stopContainer($container->docker_id);
        } catch (\Exception $e) { }

        // Execute Import
        $script = base_path('scripts/db-manager.sh');
        $process = Process::run([
            'sudo',
            $script,
            '--action=import',
            "--db-name={$dbName}",
            "--db-user={$dbUser}",
            "--file={$filePath}"
        ]);

        if (!$process->successful()) {
             // Restart anyway
             try { $this->dockerService->startContainer($container->docker_id); } catch (\Exception $e) {}
             throw new \Exception("Import Script Failed: " . $process->errorOutput());
        }

        // Restart
        $this->dockerService->startContainer($container->docker_id);
    }
}
