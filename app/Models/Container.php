<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    use HasFactory;

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
