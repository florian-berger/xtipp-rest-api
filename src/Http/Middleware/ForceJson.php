<?php
namespace FlorianBerger\RestApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API answers only with JSON, also for errors (not found, validation, ...) and without an "Accept" header
 */
class ForceJson {
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
