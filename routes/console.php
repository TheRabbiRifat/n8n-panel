<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\BackupSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

// Backup scheduling is handled externally via system cron
