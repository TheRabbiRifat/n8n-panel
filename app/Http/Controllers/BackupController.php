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
            'cron_expression' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (!\Cron\CronExpression::isValidExpression($value)) {
                    $fail($attribute . ' is not a valid cron expression.');
                }
            }],
            'retention_days' => 'required|integer|min:1',
        ]);

        if ($request->filled('cron_expression')) {
            $parts = explode(' ', $request->cron_expression);
            if (count($parts) < 5) {
                return back()->with('error', 'Invalid cron expression format.')->withInput();
            }
        }

        if ($request->has('enabled')) {
            try {
                $this->backupService->testConnection($request->all());
            } catch (\Exception $e) {
                session()->flash('warning', 'Connection test failed: ' . $e->getMessage() . '. Settings were saved but backups may not work.');
            }
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

        foreach ($folders as $folderPath) {
            try {
                // $folderPath can be 'hostname/instanceName' or just 'instanceName'
                // Use basename to get the actual instance name for the container
                $instanceName = basename($folderPath);

                // Check if instance exists
                $container = Container::where('name', $instanceName)->first();
                $encryptionKey = null;
                $metadata = [];
                $package = null;
                $targetUser = $adminUser;
                $imageTag = 'latest';

                // 0. Load Metadata if available (using full folder path)
                $metaPath = "{$folderPath}/metadata.json";
                if (Storage::disk('backup')->exists($metaPath)) {
                    try {
                        $metadata = json_decode(Storage::disk('backup')->get($metaPath), true);
                    } catch (\Exception $e) {}
                }

                // 1. Retrieve Key (Priority: Metadata > key.txt)
                if (!empty($metadata['encryption_key'])) {
                    $encryptionKey = $metadata['encryption_key'];
                } else {
                    $keyPath = "{$folderPath}/key.txt";
                    if (Storage::disk('backup')->exists($keyPath)) {
                        $encryptionKey = trim(Storage::disk('backup')->get($keyPath));
                    }
                }

                if (!empty($metadata['n8n_version'])) {
                    $imageTag = $metadata['n8n_version'];
                }

                // 2. Create Instance if missing
                if (!$container) {
                    // 2a. Determine Package
                    if (!empty($metadata['package'])) {
                        // Try to find package by name or create with specs
                        $pkgName = $metadata['package']['name'] ?? 'Restored';
                        $pkgCpu = $metadata['package']['cpu_limit'] ?? 1;
                        $pkgRam = $metadata['package']['ram_limit'] ?? 1;
                        $pkgDisk = $metadata['package']['disk_limit'] ?? 10;

                        // Try find exact match
                        $package = Package::where('name', $pkgName)
                                          ->where('cpu_limit', $pkgCpu)
                                          ->where('ram_limit', $pkgRam)
                                          ->first();

                        if (!$package) {
                            // Create or Reuse logic?
                            // Let's see if name exists but specs differ
                            $existingPkg = Package::where('name', $pkgName)->first();
                            if ($existingPkg) {
                                // If specs differ significantly, maybe create "Restored - {Name}"?
                                // For now, let's reuse if name matches to avoid clutter,
                                // UNLESS user wants exact specs. The prompt says "store... package details".
                                // To be safe, if we have specs, we should honor them.
                                // Let's create a custom package if needed.
                                $package = Package::create([
                                    'name' => "{$pkgName} (Restored)",
                                    'cpu_limit' => $pkgCpu,
                                    'ram_limit' => $pkgRam,
                                    'disk_limit' => $pkgDisk,
                                    'price' => 0,
                                    'type' => 'instance',
                                    'user_id' => $adminUser->id
                                ]);
                            } else {
                                $package = Package::create([
                                    'name' => $pkgName,
                                    'cpu_limit' => $pkgCpu,
                                    'ram_limit' => $pkgRam,
                                    'disk_limit' => $pkgDisk,
                                    'price' => 0,
                                    'type' => 'instance',
                                    'user_id' => $adminUser->id
                                ]);
                            }
                        }
                    } else {
                        // Fallback
                        $package = Package::first();
                        if (!$package) {
                            $package = Package::create([
                                'name' => 'Standard',
                                'cpu_limit' => 2,
                                'ram_limit' => 4,
                                'disk_limit' => 20,
                                'price' => 0,
                                'type' => 'instance',
                                'user_id' => $adminUser->id
                            ]);
                        }
                    }

                    // 2b. Determine Owner
                    if (!empty($metadata['owner']['email'])) {
                        $existingUser = User::where('email', $metadata['owner']['email'])->first();
                        if ($existingUser) {
                            $targetUser = $existingUser;
                        }
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
                    $image = 'n8nio/n8n:' . $imageTag;
                    $email = env('MAIL_FROM_ADDRESS', 'admin@example.com');
                    $panelDbUser = config('database.connections.pgsql.username');

                    DB::beginTransaction();
                    try {
                         $container = Container::create([
                            'user_id' => $targetUser->id,
                            'package_id' => $package->id,
                            'docker_id' => 'pending_' . Str::random(8),
                            'name' => $instanceName,
                            'port' => $port,
                            'domain' => $subdomain,
                            'image_tag' => $imageTag,
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
                // Find latest SQL using full folder path
                $files = Storage::disk('backup')->files($folderPath);
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
