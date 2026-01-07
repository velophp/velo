<?php

namespace App;

use App\Enums\CollectionType;
use App\Enums\FieldType;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Project;
use App\Models\User;
use DB;
use Illuminate\Support\Str;

class Helper
{
    /**
     * Create first project, and a super user account
     * @param string $superuserEmail
     * @param string $superuserPassword
     * @return User
     */
    public static function initProject(string $superuserEmail = 'admin@larabase.com', string $superuserPassword = 'password'): User
    {
        DB::beginTransaction();

        $project = Project::create([
            'name' => 'Acme'
        ]);

        $userCollection = Collection::create([
            'name' => 'users',
            'project_id' => $project->id,
            'type' => CollectionType::Auth,
        ]);

        $collectionFields = CollectionField::createAuthFrom([
            [
                'name' => 'name',
                'type' => FieldType::Text,
                'unique' => false,
                'required' => true,
                'options' => [],
            ],
            [
                'name' => 'avatar',
                'type' => FieldType::File,
                'unique' => false,
                'required' => false,
                'options' => [],
            ],
        ]);

        foreach ($collectionFields as $f) {
            $userCollection->fields()->create($f);
        }

        $user = User::create([
            'name' => 'superuser_' . Str::random(8),
            'email' => $superuserEmail,
            'password' => $superuserPassword,
        ]);

        DB::commit();

        return $user;
    }

    public static function generateIndexName(Collection $collection, string $fieldName, bool $unique): string
    {
        $prefix = $unique ? 'uq' : 'idx';
        return "{$prefix}_{$collection->id}_{$fieldName}";
    }

    public static function generateVirtualColumnName(Collection $collection, string $fieldName): string
    {
        return "col_{$collection->id}_{$fieldName}";
    }

    public static function getFieldTypeIcon($name, $type) {
        if ($name === 'id') return 'lucide.key';
        if ($name === 'password') return 'lucide.lock';
        return match ($type) {
            FieldType::Number => 'lucide.hash',
            FieldType::Email => 'lucide.mail',
            FieldType::Bool => 'lucide.toggle-right',
            FieldType::Datetime => 'lucide.calendar-clock',
            FieldType::File => 'lucide.image',
            default => 'lucide.text-cursor',
        };
    }
}
