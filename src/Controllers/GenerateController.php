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
        $detectedPm = $data['detected_pm'] ?? null;
        $isSelfConvert = $data['self_convert'] ?? false;
        $quirks = $data['quirks'] ?? false;
        $allowUserEnv = $data['allow_user_env'] ?? false;
        $bashioVersion = $data['bashio_version'] ?? '';
        $startupScript = $data['startup_script'] ?? '';
        
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

        if ($detectedPm) {
            $this->saveMetadata($addonPath, ['detected_pm' => $detectedPm]);
            // Paketmanager als feste Umgebungsvariable hinzufügen
            $config['environment']['HAOS_CONVERTER_PM'] = $detectedPm;
        }

        if ($bashioVersion) {
            $config['environment']['HAOS_CONVERTER_BASHIO_VERSION'] = $bashioVersion;
        } else {
            // Standardversion falls aus irgendeinem Grund nichts übergeben wurde
            $config['environment']['HAOS_CONVERTER_BASHIO_VERSION'] = '0.17.5';
        }

        // Image Informationen via crane abrufen
        $imageConfig = $this->getImageConfig($image);
        $origEntrypoint = $imageConfig['config']['Entrypoint'] ?? null;
        $origCmd = $imageConfig['config']['Cmd'] ?? null;

        $this->saveMetadata($addonPath, [
            'original_entrypoint' => $origEntrypoint,
            'original_cmd' => $origCmd
        ]);

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

        if ($backup === 'hot' || $backup === 'cold') {
            $config['backup'] = $backup;
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
        if (!empty($envVars) || $allowUserEnv) {
            $environment = [];
            $options = [];
            $schema = [];

            foreach ($envVars as $var) {
                if (!empty($var['key'])) {
                    $key = $var['key'];
                    $value = $var['value'] ?? '';
                    $editable = $var['editable'] ?? false;

                    if ($quirks && $editable) {
                        $options[$key] = $value;
                        $schema[$key] = 'str?';
                    } else {
                        $environment[$key] = $value;
                    }
                }
            }

            if ($allowUserEnv) {
                $options['env_vars'] = [];
                $schema['env_vars'] = ['str'];
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

        // README.md (long description) speichern
        if (!empty($longDescription)) {
            $readmeContent = $longDescription;
            if (file_exists($addonPath . '/icon.png') && strpos($readmeContent, '![Logo](icon.png)') === false) {
                $readmeContent = "![Logo](icon.png)\n\n" . $readmeContent;
            }
            file_put_contents($addonPath . '/README.md', $readmeContent);
        } elseif (file_exists($addonPath . '/icon.png')) {
            file_put_contents($addonPath . '/README.md', "![Logo](icon.png)\n\n# $addonName\n\n$description");
        }

        // Hilfsdateien kopieren/erstellen
        $this->saveMetadata($addonPath, [
            'quirks' => $quirks,
            'allow_user_env' => $allowUserEnv,
            'bashio_version' => $bashioVersion,
            'has_startup_script' => !empty($startupScript)
        ]);

        if ($quirks) {
            // Wrapper run.sh wird kopiert, wenn Quirks aktiv sind (für Startup Script ODER editable Env)
            // Auch wenn allow_user_env aktiv ist, brauchen wir run.sh
            if ($hasEditableEnv || !empty($startupScript) || $allowUserEnv) {
                copy(__DIR__ . '/../../helper/run.sh', $addonPath . '/run.sh');
                chmod($addonPath . '/run.sh', 0755);

                file_put_contents($addonPath . '/original_entrypoint', (is_array($origEntrypoint) && !empty($origEntrypoint)) ? implode(' ', $origEntrypoint) : ($origEntrypoint ?? ''));
                file_put_contents($addonPath . '/original_cmd', (is_array($origCmd) && !empty($origCmd)) ? implode(' ', $origCmd) : ($origCmd ?? ''));

                // Dockerfile erstellen
                $dockerfileTemplate = file_get_contents(__DIR__ . '/../../helper/template.Dockerfile');
                $dockerfileContent = str_replace('$image', $image, $dockerfileTemplate);

                if ($allowUserEnv) {
                    $dockerfileContent = str_replace("FROM $image", "FROM $image\nCOPY --from=hairyhenderson/gomplate:stable /gomplate /bin/gomplate", $dockerfileContent);
                }

                if (!empty($startupScript)) {
                    file_put_contents($addonPath . '/start.sh', $startupScript);
                    chmod($addonPath . '/start.sh', 0755);
                    $dockerfileContent .= "\n# Add startup script\nCOPY start.sh /start.sh\nRUN chmod +x /start.sh\n";
                }

                // Entrypoint und Command ins Dockerfile schreiben, da config.yaml ignoriert wird
                $dockerfileContent .= "\nENTRYPOINT [\"/run.sh\"]\n";
                $dockerfileContent .= "CMD []\n";

                file_put_contents($addonPath . '/Dockerfile', $dockerfileContent);
            } else {
                // Quirks an, aber keine Features genutzt -> Standard Dockerfile
                file_put_contents($addonPath . '/Dockerfile', "FROM $image\n");
            }
        } elseif ($allowUserEnv) {
            // Auch ohne Quirks-Modus: Wenn allow_user_env aktiv ist, brauchen wir run.sh und gomplate
            copy(__DIR__ . '/../../helper/run.sh', $addonPath . '/run.sh');
            chmod($addonPath . '/run.sh', 0755);

            file_put_contents($addonPath . '/original_entrypoint', (is_array($origEntrypoint) && !empty($origEntrypoint)) ? implode(' ', $origEntrypoint) : ($origEntrypoint ?? ''));
            file_put_contents($addonPath . '/original_cmd', (is_array($origCmd) && !empty($origCmd)) ? implode(' ', $origCmd) : ($origCmd ?? ''));

            $dockerfileTemplate = file_get_contents(__DIR__ . '/../../helper/template.Dockerfile');
            $dockerfileContent = str_replace('$image', $image, $dockerfileTemplate);
            
            $dockerfileContent = str_replace("FROM $image", "FROM $image\nCOPY --from=hairyhenderson/gomplate:stable /gomplate /bin/gomplate", $dockerfileContent);
            
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

    private function saveMetadata(string $addonPath, array $newData): void
    {
        $metadataFile = $addonPath . '/metadata.json';
        $metadata = [];
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true) ?: [];
        }
        $metadata = array_merge($metadata, $newData);
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
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
