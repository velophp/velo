<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthSession extends Model
{
    protected $fillable = ['project_id', 'collection_id', 'record_id', 'token_hash', 'expires_at', 'last_used_at', 'device_name', 'ip_address'];

    public static function generateToken()
    {
        $plainToken = \Str::random(64);
        $hashed = hash('sha256', $plainToken);

        return [$plainToken, $hashed];
    }
}
