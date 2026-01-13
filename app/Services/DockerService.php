<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Exception;

class DockerService
{
    public function listContainers()
    {
        $process = Process::run('docker ps -a --format "{{.ID}}|{{.Names}}|{{.Image}}|{{.Status}}|{{.State}}|{{.Ports}}"');

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

    public function createContainer(string $image, string $name, int $port, int $internalPort = 5678, $cpu = null, $memory = null, array $environment = [], array $volumes = [], array $labels = [], string $domain = '', string $email = '')
    {
        // Extract tag from image (e.g. n8nio/n8n:latest -> latest)
        $imageParts = explode(':', $image);
        $tag = count($imageParts) > 1 ? $imageParts[1] : 'latest';

        $envJson = json_encode($environment);

        // Path to script
        $scriptPath = base_path('scripts/create-instance.sh');

        $command = [
            $scriptPath,
            "--name={$name}",
            "--port={$port}",
            "--image={$tag}",
            "--domain={$domain}",
            "--email={$email}",
            "--env-json={$envJson}",
        ];

        if ($cpu) {
            $command[] = "--cpu={$cpu}";
        }
        if ($memory) {
             $command[] = "--memory={$memory}";
        }

        // Execute with timeout for pulling image
        $process = Process::timeout(600)->run($command);

        if (!$process->successful()) {
            throw new Exception("Instance creation failed: " . $process->errorOutput() . " " . $process->output());
        }

        // Need to fetch ID separately since script output might be noisy
        // Or assume the script was successful and we can just inspect the container by name to get ID
        $inspect = Process::run("docker inspect --format '{{.Id}}' $name");
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
             Process::run([base_path('scripts/manage-container.sh'), "--name={$name}", "--action=stop"]);
        } else {
             // Fallback
             Process::run("docker stop $id");
        }
    }

    public function startContainer(string $id)
    {
        $name = $this->getNameById($id);
        if ($name) {
             Process::run([base_path('scripts/manage-container.sh'), "--name={$name}", "--action=start"]);
        } else {
             Process::run("docker start $id");
        }
    }

    public function removeContainer(string $id, string $domain = '')
    {
        $name = $this->getNameById($id);
        // If name not found (already deleted?), fallback to docker rm
        if ($name) {
             $command = [base_path('scripts/delete-instance.sh'), "--name={$name}"];
             if ($domain) {
                 $command[] = "--domain={$domain}";
             }
             Process::run($command);
        } else {
             Process::run("docker rm -f $id");
        }
    }

    public function restartContainer(string $id)
    {
        $name = $this->getNameById($id);
        if ($name) {
             Process::run([base_path('scripts/manage-container.sh'), "--name={$name}", "--action=restart"]);
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
         $process = Process::run("docker logs --tail {$lines} $id");
         return $process->successful() ? $process->output() : 'Could not retrieve logs.';
    }

    public function getContainer(string $id)
    {
         $process = Process::run("docker inspect $id");
         if ($process->successful()) {
             return json_decode($process->output(), true)[0] ?? null;
         }
         return null;
    }

    public function getContainerStats(string $id)
    {
         $process = Process::run("docker stats --no-stream --format \"{{json .}}\" $id");
         if ($process->successful()) {
             return json_decode($process->output(), true);
         }
         return null;
    }
}
