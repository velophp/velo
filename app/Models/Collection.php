<?php

namespace App\Models;

use App\Casts\AsSafeCollection;
use App\Enums\CollectionType;
use App\Services\RecordQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $table = 'collections';

    protected $fillable = ['project_id', 'name', 'type', 'api_rules', 'options'];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'api_rules' => 'array',
            'options' => AsSafeCollection::class,
        ];
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            CollectionType::Auth => 'o-users',
            CollectionType::View => 'o-table-cells',
            default => 'o-archive-box',
        };
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function fields()
    {
        return $this->hasMany(CollectionField::class)->orderBy('order');
    }

    public function records(): RecordQuery
    {
        return new RecordQuery($this);
    }

    public function recordRelation(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    public function indexes()
    {
        return $this->hasMany(CollectionIndex::class, 'collection_id');
    }

    public function recordIndexes(): HasMany
    {
        return $this->hasMany(RecordIndex::class, 'collection_id');
    }

    public static function getDefaultApiRules(): array
    {
        return [
            'list' => 'SUPERUSER_ONLY',
            'view' => 'SUPERUSER_ONLY',
            'create' => 'SUPERUSER_ONLY',
            'update' => 'SUPERUSER_ONLY',
            'delete' => 'SUPERUSER_ONLY',
        ];
    }

    public static function getDefaultAuthOptions(): array
    {
        return [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'fields' => ['email'],
                ],
                'oauth2' => [
                    'enabled' => false,
                    'providers' => [],
                    'config' => [],
                ],
                'otp' => [
                    'enabled' => false,
                    'config' => [
                        'duration_s' => 180,
                        'generate_password_length' => 8,
                    ],
                ],
            ],
            'mail_templates' => [
                'verification' => [
                    'subject' => 'Verify your email address',
                    'body' => <<<HTML
<p>Hello,</p>
<p>To confirm this email address click the link below:</p>
<p>Confirm: <a href="{{action_url}}">{{action_url}}</a></p>
<p>If you did not create an account, no further action is required.</p>
<p>Regards,<br><strong>{{app_name}}</strong></p>
HTML,

                ],
                'password_reset' => [
                    'subject' => 'Reset your password',
                    'body' => <<<HTML
<p>Hello,</p>
<p>You are receiving this email because we received a password reset request for your account.</p>
<p>Action URL: <a href="{{action_url}}">{{action_url}}</a></p>
<p>If you did not request a password reset, no further action is required.</p>
<p>Regards,<br><strong>{{app_name}}</strong></p>
HTML,
                ],
                'confirm_email_change' => ['subject' => '', 'body' => ''],
                'otp_email' => [
                    'subject' => 'Your OTP Code',
                    'body' => <<<HTML
<p>Hello,</p>
<p>Your OTP code is: <strong>{{otp}}</strong></p>
<p>If you did not request an OTP code, no further action is required.</p>
<p>Regards,<br><strong>{{app_name}}</strong></p>
HTML,
                ],
                'login_alert' => [
                    'subject' => 'Login Alert',
                    'body' => <<<HTML
<p>Hello,</p>
<p>We noticed a new login to your account.</p>
<p>
    <b>Device:</b> {{device_name}}<br>
    <b>IP Address:</b> {{ip_address}}<br>
    <b>Time:</b> {{date}}
</p>
<p>If this was you, you can ignore this email.</p>
<p>If you do not recognize this activity, please change your password immediately.</p>
<p>Regards,<br><strong>{{app_name}}</strong></p>
HTML,
                ],
            ],
            'other' => [
                'tokens_options' => [
                    'auth_duration' => [
                        'value' => '1209600',
                        'invalidate_previous_tokens' => false,
                    ],
                    'email_verification' => [
                        'value' => '604800',
                        'invalidate_previous_tokens' => false,
                    ],
                    'password_reset_duration' => [
                        'value' => '1800',
                        'invalidate_previous_tokens' => false,
                    ],
                    'email_change_duration' => [
                        'value' => '1800',
                        'invalidate_previous_tokens' => false,
                    ],
                    'protected_file_access_duration' => [
                        'value' => '120',
                        'invalidate_previous_tokens' => false,
                    ],
                ],
            ],
        ];
    }

    protected static function booted()
    {
        static::saving(function (Collection $collection) {
            if ($collection->api_rules == null && $collection->type === CollectionType::Base) {
                $collection->api_rules = static::getDefaultApiRules();
            }

            if ($collection->api_rules == null && $collection->type === CollectionType::Auth) {
                $collection->api_rules = [
                    'authenticate' => '',
                    'manage' => 'SUPERUSER_ONLY',
                    'list' => '@request.auth.id = id',
                    'view' => '@request.auth.id = id',
                    'create' => '',
                    'update' => '@request.auth.id = id',
                    'delete' => 'SUPERUSER_ONLY',
                ];
            }

            if ($collection->options == null) {
                $collection->options = static::getDefaultAuthOptions();
            }
        });
    }
}
