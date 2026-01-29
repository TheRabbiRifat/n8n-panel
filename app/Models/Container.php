<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    use HasFactory;

    /**
     * List of SMTP-related environment variables that should be stripped
     * from global settings unless explicitly injected in Recovery Mode.
     */
    public const SMTP_ENV_KEYS = [
        'N8N_EMAIL_MODE',
        'N8N_SMTP_HOST',
        'N8N_SMTP_PORT',
        'N8N_SMTP_USER',
        'N8N_SMTP_PASS',
        'N8N_SMTP_SENDER',
        'N8N_SMTP_SSL',
    ];

    protected $fillable = [
        'user_id',
        'package_id',
        'docker_id',
        'name',
        'port',
        'domain',
        'environment',
        'is_recovery_mode',
        'image_tag',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
    ];

    protected $hidden = [
        'db_password',
    ];

    protected $casts = [
        'environment' => 'array',
        'is_recovery_mode' => 'boolean',
        'db_password' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
