<?php
namespace FlorianBerger\RestApi\Http\Middleware;

use Closure;
use FlorianBerger\RestApi\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate a call with the API key as Bearer, only for this request (no session, no cookie)
 */
class AuthenticateApiKey {
    /** Failed attempts per IP and minute */
    private const MAX_FAILURES = 10;

    /** Calls per key and minute */
    private const MAX_CALLS = 60;

    /**
     * @param ApiKeyService $keys
     */
    public function __construct(private readonly ApiKeyService $keys) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        // Slow down guessing
        $failureKey = 'rest-api-failures:' . $request->ip();
        if (RateLimiter::tooManyAttempts($failureKey, self::MAX_FAILURES)) {
            return response()->json(['message' => 'Too many failed attempts.'], 429, [
                'Retry-After' => RateLimiter::availableIn($failureKey),
            ]);
        }

        // The same answer for every reason, so that nothing is revealed about keys
        $apiKey = null;
        $user   = null;
        $key    = $request->bearerToken();
        if (is_string($key) && $key !== '') {
            $apiKey = $this->keys->findByKey($key);
            if ($apiKey !== null && $apiKey->isActive()) {
                $user = $apiKey->user;
            }
        }

        // Inactive or deleted users can not use the API (the web middleware "IsActive" does not run here)
        if ($user === null || $user->trashed() || !$user->active) {
            RateLimiter::hit($failureKey, 60);

            return $this->unauthorized();
        }

        // Maintenance mode: like the web, only admins may continue
        if (app()->maintenanceMode()->active() && !$user->hasRole('Admin')) {
            return response()->json(['message' => 'The service is in maintenance mode.'], 503);
        }

        // Fixed limit per key, not configurable (done here, so it does not depend on the order of the middleware)
        $limitKey = 'rest-api-calls:' . $apiKey->id;
        if (RateLimiter::tooManyAttempts($limitKey, self::MAX_CALLS)) {
            return response()->json(['message' => 'Too many requests.'], 429, [
                'Retry-After' => RateLimiter::availableIn($limitKey),
            ]);
        }
        RateLimiter::hit($limitKey, 60);

        // Only for this request, nothing is stored in a session
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $this->keys->touch($apiKey);

        $response = $next($request);

        // Answers depend on the key and must not be stored by shared caches
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Vary', 'Authorization');

        return $response;
    }

    /**
     * @return JsonResponse
     */
    private function unauthorized(): JsonResponse {
        return response()->json(['message' => 'Unauthenticated.'], 401, [
            'WWW-Authenticate' => 'Bearer error="invalid_token"',
        ]);
    }
}
