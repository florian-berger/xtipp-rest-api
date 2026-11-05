<?php
namespace FlorianBerger\RestApi;

use FlorianBerger\RestApi\Http\Middleware\AuthenticateApiKey;
use FlorianBerger\RestApi\Http\Middleware\ForceJson;
use FlorianBerger\RestApi\Http\Middleware\RequirePage;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * REST-API for xTIPP, registered completely by the plugin
 */
class RestApiServiceProvider extends ServiceProvider {
    /**
     * Boot the plugin
     *
     * @return void
     */
    public function boot(): void {
        // The API has no session, so it does not use the middleware group "web". The aliases must exist before the
        // routes are loaded.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('rest-api.json', ForceJson::class);
        $router->aliasMiddleware('rest-api.auth', AuthenticateApiKey::class);
        $router->aliasMiddleware('rest-api.page', RequirePage::class);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'rest-api');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'rest-api');

        // Own tables: The migrations in "database/migrations" run when the plugin is enabled
    }
}
