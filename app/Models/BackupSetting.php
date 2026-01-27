<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $fillable = [
        'driver',
        'host',
        'username',
        'password',
        'port',
        'encryption',
        'bucket',
        'region',
        'endpoint',
        'path',
        'cron_expression',
        'enabled',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'enabled' => 'boolean',
        'port' => 'integer',
    ];
}
