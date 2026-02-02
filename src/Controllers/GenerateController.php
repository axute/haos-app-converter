<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Yaml\Yaml;

class GenerateController
{
    private function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    public function generate(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);
        
        $addonName = $data['name'] ?? '';
        $image = $data['image'] ?? '';
        $description = $data['description'] ?? 'Converted HA Add-on';
        $iconFile = $data['icon_file'] ?? ''; // Base64 encoded icon file data
        $version = $data['version'] ?? '1.0.0';
        $ingress = $data['ingress'] ?? false;
        $ingressPort = $data['ingress_port'] ?? 80;
        $ingressStream = $data['ingress_stream'] ?? false;
        $webuiPort = $data['webui_port'] ?? null;
        $panelIcon = $data['panel_icon'] ?? 'mdi:link-variant';
        $backup = $data['backup'] ?? false;
        $ports = $data['ports'] ?? [];
        $map = $data['map'] ?? [];
        $envVars = $data['env_vars'] ?? [];
        $isSelfConvert = $data['self_convert'] ?? false;
        
        if (empty($addonName) || empty($image)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Name und Image sind erforderlich']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $slug = strtolower($addonName);
        $slug = str_replace([' ', '-', '.'], '_', $slug);
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        
        // Fix für HAOS Add-on Converter Slug (manchmal wird er zu dd_on_onverter)
        if ($isSelfConvert) {
            $slug = 'haos_addon_converter';
        }

        $dataDir = str_replace('\\', '/', $this->getDataDir());
        $addonPath = $dataDir . '/' . $slug;
        
        if (!is_dir($addonPath)) {
            mkdir($addonPath, 0777, true);
        }
        
        // config.yaml erstellen
        $config = [
            'name' => $addonName,
            'version' => $version,
            'slug' => $slug,
            'description' => $description,
            'arch' => ['aarch64', 'amd64', 'armhf', 'armv7', 'i386'],
            'startup' => 'application',
            'boot' => 'auto',
            'options' => (object)[],
            'schema' => (object)[],
            // 'image' => $image // Removed because it's built locally from Dockerfile
        ];

        if ($ingress) {
            $config['ingress'] = true;
            $config['ingress_port'] = $ingressPort;
            if ($ingressStream) {
                $config['ingress_stream'] = true;
            }
            $config['panel_icon'] = $panelIcon;
        } elseif ($webuiPort) {
            $config['webui'] = "http://[HOST]:[PORT:$webuiPort]/";
        }

        if ($backup) {
            $config['backup'] = 'hot';
        }

        if (!empty($map)) {
            $config['map'] = $map;
        }

        // Ports verarbeiten
        if (!empty($ports)) {
            $configPorts = [];
            foreach ($ports as $p) {
                if (!empty($p['container']) && !empty($p['host'])) {
                    $configPorts[$p['container'] . '/tcp'] = (int)$p['host'];
                }
            }
            if (!empty($configPorts)) {
                $config['ports'] = $configPorts;
            }
        }

        // Umgebungsvariablen verarbeiten
        if (!empty($envVars)) {
            $environment = [];
            $options = [];
            $schema = [];

            foreach ($envVars as $var) {
                $key = $var['key'];
                $value = $var['value'];
                $userEditable = $var['userEditable'] ?? false;

                if ($userEditable) {
                    $options[$key] = $value;
                    $schema[$key] = 'str?'; // Standardmäßig als optionaler String
                } else {
                    $environment[$key] = $value;
                }
            }

            if (!empty($environment)) {
                $config['environment'] = $environment;
            }
            if (!empty($options)) {
                $config['options'] = $options;
            }
            if (!empty($schema)) {
                $config['schema'] = $schema;
            }
        }
        
        file_put_contents($addonPath . '/config.yaml', Yaml::dump($config, 4, 2));
        
        // Icon Datei speichern, falls vorhanden
        if (!empty($iconFile)) {
            // Wir erwarten ein Format wie "data:image/png;base64,..."
            if (preg_match('/^data:image\/(\w+);base64,/', $iconFile, $type)) {
                $iconData = substr($iconFile, strpos($iconFile, ',') + 1);
                $iconData = base64_decode($iconData);
                if ($iconData !== false) {
                    file_put_contents($addonPath . '/icon.png', $iconData);
                }
            }
        }
        
        // Dockerfile erstellen
        $dockerfileContent = "FROM $image\n";
        
        // Wenn es sich um eine Selbst-Konvertierung handelt, müssen wir die Dateien kopieren
        if ($isSelfConvert) {
            $dockerfileContent = "FROM $image\n\n";
            $dockerfileContent .= "WORKDIR /var/www/html\n";
            $dockerfileContent .= "COPY . .\n";
            $dockerfileContent .= "RUN composer install --no-interaction --optimize-autoloader\n";
            $dockerfileContent .= "RUN mkdir -p data && chown -R www-data:www-data data\n";
        }
        
        file_put_contents($addonPath . '/Dockerfile', $dockerfileContent);
        
        // repository.yaml im Haupt-data-Verzeichnis erstellen/aktualisieren (falls nicht vorhanden oder Standardwerte)
        $this->ensureRepositoryYaml($dataDir);

        // Falls Selbst-Konvertierung, kopieren wir den aktuellen Code in das Zielverzeichnis
        if ($isSelfConvert) {
            $this->recursiveCopy(realpath(__DIR__ . '/../../'), $addonPath);
        }

        $result = [
            'status' => 'success',
            'path' => realpath($addonPath)
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function ensureRepositoryYaml($dataDir)
    {
        $repoFile = $dataDir . '/repository.yaml';
        if (!file_exists($repoFile)) {
            $repoConfig = [
                'name' => 'My HAOS Add-on Repository',
                'maintainer' => 'HAOS Add-on Converter'
            ];
            file_put_contents($repoFile, Yaml::dump($repoConfig, 4, 2));
        }
    }

    private function recursiveCopy($src, $dst)
    {
        $src = str_replace('\\', '/', $src);
        $dst = str_replace('\\', '/', $dst);
        
        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                
                if (is_dir($srcFile)) {
                    // Wir überspringen das data Verzeichnis und vendor (wird im Dockerfile installiert)
                    // Wichtig: Wir müssen den relativen Pfad prüfen oder den absoluten Pfad des data-Verzeichnisses
                    if ($file === 'data' || $file === 'vendor' || $file === '.git' || strpos($srcFile, 'data_self_test') !== false) {
                        continue;
                    }
                    $this->recursiveCopy($srcFile, $dstFile);
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }
}
