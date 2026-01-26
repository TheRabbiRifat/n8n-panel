<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\BackupSetting;
use Illuminate\Support\Facades\Schema;

if (Schema::hasTable('backup_settings')) {
    $setting = BackupSetting::first();
    if ($setting && $setting->enabled) {
        // Use configured cron expression or default to daily
        Schedule::command('backup:run')->cron($setting->cron_expression);
    }
}
