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
        if ($container->db_database && $container->db_username && $container->db_password) {
            // Use instance credentials directly as requested
            // Host: Force localhost (127.0.0.1) for backup process running on the host itself
            // This avoids routing issues where connection to 172.17.0.1 appears as public IP blocked by pg_hba
            $dbHost = '127.0.0.1';
            $dbPort = $container->db_port ?: 5432;
            $dbUser = $container->db_username;
            $dbName = $container->db_database;

            // We use PGPASSWORD env var for safety
            $command = "pg_dump -h {$dbHost} -p {$dbPort} -U {$dbUser} --no-owner --no-acl \"{$dbName}\" > \"{$tempFile}\"";

            $p = Process::env(['PGPASSWORD' => $container->db_password])->run($command);

            if (!$p->successful()) {
                // If failed, try sudo fallback? No, user explicitly asked for instance credentials approach.
                throw new \Exception("Database dump failed (Credentials): " . $p->errorOutput() . " " . $p->output());
            }
        } else {
            throw new \Exception("No database credentials configured for this instance.");
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

            // 5a. Upload Encryption Key
            $env = json_decode($container->environment, true);
            if (isset($env['N8N_ENCRYPTION_KEY'])) {
                $keyFile = "{$container->name}/key.txt";
                try {
                    Storage::disk('backup')->put($keyFile, $env['N8N_ENCRYPTION_KEY']);
                } catch (\Exception $e) {
                    Log::warning("Failed to upload encryption key for {$container->name}: " . $e->getMessage());
                }
            }

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

        $allFiles = Storage::disk('backup')->allFiles();
        $folders = [];

        foreach ($allFiles as $file) {
            $parts = explode('/', $file);
            // Expected format: instance_name/backup-date.sql
            // If in root, put in 'Unsorted'

            if (count($parts) > 1) {
                $folderName = $parts[0];
                $fileName = $parts[count($parts) - 1];
            } else {
                $folderName = 'Root';
                $fileName = $file;
            }

            if (!isset($folders[$folderName])) {
                $folders[$folderName] = [
                    'name' => $folderName,
                    'count' => 0,
                    'last_backup_timestamp' => 0,
                    'last_backup' => 'N/A',
                    'files' => []
                ];
            }

            // Extract timestamp from filename if possible: backup-NAME-YYYY-MM-DD-HH-II-SS.sql
            // Or use Storage::lastModified (slow on S3?)
            // We'll rely on filename parsing for speed if strictly named, else lastModified if needed.
            // Let's rely on filename sorting or regex.

            // Try to extract date from filename
            $time = null;
            if (preg_match('/(\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})/', $fileName, $matches)) {
                $dt = \DateTime::createFromFormat('Y-m-d-H-i-s', $matches[1]);
                $time = $dt ? $dt->getTimestamp() : null;
            } else {
                try {
                    $time = Storage::disk('backup')->lastModified($file);
                } catch (\Exception $e) { $time = 0; }
            }

            $folders[$folderName]['count']++;
            $folders[$folderName]['files'][] = [
                'path' => $file,
                'name' => $fileName,
                'timestamp' => $time,
                'date' => $time ? date('Y-m-d H:i:s', $time) : 'Unknown'
            ];

            if ($time > $folders[$folderName]['last_backup_timestamp']) {
                $folders[$folderName]['last_backup_timestamp'] = $time;
                $folders[$folderName]['last_backup'] = date('Y-m-d H:i:s', $time);
            }
        }

        // Sort files desc
        foreach ($folders as &$folder) {
            usort($folder['files'], fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        }

        // Hide Root files as requested
        if (isset($folders['Root'])) {
            unset($folders['Root']);
        }

        return $folders;
    }

    public function listBackupsForInstance(string $instanceName)
    {
        if (!$this->configureDisk()) {
            return [];
        }

        // List files in the instance directory (use allFiles for S3 "folders")
        $files = Storage::disk('backup')->allFiles($instanceName);
        $backups = [];

        foreach ($files as $file) {
            $fileName = basename($file);

            // Extract timestamp
            $time = null;
            if (preg_match('/(\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})/', $fileName, $matches)) {
                $dt = \DateTime::createFromFormat('Y-m-d-H-i-s', $matches[1]);
                $time = $dt ? $dt->getTimestamp() : null;
            } else {
                try {
                    $time = Storage::disk('backup')->lastModified($file);
                } catch (\Exception $e) { $time = 0; }
            }

            $backups[] = [
                'path' => $file, // Full path for retrieval
                'name' => $fileName,
                'timestamp' => $time,
                'date' => $time ? date('Y-m-d H:i:s', $time) : 'Unknown',
                'size' => $this->formatBytes(Storage::disk('backup')->size($file))
            ];
        }

        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
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
