<?php
namespace FlorianBerger\RestApi\Services;

use App\Models\User;

/**
 * Which users may use the API (setting "allowed_users"): create and regenerate a key, call the API with it and open
 * Swagger UI. Revoking and deleting the own key stays possible for everyone, so that a user can always clean up.
 */
class ApiAccess {
    /**
     * Name of the role of the selected members, created by the migration of the plugin. The migration has its own copy
     * of the name (database/migrations/2026_11_23_create_plugin_rest_api_role.php), change both together.
     */
    public const ROLE = 'API-Benutzer';

    /** Name of the role of the administrators in xTIPP */
    private const ADMIN_ROLE = 'Admin';

    /**
     * @param PluginSettings $settings
     */
    public function __construct(private readonly PluginSettings $settings) {
    }

    /**
     * Check, if the user may use the API
     *
     * @param User|null $user
     *
     * @return bool
     */
    public function allows(?User $user): bool {
        if ($user === null) {
            return false;
        }

        return match ($this->settings->allowedUsers()) {
            PluginSettings::ALLOWED_ADMINS => $user->hasRole(self::ADMIN_ROLE),
            // Only the role counts, administrators need it, too
            PluginSettings::ALLOWED_ROLE   => $user->hasRole(self::ROLE),
            default                        => true,
        };
    }
}
