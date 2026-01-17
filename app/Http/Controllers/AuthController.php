<?php

namespace App\Http\Controllers;

use App\Enums\CollectionType;
use App\Http\Resources\RecordResource;
use App\Models\AuthSession;
use App\Models\Collection;
use App\Models\Record;
use App\Services\RecordQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class AuthController extends Controller
{
    public function authenticateWithPassword(Request $request, Collection $collection)
    {
        if (CollectionType::Auth !== $collection->type) {
            throw new RouteNotFoundException('Collection is not auth enabled.');
        }

        if (!isset($collection->options['auth_methods']['standard'])) {
            throw new RouteNotFoundException('Collection is not setup for standard auth method.');
        }

        if (!$collection->options['auth_methods']['standard']['enabled']) {
            throw new RouteNotFoundException('Collection is not auth enabled.');
        }

        $identifiers = $collection->options['auth_methods']['standard']['fields'];

        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $validFields = $collection->fields()->pluck('name')->toArray();
        $identifiers = array_filter($identifiers, fn ($field) => in_array($field, $validFields));

        if (empty($identifiers)) {
            throw new ModelNotFoundException('Collection is not setup for standard auth method.');
        }

        $identifierValue = $request->input('identifier');
        $conditions = array_map(fn ($field) => ['field' => $field, 'value' => $identifierValue], $identifiers);
        $filterString = RecordQuery::buildFilterString($conditions, 'OR');
        $record = $collection->records()->filterFromString($filterString)->first();

        if (!$record) {
            throw ValidationException::withMessages(['identifier' => 'Invalid credentials.']);
        }

        if (!\Hash::check($request->input('password'), $record->data->get('password'))) {
            throw ValidationException::withMessages(['identifier' => 'Invalid credentials.']);
        }

        // Apply authenticate API rule
        $authenticateRule = $collection->api_rules['authenticate'] ?? '';
        if ('' !== $authenticateRule) {
            $context = [
                'sys_request' => \App\Helper::toObject([
                    'auth' => null, // Not authenticated yet
                    'body' => $request->post(),
                    'param' => $request->route()->parameters(),
                    'query' => $request->query(),
                ]),
                ...$record->data->toArray(),
            ];

            $rule = app(\App\Services\EvaluateRuleExpression::class)
                ->forExpression($authenticateRule)
                ->withContext($context);

            if (!$rule->evaluate()) {
                throw ValidationException::withMessages(['identifier' => 'Authentication failed due to collection rules.']);
            }
        }

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

        return \Response::json([
            'message' => 'Authenticated.',
            'data' => $token,
        ]);
    }

    public function me(Request $request, Collection $collection)
    {
        if (CollectionType::Auth !== $collection->type) {
            throw new RouteNotFoundException('Collection is not auth enabled.');
        }

        $session = $request->auth;
        if (!$session || !$session->get('meta')?->get('_id')) {
            return \Response::json(['message' => 'Unauthorized.'], 401);
        }

        $record = Record::find($session->get('meta')?->get('_id'));
        if (!$record) {
            return \Response::json(['message' => 'User not found.'], 404);
        }

        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function logout(Request $request, Collection $collection)
    {
        if (CollectionType::Auth !== $collection->type) {
            throw new RouteNotFoundException('Collection is not auth enabled.');
        }

        $session = $request->auth;
        if (!$session || !$session->get('meta')?->get('_id')) {
            return \Response::json(['message' => 'Unauthorized.'], 401);
        }

        $token = $request->bearerToken();
        $hashedToken = hash('sha256', $token);

        AuthSession::where('record_id', $session->get('meta')->get('_id'))
            ->where('collection_id', $collection->id)
            ->where('token_hash', $hashedToken)
            ->delete();

        return \Response::json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request, Collection $collection)
    {
        if (CollectionType::Auth !== $collection->type) {
            throw new RouteNotFoundException('Collection is not auth enabled.');
        }

        $session = $request->auth;
        if (!$session || !$session->get('meta')?->get('_id')) {
            return \Response::json(['message' => 'Unauthorized.'], 401);
        }

        AuthSession::where('record_id', $session->get('meta')->get('_id'))
            ->where('collection_id', $collection->id)
            ->delete();

        return \Response::json(['message' => 'Logged out from all sessions.']);
    }
}
