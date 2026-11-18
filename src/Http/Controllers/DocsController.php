<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Http\Controllers\Controller;
use FlorianBerger\RestApi\Services\PluginSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Swagger UI with the description of the API, only for logged-in users
 */
class DocsController extends Controller {
    /**
     * Files which are delivered, with their path below "resources" and their content type. Nothing else is read, so
     * the name of a file in the address can not be used to read other files.
     *
     * xTIPP sends a Content-Security-Policy, which blocks inline scripts and event attributes. Every script of the
     * plugin is therefore a file delivered here.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const ASSETS = [
        'swagger-ui.css'       => ['swagger-ui/swagger-ui.css', 'text/css; charset=utf-8'],
        'swagger-ui-bundle.js' => ['swagger-ui/swagger-ui-bundle.js', 'application/javascript; charset=utf-8'],
        'docs.js'              => ['swagger-ui/docs.js', 'application/javascript; charset=utf-8'],
        'keys.js'              => ['js/keys.js', 'application/javascript; charset=utf-8'],
    ];

    /**
     * The page with Swagger UI
     *
     * @return View
     */
    public function index(): View {
        return view('rest-api::docs');
    }

    /**
     * The description of the API as OpenAPI document
     *
     * @param PluginSettings $settings
     *
     * @return JsonResponse
     */
    public function spec(PluginSettings $settings): JsonResponse {
        // The limit is set by the administrator, the document contains a placeholder
        $json = str_replace('{rate_limit}', (string) $settings->rateLimit(), (string) file_get_contents(__DIR__ . '/../../../resources/openapi/openapi.json'));
        $spec = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // The address of the installation, which can be in a subdirectory
        $spec['servers'] = [['url' => url('/api/v1')]];

        return response()->json($spec, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * CSS and JavaScript of Swagger UI and of the key page. The web root is "public", the files of a plugin are
     * outside, so they are delivered here.
     *
     * @param string $file
     *
     * @return BinaryFileResponse
     */
    public function asset(string $file): BinaryFileResponse {
        if (!array_key_exists($file, self::ASSETS)) {
            abort(404);
        }

        [$path, $contentType] = self::ASSETS[$file];

        return response()->file(__DIR__ . '/../../../resources/' . $path, [
            'Content-Type'           => $contentType,
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
