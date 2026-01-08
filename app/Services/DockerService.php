<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Exception;

class DockerService
{
    public function listContainers()
    {
        $process = Process::run('sudo docker ps -a --format "{{.ID}}|{{.Names}}|{{.Image}}|{{.Status}}|{{.State}}|{{.Ports}}"');

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

    public function createContainer(string $image, string $name, int $port, int $internalPort = 5678, $cpu = null, $memory = null, array $environment = [], array $volumes = [])
    {
        // Manually construct sudo docker run command
        $command = ['sudo', 'docker', 'run', '-d', '--name', $name, '--restart', 'unless-stopped'];

        // Port
        $command[] = '-p';
        $command[] = "{$port}:{$internalPort}";

        // Env
        foreach ($environment as $key => $value) {
            $command[] = '-e';
            $command[] = "{$key}={$value}";
        }

        // Volumes
        foreach ($volumes as $hostPath => $containerPath) {
            $command[] = '-v';
            $command[] = "{$hostPath}:{$containerPath}";
        }

        // Resources
        if ($cpu) {
            $command[] = "--cpus={$cpu}";
        }
        if ($memory) {
            $command[] = "--memory={$memory}";
        }

        $command[] = $image;

        // Execute with timeout for pulling image
        $process = Process::timeout(600)->run($command);

        if (!$process->successful()) {
            throw new Exception("Docker creation failed: " . $process->errorOutput() . " " . $process->output());
        }

        $containerId = trim($process->output());

        // Return Mock Object
        return new class($containerId) {
            private $id;
            public function __construct($id) { $this->id = $id; }
            public function getShortDockerIdentifier() { return substr($this->id, 0, 12); }
        };
    }

    public function stopContainer(string $id)
    {
         Process::run("sudo docker stop $id");
    }

    public function startContainer(string $id)
    {
         Process::run("sudo docker start $id");
    }

    public function removeContainer(string $id)
    {
         Process::run("sudo docker rm -f $id");
    }

    public function restartContainer(string $id)
    {
         Process::run("sudo docker restart $id");
    }

    public function getContainerLogs(string $id, int $lines = 100)
    {
         $process = Process::run("sudo docker logs --tail {$lines} $id");
         return $process->successful() ? $process->output() : 'Could not retrieve logs.';
    }

    public function getContainer(string $id)
    {
         $process = Process::run("sudo docker inspect $id");
         if ($process->successful()) {
             return json_decode($process->output(), true)[0] ?? null;
         }
         return null;
    }
}
