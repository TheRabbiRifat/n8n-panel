<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Exception;

class DockerService
{
    public function listContainers()
    {
        $process = Process::run([base_path('scripts/docker-utils.sh'), '--action=list']);

        if ($process->failed()) {
            return [];
        }

        $output = $process->output();
        $lines = explode("\n", trim($output));
        $containers = [];

        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode('|', $line);
            if (count($parts) >= 6) {
                $containers[] = [
                    'id' => $parts[0],
                    'name' => $parts[1],
                    'image' => $parts[2],
                    'status' => $parts[3],
                    'state' => $parts[4],
                    'ports' => $parts[5],
                ];
            }
        }

        return $containers;
    }

    public function createContainer(string $image, string $name, int $port, int $internalPort = 5678, $cpu = null, $memory = null, array $environment = [], array $volumes = [], array $labels = [], string $domain = '', string $email = '', ?int $dbId = null, array $dbConfig = [], string $panelDbUser = '')
    {
        // Extract tag from image (e.g. n8nio/n8n:latest -> latest)
        $imageParts = explode(':', $image);
        $tag = count($imageParts) > 1 ? $imageParts[1] : 'latest';

        $envJson = json_encode($environment);

        // Path to script
        $scriptPath = base_path('scripts/create-instance.sh');

        $command = [
            'sudo',
            $scriptPath,
            "--name={$name}",
            "--port={$port}",
            "--image={$tag}",
            "--domain={$domain}",
            "--email={$email}",
            "--env-json={$envJson}",
        ];

        if ($dbId) {
            $command[] = "--id={$dbId}";
        }

        if ($cpu) {
            $command[] = "--cpu={$cpu}";
        }
        if ($memory) {
             $command[] = "--memory={$memory}";
        }

        // DB Configuration
        if (!empty($dbConfig)) {
            if (!empty($dbConfig['host'])) $command[] = "--db-host={$dbConfig['host']}";
            if (!empty($dbConfig['port'])) $command[] = "--db-port={$dbConfig['port']}";
            if (!empty($dbConfig['database'])) $command[] = "--db-name={$dbConfig['database']}";
            if (!empty($dbConfig['username'])) $command[] = "--db-user={$dbConfig['username']}";
            if (!empty($dbConfig['password'])) $command[] = "--db-pass={$dbConfig['password']}";
        }

        if ($panelDbUser) {
            $command[] = "--panel-db-user={$panelDbUser}";
        }

        // Execute with timeout for pulling image
        $process = Process::timeout(600)->run($command);

        if (!$process->successful()) {
            throw new Exception("Instance creation failed: " . $process->errorOutput() . " " . $process->output());
        }

        // Need to fetch ID separately using utils script
        $inspect = Process::run([base_path('scripts/docker-utils.sh'), '--action=inspect-format', "--id={$name}", "--arg={{.Id}}"]);
        $containerId = trim($inspect->output());

        if (empty($containerId)) {
             throw new Exception("Instance created but could not retrieve ID. Output: " . $process->output());
        }

        // Return Mock Object
        return new class($containerId) {
            private $id;
            public function __construct($id) { $this->id = $id; }
            public function getShortDockerIdentifier() { return substr($this->id, 0, 12); }
        };
    }

    public function stopContainer(string $id)
    {
        // Need name to use script, or just use docker stop if ID provided
        // But let's look up name if needed.
        // The script manages via docker command anyway.
        // If we want to strictly use scripts, we should pass name.
        // However, current signature uses ID.
        // Let's resolve name from ID.
        $name = $this->getNameById($id);
        if ($name) {
             Process::run(['sudo', base_path('scripts/manage-container.sh'), "--name={$name}", "--action=stop"]);
        } else {
             // Fallback
             Process::run("docker stop $id");
        }
    }

    public function startContainer(string $id)
    {
        $name = $this->getNameById($id);
        if ($name) {
             Process::run(['sudo', base_path('scripts/manage-container.sh'), "--name={$name}", "--action=start"]);
        } else {
             Process::run("docker start $id");
        }
    }

    public function removeContainer(string $id, string $domain = '', ?int $dbId = null, array $dbConfig = [])
    {
        $name = $this->getNameById($id);
        // If name not found (already deleted?), fallback to docker rm
        if ($name) {
             $command = ['sudo', base_path('scripts/delete-instance.sh'), "--name={$name}"];
             if ($domain) {
                 $command[] = "--domain={$domain}";
             }
             if ($dbId) {
                 $command[] = "--id={$dbId}";
             }
             if (!empty($dbConfig['database'])) $command[] = "--db-name={$dbConfig['database']}";
             if (!empty($dbConfig['username'])) $command[] = "--db-user={$dbConfig['username']}";

             Process::run($command);
        } else {
             Process::run("docker rm -f $id");
        }
    }

    public function restartContainer(string $id)
    {
        $name = $this->getNameById($id);
        if ($name) {
             Process::run(['sudo', base_path('scripts/manage-container.sh'), "--name={$name}", "--action=restart"]);
        } else {
             Process::run("docker restart $id");
        }
    }

    private function getNameById($id) {
         $p = Process::run("docker inspect --format '{{.Name}}' $id");
         if ($p->successful()) {
             return trim($p->output(), "/ \n\r");
         }
         return null;
    }

    public function getContainerLogs(string $id, int $lines = 100)
    {
         $process = Process::run([base_path('scripts/docker-utils.sh'), '--action=logs', "--id={$id}", "--lines={$lines}"]);
         return $process->successful() ? $process->output() : 'Could not retrieve logs.';
    }

    public function getContainer(string $id)
    {
         $process = Process::run([base_path('scripts/docker-utils.sh'), '--action=inspect', "--id={$id}"]);
         if ($process->successful()) {
             return json_decode($process->output(), true)[0] ?? null;
         }
         return null;
    }

    public function getContainerStats(string $id)
    {
         // Use default json format in utils script (or pass arg)
         $process = Process::run([base_path('scripts/docker-utils.sh'), '--action=stats', "--id={$id}"]);
         if ($process->successful()) {
             return json_decode($process->output(), true);
         }
         return null;
    }
}
