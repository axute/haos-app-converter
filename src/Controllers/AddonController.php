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
                    $hasLocalIcon = file_exists($dir . '/icon.png');
                    
                    $image = '';
                    $dockerfile = $dir . '/Dockerfile';
                    if (file_exists($dockerfile)) {
                        $content = file_get_contents($dockerfile);
                        if (preg_match('/^FROM\s+(.+)$/m', $content, $matches)) {
                            $image = trim($matches[1]);
                        }
                    }

                    $addons[] = [
                        'slug' => $slug,
                        'name' => $config['name'] ?? $slug,
                        'version' => $config['version'] ?? 'unknown',
                        'description' => $config['description'] ?? '',
                        'image' => $image ?: ($config['image'] ?? ''),
                        'has_local_icon' => $hasLocalIcon
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
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Add-on not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $config = Yaml::parseFile($configFile);
        
        $longDescription = '';
        $readmeFile = $dataDir . '/' . $slug . '/README.md';
        if (file_exists($readmeFile)) {
            $longDescription = file_get_contents($readmeFile);
        }
        
        $hasLocalIcon = file_exists($dataDir . '/' . $slug . '/icon.png');
        $iconFileContent = '';
        if ($hasLocalIcon) {
            $type = pathinfo($dataDir . '/' . $slug . '/icon.png', PATHINFO_EXTENSION);
            $data = file_get_contents($dataDir . '/' . $slug . '/icon.png');
            $iconFileContent = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        $image = '';
        $image_tag = '';
        $dockerfile = $dataDir . '/' . $slug . '/Dockerfile';
        if (file_exists($dockerfile)) {
            $content = file_get_contents($dockerfile);
            if (preg_match('/^FROM\s+(.+)$/m', $content, $matches)) {
                $fullImage = trim($matches[1]);
                if (strpos($fullImage, ':') !== false) {
                    list($image, $image_tag) = explode(':', $fullImage, 2);
                } else {
                    $image = $fullImage;
                    $image_tag = 'latest';
                }
            }
        }

        $envVars = [];
        // Fixierte Variablen aus 'environment'
        if (isset($config['environment']) && is_array($config['environment'])) {
            foreach ($config['environment'] as $key => $value) {
                $envVars[] = ['key' => $key, 'value' => $value, 'editable' => false];
            }
        }
        // Editierbare Variablen aus 'options'/'schema'
        if (isset($config['options']) && is_array($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                // Nur hinzufügen, wenn noch nicht als fixierte Variable vorhanden (sollte eigentlich nicht passieren)
                $exists = false;
                foreach ($envVars as $ev) {
                    if ($ev['key'] === $key) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $envVars[] = ['key' => $key, 'value' => $value, 'editable' => true];
                }
            }
        }

        $ports = [];
        if (isset($config['ports']) && is_array($config['ports'])) {
            foreach ($config['ports'] as $containerPort => $hostPort) {
                // Wir nehmen an, dass es immer /tcp ist oder schneiden es einfach ab
                $container = (int)str_replace('/tcp', '', $containerPort);
                $ports[] = ['container' => $container, 'host' => (int)$hostPort];
            }
        }

        // Versuchen wir auch den Ingress Port zu finden, falls er nicht in der config steht (obwohl er dort stehen sollte)
        $data = [
            'name' => $config['name'] ?? '',
            'description' => $config['description'] ?? '',
            'image' => $image ?: ($config['image'] ?? ''),
            'image_tag' => $image_tag,
            'version' => $config['version'] ?? '',
            'ingress' => $config['ingress'] ?? false,
            'ingress_port' => $config['ingress_port'] ?? 80,
            'ingress_stream' => $config['ingress_stream'] ?? false,
            'panel_icon' => $config['panel_icon'] ?? 'mdi:link-variant',
            'webui' => $config['webui'] ?? '',
            'backup' => (isset($config['backup']) && $config['backup'] !== false),
            'has_local_icon' => $hasLocalIcon,
            'icon_file' => $iconFileContent,
            'ports' => $ports,
            'map' => $config['map'] ?? [],
            'env_vars' => $envVars
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getTags(Request $request, Response $response): Response
    {
        $imageName = 'axute/haos-addon-converter';
        $tags = ['latest'];

        try {
            $tokenUrl = "https://ghcr.io/token?scope=repository:$imageName:pull&service=ghcr.io";
            $tokenJson = @file_get_contents($tokenUrl);
            if ($tokenJson) {
                $tokenData = json_decode($tokenJson, true);
                $token = $tokenData['token'] ?? '';

                if ($token) {
                    $tagsUrl = "https://ghcr.io/v2/$imageName/tags/list";
                    $opts = [
                        'http' => [
                            'method' => 'GET',
                            'header' => "Authorization: Bearer $token\r\n"
                        ]
                    ];
                    $context = stream_context_create($opts);
                    $tagsJson = @file_get_contents($tagsUrl, false, $context);
                    if ($tagsJson) {
                        $tagsData = json_decode($tagsJson, true);
                        if (isset($tagsData['tags']) && is_array($tagsData['tags'])) {
                            $tags = $tagsData['tags'];
                            // Sort tags, latest should be first or handled specially
                            rsort($tags);
                            // Ensure 'latest' is in there if not present (though it should be)
                            if (!in_array('latest', $tags)) {
                                array_unshift($tags, 'latest');
                            } else {
                                // Move latest to the front
                                $tags = array_diff($tags, ['latest']);
                                array_unshift($tags, 'latest');
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback to ['latest']
        }

        $response->getBody()->write(json_encode($tags));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getImageTags(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $image = $queryParams['image'] ?? '';

        if (empty($image)) {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // crane ls verwenden
        $command = "crane ls " . escapeshellarg($image) . " 2>&1";
        $output = shell_exec($command);
        $tags = explode("\n", trim($output));
        $tags = array_filter($tags, function($tag) {
            return !empty($tag) && strpos($tag, "error") === false && strpos($tag, "standard_init_linux") === false;
        });

        if (empty($tags)) {
            $response->getBody()->write(json_encode(['latest']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Tags nach Version sortieren (neueste oben)
        usort($tags, function($a, $b) {
            if ($a === 'latest') return -1;
            if ($b === 'latest') return 1;
            
            // Handle versions like "1.2.3" vs "1.2"
            $a_v = preg_replace('/[^0-9.]/', '', $a);
            $b_v = preg_replace('/[^0-9.]/', '', $b);
            
            if ($a_v && $b_v && $a_v !== $b_v) {
                return version_compare($b_v, $a_v);
            }
            
            // Fallback: SHA-Tags ans Ende sortieren
            $a_is_sha = (strpos($a, 'sha256-') === 0);
            $b_is_sha = (strpos($b, 'sha256-') === 0);
            if ($a_is_sha && !$b_is_sha) return 1;
            if (!$a_is_sha && $b_is_sha) return -1;

            return strcasecmp($b, $a);
        });

        $response->getBody()->write(json_encode(array_values($tags)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function selfConvert(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $params = json_decode($body, true);
        $tag = $params['tag'] ?? 'latest';

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
            'image' => "ghcr.io/axute/haos-addon-converter:$tag",
            'description' => 'Web-Converter zum Konvertieren von Docker-Images in Home Assistant Add-ons.',
            'version' => $newVersion,
            'ingress' => true,
            'ingress_port' => 80,
            'panel_icon' => 'mdi:toy-brick',
            'backup' => true,
            'self_convert' => true,
            'map' => ['addons:rw'],
            'env_vars' => [
                [
                    'key' => 'CONVERTER_DATA_DIR',
                    'value' => '/addons'
                ]
            ]
        ];

        // Icon hinzufügen falls vorhanden
        $iconPath = __DIR__ . '/../../icon.png';
        if (file_exists($iconPath)) {
            $iconData = file_get_contents($iconPath);
            $data['icon_file'] = 'data:image/png;base64,' . base64_encode($iconData);
        }

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
        
        // System addon cannot be deleted
        if ($slug === 'haos_addon_converter') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'System add-on cannot be deleted']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $addonDir = $this->getDataDir() . '/' . $slug;

        if (!is_dir($addonDir)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Add-on not found']));
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
