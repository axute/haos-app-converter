<?php

namespace App\Controllers;

use App\App\FilesReader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FragmentController extends ControllerAbstract
{

    public static function appList(Request $request, Response $response): Response
    {
        $appController = new AppController();
        $tempResponse = new \Slim\Psr7\Response();
        $listResponse = $appController->list($request, $tempResponse);
        $data = json_decode((string)$listResponse->getBody(), true);

        return self::render($response, 'fragments/app-list', [
            'apps'       => $data['apps'] ?? [],
            'repository' => $data['repository'] ?? null
        ]);
    }

    public static function appDetails(Request $request, Response $response, string $slug): Response
    {
        $details = (new FilesReader($slug))->jsonSerialize();
        return self::render($response, 'fragments/app-details', [
            'app'  => $details,
            'slug' => $slug
        ]);
    }

    public static function checkUpdate(Request $request, Response $response, string $slug): Response
    {
        $force = $request->getQueryParams()['force'] ?? null;

        $appController = new AppController();
        $tempResponse = new \Slim\Psr7\Response();

        // Query Parameter an den AppController weiterreichen
        if ($force) {
            $request = $request->withQueryParams(['force' => $force]);
        }

        $updateResponse = $appController->checkImageUpdate(request: $request, response: $tempResponse, slug: $slug);
        $updateData = json_decode((string)$updateResponse->getBody(), true);

        return self::render($response, 'fragments/update-status', [
            'update' => $updateData,
            'slug'   => $slug
        ]);
    }
}
