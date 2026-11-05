<?php
namespace FlorianBerger\RestApi\Services;

use App\Helper\PageHelper;
use App\Models\Page;
use Exception;

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
     * user. A page below a parent (e.g. "tip-overview" below "tip-parent") needs a visible parent, too.
     *
     * @param string $internalType
     *
     * @return bool
     */
    public function isPageVisible(string $internalType): bool {
        if (!PageHelper::isPageVisible($internalType)) {
            return false;
        }

        $parent = $this->parentType($internalType);

        return $parent === null || PageHelper::isPageVisible($parent);
    }

    /**
     * Internal type of the parent page, null for a page without parent
     *
     * @param string $internalType
     *
     * @return string|null
     */
    private function parentType(string $internalType): ?string {
        try {
            $page = Page::where('internal_type', $internalType)->first();
            if ($page !== null) {
                if ((int) $page->page_parent === 0) {
                    return null;
                }

                $parent = Page::whereKey($page->page_parent)->value('internal_type');

                return is_string($parent) && $parent !== '' ? $parent : null;
            }
        } catch (Exception) {
            // The table is not available, the default settings are used
        }

        $parent = PageHelper::defaultPageSettings()[$internalType]['page_parent'] ?? '';

        return $parent !== '' ? $parent : null;
    }
}
