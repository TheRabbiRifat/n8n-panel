<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'cpu_limit',
        'ram_limit',
        'disk_limit',
        'instance_count',
    ];

    /**
     * Scope a query to only include instance packages.
     */
    public function scopeInstance(Builder $query): void
    {
        $query->where('type', 'instance');
    }

    /**
     * Scope a query to only include reseller packages.
     */
    public function scopeReseller(Builder $query): void
    {
        $query->where('type', 'reseller');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function containers()
    {
        return $this->hasMany(Container::class);
    }
}
