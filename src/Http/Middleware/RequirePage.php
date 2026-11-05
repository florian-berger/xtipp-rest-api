<?php
namespace FlorianBerger\RestApi\Http\Middleware;

use Closure;
use FlorianBerger\RestApi\Services\AccessRules;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An endpoint is only available, when the page of the website with the same content is visible for the user (the
 * administrator can disable pages, e.g. the tip overview or the all-time ranking). The middleware "CheckPageVisibility"
 * of the website does not run for the API, so this replaces it.
 *
 * Usage: "rest-api.page:tip-games,tip-overview" - at least one of the pages must be visible. Must run after
 * "rest-api.auth", because the visibility depends on the user.
 */
class RequirePage {
    /**
     * @param AccessRules $rules
     */
    public function __construct(private readonly AccessRules $rules) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @param string  ...$pages Internal types of the pages
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$pages): Response {
        foreach ($pages as $page) {
            if ($this->rules->isPageVisible($page)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'This function is disabled on this website.'], 403);
    }
}
