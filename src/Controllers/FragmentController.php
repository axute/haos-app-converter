<?php

namespace App\Controllers;

use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Yaml\Yaml;

class FragmentController
{
    private function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    private function render(Response $response, string $template, array $data = []): Response
    {
        $view = new View();
        $html = $view->render('fragments/' . $template . '.html.twig', $data);
        $response->getBody()->write($html);
        return $response;
    }

    public function addonList(Request $request, Response $response): Response
    {
        $addonController = new AddonController();
        $tempResponse = new \Slim\Psr7\Response();
        $listResponse = $addonController->list($request, $tempResponse);
        $data = json_decode((string)$listResponse->getBody(), true);

        return $this->render($response, 'addon-list', [
            'addons' => $data['addons'] ?? [],
            'repository' => $data['repository'] ?? null
        ]);
    }

    public function addonDetails(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $addonController = new AddonController();
        // Hier rufen wir die get() Methode auf und parsen das JSON
        $tempResponse = new \Slim\Psr7\Response();
        $detailsResponse = $addonController->get($request, $tempResponse, ['slug' => $slug]);
        $details = json_decode((string)$detailsResponse->getBody(), true);

        return $this->render($response, 'addon-details', [
            'addon' => $details,
            'slug' => $slug
        ]);
    }
}
