<?php

namespace App\Http\Middleware;

use App\Models\AuthSession;
use App\Models\Record;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware
{
    /**
     * Inject user record for authentication.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $token = $this->extractToken($request);
        $hash = hash('sha256', $token);

        $session = AuthSession::where('token_hash', $hash)->where('expires_at', '>', now())->first();

        if (! $token || ! $session) {
            $request->attributes->set('auth', collect([
                'id' => null,
                'name' => null,
                'email' => null,
                'meta' => collect([
                    '_id' => null,
                    'collection_id' => null,
                    'project_id' => null,
                ]),
            ]));

            return $next($request);
        }

        $record = Record::find($session->record_id);

        if (! $record) {
            $session->delete();
            $request->attributes->set('auth', collect([
                'id' => null,
                'name' => null,
                'email' => null,
                'meta' => collect([
                    '_id' => null,
                    'collection_id' => null,
                    'project_id' => null,
                ]),
            ]));

            return $next($request);
        }

        $request->merge([
            'auth' => collect([
                ...$record->data->toArray(),
                'meta' => collect([
                    '_id' => $session->record_id,
                    'collection_id' => $session->collection_id,
                    'project_id' => $session->project_id,
                ]),
            ]),
        ]);

        $session->update(['last_used_at' => now()]);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->bearerToken();

        if ($token) {
            return $token;
        }

        $token = $request->input('token');

        if ($token) {
            return $token;
        }

        return null;
    }
}
