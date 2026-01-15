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
        // 1. If value is already an array (standard cast worked), return it.
        if (is_array($value)) {
            return $value;
        }

        // 2. If value is a string, it could be JSON (new format) or CSV (legacy).
        if (is_string($value)) {
            // Try to decode as JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // Fallback: Treat as CSV
            $ips = [];
            $rawIps = explode(',', $value);
            foreach ($rawIps as $ip) {
                $trimmed = trim($ip);
                if (!empty($trimmed)) {
                    $ips[] = $trimmed;
                }
            }
            return $ips;
        }

        // 3. Handle null/empty from cast failure or empty DB
        // If cast failed, $value is null. We check raw attributes.
        if (is_null($value) && !empty($this->attributes['allowed_ips'])) {
             $raw = $this->attributes['allowed_ips'];

             if (is_string($raw)) {
                 // Try JSON first on raw
                 $decoded = json_decode($raw, true);
                 if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                 }

                 // Fallback CSV on raw
                 return array_values(array_filter(array_map('trim', explode(',', $raw))));
             }
        }

        return $value;
    }
}
