<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use App\Models\BackupSetting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dynamic Backup Scheduling
try {
    if (Schema::hasTable('backup_settings')) {
        $setting = BackupSetting::first();
        if ($setting && $setting->enabled && $setting->cron_expression) {
            Schedule::command('backup:run')
                ->cron($setting->cron_expression)
                ->withoutOverlapping();
        }
    }
} catch (\Exception $e) {
    // Ignore DB errors during installation/migration
}
