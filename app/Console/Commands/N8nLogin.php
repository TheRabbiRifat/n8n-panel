<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Container;
use Illuminate\Support\Facades\Process;

class N8nLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:n8nlogin {name : The name of the instance}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset n8n user management for a specific instance to generate a login setup link';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        $container = Container::where('name', $name)->first();

        if (!$container) {
            $this->error("Instance '{$name}' not found.");
            return 1;
        }

        $this->info("Resetting user management for instance '{$name}'...");

        $process = Process::run([
            'docker',
            'exec',
            $container->docker_id,
            'n8n',
            'user-management:reset'
        ]);

        if ($process->successful()) {
            $this->info("Command executed successfully. " . $process->output());
            $this->info("If reset, please navigate to the instance URL to setup a new owner account.");
            return 0;
        } else {
            $this->error("Failed to execute n8n login reset: " . $process->errorOutput());
            return 1;
        }
    }
}
