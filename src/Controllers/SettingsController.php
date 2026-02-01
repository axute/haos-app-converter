<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Yaml\Yaml;

class SettingsController
{
    private function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    public function get(Request $request, Response $response): Response
    {
        $dataDir = $this->getDataDir();
        $repoFile = $dataDir . '/repository.yaml';
        
        $settings = [
            'name' => 'My HAOS Add-on Repository',
            'maintainer' => 'HAOS Add-on Converter'
        ];

        if (file_exists($repoFile)) {
            $config = Yaml::parseFile($repoFile);
            $settings['name'] = $config['name'] ?? $settings['name'];
            $settings['maintainer'] = $config['maintainer'] ?? $settings['maintainer'];
        }

        $response->getBody()->write(json_encode($settings));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);
        
        $name = $data['name'] ?? 'My HAOS Add-on Repository';
        $maintainer = $data['maintainer'] ?? 'HAOS Add-on Converter';
        
        $dataDir = $this->getDataDir();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        
        $repoFile = $dataDir . '/repository.yaml';
        $repoConfig = [
            'name' => $name,
            'maintainer' => $maintainer
        ];
        
        file_put_contents($repoFile, Yaml::dump($repoConfig, 4, 2));
        
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
