<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;

class PanelUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:panel-update {--force : Force the update without asking for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the panel by pulling the latest code and running migrations/composer';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');

        if (!$force && !$this->confirm("Are you sure you want to update the panel? This will pull latest code and run migrations.")) {
            $this->info('Update cancelled.');
            return 0;
        }

        $this->info("Updating panel...");

        $this->info("1. Pulling latest code from git...");
        $gitProcess = Process::run(['git', 'pull']);
        if (!$gitProcess->successful()) {
            $this->error("Failed to pull from git: " . $gitProcess->errorOutput());
            return 1;
        }
        $this->line($gitProcess->output());

        $this->info("2. Installing dependencies via Composer...");
        $composerProcess = Process::run(['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader']);
        if (!$composerProcess->successful()) {
            $this->error("Failed to install composer dependencies: " . $composerProcess->errorOutput());
            return 1;
        }
        $this->line($composerProcess->output());

        $this->info("3. Running database migrations...");
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        $this->info("4. Clearing caches...");
        Artisan::call('optimize:clear');
        $this->line(Artisan::output());

        $this->info("Panel update completed successfully.");
        return 0;
    }
}
