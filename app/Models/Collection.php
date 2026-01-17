<?php

namespace App\Models;

use App\Enums\CollectionType;
use App\Services\RecordQuery;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';

    protected $fillable = ['project_id', 'name', 'type', 'api_rules', 'options'];

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
        return new RecordQuery($this);
    }

    public function recordRelation()
    {
        return $this->hasMany(Record::class);
    }

    public function indexes()
    {
        return $this->hasMany(CollectionIndex::class, 'collection_id');
    }

    public function recordIndexes()
    {
        return $this->hasMany(RecordIndex::class, 'collection_id');
    }

    public static function getDefaultApiRules()
    {
        return [
            'list' => 'SUPERUSER_ONLY',
            'view' => 'SUPERUSER_ONLY',
            'create' => 'SUPERUSER_ONLY',
            'update' => 'SUPERUSER_ONLY',
            'delete' => 'SUPERUSER_ONLY',
        ];
    }

    public static function getDefaultAuthOptions()
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
            if (null == $collection->api_rules && CollectionType::Base === $collection->type) {
                $collection->api_rules = static::getDefaultApiRules();
            }

            if (null == $collection->api_rules && CollectionType::Auth === $collection->type) {
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

            if (null == $collection->options) {
                $collection->options = static::getDefaultAuthOptions();
            }
        });
    }
}
