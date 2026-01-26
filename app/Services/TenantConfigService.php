<?php

namespace App\Services;

use App\Models\AppConfig;
use Illuminate\Support\Facades\Cache;

class TenantConfigService
{
    public function load(int $projectId): ?AppConfig
    {
        $cacheKey = 'tenant_config_' . $projectId;
        $ttl = config('larabase.cache_ttl', 60);

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($projectId) {
                return AppConfig::where('project_id', $projectId)->first();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Table might not exist yet during testing or migrations
            return null;
        }
    }
}
