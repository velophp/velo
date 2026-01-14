<?php

namespace App\Collections\Handlers;

use App\Models\Record;
use Illuminate\Support\Facades\Hash;

class AuthCollectionHandler implements CollectionTypeHandler
{
    public function beforeSave(Record $record): void
    {
        $data = $record->data;

        if ((!$record->exists && $data->has('password')) || ($data->has('password_new') && filled($data->get('password_new')))) {
            $data->put('password', Hash::make($data->get('password')));
        }

        $record->data = $data;
    }

    public function beforeDelete(Record $record): void
    {
    }
}
