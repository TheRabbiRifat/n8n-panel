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
    public function configureDisk()
    {
        $setting = BackupSetting::first();
        if (!$setting || !$setting->enabled) {
            return false;
        }

        $config = $this->getDiskConfig($setting);

        Config::set('filesystems.disks.backup', $config);
        // Force reload of the disk to pick up new config
        Storage::forgetDisk('backup');
        return true;
    }

    protected function getDiskConfig($setting)
    {
        if ($setting->driver === 's3') {
            return [
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
            // Explicitly cast port to int and provide default
            $port = 21;
            if (isset($setting->port) && is_numeric($setting->port)) {
                $port = (int) $setting->port;
            }

            return [
                'driver' => 'ftp',
                'host' => $setting->host,
                'username' => $setting->username,
                'password' => $setting->password,
                'port' => $port,
                'root' => $setting->path ?: '/',
                'ssl' => $setting->encryption === 'ssl',
                'timeout' => 30,
                'throw' => true,
            ];
        } else {
            // Local
            return [
                'driver' => 'local',
                'root' => storage_path('app/backups'),
                'throw' => true,
            ];
        }
    }

    public function testConnection(array $data)
    {
        $setting = new BackupSetting($data);
        $config = $this->getDiskConfig($setting);

        Config::set('filesystems.disks.backup_test', $config);
        Storage::forgetDisk('backup_test');

        try {
            $testFile = 'connection_test_' . time() . '.txt';
            Storage::disk('backup_test')->put($testFile, 'test');
            Storage::disk('backup_test')->delete($testFile);
            return true;
        } finally {
            Storage::forgetDisk('backup_test');
        }
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

        // Cleanup old backups after run
        $this->cleanupOldBackups();

        return $results;
    }

    public function cleanupOldBackups()
    {
        $setting = BackupSetting::first();
        if (!$setting || !$setting->retention_days) {
            return;
        }

        $days = (int) $setting->retention_days;
        $cutoff = now()->subDays($days)->timestamp;

        try {
            $allFiles = Storage::disk('backup')->allFiles();
            $deletedCount = 0;

            foreach ($allFiles as $file) {
                // Only clean SQL files, ignore metadata/keys to keep folder structure valid?
                // Or clean everything older than X days?
                // Typically we want to remove the backup file (.sql).
                // If we remove the .sql, the folder remains with key/metadata.
                // Should we remove the whole folder if empty?
                // Given the structure 'hostname/instance/backup-DATE.sql', we should target the SQL files.
                // The keys/metadata are not timestamped in filename, but they are overwritten.
                // We shouldn't delete keys/metadata based on age, as they are needed for the LATEST backup too.
                // So only delete .sql files that are old.

                if (!str_ends_with($file, '.sql')) {
                    continue;
                }

                $lastModified = Storage::disk('backup')->lastModified($file);

                if ($lastModified < $cutoff) {
                    Storage::disk('backup')->delete($file);
                    $deletedCount++;
                }
            }

            if ($deletedCount > 0) {
                Log::info("Backup cleanup: Deleted {$deletedCount} backups older than {$days} days.");
            }

        } catch (\Exception $e) {
            Log::warning("Backup cleanup failed: " . $e->getMessage());
        }
    }

    public function backupInstance(Container $container)
    {
        $timestamp = date('Y-m-d-H-i-s');
        $hostname = gethostname();
        // Only backing up SQL as per user request (no zip)
        // Store in instance-specific folder: hostname/instance/backup
        $backupName = "{$hostname}/{$container->name}/backup-{$timestamp}.sql";

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
            // Use Process component directly to stream output to file to avoid memory limits and shell redirection issues
            $process = new \Symfony\Component\Process\Process([
                'pg_dump', '-h', $dbHost, '-p', $dbPort, '-U', $dbUser, '--no-owner', '--no-acl', $dbName
            ]);
            $process->setEnv(['PGPASSWORD' => $container->db_password]);
            $process->setTimeout(300); // 5 minutes

            $fileHandle = fopen($tempFile, 'w');
            $process->run(function ($type, $buffer) use ($fileHandle) {
                if (\Symfony\Component\Process\Process::OUT === $type) {
                    fwrite($fileHandle, $buffer);
                }
            });
            fclose($fileHandle);

            if (!$process->isSuccessful()) {
                throw new \Exception("Database dump failed (Credentials): " . $process->getErrorOutput());
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
                $keyFile = "{$hostname}/{$container->name}/key.txt";
                try {
                    Storage::disk('backup')->put($keyFile, $env['N8N_ENCRYPTION_KEY']);
                } catch (\Exception $e) {
                    Log::warning("Failed to upload encryption key for {$container->name}: " . $e->getMessage());
                }
            }

            // 5b. Upload Metadata
            $metadata = [
                'version' => '1.0',
                'n8n_version' => $container->image_tag,
                'encryption_key' => $env['N8N_ENCRYPTION_KEY'] ?? null,
                'package' => [
                    'name' => $container->package->name ?? 'Standard',
                    'cpu_limit' => $container->package->cpu_limit ?? 1,
                    'ram_limit' => $container->package->ram_limit ?? 1,
                    'disk_limit' => $container->package->disk_limit ?? 10,
                ],
                'owner' => [
                    'email' => $container->user->email ?? null,
                    'username' => $container->user->username ?? null,
                ],
                'created_at' => now()->toIso8601String(),
            ];

            $metaFile = "{$hostname}/{$container->name}/metadata.json";
            try {
                Storage::disk('backup')->put($metaFile, json_encode($metadata, JSON_PRETTY_PRINT));
            } catch (\Exception $e) {
                Log::warning("Failed to upload metadata for {$container->name}: " . $e->getMessage());
            }

        } else {
            throw new \Exception("Backup file creation failed.");
        }

        // 6. Cleanup
        Process::run(['rm', '-f', $tempFile]);
    }

    public function listBackups()
    {
        if (!$this->configureDisk()) {
            return [];
        }

        $allFiles = Storage::disk('backup')->allFiles();
        $folders = [];

        foreach ($allFiles as $file) {
            // Group by full directory path (excluding filename)
            // e.g., 'hostname/instance/backup.sql' -> 'hostname/instance'
            // e.g., 'instance/backup.sql' -> 'instance' (Legacy)

            $dir = dirname($file);
            if ($dir === '.' || $dir === '') {
                $dir = 'Root';
            }
            $folderName = $dir;
            $fileName = basename($file);

            // Skip key.txt and metadata.json
            if ($fileName === 'key.txt' || $fileName === 'metadata.json') {
                continue;
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

        // Handle both new format (hostname/instance) and old format (instance)
        // For new backups, we assume they are under current hostname
        $hostname = gethostname();
        $path = "{$hostname}/{$instanceName}";

        // If nothing in hostname folder, maybe old format?
        if (empty(Storage::disk('backup')->allFiles($path))) {
            $path = $instanceName;
        }

        // List files in the directory
        $files = Storage::disk('backup')->allFiles($path);
        $backups = [];

        foreach ($files as $file) {
            $fileName = basename($file);

            // Skip key.txt and metadata.json
            if ($fileName === 'key.txt' || $fileName === 'metadata.json') {
                continue;
            }

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

    public function deleteBackup(string $instanceName)
    {
        if (!$this->configureDisk()) {
            return;
        }

        try {
            // Delete new format (hostname/instance)
            $hostname = gethostname();
            $path = "{$hostname}/{$instanceName}";

            if (Storage::disk('backup')->exists($path)) {
                Storage::disk('backup')->deleteDirectory($path);
                Log::info("Deleted backups for terminated instance (new format): {$path}");
            }

            // Also check old format
            if (Storage::disk('backup')->exists($instanceName)) {
                Storage::disk('backup')->deleteDirectory($instanceName);
                Log::info("Deleted backups for terminated instance (legacy format): {$instanceName}");
            }

        } catch (\Exception $e) {
            Log::warning("Failed to delete backups for {$instanceName}: " . $e->getMessage());
        }
    }
}
