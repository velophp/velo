<?php

namespace App\Domain\Project\Services;

use App\Domain\Auth\Models\AppConfig;
use Illuminate\Support\Facades\Cache;

class TenantConfigService
{
    public function load(int $projectId): ?AppConfig
    {
        $cacheKey = 'tenant_config_' . $projectId;

        try {
            return Cache::rememberForever($cacheKey, function () use ($projectId) {
                return AppConfig::where('project_id', $projectId)->first();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Table might not exist yet during testing or migrations
            return null;
        }
    }

    public function refresh(int $projectId): void
    {
        $cacheKey = 'tenant_config_' . $projectId;
        Cache::forget($cacheKey);
    }
}
