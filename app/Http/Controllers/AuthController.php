<?php

namespace App\Http\Controllers;

use App\Models\AuthSession;
use App\Models\Collection;
use Hash;
use Illuminate\Http\Request;
use App\Enums\CollectionType;
use App\Services\RecordQueryCompiler;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class AuthController extends Controller
{
    public function authenticateWithPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth)
            throw new RouteNotFoundException('Collection is not auth enabled.');

        if (!isset($collection->options['auth_methods']['standard']))
            throw new RouteNotFoundException('Collection is not setup for standard auth method.');

        if (!$collection->options['auth_methods']['standard']['enabled'])
            throw new RouteNotFoundException('Collection is not auth enabled.');

        $identifiers = $collection->options['auth_methods']['standard']['fields'];

        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string'
        ]);

        $validFields = $collection->fields()->pluck('name')->toArray();
        $identifiers = array_filter($identifiers, fn($field) => in_array($field, $validFields));

        if (empty($identifiers))
            throw new ModelNotFoundException('Collection is not setup for standard auth method.');

        $identifierValue = $request->input('identifier');
        $conditions = array_map(fn($field) => ['field' => $field, 'value' => $identifierValue], $identifiers);
        $filterString = RecordQueryCompiler::buildSaveFilterString($conditions, 'OR');
        $record = $collection->recordQueryCompiler()->filterFromString($filterString)->first();

        if (!$record)
            throw ValidationException::withMessages([
                'identifier' => 'Invalid credentials.'
            ]);

        if (!Hash::check($request->input('password'), $record->data->get('password')))
            throw ValidationException::withMessages([
                'identifier' => 'Invalid credentials.'
            ]);

        [$token, $hashed] = AuthSession::generateToken();

        AuthSession::create([
            'project_id' => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed,
            'expires_at' => now()->addHours(24),
            'last_used_at' => now(),
            'device_name' => $request->input('device_name'),
            'ip_address' => $request->ip(),
        ]);

        return Response::json([
            'message' => 'Authenticated.',
            'data' => $token
        ]);
    }
}
