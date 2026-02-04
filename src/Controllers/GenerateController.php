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
        $image_tag = $data['image_tag'] ?? '';
        if (!empty($image_tag) && strpos($image, ':') === false) {
            $image .= ':' . $image_tag;
        }

        $description = $data['description'] ?? 'Converted HA Add-on';
        $longDescription = $data['long_description'] ?? '';
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
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Name and image are required']));
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
            // 'image' => $image // Removed because it's built locally from Dockerfile
        ];

        // Image Informationen via crane abrufen
        $imageConfig = $this->getImageConfig($image);
        $origEntrypoint = $imageConfig['config']['Entrypoint'] ?? null;
        $origCmd = $imageConfig['config']['Cmd'] ?? null;

        // Prüfen, ob editierbare Umgebungsvariablen vorhanden sind
        $hasEditableEnv = false;
        foreach ($envVars as $var) {
            if (!empty($var['editable'])) {
                $hasEditableEnv = true;
                break;
            }
        }

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
                if (!empty($var['key'])) {
                    $key = $var['key'];
                    $value = $var['value'] ?? '';
                    $editable = $var['editable'] ?? false;

                    if ($editable) {
                        $options[$key] = $value;
                        $schema[$key] = 'str?';
                    } else {
                        $environment[$key] = $value;
                    }
                }
            }

            if (!empty($environment)) {
                $config['environment'] = $environment;
            }
            if (!empty($options)) {
                $config['options'] = $options;
                $config['schema'] = $schema;
            }
        }
        
        file_put_contents($addonPath . '/config.yaml', Yaml::dump($config, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
        
        // README.md (long description) speichern
        if (!empty($longDescription)) {
            file_put_contents($addonPath . '/README.md', $longDescription);
        }
        
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

        // Hilfsdateien kopieren/erstellen
        if ($hasEditableEnv) {
            copy(__DIR__ . '/../../helper/run.sh', $addonPath . '/run.sh');
            chmod($addonPath . '/run.sh', 0755);

            file_put_contents($addonPath . '/original_entrypoint', (is_array($origEntrypoint) && !empty($origEntrypoint)) ? implode(' ', $origEntrypoint) : ($origEntrypoint ?? ''));
            file_put_contents($addonPath . '/original_cmd', (is_array($origCmd) && !empty($origCmd)) ? implode(' ', $origCmd) : ($origCmd ?? ''));

            // Dockerfile erstellen
            $dockerfileTemplate = file_get_contents(__DIR__ . '/../../helper/template.Dockerfile');
            $dockerfileContent = str_replace('$image', $image, $dockerfileTemplate);
            
            // Entrypoint und Command ins Dockerfile schreiben, da config.yaml ignoriert wird
            $dockerfileContent .= "\nENTRYPOINT [\"/run.sh\"]\n";
            $dockerfileContent .= "CMD []\n";
            
            file_put_contents($addonPath . '/Dockerfile', $dockerfileContent);
        } else {
            // "Legacy" Modus: Wir brauchen kein spezielles Dockerfile oder Wrapper-Script
            // Falls Dateien von vorherigen Versuchen existieren, löschen wir sie lieber nicht (Cleanup könnte riskant sein)
            // Aber wir stellen sicher, dass das Dockerfile das Image einfach nutzt, falls HA es doch bauen will
            // Normalerweise reicht bei Angabe von 'image' in config.yaml das Image direkt, 
            // aber der Converter hat bisher immer ein Dockerfile erstellt.
            file_put_contents($addonPath . '/Dockerfile', "FROM $image\n");
        }
        
        // repository.yaml im Haupt-data-Verzeichnis erstellen/aktualisieren (falls nicht vorhanden oder Standardwerte)
        $this->ensureRepositoryYaml($dataDir);

        $result = [
            'status' => 'success',
            'path' => realpath($addonPath)
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getImageConfig(string $image): array
    {
        $command = "crane config " . escapeshellarg($image) . " 2>&1";
        $output = shell_exec($command);
        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        return $data;
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
