<?php
namespace FlorianBerger\RestApi\Http\Middleware;

use Closure;
use FlorianBerger\RestApi\Services\PluginSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject calls over plain HTTP, before the API key is processed (setting "require_https", enabled by default).
 *
 * xTIPP does not register TrustProxies, so isSecure() ignores "X-Forwarded-Proto" and only checks the server variable
 * "HTTPS". Behind a proxy that terminates TLS, the web server must pass "HTTPS=on" to PHP. The core depends on this as
 * well: its middleware CheckHttpsProtocol redirects with the same check, when "app.url" uses https.
 */
class RequireHttps {
    /**
     * @param PluginSettings $settings
     */
    public function __construct(private readonly PluginSettings $settings) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        if (!$request->isSecure() && $this->settings->requireHttps()) {
            return response()->json(['message' => 'The API is only available over HTTPS.'], 403);
        }

        return $next($request);
    }
}
