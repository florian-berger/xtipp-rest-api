<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Season\Season;
use FlorianBerger\RestApi\Services\AccessRules;
use Illuminate\Http\JsonResponse;

/**
 * Base of the API controllers
 */
abstract class ApiController extends Controller {
    /**
     * Stop with a JSON error, for example "not found"
     *
     * @param int    $status
     * @param string $message
     *
     * @return never
     */
    protected function fail(int $status, string $message): never {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => $message], $status));
    }

    /**
     * Rules of the administrator (available leagues, visible pages)
     *
     * @return AccessRules
     */
    protected function rules(): AccessRules {
        return app(AccessRules::class);
    }

    /**
     * A league, only if the administrator made it available (otherwise "not found", like an unknown league)
     *
     * @param int $id
     *
     * @return League
     */
    protected function league(int $id): League {
        $league = $this->rules()->isLeagueAvailable($id) ? League::find($id) : null;

        return $league ?? $this->fail(404, 'League not found.');
    }

    /**
     * A season, only if its league is available (otherwise "not found", like an unknown season)
     *
     * @param int $id
     *
     * @return Season
     */
    protected function season(int $id): Season {
        $season = Season::find($id);
        if ($season === null || !$this->rules()->isLeagueAvailable((int) $season->league_id)) {
            $this->fail(404, 'Season not found.');
        }

        return $season;
    }

    /**
     * @param mixed $data
     *
     * @return JsonResponse
     */
    protected function data(mixed $data): JsonResponse {
        return response()->json(['data' => $data]);
    }
}
