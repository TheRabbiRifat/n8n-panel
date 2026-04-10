<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Container;
use App\Services\DockerService;

class RestartInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:restart-instance {name : The name of the instance}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart a specific n8n instance container';

    /**
     * Execute the console command.
     */
    public function handle(DockerService $dockerService)
    {
        $name = $this->argument('name');

        $container = Container::where('name', $name)->first();

        if (!$container) {
            $this->error("Instance '{$name}' not found.");
            return 1;
        }

        $this->info("Restarting instance '{$name}'...");

        try {
            $dockerService->restartContainer($container->docker_id);
            $this->info("Instance '{$name}' restarted successfully.");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to restart instance: " . $e->getMessage());
            return 1;
        }
    }
}
