<?php
namespace FlorianBerger\RestApi\Services;

use App\Plugin\PluginManager;

/**
 * Settings of the plugin, which the administrator changes in the admin center (defined in "settings" of plugin.json)
 */
class PluginSettings {
    /** Name of the plugin, as in plugin.json */
    private const PLUGIN = 'rest-api';

    /** Used, when the setting has no valid value */
    public const DEFAULT_RATE_LIMIT = 60;

    /** Bounds of the rate limit, the same as "min" and "max" in plugin.json */
    private const MIN_RATE_LIMIT = 1;
    private const MAX_RATE_LIMIT = 10000;

    /**
     * @param PluginManager $plugins
     */
    public function __construct(private readonly PluginManager $plugins) {
    }

    /**
     * Calls per key and minute
     *
     * @return int
     */
    public function rateLimit(): int {
        $value = $this->plugins->setting(self::PLUGIN, 'rate_limit', self::DEFAULT_RATE_LIMIT);
        if (!is_numeric($value)) {
            return self::DEFAULT_RATE_LIMIT;
        }

        return max(self::MIN_RATE_LIMIT, min(self::MAX_RATE_LIMIT, (int) $value));
    }
}
