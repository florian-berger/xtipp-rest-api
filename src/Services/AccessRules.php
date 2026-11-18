<?php
namespace FlorianBerger\RestApi\Services;

use App\Helper\PageHelper;

/**
 * Rules of the administrator, which the website enforces with its middleware and which the API must respect, too:
 * the available leagues and the visibility of the pages
 */
class AccessRules {
    /**
     * Ids of the leagues which the administrator made available ("available-leagues"). An empty setting means that no
     * league is available (the website offers no league then, too).
     *
     * @return int[]
     */
    public function availableLeagueIds(): array {
        $config = config('xtipp.available-leagues', '');
        $parts  = is_array($config) ? $config : explode(',', (string) $config);

        return array_values(array_unique(array_filter(
            array_map(fn ($id) => (int) trim((string) $id), $parts),
            fn (int $id) => $id > 0
        )));
    }

    /**
     * @param int $leagueId
     *
     * @return bool
     */
    public function isLeagueAvailable(int $leagueId): bool {
        return in_array($leagueId, $this->availableLeagueIds(), true);
    }

    /**
     * Check, if a page of the website (internal type, see PageHelper::defaultPageSettings()) is visible for the current
     * user.
     *
     * Only the page itself counts, not its parent: since xTIPP 1.0.36 the website checks the most specific page of an
     * address (e.g. "/rangliste/ewige-rangliste" and not "/rangliste"), see CheckPageVisibility.
     *
     * @param string $internalType
     *
     * @return bool
     */
    public function isPageVisible(string $internalType): bool {
        return PageHelper::isPageVisible($internalType);
    }
}
