<?php

namespace App\Controllers;

use App\Generator\{AddonFiles, Dockerfile, HAconfig, HArepository, Metadata};
use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};

class GenerateController
{

    public function generate(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        try {
            $addonFiles = new AddonFiles($data);
            $result = $addonFiles->create();
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $exception) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => $exception->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);

        }
    }

}
