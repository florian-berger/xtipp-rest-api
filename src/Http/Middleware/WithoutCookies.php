<?php
namespace FlorianBerger\RestApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API is stateless and answers without any cookie.
 *
 * xTIPP sets the cookie "xtipp-current-league-id" for every guest with the native setcookie() in
 * AppServiceProvider::boot() (when "xtipp.available-leagues" is configured). That runs before any middleware and before
 * the API key is checked, so every API call is a guest at that time. Native headers are only queued by PHP until the
 * response is sent, so they can still be removed here. Cookies of the Laravel response are removed as well, so that
 * nothing (core or other plugins) can add one.
 */
class WithoutCookies {
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        if (!headers_sent()) {
            header_remove('Set-Cookie');
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        return $response;
    }
}
