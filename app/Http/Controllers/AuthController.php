<?php

namespace App\Http\Controllers;

use App\Enums\CollectionType;
use App\Enums\OtpType;
use App\Http\Resources\RecordResource;
use App\Mail\LoginAlert;
use App\Mail\Otp;
use App\Models\AuthOtp;
use App\Models\AuthSession;
use App\Models\Collection;
use App\Models\Record;
use App\Services\OtpService;
use App\Services\RecordQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Mail;

class AuthController extends Controller
{
    public function authenticateWithPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        if (! isset($collection->options['auth_methods']['standard'])) {
            return response()->json(['message' => 'Collection is not setup for standard auth method.'], 400);
        }

        if (! $collection->options['auth_methods']['standard']['enabled']) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $identifiers = $collection->options['auth_methods']['standard']['fields'];

        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $validFields = $collection->fields()->pluck('name')->toArray();
        $identifiers = array_filter($identifiers, fn ($field) => in_array($field, $validFields));

        if (empty($identifiers)) {
            return response()->json(['message' => 'Collection is not setup for standard auth method.'], 400);
        }

        $identifierValue = $request->input('identifier');
        $conditions = array_map(fn ($field) => ['field' => $field, 'value' => $identifierValue], $identifiers);
        $filterString = RecordQuery::buildFilterString($conditions, 'OR');
        $record = $collection->records()->filterFromString($filterString)->first();

        if (! $record) {
            throw ValidationException::withMessages(['identifier' => 'Invalid credentials.']);
        }

        if (! \Hash::check($request->input('password'), $record->data->password)) {
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

        $isNewIp = ! AuthSession::where('record_id', $record->id)
            ->where('collection_id', $collection->id)
            ->where('ip_address', $request->ip())
            ->exists();

        $authTokenExpires = (int) $collection->options['other']['tokens_options']['auth_duration']['value'] ?? 604800;
        AuthSession::create([
            'project_id' => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed,
            'expires_at' => now()->addSeconds($authTokenExpires),
            'last_used_at' => now(),
            'device_name' => $request->input('device_name'),
            'ip_address' => $request->ip(),
        ]);

        if ($isNewIp && isset($collection->options['mail_templates']['login_alert']['body']) && ! empty($collection->options['mail_templates']['login_alert']['body'])) {
            $email = $record->data->email;
            if ($email) {
                Mail::to($email)->queue(new LoginAlert(
                    $collection,
                    $record,
                    $request->input('device_name'),
                    $request->ip()
                ));
            }
        }

        return response()->json([
            'message' => 'Authenticated.',
            'data' => $token,
        ]);
    }

    public function me(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->user();
        if (! $session || ! $session->meta?->_id) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $record = Record::find($session->meta?->_id);
        if (! $record) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function logout(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->user();
        if (! $session || ! $session->meta?->_id) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $token = $request->bearerToken();
        $hashedToken = hash('sha256', $token);

        AuthSession::where('record_id', $session->meta->_id)
            ->where('collection_id', $collection->id)
            ->where('token_hash', $hashedToken)
            ->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->user();
        if (! $session || ! $session->meta?->_id) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        AuthSession::where('record_id', $session->meta->_id)
            ->where('collection_id', $collection->id)
            ->delete();

        return response()->json(['message' => 'Logged out from all sessions.']);
    }

    public function resetPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $session = $request->user();
        if (! $session || ! $session->meta?->_id) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $request->validate([
            'password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8)],
            'invalidate_sessions' => 'boolean',
        ]);

        $record = Record::find($session->meta->_id);
        if (! $record) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (! Hash::check($request->input('password'), $record->data->password)) {
            return response()->json(['message' => 'Invalid current password.'], 400);
        }

        $record->data->put('password', Hash::make($request->input('new_password')));
        $record->save();

        if ($request->input('invalidate_sessions')) {
            AuthSession::where('record_id', $session->meta->_id)
                ->where('collection_id', $collection->id)
                ->delete();
        }

        return response()->json(['message' => 'Password has been reset.']);
    }

    public function forgotPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        if (! isset($collection->options['auth_methods']['standard']) || ! $collection->options['auth_methods']['standard']['enabled']) {
            return response()->json(['message' => 'Collection is not setup for standard auth method.'], 400);
        }

        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        $filterString = "email = '{$email}'";
        $record = $collection->records()->filterFromString($filterString)->first();

        if (! $record) {
            return response()->json([
                'message' => 'If an account exists with this email, you will receive a password reset token.',
            ]);
        }

        $otpLength = (int) ($collection->options['auth_methods']['otp']['config']['generate_password_length'] ?? 6);
        [$otp, $hashed] = app(OtpService::class)->generate($otpLength);
        $expires = (int) $collection->options['other']['tokens_options']['password_reset_duration']['value'] ?? 1800;

        AuthOtp::create([
            'project_id' => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed,
            'action' => OtpType::PASSWORD_RESET,
            'created_at' => now(),
            'expires_at' => now()->addSeconds($expires),
            'ip_address' => $request->ip(),
            'device_name' => $request->input('device_name'),
        ]);

        Mail::to($email)->queue(new Otp($otp, $expires, $collection, config('app.name')));

        return response()->json([
            'message' => 'If an account exists with this email, you will receive a password reset token.',
        ]);
    }

    public function confirmForgotPassword(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $request->validate([
            'otp' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8), 'confirmed'],
            'invalidate_sessions' => 'boolean',
        ]);

        $reset = AuthOtp::where('collection_id', $collection->id)
            ->where('action', OtpType::PASSWORD_RESET)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('token_hash', hash('sha256', $request->input('otp')))
            ->first();

        if (! $reset) {
            return response()->json(['message' => 'Invalid code.'], 400);
        }

        $record = $reset->record;

        if (! $record) {
            return response()->json(['message' => 'User associated with this request no longer exists.'], 404);
        }

        $record->data->put('password', Hash::make($request->input('new_password')));
        $record->save();

        $reset->used_at = now();
        $reset->save();

        if ($request->boolean('invalidate_sessions')) {
            AuthSession::where('record_id', $record->id)
                ->where('collection_id', $collection->id)
                ->delete();
        }

        return response()->json(['message' => 'Password reset successful.']);
    }

    public function requestAuthOtp(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        if (! isset($collection->options['auth_methods']['otp']) || ! $collection->options['auth_methods']['otp']['enabled']) {
            return response()->json(['message' => 'OTP authentication is not enabled.'], 400);
        }

        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');
        $record = $collection->records()->filter('email', '=', $email)->first();

        if (! $record) {
            return response()->json(['message' => 'If an account exists with this email, you will receive a login code.']);
        }

        $otpLength = (int) ($collection->options['auth_methods']['otp']['config']['generate_password_length'] ?? 6);
        [$otp, $hashed] = app(OtpService::class)->generate($otpLength);

        $duration = (int) ($collection->options['auth_methods']['otp']['config']['duration_s'] ?? 180);
        $expiresAt = now()->addSeconds($duration);

        AuthOtp::create([
            'project_id' => $collection->project_id,
            'collection_id' => $collection->id,
            'otpable_type' => Record::class,
            'otpable_id' => $record->id,
            'token_hash' => $hashed,
            'action' => OtpType::AUTHENTICATION,
            'expires_at' => $expiresAt,
            'ip_address' => $request->ip(),
            'device_name' => $request->input('device_name'),
        ]);

        Mail::to($email)->queue(new Otp($otp, $duration, $collection, $collection->project->name));

        return response()->json(['message' => 'If an account exists with this email, you will receive a login code.']);
    }

    public function authenticateWithOtp(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $email = $request->input('email');
        $otp = $request->input('otp');

        $record = $collection->records()->filter('email', '=', $email)->first();

        if (! $record) {
            return response()->json(['message' => 'User with associated email not found.'], 404);
        }

        $authOtp = AuthOtp::where('collection_id', $collection->id)
            ->where('action', OtpType::AUTHENTICATION)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('token_hash', hash('sha256', $otp))
            ->first();

        if (! $authOtp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 400);
        }

        $authOtp->used_at = now();
        $authOtp->save();

        [$token, $hashed] = AuthSession::generateToken();

        $authTokenExpires = (int) ($collection->options['other']['tokens_options']['auth_duration']['value'] ?? 604800);

        $session = AuthSession::create([
            'project_id' => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed,
            'expires_at' => now()->addSeconds($authTokenExpires),
            'last_used_at' => now(),
            'device_name' => $request->input('device_name'),
            'ip_address' => $request->ip(),
        ]);

        $isNewIp = ! AuthSession::where('record_id', $record->id)
            ->where('collection_id', $collection->id)
            ->where('ip_address', $request->ip())
            ->where('id', '!=', $session->id)
            ->exists();

        if ($isNewIp && isset($collection->options['mail_templates']['login_alert']['body']) && ! empty($collection->options['mail_templates']['login_alert']['body'])) {
            if ($email) {
                Mail::to($email)->queue(new LoginAlert(
                    $collection,
                    $record,
                    $request->input('device_name'),
                    $request->ip()
                ));
            }
        }

        return response()->json([
            'message' => 'Authenticated.',
            'data' => $token,
        ]);
    }
}
