<?php

namespace App\Models;

use App\Enums\CollectionType;
use App\Casts\ApiRulesCast;
use App\Services\RecordQueryCompiler;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';

    protected $fillable = ['project_id', 'name', 'type', 'api_rules', 'option'];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'api_rules' => 'array',
            'options' => 'array',
        ];
    }

    public function fields()
    {
        return $this->hasMany(CollectionField::class)->orderBy('order');
    }

    public function records()
    {
        return $this->hasMany(Record::class);
    }

    public function indexes()
    {
        return $this->hasMany(CollectionIndex::class, 'collection_id');
    }

    public function recordQueryCompiler()
    {
        return new RecordQueryCompiler($this);
    }

    public static function getDefaultApiRules()
    {
        return [
            'list' => "SUPERUSER_ONLY",
            'view' => "SUPERUSER_ONLY",
            'create' => "SUPERUSER_ONLY",
            'update' => "SUPERUSER_ONLY",
            'delete' => "SUPERUSER_ONLY",
        ];
    }

    public static function getDefaultOptions()
    {
        return [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'fields' => ['email']
                ],
                'oauth2' => [
                    'enabled' => false,
                    'providers' => [],
                    'config' => []
                ],
                'otp' => [
                    'enabled' => false,
                    'config' => [
                        'duration_s' => 180,
                        'generate_password_length' => 8
                    ],
                ],
            ],
            'mail_templates' => [
                'verification' => ['subject' => '', 'body' => ''],
                'password_reset' => ['subject' => '', 'body' => ''],
                'confirm_email_change' => ['subject' => '', 'body' => ''],
                'otp_email' => ['subject' => '', 'body' => ''],
                'login_alert' => ['subject' => '', 'body' => ''],
            ],
            'other' => [
                'tokens_options' => [
                    'auth_duration' => [
                        'value' => '1209600',
                        'invalidate_previous_tokens' => false
                    ],
                    'email_verification' => [
                        'value' => '604800',
                        'invalidate_previous_tokens' => false
                    ],
                    'password_reset_duration' => [
                        'value' => '1800',
                        'invalidate_previous_tokens' => false
                    ],
                    'email_change_duration' => [
                        'value' => '1800',
                        'invalidate_previous_tokens' => false
                    ],
                    'protected_file_access_duration' => [
                        'value' => '120',
                        'invalidate_previous_tokens' => false
                    ],
                ]
            ]
        ];
    }

    protected static function booted()
    {
        static::saving(function (Collection $collection) {
            if ($collection->api_rules == null && $collection->type === CollectionType::Base) {
                $collection->api_rules = [
                    ...static::getDefaultApiRules(),
                ];
            }

            if ($collection->api_rules == null && $collection->type === CollectionType::Auth) {
                $collection->api_rules = [
                    ...static::getDefaultApiRules(),
                    'authenticate' => '',
                    'manage' => 'SUPERUSER_ONLY',
                    'list' => '@request.auth.id = id',
                    'view' => '@request.auth.id = id',
                    'update' => '@request.auth.id = id',
                ];
            }

            if ($collection->options == null) {
                $collection->options = static::getDefaultOptions();
            }
        });
    }
}
