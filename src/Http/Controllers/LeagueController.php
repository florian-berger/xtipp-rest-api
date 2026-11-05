<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Models\League;
use FlorianBerger\RestApi\Services\Presenter;
use Illuminate\Http\JsonResponse;

/**
 * Leagues and their seasons
 */
class LeagueController extends ApiController {
    /**
     * @param Presenter $presenter
     */
    public function __construct(private readonly Presenter $presenter) {
    }

    /**
     * All leagues which the administrator made available
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse {
        return $this->data(
            League::whereIn('id', $this->rules()->availableLeagueIds())
                ->orderBy('name')
                ->get()
                ->map(fn (League $league) => $this->presenter->league($league))
                ->all()
        );
    }

    /**
     * The seasons of a league, the newest first
     *
     * @param int $league
     *
     * @return JsonResponse
     */
    public function seasons(int $league): JsonResponse {
        return $this->data($this->league($league)->seasons->map(fn ($season) => $this->presenter->season($season))->all());
    }
}
