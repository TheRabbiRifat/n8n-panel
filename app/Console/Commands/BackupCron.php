<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;

class BackupCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled backups for all instances';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService)
    {
        $this->info('Starting backup process...');

        $results = $backupService->backupAll();

        if ($results) {
            foreach ($results as $name => $status) {
                $this->line("Instance {$name}: {$status}");
            }
        } else {
            $this->warn('Backups not configured or disabled.');
        }

        $this->info('Backup process completed.');
    }
}
