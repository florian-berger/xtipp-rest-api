<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Swagger UI with the description of the API, only for logged-in users
 */
class DocsController extends Controller {
    /**
     * Files of Swagger UI which are delivered, with their content type. Nothing else is read from the directory, so
     * the name of a file in the address can not be used to read other files.
     *
     * @var array<string, string>
     */
    public const ASSETS = [
        'swagger-ui.css'       => 'text/css; charset=utf-8',
        'swagger-ui-bundle.js' => 'application/javascript; charset=utf-8',
        'docs.js'              => 'application/javascript; charset=utf-8',
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
     * @return JsonResponse
     */
    public function spec(): JsonResponse {
        $spec = json_decode((string) file_get_contents(__DIR__ . '/../../../resources/openapi/openapi.json'), true, 512, JSON_THROW_ON_ERROR);

        // The address of the installation, which can be in a subdirectory
        $spec['servers'] = [['url' => url('/api/v1')]];

        return response()->json($spec, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * CSS and JavaScript of Swagger UI. The web root is "public", the files of a plugin are outside, so they are
     * delivered here.
     *
     * @param string $file
     *
     * @return BinaryFileResponse
     */
    public function asset(string $file): BinaryFileResponse {
        if (!array_key_exists($file, self::ASSETS)) {
            abort(404);
        }

        return response()->file(__DIR__ . '/../../../resources/swagger-ui/' . $file, [
            'Content-Type'           => self::ASSETS[$file],
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
