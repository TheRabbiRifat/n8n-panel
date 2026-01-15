<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'allowed_ips',
    ];

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'allowed_ips' => 'array',
    ];

    /**
     * Get the allowed IPs.
     *
     * @param  mixed  $value
     * @return array|null
     */
    public function getAllowedIpsAttribute($value)
    {
        // Fix for "String inside JSON" issue (User's current state)
        if (is_string($value)) {
             return array_map('trim', explode(',', $value));
        }

        // Fix for "Raw CSV" legacy data (if any exists and json_decode failed)
        // If cast failed, $value is null. We check raw attributes.
        if (is_null($value) && !empty($this->attributes['allowed_ips'])) {
             $raw = $this->attributes['allowed_ips'];

             // If raw is not empty string
             if (!empty($raw) && is_string($raw)) {
                 // We assume it is CSV if it failed JSON decode (which resulted in null)
                 return array_map('trim', explode(',', $raw));
             }
        }

        return $value;
    }
}
