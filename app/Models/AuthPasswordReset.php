<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthPasswordReset extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'collection_id',
        'record_id',
        'email',
        'token',
        'expires_at',
        'used_at',
        'device_name',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
