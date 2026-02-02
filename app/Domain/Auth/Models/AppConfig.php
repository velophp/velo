<?php

namespace App\Domain\Auth\Models;

use App\Domain\Project\Services\TenantConfigService;
use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    protected $fillable = [
        'project_id',
        'app_name',
        'app_url',
        'trusted_proxies',
        'rate_limits',
        'email_settings',
        'storage_settings',
    ];

    protected function casts(): array
    {
        return [
            'trusted_proxies'  => 'array',
            'rate_limits'      => 'integer',
            'email_settings'   => 'encrypted:array',
            'storage_settings' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (AppConfig $appConfig) {
            app(TenantConfigService::class)->refresh($appConfig->project_id);
        });
    }
}
