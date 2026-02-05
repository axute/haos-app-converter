<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class IndexController
{
    private function render(Response $response, string $template, array $data = []): Response
    {
        // Base path detection (simple version for the controller)
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
        // If we are in an ingress context, the path might be deeper. 
        // But for the initial load, we can often rely on relative paths or JS detection.
        // We'll pass a basePath variable to the templates.
        $basePath = rtrim(str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']), '/');

        $data['basePath'] = $basePath;
        
        extract($data);
        
        // Render content
        ob_start();
        include __DIR__ . '/../../templates/' . $template . '.php';
        $content = ob_get_clean();
        
        // Render layout
        ob_start();
        include __DIR__ . '/../../templates/layout.php';
        $fullHtml = ob_get_clean();
        
        $response->getBody()->write($fullHtml);
        return $response;
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'index', [
            'title' => 'HA Add-on Converter'
        ]);
    }
}
