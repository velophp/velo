<?php

namespace App\Delivery\Http\Controllers;

use App\Domain\Auth\Enums\OtpType;
use App\Domain\Auth\Models\AuthOtp;
use App\Domain\Auth\Models\AuthSession;
use App\Domain\Auth\Models\Mail\LoginAlert;
use App\Domain\Auth\Models\Mail\Otp;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Project\Exceptions\InvalidRuleException;
use App\Domain\Record\Authorization\RuleContext;
use App\Domain\Record\Models\Record;
use App\Domain\Record\Resources\RecordResource;
use App\Domain\Record\Services\RecordQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Mail;

class AuthController extends Controller
{
    /**
     * @throws InvalidRuleException
     */
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
            'password'   => 'required|string',
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

        $authenticateRule = $collection->api_rules['authenticate'] ?? '';
        if ($authenticateRule !== '') {
            $context = RuleContext::fromRequest($request, $record->data->toArray());

            $rule = app(\App\Delivery\Services\EvaluateRuleExpression::class)
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
            'project_id'    => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed,
            'expires_at'    => now()->addSeconds($authTokenExpires),
            'last_used_at'  => now(),
            'device_name'   => $request->input('device_name'),
            'ip_address'    => $request->ip(),
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

        // Hook: auth.login
        \App\Domain\Hooks\Facades\Hooks::trigger('auth.login', [
            'collection' => $collection,
            'record'     => $record->data->toArray(),
            'record_id'  => $record->id,
            'token'      => $token,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Authenticated.',
            'data'    => $token,
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

        // Hook: auth.logout
        \App\Domain\Hooks\Facades\Hooks::trigger('auth.logout', [
            'collection' => $collection,
            'record_id'  => $session->meta->_id,
            'token'      => $token,
        ]);

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

        // Hook: auth.logout
        \App\Domain\Hooks\Facades\Hooks::trigger('auth.logout', [
            'collection'   => $collection,
            'record_id'    => $session->meta->_id,
            'all_sessions' => true,
        ]);

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
            'password'            => 'required|string',
            'new_password'        => ['required', 'string', Password::min(8)],
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

        // Hook: auth.password_reset
        \App\Domain\Hooks\Facades\Hooks::trigger('auth.password_reset', [
            'collection' => $collection,
            'record'     => $record->data->toArray(),
            'record_id'  => $record->id,
            'by_admin'   => false,
        ]);

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
            'project_id'    => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed,
            'action'        => OtpType::PASSWORD_RESET,
            'expires_at'    => now()->addSeconds($expires),
            'ip_address'    => $request->ip(),
            'device_name'   => $request->input('device_name'),
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
            'otp'                 => 'required|string',
            'new_password'        => ['required', 'string', Password::min(8), 'confirmed'],
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
            dump('Reset record_id: ' . $reset->record_id);
            dump('Reset content:', $reset->toArray());

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

        // Hook: auth.password_reset
        \App\Domain\Hooks\Facades\Hooks::trigger('auth.password_reset', [
            'collection' => $collection,
            'record'     => $record->data->toArray(),
            'record_id'  => $record->id,
            'by_admin'   => false,
            'via_otp'    => true,
        ]);

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
            'project_id'    => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed,
            'action'        => OtpType::AUTHENTICATION,
            'expires_at'    => $expiresAt,
            'ip_address'    => $request->ip(),
            'device_name'   => $request->input('device_name'),
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
            'email'       => 'required|email',
            'otp'         => 'required|string',
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
            'project_id'    => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed,
            'expires_at'    => now()->addSeconds($authTokenExpires),
            'last_used_at'  => now(),
            'device_name'   => $request->input('device_name'),
            'ip_address'    => $request->ip(),
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
            'data'    => $token,
        ]);
    }

    public function requestUpdateEmail(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $request->validate([
            'id'    => 'required_without:email',
            'email' => 'required_without:id|email',
        ]);

        $id = $request->input('id');
        $email = $request->input('email');
        $record = $collection->records()->filter('email', '=', $email)->orFilter('id', '=', $id)->first();

        if (! $record) {
            return response()->json(['message' => 'If an account exists with associated details, you will receive a authorization code.']);
        }

        $otpLength = (int) ($collection->options['auth_methods']['otp']['config']['generate_password_length'] ?? 6);
        [$otp, $hashed] = app(OtpService::class)->generate($otpLength);

        $duration = (int) $collection->options['other']['tokens_options']['email_change_duration']['value'] ?? 1800;
        $expiresAt = now()->addSeconds($duration);

        AuthOtp::create([
            'project_id'    => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed,
            'action'        => OtpType::EMAIL_CHANGE,
            'expires_at'    => $expiresAt,
            'ip_address'    => $request->ip(),
            'device_name'   => $request->input('device_name'),
        ]);

        Mail::to($email)->queue(new Otp($otp, $duration, $collection, $collection->project->name));

        return response()->json(['message' => 'If an account exists with this email, you will receive a authorization code.']);
    }

    public function confirmUpdateEmail(Request $request, Collection $collection)
    {
        if ($collection->type !== CollectionType::Auth) {
            return response()->json(['message' => 'Collection is not auth enabled.'], 400);
        }

        $request->validate([
            'otp'       => 'required|string',
            'new_email' => ['required', 'email'],
        ]);

        $reset = AuthOtp::where('collection_id', $collection->id)
            ->where('action', OtpType::EMAIL_CHANGE)
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

        $record->data->put('email', $request->input('new_email'));
        $record->save();

        $reset->used_at = now();
        $reset->save();

        return response()->json(['message' => 'Email updated successfully.']);
    }
}
