<?php
namespace FlorianBerger\RestApi\Http\Middleware;

use Closure;
use FlorianBerger\RestApi\Services\PluginSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject calls over plain HTTP, before the API key is processed (setting "require_https", enabled by default).
 *
 * isSecure() checks the server variable "HTTPS" and, since xTIPP 1.0.36, also "X-Forwarded-Proto" of the proxies in
 * TRUSTED_PROXIES (".env", global middleware TrustProxies of xTIPP, which runs before this one). Behind a proxy that
 * terminates TLS, the proxy is listed there, or the web server passes "HTTPS=on" to PHP. The core depends on the same
 * check: its middleware CheckHttpsProtocol redirects with it, when "app.url" uses https.
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
