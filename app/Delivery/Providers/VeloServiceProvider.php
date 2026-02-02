<?php

namespace App\Delivery\Providers;

use App\Domain\Auth\Models\AppConfig;
use App\Domain\Project\Services\TenantConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class VeloServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->bootTenantConfig();
        $this->loadHooks();
    }

    private function bootTenantConfig(): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $strat = strtoupper(config('velo.sql_generated_column_strategy'));
        if (! in_array($strat, ['STORED', 'VIRTUAL'])) {
            throw new \Exception('Invalid SQL generated column strategy: ' . $strat);
        }

        // For now, hardcode project_id to 1. In a real multi-tenant app, this would come from the request/domain.
        $project_id = 1;

        $config = app(TenantConfigService::class)->load($project_id);

        $this->applyAppConfig($config);
        $this->applyTrustProxies($config?->trusted_proxies);
        $this->applyEmailConfig($config?->email_settings);
        $this->applyStorageConfig($config?->storage_settings);
        $this->applyRateLimiter($config?->rate_limits);
    }

    private function applyAppConfig(?AppConfig $config): void
    {
        if ($config) {
            config([
                'app.name' => $config->app_name,
                'app.url'  => $config->app_url,
            ]);
        }
    }

    private function applyTrustProxies(?array $proxies): void
    {
        if ($proxies) {
            Request::setTrustedProxies(
                proxies: $proxies,
                trustedHeaderSet: SymfonyRequest::HEADER_X_FORWARDED_FOR |
                    SymfonyRequest::HEADER_X_FORWARDED_HOST |
                    SymfonyRequest::HEADER_X_FORWARDED_PORT |
                    SymfonyRequest::HEADER_X_FORWARDED_PROTO |
                    SymfonyRequest::HEADER_X_FORWARDED_AWS_ELB
            );
        }
    }

    private function applyEmailConfig(?array $settings): void
    {
        if ($settings) {
            config([
                'mail.mailers.smtp.host'       => $settings['host'] ?? null,
                'mail.mailers.smtp.port'       => $settings['port'] ?? null,
                'mail.mailers.smtp.username'   => $settings['username'] ?? null,
                'mail.mailers.smtp.password'   => $settings['password'] ?? null,
                'mail.mailers.smtp.encryption' => $settings['encryption'] ?? null,
                'mail.from.address'            => $settings['from_address'] ?? null,
                'mail.from.name'               => $settings['from_name'] ?? null,
            ]);
        }
    }

    private function applyStorageConfig(?array $settings): void
    {
        if ($settings && ($settings['provider'] ?? 'local') === 's3') {
            config([
                'filesystems.disks.s3.endpoint'                => $settings['endpoint'] ?? null,
                'filesystems.disks.s3.bucket'                  => $settings['bucket'] ?? null,
                'filesystems.disks.s3.region'                  => $settings['region'] ?? null,
                'filesystems.disks.s3.key'                     => $settings['access_key'] ?? null,
                'filesystems.disks.s3.secret'                  => $settings['secret_key'] ?? null,
                'filesystems.disks.s3.use_path_style_endpoint' => $settings['s3_force_path_styling'] ?? false,
            ]);
        }
    }

    private function applyRateLimiter(?int $rateLimit): void
    {
        $limit = $rateLimit ?: 120;

        \Illuminate\Support\Facades\RateLimiter::for('dynamic-api', function (Request $request) use ($limit) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function loadHooks(): void
    {
        $this->app->singleton(\App\Domain\Hooks\Hooks::class, function ($app) {
            return new \App\Domain\Hooks\Hooks();
        });

        if (file_exists(base_path('routes/hooks.php'))) {
            require base_path('routes/hooks.php');
        }
    }
}
