<?php

namespace App\Domain\Collection\Handlers;

use App\Domain\Collection\Contracts\CollectionTypeHandler;
use App\Domain\Record\Models\Record;
use Illuminate\Support\Facades\Hash;

class AuthCollectionHandler implements CollectionTypeHandler
{
    public function beforeSave(Record &$record): void
    {
        $data = $record->data;

        if ($data->has('password_new') && filled($data->get('password_new'))) {
            $data->put('password', Hash::make($data->pull('password_new')));
        } elseif ($data->has('password') && filled($data->get('password'))) {
            $value = $data->get('password');
            $info = Hash::info($value);

            if ($info['algoName'] === 'unknown') {
                $data->put('password', Hash::make($data->pull('password')));
            }
        }

        $record->data = $data;
    }

    public function beforeDelete(Record &$record): void
    {
    }

    public function onRetrieved(Record &$record): void
    {
    }
}
