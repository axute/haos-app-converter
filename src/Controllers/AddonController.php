<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Yaml\Yaml;

class AddonController
{
    private function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    public function list(Request $request, Response $response): Response
    {
        $dataDir = $this->getDataDir();
        $addons = [];

        if (is_dir($dataDir)) {
            $dirs = array_filter(glob($dataDir . '/*'), 'is_dir');
            foreach ($dirs as $dir) {
                $slug = basename($dir);
                $configFile = $dir . '/config.yaml';
                if (file_exists($configFile)) {
                    $config = Yaml::parseFile($configFile);
                    $addons[] = [
                        'slug' => $slug,
                        'name' => $config['name'] ?? $slug,
                        'version' => $config['version'] ?? 'unknown',
                        'icon' => $config['icon'] ?? ''
                    ];
                }
            }
        }

        // Alphabetisch sortieren nach Name
        usort($addons, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $response->getBody()->write(json_encode($addons));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $dataDir = $this->getDataDir();
        $configFile = $dataDir . '/' . $slug . '/config.yaml';

        if (!file_exists($configFile)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Add-on nicht gefunden']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $config = Yaml::parseFile($configFile);
        
        $envVars = [];
        // Fixierte Variablen aus 'environment'
        if (isset($config['environment']) && is_array($config['environment'])) {
            foreach ($config['environment'] as $key => $value) {
                $envVars[] = ['key' => $key, 'value' => $value, 'userEditable' => false];
            }
        }
        // Änderbare Variablen aus 'options'
        if (isset($config['options']) && is_array($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                // Wir prüfen nicht extra schema, da wir davon ausgehen dass es zusammengehört
                $envVars[] = ['key' => $key, 'value' => $value, 'userEditable' => true];
            }
        }

        // Versuchen wir auch den Ingress Port zu finden, falls er nicht in der config steht (obwohl er dort stehen sollte)
        $data = [
            'name' => $config['name'] ?? '',
            'description' => $config['description'] ?? '',
            'icon' => $config['icon'] ?? '',
            'image' => $config['image'] ?? '',
            'version' => $config['version'] ?? '',
            'ingress' => $config['ingress'] ?? false,
            'ingress_port' => $config['ingress_port'] ?? 80,
            'backup' => $config['backup'] ?? false,
            'env_vars' => $envVars
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function selfConvert(Request $request, Response $response): Response
    {
        $slug = 'haos_addon_converter';
        $configFile = $this->getDataDir() . '/' . $slug . '/config.yaml';
        $currentVersion = '1.0.0';

        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
            $currentVersion = $config['version'] ?? '1.0.0';
        }

        // Version hochzählen
        $parts = explode('.', $currentVersion);
        if (count($parts) === 3) {
            $parts[2]++;
            $newVersion = implode('.', $parts);
        } else {
            $newVersion = $currentVersion . '.1';
        }

        // Daten für die Generierung vorbereiten
        $data = [
            'name' => 'HAOS Add-on Converter',
            'image' => 'php:8.3-apache',
            'description' => 'Web-Wizard zum Konvertieren von Docker-Images in Home Assistant Add-ons.',
            'version' => $newVersion,
            'ingress' => true,
            'ingress_port' => 80,
            'backup' => true,
            'self_convert' => true,
            'env_vars' => [
                [
                    'key' => 'CONVERTER_DATA_DIR',
                    'value' => '/data',
                    'userEditable' => false
                ]
            ]
        ];

        // Wir rufen intern die Generate-Logik auf (oder duplizieren sie hier, aber ein interner Request wäre sauberer)
        // Da wir in Slim sind, können wir den GenerateController direkt instanziieren.
        $generateController = new GenerateController();
        
        // Wir müssen einen neuen Request mit dem Body erstellen
        $factory = new \Slim\Psr7\Factory\ServerRequestFactory();
        $genRequest = $factory->createServerRequest('POST', '/generate');
        $genRequest->getBody()->write(json_encode($data));
        
        return $generateController->generate($genRequest, $response);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        
        // System-Addon darf nicht gelöscht werden
        if ($slug === 'haos_addon_converter') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'System-Add-on kann nicht gelöscht werden']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $addonDir = $this->getDataDir() . '/' . $slug;

        if (!is_dir($addonDir)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Add-on nicht gefunden']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verzeichnis rekursiv löschen
        $this->recursiveRmdir($addonDir);

        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function recursiveRmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                        $this->recursiveRmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
