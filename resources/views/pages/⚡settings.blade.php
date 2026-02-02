<?php

use App\Domain\Auth\Models\AppConfig;
use App\Domain\Project\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public Project $project;
    public array $breadcrumbs = [];
    public string $activeTab = 'general';

    // General Settings (AppConfig)
    public string $app_name = '';
    public string $app_url = '';
    public string $trusted_proxies_input = '';
    public ?int $rate_limits = null;

    // Mail Settings (EmailConfig)
    public string $mail_mailer = 'smtp';
    public string $mail_host = '';
    public string $mail_port = '587';
    public string $mail_username = '';
    public string $mail_password = '';
    public string $mail_encryption = 'tls';
    public string $mail_from_address = '';
    public string $mail_from_name = '';

    // Storage Settings (StorageConfig)
    public string $storage_provider = 'local';
    public string $storage_endpoint = '';
    public string $storage_bucket = '';
    public string $storage_region = '';
    public string $storage_access_key = '';
    public string $storage_secret_key = '';
    public bool $s3_force_path_styling = false;

    protected function rules(): array
    {
        return [
            // General
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'trusted_proxies_input' => ['nullable', 'string'],
            'rate_limits' => ['nullable', 'integer', 'min:1'],

            // Mail
            'mail_mailer' => ['required', 'string', 'in:smtp,sendmail,mailgun,ses,postmark,log,array'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'string', 'max:10'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],

            // Storage
            'storage_provider' => ['required', 'string', 'in:local,s3'],
            'storage_endpoint' => ['nullable', 'string', 'max:255'],
            'storage_bucket' => ['nullable', 'string', 'max:255'],
            'storage_region' => ['nullable', 'string', 'max:255'],
            'storage_access_key' => ['nullable', 'string', 'max:255'],
            'storage_secret_key' => ['nullable', 'string', 'max:255'],
            's3_force_path_styling' => ['boolean'],
        ];
    }

    public function mount(): void
    {
        $this->project = Project::firstOrFail();

        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => 'System'],
            ['label' => 'Settings'],
        ];

        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        // Load AppConfig
        $appConfig = AppConfig::where('project_id', $this->project->id)->first();

        // General
        $this->app_name = $appConfig->app_name ?? $this->project->name ?? '';
        $this->app_url = $appConfig->app_url ?? 'http://localhost';
        $this->trusted_proxies_input = $appConfig?->trusted_proxies ? implode(', ', $appConfig->trusted_proxies) : '';
        $this->rate_limits = $appConfig?->rate_limits;

        // Email
        $emailSettings = $appConfig->email_settings ?? [];
        $this->mail_mailer = $emailSettings['mailer'] ?? 'smtp';
        $this->mail_host = $emailSettings['host'] ?? '';
        $this->mail_port = $emailSettings['port'] ?? '587';
        $this->mail_username = $emailSettings['username'] ?? '';
        $this->mail_password = $emailSettings['password'] ?? '';
        $this->mail_encryption = $emailSettings['encryption'] ?? 'tls';
        $this->mail_from_address = $emailSettings['from_address'] ?? '';
        $this->mail_from_name = $emailSettings['from_name'] ?? '';

        // Storage
        $storageSettings = $appConfig->storage_settings ?? [];
        $this->storage_provider = $storageSettings['provider'] ?? 'local';
        $this->storage_endpoint = $storageSettings['endpoint'] ?? '';
        $this->storage_bucket = $storageSettings['bucket'] ?? '';
        $this->storage_region = $storageSettings['region'] ?? '';
        $this->storage_access_key = $storageSettings['access_key'] ?? '';
        $this->storage_secret_key = $storageSettings['secret_key'] ?? '';
        $this->s3_force_path_styling = $storageSettings['s3_force_path_styling'] ?? false;
    }

    public function saveGeneralSettings(): void
    {
        $this->validate([
            'app_name' => $this->rules()['app_name'],
            'app_url' => $this->rules()['app_url'],
            'trusted_proxies_input' => $this->rules()['trusted_proxies_input'],
            'rate_limits' => $this->rules()['rate_limits'],
        ]);

        $trustedProxies = null;
        if (!empty($this->trusted_proxies_input)) {
            $trustedProxies = array_map('trim', explode(',', $this->trusted_proxies_input));
            $trustedProxies = array_filter($trustedProxies);
            $trustedProxies = array_values($trustedProxies);
        }

        AppConfig::updateOrCreate(
            ['project_id' => $this->project->id],
            [
                'app_name' => $this->app_name,
                'app_url' => $this->app_url,
                'trusted_proxies' => $trustedProxies,
                'rate_limits' => $this->rate_limits,
            ]
        );

        $this->success('General settings saved successfully.');
    }

    public function saveMailSettings(): void
    {
        $this->validate([
            'mail_mailer' => $this->rules()['mail_mailer'],
            'mail_host' => $this->rules()['mail_host'],
            'mail_port' => $this->rules()['mail_port'],
            'mail_username' => $this->rules()['mail_username'],
            'mail_password' => $this->rules()['mail_password'],
            'mail_encryption' => $this->rules()['mail_encryption'],
            'mail_from_address' => $this->rules()['mail_from_address'],
            'mail_from_name' => $this->rules()['mail_from_name'],
        ]);

        $settings = [
            'mailer' => $this->mail_mailer,
            'host' => $this->mail_host,
            'port' => $this->mail_port,
            'username' => $this->mail_username,
            'password' => $this->mail_password,
            'encryption' => $this->mail_encryption,
            'from_address' => $this->mail_from_address,
            'from_name' => $this->mail_from_name,
        ];

        $appConfig = AppConfig::firstOrNew(['project_id' => $this->project->id]);
        $appConfig->email_settings = $settings;

        if (!$appConfig->exists) {
            $appConfig->app_name = $this->project->name;
        }

        $appConfig->save();

        $this->success('Mail settings saved successfully.');
    }

    public function saveStorageSettings(): void
    {
        $this->validate([
            'storage_provider' => $this->rules()['storage_provider'],
            'storage_endpoint' => $this->rules()['storage_endpoint'],
            'storage_bucket' => $this->rules()['storage_bucket'],
            'storage_region' => $this->rules()['storage_region'],
            'storage_access_key' => $this->rules()['storage_access_key'],
            'storage_secret_key' => $this->rules()['storage_secret_key'],
            's3_force_path_styling' => $this->rules()['s3_force_path_styling'],
        ]);

        $settings = [
            'provider' => $this->storage_provider,
            'endpoint' => $this->storage_endpoint,
            'bucket' => $this->storage_bucket,
            'region' => $this->storage_region,
            'access_key' => $this->storage_access_key,
            'secret_key' => $this->storage_secret_key,
            's3_force_path_styling' => $this->s3_force_path_styling,
        ];

        $appConfig = AppConfig::firstOrNew(['project_id' => $this->project->id]);
        $appConfig->storage_settings = $settings;

        if (!$appConfig->exists) {
            $appConfig->app_name = $this->project->name;
        }
        $appConfig->save();

        $this->success('Storage settings saved successfully.');
    }

    #[Computed]
    public function mailerOptions(): array
    {
        return [
            ['id' => 'smtp', 'name' => 'SMTP'],
            ['id' => 'sendmail', 'name' => 'Sendmail'],
            ['id' => 'mailgun', 'name' => 'Mailgun'],
            ['id' => 'ses', 'name' => 'Amazon SES'],
            ['id' => 'postmark', 'name' => 'Postmark'],
            ['id' => 'log', 'name' => 'Log (Testing)'],
            ['id' => 'array', 'name' => 'Array (Testing)'],
        ];
    }

    #[Computed]
    public function encryptionOptions(): array
    {
        return [
            ['id' => 'tls', 'name' => 'TLS'],
            ['id' => 'ssl', 'name' => 'SSL'],
            ['id' => 'null', 'name' => 'None'],
        ];
    }

    #[Computed]
    public function storageProviderOptions(): array
    {
        return [
            ['id' => 'local', 'name' => 'Local'],
            ['id' => 's3', 'name' => 'Amazon S3 / S3-Compatible'],
        ];
    }

    #[Computed]
    public function serverInfo(): array
    {
        return [
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'Server OS' => PHP_OS_FAMILY . ' (' . php_uname('r') . ')',
            'Architecture' => php_uname('m'),
            'PHP SAPI' => php_sapi_name(),
            'Timezone' => config('app.timezone'),
            'Locale' => config('app.locale'),
            'Debug Mode' => config('app.debug') ? 'Enabled' : 'Disabled',
            'Environment' => app()->environment(),
            'Cache Driver' => config('cache.default'),
            'Session Driver' => config('session.driver'),
            'Queue Driver' => config('queue.default'),
            'Database' => config('database.default'),
            'Max Upload Size' => ini_get('upload_max_filesize'),
            'Max POST Size' => ini_get('post_max_size'),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's',
        ];
    }

    #[Computed]
    public function phpExtensions(): array
    {
        $required = ['openssl', 'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'curl'];
        $extensions = [];

        foreach ($required as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }

        return $extensions;
    }
};

?>

<div>
    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs"/>
        </div>
    </div>

    <div class="my-8"></div>

    <x-tabs wire:model="activeTab">
        {{-- General Tab --}}
        <x-tab name="general" label="General" icon="o-cog-6-tooth">
            <x-form wire:submit="saveGeneralSettings" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Application Name" wire:model="app_name" icon="o-building-office"
                             hint="The name of your application" required/>

                    <x-input label="Application URL" wire:model="app_url" icon="o-globe-alt"
                             hint="The base URL of your application" required/>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Rate Limits" wire:model="rate_limits" type="number" icon="o-clock"
                             hint="Max API requests per minute (leave empty for unlimited)" min="1"/>

                    <x-textarea label="Trusted Proxies" wire:model="trusted_proxies_input"
                                placeholder="192.168.1.1, 10.0.0.0/8, *"
                                hint="Comma-separated list of trusted proxy IPs or CIDR ranges. Use * for all."
                                rows="2"/>
                </div>

                <x-slot:actions>
                    <x-button label="Save General Settings" type="submit" class="btn-primary" icon="o-check"
                              spinner="saveGeneralSettings"/>
                </x-slot:actions>
            </x-form>
        </x-tab>

        {{-- Mail Tab --}}
        <x-tab name="mail" label="Mail" icon="o-envelope">
            <x-form wire:submit="saveMailSettings" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select label="Mail Driver" wire:model.live="mail_mailer" :options="$this->mailerOptions"
                              icon="o-paper-airplane" required/>

                    <x-select label="Encryption" wire:model="mail_encryption" :options="$this->encryptionOptions"
                              icon="o-lock-closed"/>
                </div>

                @if(in_array($mail_mailer, ['smtp', 'sendmail']))
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input label="Mail Host" wire:model="mail_host" icon="o-server"
                                 placeholder="smtp.example.com"/>

                        <x-input label="Mail Port" wire:model="mail_port" icon="o-hashtag" placeholder="587"/>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input label="Username" wire:model="mail_username" icon="o-user"/>

                        <x-password label="Password" wire:model="mail_password" password-icon="o-key"/>
                    </div>
                @endif

                <div class="divider">Sender Information</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="From Name" wire:model="mail_from_name" icon="o-user"
                             hint="The name emails will be sent from" required/>

                    <x-input label="From Address" wire:model="mail_from_address" type="email" icon="o-envelope"
                             hint="The email address emails will be sent from" required/>
                </div>

                <x-slot:actions>
                    <x-button label="Save Mail Settings" type="submit" class="btn-primary" icon="o-check"
                              spinner="saveMailSettings"/>
                </x-slot:actions>
            </x-form>
        </x-tab>

        {{-- Storage Tab --}}
        <x-tab name="storage" label="Storage" icon="o-circle-stack">
            <x-form wire:submit="saveStorageSettings" class="space-y-4">
                <x-select label="Storage Provider" wire:model.live="storage_provider"
                          :options="$this->storageProviderOptions" icon="o-cloud" required/>

                @if($storage_provider === 's3')
                    <div class="p-4 bg-base-200 rounded-lg space-y-4">
                        <h3 class="font-semibold text-lg">S3 Configuration</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input label="Endpoint" wire:model="storage_endpoint" icon="o-globe-alt"
                                     placeholder="https://s3.amazonaws.com"
                                     hint="Leave empty for AWS S3, or provide custom endpoint for S3-compatible storage"/>

                            <x-input label="Bucket" wire:model="storage_bucket" icon="o-archive-box"
                                     placeholder="my-bucket"/>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input label="Region" wire:model="storage_region" icon="o-map" placeholder="us-east-1"/>

                            <x-toggle label="Force Path Style" wire:model="s3_force_path_styling"
                                      hint="Enable for S3-compatible services like MinIO"/>
                        </div>

                        <div class="divider">Credentials</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input label="Access Key" wire:model="storage_access_key" icon="o-key"/>

                            <x-password label="Secret Key" wire:model="storage_secret_key"
                                        password-icon="o-lock-closed"/>
                        </div>
                    </div>
                @else
                    <x-alert icon="o-information-circle" class="alert-info">
                        Using local storage. Files will be stored in the application's storage directory.
                    </x-alert>
                @endif

                <x-slot:actions>
                    <x-button label="Save Storage Settings" type="submit" class="btn-primary" icon="o-check"
                              spinner="saveStorageSettings"/>
                </x-slot:actions>
            </x-form>
        </x-tab>

        {{-- Server Info Tab --}}
        <x-tab name="server" label="Server Info" icon="o-server">
            <div class="space-y-6">
                {{-- Server Information --}}
                <div class="card bg-base-200">
                    <div class="card-body">
                        <h3 class="card-title text-lg">Server Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                            @foreach($this->serverInfo as $label => $value)
                                <div class="flex flex-col p-3 bg-base-100 rounded-lg">
                                    <span class="text-xs text-gray-500 uppercase tracking-wide">{{ $label }}</span>
                                    <span class="font-medium mt-1">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- PHP Extensions --}}
                <div class="card bg-base-200">
                    <div class="card-body">
                        <h3 class="card-title text-lg">PHP Extensions</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 mt-4">
                            @foreach($this->phpExtensions as $extension => $loaded)
                                <div class="flex items-center gap-2 p-2 bg-base-100 rounded-lg">
                                    @if($loaded)
                                        <x-icon name="o-check-circle" class="w-5 h-5 text-success"/>
                                    @else
                                        <x-icon name="o-x-circle" class="w-5 h-5 text-error"/>
                                    @endif
                                    <span class="text-sm">{{ $extension }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Disk Usage --}}
                <div class="card bg-base-200">
                    <div class="card-body">
                        <h3 class="card-title text-lg">Disk Space</h3>
                        @php
                            $storagePath = storage_path();
                            $totalSpace = disk_total_space($storagePath);
                            $freeSpace = disk_free_space($storagePath);
                            $usedSpace = $totalSpace - $freeSpace;
                            $usedPercent = round(($usedSpace / $totalSpace) * 100, 1);
                        @endphp
                        <div class="mt-4">
                            <div class="flex justify-between text-sm mb-2">
                                <span>Used: {{ number_format($usedSpace / 1073741824, 2) }} GB</span>
                                <span>Free: {{ number_format($freeSpace / 1073741824, 2) }} GB</span>
                                <span>Total: {{ number_format($totalSpace / 1073741824, 2) }} GB</span>
                            </div>
                            <progress
                                class="progress {{ $usedPercent > 90 ? 'progress-error' : ($usedPercent > 70 ? 'progress-warning' : 'progress-success') }} w-full"
                                value="{{ $usedPercent }}" max="100"></progress>
                            <div class="text-center text-sm mt-1">{{ $usedPercent }}% used</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-tab>
    </x-tabs>
</div>
