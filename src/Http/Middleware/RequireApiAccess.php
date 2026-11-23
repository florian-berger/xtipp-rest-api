<?php
namespace FlorianBerger\RestApi\Http\Middleware;

use Closure;
use FlorianBerger\RestApi\Services\ApiAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages of the website which only users with access to the API may open (Swagger UI, setting "allowed_users"). Like a
 * page hidden by the administrator (CheckPageVisibility), the answer is "not found".
 */
class RequireApiAccess {
    /**
     * @param ApiAccess $access
     */
    public function __construct(private readonly ApiAccess $access) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        if (!$this->access->allows($request->user())) {
            abort(404);
        }

        return $next($request);
    }
}
