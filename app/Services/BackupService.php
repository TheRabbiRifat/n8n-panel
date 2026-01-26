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
                'throw' => true,
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
                'throw' => true,
            ];
        } else {
            // Local
            $config = [
                'driver' => 'local',
                'root' => storage_path('app/backups'),
                'throw' => true,
            ];
        }

        Config::set('filesystems.disks.backup', $config);
        // Force reload of the disk to pick up new config
        Storage::forgetDisk('backup');
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
        // Only backing up SQL as per user request (no zip)
        // Store in instance-specific folder
        $backupName = "{$container->name}/backup-{$timestamp}.sql";

        // Temp file needs strict filename, not path with slashes for local storage logic here
        $tempFilename = "backup-{$container->name}-{$timestamp}.sql";
        $tempFile = storage_path("app/temp/{$tempFilename}");

        // Ensure parent dir exists
        if (!file_exists(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }

        // 2. Dump Database
        if ($container->db_database) {
            // Use the wrapper script which is allow-listed in sudoers
            $script = base_path('scripts/db-manager.sh');
            $cmd = "sudo {$script} --action=export --db-name=\"{$container->db_database}\" > \"{$tempFile}\"";
            $p = Process::run($cmd);
            if (!$p->successful()) {
                throw new \Exception("Database dump failed: " . $p->errorOutput());
            }
        } else {
            throw new \Exception("No database configured for this instance.");
        }

        // 5. Upload to Disk
        if (file_exists($tempFile)) {
            $stream = fopen($tempFile, 'r+');
            Log::info("Starting upload of {$backupName} to backup disk...");

            try {
                $uploaded = Storage::disk('backup')->writeStream($backupName, $stream);
            } catch (\Exception $e) {
                if (is_resource($stream)) fclose($stream);
                throw new \Exception("Upload failed: " . $e->getMessage());
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (!$uploaded) {
                throw new \Exception("Upload failed: Storage driver could not write stream (unknown reason).");
            }

            Log::info("Upload successful for {$backupName}.");
        } else {
            throw new \Exception("Backup file creation failed.");
        }

        // 6. Cleanup
        Process::run("rm -f \"{$tempFile}\"");
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
