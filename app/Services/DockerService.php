<?php

namespace App\Services;

use Spatie\Docker\DockerContainer;
use Spatie\Docker\DockerContainerInstance;
use Illuminate\Support\Facades\Process;
use Exception;

class DockerService
{
    public function listContainers()
    {
        // Spatie docker doesn't have a "list" command wrapper.
        // We will implement it using Laravel's Process or shell_exec
        // Since we are simulating, I will assume `docker ps -a` works.
        // I will return an array of arrays.

        $process = Process::run('sudo docker ps -a --format "{{.ID}}|{{.Names}}|{{.Image}}|{{.Status}}|{{.State}}|{{.Ports}}"');

        if ($process->failed()) {
            // If docker is not running or available, return empty
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

    public function createContainer(string $image, string $name, int $port, int $internalPort = 5678, $cpu = null, $memory = null, array $environment = [])
    {
        // Use Spatie Docker to create
        // We need to handle potential exceptions
        try {
            $container = DockerContainer::create($image)
                ->name($name)
                ->mapPort($port, $internalPort)
                ->daemonize()
                ->doNotCleanUpAfterExit() // We want it to persist so we can see it in list
                ->setStartCommandTimeout(300); // Increase timeout to 5 mins for image pulling

            foreach ($environment as $key => $value) {
                $container->environment($key, $value);
            }

            $optionalArgs = [];
            if ($cpu) {
                $optionalArgs[] = "--cpus={$cpu}";
            }
            if ($memory) {
                $optionalArgs[] = "--memory={$memory}";
            }

            if (!empty($optionalArgs)) {
                $container->setOptionalArgs(...$optionalArgs);
            }

            // start() returns a DockerContainerInstance
            $instance = $container->start();
            return $instance;
        } catch (Exception $e) {
            // Log error or rethrow
            throw $e;
        }
    }

    public function stopContainer(string $id)
    {
         // We use raw docker command because Spatie Docker Instance needs to be created from existing container which is not directly supported by "find"
         // However, we can use `docker stop`
         Process::run("docker stop $id");
    }

    public function startContainer(string $id)
    {
         Process::run("docker start $id");
    }

    public function removeContainer(string $id)
    {
         Process::run("docker rm -f $id");
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

    public function execContainer(string $id, string $command)
    {
         $process = Process::run("sudo docker exec $id $command");
         return $process->output() . "\n" . $process->errorOutput();
    }

    public function getContainer(string $id)
    {
         // Get details
         $process = Process::run("docker inspect $id");
         if ($process->successful()) {
             return json_decode($process->output(), true)[0] ?? null;
         }
         return null;
    }
}
