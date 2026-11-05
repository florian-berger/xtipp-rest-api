<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use FlorianBerger\RestApi\Services\ApiKeyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * The page where a user manages the own API key (create, regenerate, revoke, delete)
 */
class ApiKeyPageController extends Controller {
    /**
     * @param ApiKeyService $keys
     */
    public function __construct(private readonly ApiKeyService $keys) {
    }

    /**
     * Show the key of the user (never the secret, it is only shown in the answer of create/regenerate)
     *
     * @return View
     */
    public function index(): View {
        return view('rest-api::keys', [
            'apiKey' => $this->keys->findForUser(Auth::user()),
            'newKey' => null,
        ]);
    }

    /**
     * Create the key, a user has only one
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function create(Request $request): RedirectResponse|Response {
        $expiresAt = $this->expiryFromRequest($request);

        $key = $this->keys->create(Auth::user(), $expiresAt);
        if ($key === null) {
            return $this->back('message-danger', trans('rest-api::ui.message.exists'));
        }

        return $this->showNewKey(trans('rest-api::ui.message.created'), $key);
    }

    /**
     * Replace the secret of the key, the old one stops working at once
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function regenerate(Request $request): RedirectResponse|Response {
        $expiresAt = $this->expiryFromRequest($request);

        $key = $this->keys->regenerate(Auth::user(), $expiresAt);
        if ($key === null) {
            return $this->back('message-danger', trans('rest-api::ui.message.missing'));
        }

        return $this->showNewKey(trans('rest-api::ui.message.regenerated'), $key);
    }

    /**
     * Revoke the key
     *
     * @return RedirectResponse
     */
    public function revoke(): RedirectResponse {
        if (!$this->keys->revoke(Auth::user())) {
            return $this->back('message-danger', trans('rest-api::ui.message.missing'));
        }

        return $this->back('message-success', trans('rest-api::ui.message.revoked'));
    }

    /**
     * Delete the key
     *
     * @return RedirectResponse
     */
    public function delete(): RedirectResponse {
        if (!$this->keys->delete(Auth::user())) {
            return $this->back('message-danger', trans('rest-api::ui.message.missing'));
        }

        return $this->back('message-success', trans('rest-api::ui.message.deleted'));
    }

    /**
     * Read the optional expiry date, which is at the end of the chosen day
     *
     * @param Request $request
     *
     * @return Carbon|null
     */
    private function expiryFromRequest(Request $request): ?Carbon {
        $data = $request->validate([
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [], ['expires_at' => trans('rest-api::ui.keys.expires')]);

        return !empty($data['expires_at']) ? Carbon::createFromFormat('Y-m-d', $data['expires_at'])->endOfDay() : null;
    }

    /**
     * Back to the page with a message (never with a key)
     *
     * @param string $type
     * @param string $message
     *
     * @return RedirectResponse
     */
    private function back(string $type, string $message): RedirectResponse {
        return redirect()->route('plugin-rest-api.keys')->with($type, $message);
    }

    /**
     * Show the page with the new key directly in the answer of the POST request. The key is never put into the session
     * (the session is stored unencrypted, e.g. as file), and the answer must not be cached by the browser or a proxy.
     *
     * @param string $message
     * @param string $newKey
     *
     * @return Response
     */
    private function showNewKey(string $message, string $newKey): Response {
        // Only for this answer, it is removed before the session is stored
        session()->now('message-success', $message);

        return response()
            ->view('rest-api::keys', [
                'apiKey' => $this->keys->findForUser(Auth::user()),
                'newKey' => $newKey,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
