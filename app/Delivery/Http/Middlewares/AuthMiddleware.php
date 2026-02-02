<?php

namespace App\Delivery\Http\Middlewares;

use App\Delivery\Entity\SafeCollection;
use App\Domain\Auth\Models\AuthSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware
{
    private ?AuthSession $session = null;

    public function handle(Request $request, \Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            $this->handleGuest($request);

            return $next($request);
        }

        $hash = hash('sha256', $token);

        $this->session = AuthSession::query()
            ->join('records', function ($join) {
                $join->on('records.id', '=', 'auth_sessions.record_id')
                    ->on('records.collection_id', '=', 'auth_sessions.collection_id');
            })
            ->where('auth_sessions.token_hash', $hash)
            ->where('auth_sessions.expires_at', '>', now())
            ->select([
                'auth_sessions.id',
                'auth_sessions.record_id',
                'auth_sessions.collection_id',
                'auth_sessions.project_id',
                'auth_sessions.last_used_at',
                'auth_sessions.expires_at',
                'records.data AS user',
            ])
            ->first();

        if (! $this->session) {
            $this->handleGuest($request);

            return $next($request);
        }

        $recordData = json_decode($this->session->user, true);

        $request->setUserResolver(fn () => new SafeCollection([
            ...$recordData,
            'meta' => [
                '_id'           => $this->session->record_id,
                'collection_id' => $this->session->collection_id,
                'project_id'    => $this->session->project_id,
            ],
        ]));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->session) {
            return;
        }

        $threshold = config('velo.session_defer_threshold') ?? 150;

        if ($this->session->last_used_at->diffInSeconds(now()) > $threshold) {
            $sliding = config('velo.session_sliding_expiration') ?? 0;

            $newLastUsed = now();
            $newExpires = now()->addSeconds($sliding);

            $this->session->update([
                'last_used_at' => $newLastUsed,
                'expires_at'   => $newExpires,
            ]);

            $this->session->last_used_at = $newLastUsed;
            $this->session->expires_at = $newExpires;
        }
    }

    private function handleGuest($request): void
    {
        $request->setUserResolver(fn () => null);
    }
}
