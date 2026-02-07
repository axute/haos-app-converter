<?php

namespace App;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class View
{
    private Environment $twig;
    private string $basePath;

    public function __construct()
    {
        $loader = new FilesystemLoader(__DIR__ . '/../templates');
        $this->twig = new Environment($loader, [
            'cache' => false, // Für Entwicklung deaktiviert, könnte in prod aktiviert werden
            'debug' => true,
        ]);

        $this->basePath = rtrim(str_replace('/index.php', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

        // Support for Home Assistant Ingress or other Reverse Proxies
        if (isset($_SERVER['HTTP_X_INGRESS_PATH'])) {
            $this->basePath = rtrim($_SERVER['HTTP_X_INGRESS_PATH'], '/');
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PREFIX'])) {
            $this->basePath = rtrim($_SERVER['HTTP_X_FORWARDED_PREFIX'], '/');
        }
        
        $this->twig->addGlobal('basePath', $this->basePath);
    }

    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }
}
