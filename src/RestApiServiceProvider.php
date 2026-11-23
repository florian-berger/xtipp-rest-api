<?php
namespace FlorianBerger\RestApi;

use App\Plugin\PluginExtensions;
use FlorianBerger\RestApi\Http\Middleware\AuthenticateApiKey;
use FlorianBerger\RestApi\Http\Middleware\ForceJson;
use FlorianBerger\RestApi\Http\Middleware\RequireApiAccess;
use FlorianBerger\RestApi\Http\Middleware\RequireHttps;
use FlorianBerger\RestApi\Http\Middleware\RequirePage;
use FlorianBerger\RestApi\Http\Middleware\WithoutCookies;
use FlorianBerger\RestApi\Services\ApiAccess;
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
        $router->aliasMiddleware('rest-api.no-cookies', WithoutCookies::class);
        $router->aliasMiddleware('rest-api.json', ForceJson::class);
        $router->aliasMiddleware('rest-api.https', RequireHttps::class);
        $router->aliasMiddleware('rest-api.auth', AuthenticateApiKey::class);
        $router->aliasMiddleware('rest-api.page', RequirePage::class);
        $router->aliasMiddleware('rest-api.access', RequireApiAccess::class);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'rest-api');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'rest-api');

        // Entry of the key page in the profile menu of logged-in users with access to the API (since xTIPP 1.0.36).
        // Label and visibility are closures, so they are checked only when the menu is shown. Users without access
        // can still open the page by its address, to revoke or delete an existing key.
        $this->app->make(PluginExtensions::class)->menu('profile', [
            'label'   => fn () => trans('rest-api::ui.keys.sidename'),
            'route'   => 'plugin-rest-api.keys',
            'visible' => fn ($user) => $this->app->make(ApiAccess::class)->allows($user),
        ]);

        // Own tables: The migrations in "database/migrations" run when the plugin is enabled
    }
}
