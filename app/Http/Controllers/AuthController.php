<?php

namespace App\Http\Controllers;

use App\Enums\CollectionType;
use App\Http\Resources\RecordResource;
use App\Mail\PasswordReset;
use App\Models\AuthPasswordReset;
use App\Models\AuthSession;
use App\Models\Collection;
use App\Models\Record;
use App\Services\RecordQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Mail;
use Response;

class AuthController extends Controller
{
    public function authenticateWithPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        if (! isset($collection->options['auth_methods']['standard'])) {
            return Response::json(['message' => 'Collection is not setup for standard auth method.'], 400);
        }

        if (! $collection->options['auth_methods']['standard']['enabled']) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $identifiers = $collection->options['auth_methods']['standard']['fields'];

        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $validFields = $collection->fields()->pluck('name')->toArray();
        $identifiers = array_filter($identifiers, fn ($field) => in_array($field, $validFields));

        if (empty($identifiers)) {
            return Response::json(['message' => 'Collection is not setup for standard auth method.'], 400);
        }

        $identifierValue = $request->input('identifier');
        $conditions = array_map(fn ($field) => ['field' => $field, 'value' => $identifierValue], $identifiers);
        $filterString = RecordQuery::buildFilterString($conditions, 'OR');
        $record = $collection->records()->filterFromString($filterString)->first();

        if (! $record) {
            throw ValidationException::withMessages(['identifier' => 'Invalid credentials.']);
        }

        if (! \Hash::check($request->input('password'), $record->data->get('password'))) {
            throw ValidationException::withMessages(['identifier' => 'Invalid credentials.']);
        }

        // Apply authenticate API rule
        $authenticateRule = $collection->api_rules['authenticate'] ?? '';
        if ($authenticateRule !== '') {
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

            if (! $rule->evaluate()) {
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

        return Response::json([
            'message' => 'Authenticated.',
            'data' => $token,
        ]);
    }

    public function me(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->auth;
        if (! $session || ! $session->get('meta')?->get('_id')) {
            return Response::json(['message' => 'Unauthorized.'], 401);
        }

        $record = Record::find($session->get('meta')?->get('_id'));
        if (! $record) {
            return Response::json(['message' => 'User not found.'], 404);
        }

        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function logout(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->auth;
        if (! $session || ! $session->get('meta')?->get('_id')) {
            return Response::json(['message' => 'Unauthorized.'], 401);
        }

        $token = $request->bearerToken();
        $hashedToken = hash('sha256', $token);

        AuthSession::where('record_id', $session->get('meta')->get('_id'))
            ->where('collection_id', $collection->id)
            ->where('token_hash', $hashedToken)
            ->delete();

        return Response::json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->auth;
        if (! $session || ! $session->get('meta')?->get('_id')) {
            return Response::json(['message' => 'Unauthorized.'], 401);
        }

        AuthSession::where('record_id', $session->get('meta')->get('_id'))
            ->where('collection_id', $collection->id)
            ->delete();

        return Response::json(['message' => 'Logged out from all sessions.']);
    }

    public function resetPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->auth;
        if (! $session || ! $session->get('meta')?->get('_id')) {
            return Response::json(['message' => 'Unauthorized.'], 401);
        }

        $request->validate([
            'password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8)],
            'invalidate_sessions' => 'boolean',
        ]);

        $record = Record::find($session->get('meta')->get('_id'));
        if (! $record) {
            return Response::json(['message' => 'User not found.'], 404);
        }

        if (! Hash::check($request->input('password'), $record->data->get('password'))) {
            return Response::json(['message' => 'Invalid current password.'], 400);
        }

        $record->data->put('password', Hash::make($request->input('new_password')));
        $record->save();

        if ($request->input('invalidate_sessions')) {
            AuthSession::where('record_id', $session->get('meta')->get('_id'))
                ->where('collection_id', $collection->id)
                ->delete();
        }

        return Response::json(['message' => 'Password has been reset.']);
    }

    public function forgotPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        if (! isset($collection->options['auth_methods']['standard']) || ! $collection->options['auth_methods']['standard']['enabled']) {
            return Response::json(['message' => 'Collection is not setup for standard auth method.'], 400);
        }

        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        $filterString = "email = '{$email}'";
        $record = $collection->records()->filterFromString($filterString)->first();

        if (! $record) {
            return Response::json([
                'message' => 'If an account exists with this email, you will receive a password reset token.',
            ]);
        }

        $token = \Str::random(64);
        $expires = (int) $collection->options['other']['tokens_options']['password_reset_duration']['value'] ?? 1800;

        AuthPasswordReset::create([
            'project_id' => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id' => $record->id,
            'email' => $email,
            'token' => $token,
            'created_at' => now(),
            'expires_at' => now()->addSeconds($expires),
            'ip_address' => $request->ip(),
            'device_name' => $request->input('device_name'),
        ]);

        // Mail::to($email)->send(new PasswordReset($token, $collection));

        return Response::json([
            'message' => 'If an account exists with this email, you will receive a password reset token.',
        ]);
    }

    public function confirmPasswordReset(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return Response::json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $request->validate([
            'token' => 'required|string',
            'password' => ['required', 'string', Password::min(8)],
            'password_confirmation' => 'required|same:password',
            'invalidate_sessions' => 'boolean',
        ]);

        $reset = AuthPasswordReset::where('token', $request->input('token'))
            ->where('collection_id', $collection->id)
            ->first();

        if (! $reset) {
            return Response::json(['message' => 'Invalid token.'], 400);
        }

        if ($reset->used_at) {
            return Response::json(['message' => 'Token already used.'], 400);
        }

        if ($reset->expires_at && $reset->expires_at->isPast()) {
            return Response::json(['message' => 'Token expired.'], 400);
        }

        $record = Record::find($reset->record_id);

        if (! $record) {
            return Response::json(['message' => 'User associated with this token no longer exists.'], 404);
        }

        $record->data->put('password', Hash::make($request->input('password')));
        $record->save();

        $reset->used_at = now();
        $reset->save();

        if ($request->boolean('invalidate_sessions')) {
            AuthSession::where('record_id', $record->id)
                ->where('collection_id', $collection->id)
                ->delete();
        }

        return Response::json(['message' => 'Password reset successful.']);
    }
}
