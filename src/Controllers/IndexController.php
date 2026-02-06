<?php

namespace App\Controllers;

use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class IndexController
{
    private function render(Response $response, string $template, array $data = []): Response
    {
        $view = new View();
        $html = $view->render($template . '.html.twig', $data);
        $response->getBody()->write($html);
        return $response;
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'index', [
            'title' => 'HA Add-on Converter'
        ]);
    }
}
