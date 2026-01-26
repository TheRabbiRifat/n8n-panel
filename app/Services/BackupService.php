<?php

namespace App\Services;

use App\Models\BackupSetting;
use App\Models\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BackupService
{
    protected $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    public function configureDisk()
    {
        $setting = BackupSetting::first();
        if (!$setting || !$setting->enabled) {
            return false;
        }

        $config = [];

        if ($setting->driver === 's3') {
            $config = [
                'driver' => 's3',
                'key' => $setting->username, // Assuming username field stores Access Key
                'secret' => $setting->password, // Stores Secret Key
                'region' => $setting->region,
                'bucket' => $setting->bucket,
                'endpoint' => $setting->endpoint,
                'use_path_style_endpoint' => false,
                'throw' => false,
            ];
        } elseif ($setting->driver === 'ftp') {
            $config = [
                'driver' => 'ftp',
                'host' => $setting->host,
                'username' => $setting->username,
                'password' => $setting->password,
                'port' => $setting->port ?: 21,
                'root' => $setting->path ?: '/',
                'ssl' => $setting->encryption === 'ssl',
                'ignorePassiveAddress' => true,
            ];
        } else {
            // Local
            $config = [
                'driver' => 'local',
                'root' => storage_path('app/backups'),
                'throw' => false,
            ];
        }

        Config::set('filesystems.disks.backup', $config);
        return true;
    }

    public function backupAll()
    {
        if (!$this->configureDisk()) {
            Log::info("Backup skipped: Not configured or disabled.");
            return;
        }

        $containers = Container::all();
        $results = [];

        foreach ($containers as $container) {
            try {
                $this->backupInstance($container);
                $results[$container->name] = 'Success';
            } catch (\Exception $e) {
                Log::error("Backup failed for {$container->name}: " . $e->getMessage());
                $results[$container->name] = 'Failed: ' . $e->getMessage();
            }
        }

        return $results;
    }

    public function backupInstance(Container $container)
    {
        $timestamp = date('Y-m-d-H-i-s');
        $backupName = "backup-{$container->name}-{$timestamp}.zip";
        $tempDir = storage_path("app/temp/backup_{$container->name}_{$timestamp}");

        // 1. Prepare Temp Directory
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 2. Dump Database
        if ($container->db_database) {
            // We can use the db-manager script logic or just pg_dump here directly
            // Using pg_dump directly is cleaner as we are root/www-data
            $dbFile = "{$tempDir}/database.sql";
            $cmd = "sudo -u postgres pg_dump --no-owner --no-acl \"{$container->db_database}\" > \"{$dbFile}\"";
            $p = Process::run($cmd);
            if (!$p->successful()) {
                throw new \Exception("Database dump failed: " . $p->errorOutput());
            }
        }

        // 3. Archive Volume Data
        // Location: /var/lib/n8n/instances/{name} or {id}
        // We need to resolve path.
        // create-instance.sh logic: /var/lib/n8n/instances/{id} preferred, fallback {name}
        // But PHP stores ID.
        // Let's assume standard path from ContainerController logic:
        // Note: The Container model doesn't store the exact volume path, but it's predictable.
        // If create-instance.sh uses ID, we check that.
        $volumePath = "/var/lib/n8n/instances/" . $container->id;
        if (!is_dir($volumePath)) {
             // Fallback
             $volumePath = "/var/lib/n8n/instances/" . $container->name;
        }

        if (is_dir($volumePath)) {
            // Zip volume contents
            // We avoid zipping the whole path structure, just contents
            $zipCmd = "cd \"{$volumePath}\" && zip -r \"{$tempDir}/volume.zip\" . -x \"*.sock\"";
            // Check if zip is installed? It is in panel-installer.
            $p = Process::run($zipCmd);
            if (!$p->successful()) {
                 Log::warning("Volume zip failed for {$container->name} (maybe empty?): " . $p->errorOutput());
            }
        }

        // 4. Create Final Zip
        $finalZipPath = storage_path("app/temp/{$backupName}");
        $createFinalZip = "cd \"{$tempDir}\" && zip -r \"{$finalZipPath}\" .";
        Process::run($createFinalZip);

        // 5. Upload to Disk
        if (file_exists($finalZipPath)) {
            $stream = fopen($finalZipPath, 'r+');
            Storage::disk('backup')->writeStream($backupName, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } else {
            throw new \Exception("Final backup file creation failed.");
        }

        // 6. Cleanup
        Process::run("rm -rf \"{$tempDir}\" \"{$finalZipPath}\"");
    }

    public function listBackups()
    {
        if (!$this->configureDisk()) {
            return [];
        }
        return Storage::disk('backup')->files();
    }

    public function downloadBackup($filename)
    {
        if (!$this->configureDisk()) {
            abort(404);
        }
        if (!Storage::disk('backup')->exists($filename)) {
            abort(404);
        }
        return Storage::disk('backup')->download($filename);
    }
}
