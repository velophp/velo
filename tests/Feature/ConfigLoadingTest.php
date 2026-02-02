<?php

namespace Tests\Feature;

use App\Domain\Auth\Models\AppConfig;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ConfigLoadingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache to ensure fresh config loading
        \Illuminate\Support\Facades\Cache::flush();
        \Illuminate\Database\Eloquent\Model::unguard();

        if (class_exists(\App\Domain\Project\Models\Project::class)) {
            \App\Domain\Project\Models\Project::create(['id' => 1, 'name' => 'Test Project']);
        } else {
            // Fallback if no Project model (improbable given constraints)
            \Illuminate\Support\Facades\DB::table('projects')->insert(['id' => 1, 'name' => 'Test Project']);
        }
    }

    public function test_email_config_is_loaded_and_cached(): void
    {
        AppConfig::create([
            'project_id'     => 1,
            'app_name'       => 'Test App',
            'email_settings' => [
                'mailer'       => 'smtp',
                'host'         => 'smtp.example.com',
                'port'         => 587,
                'username'     => 'user',
                'password'     => 'pass',
                'encryption'   => 'tls',
                'from_address' => 'test@example.com',
                'from_name'    => 'Test App',
            ],
        ]);

        $provider = new \App\Delivery\Providers\VeloServiceProvider($this->app);
        $provider->boot();

        $this->assertEquals('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertEquals('test@example.com', config('mail.from.address'));
    }

    public function test_storage_config_is_loaded_and_cached(): void
    {
        AppConfig::create([
            'project_id'       => 1,
            'app_name'         => 'Test App',
            'storage_settings' => [
                'provider'              => 's3',
                'endpoint'              => 'https://s3.example.com',
                'bucket'                => 'my-bucket',
                'region'                => 'us-east-1',
                'access_key'            => 'key',
                'secret_key'            => 'secret',
                's3_force_path_styling' => true,
            ],
        ]);

        $provider = new \App\Delivery\Providers\VeloServiceProvider($this->app);
        $provider->boot();

        $this->assertEquals('https://s3.example.com', config('filesystems.disks.s3.endpoint'));
        $this->assertEquals('my-bucket', config('filesystems.disks.s3.bucket'));
        $this->assertTrue(config('filesystems.disks.s3.use_path_style_endpoint'));
    }

    public function test_rate_limiter_defaults_to_120(): void
    {
        // No AppConfig
        $provider = new \App\Delivery\Providers\VeloServiceProvider($this->app);
        $provider->boot();

        $limiter = RateLimiter::limiter('dynamic-api');
        $this->assertNotNull($limiter);

        $request = \Illuminate\Http\Request::create('/api/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $limitObject = $limiter($request);

        $this->assertEquals(120, $limitObject->maxAttempts);
    }

    public function test_rate_limiter_uses_db_config(): void
    {
        AppConfig::create([
            'project_id'  => 1,
            'app_name'    => 'Test',
            'rate_limits' => 200,
        ]);

        $provider = new \App\Delivery\Providers\VeloServiceProvider($this->app);
        $provider->boot();

        $limiter = RateLimiter::limiter('dynamic-api');
        $request = \Illuminate\Http\Request::create('/api/test', 'GET');

        $limitObject = $limiter($request);
        $this->assertEquals(200, $limitObject->maxAttempts);
    }
}
