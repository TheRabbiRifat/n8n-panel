<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\BackupSetting;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dynamic Backup Scheduling
if (Schema::hasTable('backup_settings')) {
    try {
        $setting = BackupSetting::first();
        if ($setting && $setting->enabled && $setting->cron_expression) {
            Schedule::command('backup:run')
                ->cron($setting->cron_expression)
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/backup.log'));
        }
    } catch (\Exception $e) {
        // Fallback or ignore if DB connection fails
    }
}
