<?php

namespace App\Support;

use App\Delivery\Models\User;
use App\Domain\Auth\Models\AppConfig;
use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\Models\CollectionField;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Support\Str;

class Helper
{
    /**
     * Create first project, and a super user account.
     */
    public static function initProject(string $superuserEmail = 'admin@velobase.dev', string $superuserPassword = 'password'): User
    {
        \DB::beginTransaction();

        $project = Project::create([
            'name' => 'Velo',
        ]);

        $appConfig = AppConfig::create([
            'project_id' => $project->id,
            'app_name'   => 'Velo',
            'app_url'    => 'http://localhost',
        ]);

        $userCollection = Collection::create([
            'name'       => 'users',
            'project_id' => $project->id,
            'type'       => CollectionType::Auth,
        ]);

        $collectionFields = CollectionField::createAuthFrom([
            [
                'name'     => 'name',
                'type'     => FieldType::Text,
                'unique'   => false,
                'required' => true,
                'options'  => [],
            ],
            [
                'name'     => 'avatar',
                'type'     => FieldType::File,
                'unique'   => false,
                'required' => false,
                'options'  => [
                    'allowedMimeTypes' => [
                        'image/gif',
                        'image/jpeg',
                        'image/png',
                        'image/svg+xml',
                        'image/webp',
                    ],
                ],
            ],
        ]);

        foreach ($collectionFields as $f) {
            $userCollection->fields()->create($f);
        }

        $user = User::create([
            'project_id' => $project->id,
            'name'       => 'superuser_' . Str::random(8),
            'email'      => $superuserEmail,
            'password'   => $superuserPassword,
        ]);

        foreach (range(1, 10) as $i) {
            Record::create([
                'collection_id' => $userCollection->id,
                'data'          => [
                    'name'     => fake()->name(),
                    'email'    => fake()->email(),
                    'password' => 'password',
                ],
            ]);
        }

        \DB::commit();

        return $user;
    }

    public static function generateIndexName(Collection $collection, string $fieldName, bool $unique): string
    {
        $prefix = $unique ? 'uq' : 'idx';
        $name = "{$prefix}_{$collection->id}_{$fieldName}";

        if (\strlen($name) > 64) {
            $hash = substr(md5($fieldName), 0, 8);
            $name = "{$prefix}{$collection->id}_{$hash}";
        }

        return $name;
    }

    public static function decodeIndexName(string $indexName): array
    {
        $parts = explode('_', $indexName);

        $prefix = array_shift($parts); // 'idx' or 'uq'
        $collectionId = array_shift($parts); // '1'

        return [
            'is_unique'     => $prefix === 'uq',
            'collection_id' => $collectionId,
            'field_names'   => $parts, // Returns ['id', 'email']
        ];
    }

    public static function generateVirtualColumnName(Collection $collection, string $fieldName): string
    {
        return "col_{$collection->id}_{$fieldName}";
    }

    public static function getFieldTypeIcon($name, $type)
    {
        if ($name === 'id') {
            return 'lucide.key';
        }
        if ($name === 'password') {
            return 'lucide.lock';
        }

        return match ($type) {
            FieldType::Number   => 'lucide.hash',
            FieldType::Email    => 'lucide.mail',
            FieldType::Bool     => 'lucide.toggle-right',
            FieldType::Datetime => 'lucide.calendar-clock',
            FieldType::File     => 'lucide.image',
            FieldType::Relation => 'lucide.share-2',
            default             => 'lucide.text-cursor',
        };
    }

    public static function toObject($array)
    {
        return (object) array_map(function ($item) {
            return \is_array($item) ? self::toObject($item) : $item;
        }, $array);
    }
}
