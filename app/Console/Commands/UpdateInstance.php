<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Container;
use App\Http\Controllers\ContainerController;

class UpdateInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-instance {name : The name of the instance} {--force : Force the update without asking for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recreate and update an n8n instance container';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $force = $this->option('force');

        $container = Container::where('name', $name)->first();

        if (!$container) {
            $this->error("Instance '{$name}' not found.");
            return 1;
        }

        if (!$force && !$this->confirm("Are you sure you want to recreate and update instance '{$name}'? This will cause brief downtime.")) {
            $this->info('Update cancelled.');
            return 0;
        }

        $this->info("Recreating and updating instance '{$name}'...");

        try {
            $controller = app()->make(ContainerController::class);
            $controller->recreateContainer($container);

            $this->info("Instance '{$name}' updated and recreated successfully.");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to update instance: " . $e->getMessage());
            return 1;
        }
    }
}
