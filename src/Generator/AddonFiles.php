<?php

namespace App\Generator;

use InvalidArgumentException;
use RuntimeException;

class AddonFiles
{
    protected bool $quirks = false;
    protected bool $hasEditableEnv = false;
    protected string $addonName;
    protected string $image;
    protected bool $isSelfConvert = false;
    protected string $slug;
    protected string $dataDir;
    protected string $addonPath;

    public function __construct(protected array $data)
    {
        $this->quirks = $this->data['quirks'] ?? $this->quirks;
        foreach ($this->data['env_vars'] ?? [] as $var) {
            if (!empty($var['editable'])) {
                $this->hasEditableEnv = true;
                break;
            }
        }
        $this->addonName = $this->data['name'] ?? '';
        $this->image = $this->data['image'] ?? '';
        if (empty($this->addonName) || empty($this->image)) {
            throw new InvalidArgumentException('Name and image are required');
        }
        $image_tag = $this->data['image_tag'] ?? '';
        if (!str_contains($this->image, ':')) {
            $this->image .= ':' . $image_tag;
        }
        $this->isSelfConvert = $this->data['self_convert'] ?? false;
        $this->slug = $this->generateSlug();
        $this->dataDir = str_replace('\\', '/', $this->getDataDir());
        $this->addonPath = $this->dataDir . '/' . $this->slug;
        if (!is_dir($this->addonPath)) {
            if(@mkdir($this->addonPath, 0777, true) === false) {
                throw new RuntimeException('Could not create directory ' . $this->addonPath);
            }
        }
    }

    public function create(): array
    {

        // Metadaten initial speichern/laden
        $imageConfig = $this->getImageConfig();
        $origEntrypoint = $imageConfig['config']['Entrypoint'] ?? null;
        $origCmd = $imageConfig['config']['Cmd'] ?? null;
        $addonMetadata = new Metadata();
        $addonMetadata->add('detected_pm', $this->data['detected_pm'] ?? null);
        $addonMetadata->add('quirks', $this->data['quirks'] ?? false);
        $addonMetadata->add('allow_user_env', $this->data['allow_user_env'] ?? false);
        $addonMetadata->add('tmpfs', $this->data['tmpfs'] ?? false);
        $addonMetadata->add('bashio_version', $this->data['bashio_version'] ?? '0.17.5');
        $addonMetadata->add('has_startup_script', !empty($this->data['startup_script'] ?? ''));
        $addonMetadata->add('original_entrypoint', $origEntrypoint);
        $addonMetadata->add('original_cmd', $origCmd);
        $this->saveMetadata($addonMetadata);

        // Dateien generieren
        $this->generateConfigYaml();
        $this->handleIcon($this->data['icon_file'] ?? '');
        $this->generateReadme();
        $this->handleHelperFiles($origEntrypoint, $origCmd);
        $this->generateDockerfile();

        // repository.yaml im Haupt-data-Verzeichnis erstellen/aktualisieren
        $this->ensureRepositoryYaml();

        return [
            'status' => 'success',
            'path'   => realpath($this->addonPath)
        ];


    }

    private function generateSlug(): string
    {
        if ($this->isSelfConvert) {
            return 'haos_addon_converter';
        }

        $slug = strtolower($this->addonName);
        $slug = str_replace([
            ' ',
            '-',
            '.'
        ], '_', $slug);
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        return trim($slug, '_');
    }

    private function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    private function getImageConfig(): array
    {
        $command = "crane config " . escapeshellarg($this->image) . " 2>&1";
        $output = shell_exec($command);
        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg());
        }
        return $data;
    }

    protected function saveMetadata(Metadata $newMetadata): static
    {
        $metadataFile = $this->addonPath . '/' . Metadata::FILENAME;
        $oldMetadata = [];
        if (file_exists($metadataFile)) {
            $oldMetadata = json_decode(file_get_contents($metadataFile), true) ?: [];
        }
        $metadata = array_merge($oldMetadata, $newMetadata->getAll());

        file_put_contents($metadataFile, new Metadata($metadata));
        return $this;
    }

    protected function generateConfigYaml(): static
    {
        $haConfig = new HAconfig(
            $this->data['name'], $this->data['version'] ?? '1.0.0', $this->slug, $this->data['description'] ?? 'Converted HA Add-on',
        );
        $haConfig->addEnvironment('HAOS_CONVERTER_BASHIO_VERSION', $this->data['bashio_version'] ?? '0.17.5');

        if (!empty($this->data['detected_pm'])) {
            $haConfig->addEnvironment('HAOS_CONVERTER_PM', $this->data['detected_pm']);
        }

        if (!empty($this->data['ingress'])) {
            $haConfig->setIngress(
                port: $this->data['ingress_port'] ?? 80,
                stream: !empty($this->data['ingress_stream']),
                title: $this->data['panel_title'] ?? null,
                icon: $this->data['panel_icon'] ?? null,
                ingressEntry: $this->data['ingress_entry'] ?? '/'
            );
        } elseif (!empty($this->data['webui_port'])) {
            $haConfig->setWebUI(
                port: $this->data['webui_port'],
                path: $this->data['webui_path'] ?? '/',
                scheme: $this->data['webui_protocol'] ?? 'http'
            );
        }

        if (isset($this->data['backup'])) {
            $haConfig->setBackup($this->data['backup']);
        }

        if (isset($this->data['tmpfs'])) {
            $haConfig->setTmpfs((bool)$this->data['tmpfs']);
        }

        if (!empty($this->data['map'])) {
            foreach ($this->data['map'] as $map) {
                $haConfig->addMap(
                    type: $map['folder'],
                    readOnly: $map['mode'] === 'ro',
                    path: $map['path'] ?? null
                );
            }
        }

        if (!empty($this->data['ports'])) {
            foreach ($this->data['ports'] as $p) {
                if (!empty($p['container'])) {
                    $haConfig->addPort($p['container'], $p['host'] ?? null);
                }
            }
        }

        if (!empty($this->data['env_vars']) || !empty($this->data['allow_user_env'])) {

            foreach ($this->data['env_vars'] ?? [] as $var) {
                if (!empty($var['key'])) {
                    $key = $var['key'];
                    $value = $var['value'] ?? '';
                    $editable = $var['editable'] ?? false;

                    if (!empty($this->data['quirks']) && $editable) {
                        $haConfig->addOption($key, $value, 'str?');
                    } else {
                        $haConfig->addEnvironment($key, $value);
                    }
                }
            }

            if (!empty($this->data['allow_user_env'])) {
                $haConfig->addOption('env_vars', [], ['str']);
            }
        }

        file_put_contents($this->addonPath . '/' . HAconfig::FILENAME, $haConfig);
        return $this;
    }

    protected function handleIcon(string $iconFile): static
    {
        if (!empty($iconFile)) {
            if (preg_match('/^data:image\/(\w+);base64,/', $iconFile)) {
                $iconData = substr($iconFile, strpos($iconFile, ',') + 1);
                $iconData = base64_decode($iconData);
                if ($iconData !== false) {
                    file_put_contents($this->addonPath . '/icon.png', $iconData);
                }
            }
        }
        return $this;
    }

    protected function generateReadme(): static
    {
        $longDescription = $this->data['long_description'] ?? '';
        $addonName = $this->data['name'];
        $description = $this->data['description'] ?? 'Converted HA Add-on';

        if (!empty($longDescription)) {
            $readmeContent = $longDescription;
            if (file_exists($this->addonPath . '/icon.png') && !str_contains($readmeContent, '![Logo](icon.png)')) {
                $readmeContent = "![Logo](icon.png)\n\n" . $readmeContent;
            }
            file_put_contents($this->addonPath . '/README.md', $readmeContent);
        } elseif (file_exists($this->addonPath . '/icon.png')) {
            file_put_contents($this->addonPath . '/README.md', "![Logo](icon.png)\n\n# $addonName\n\n$description");
        }
        return $this;
    }

    private function handleHelperFiles($origEntrypoint, $origCmd): void
    {
        $allowUserEnv = $this->data['allow_user_env'] ?? false;
        $startupScript = $this->data['startup_script'] ?? '';

        $needsRunSh = ($this->quirks && ($this->hasEditableEnv || !empty($startupScript) || $allowUserEnv)) || $allowUserEnv;

        if ($needsRunSh) {
            copy(__DIR__ . '/../../helper/run.sh', $this->addonPath . '/run.sh');
            chmod($this->addonPath . '/run.sh', 0755);

            file_put_contents($this->addonPath . '/original_entrypoint', (is_array($origEntrypoint) && !empty($origEntrypoint)) ? implode(' ', $origEntrypoint) : ($origEntrypoint ?? ''));
            file_put_contents($this->addonPath . '/original_cmd', (is_array($origCmd) && !empty($origCmd)) ? implode(' ', $origCmd) : ($origCmd ?? ''));

            if (!empty($startupScript)) {
                file_put_contents($this->addonPath . '/start.sh', $startupScript);
                chmod($this->addonPath . '/start.sh', 0755);
            }
        }
    }
    private function generateDockerfile(): void
    {
        $allowUserEnv = $this->data['allow_user_env'] ?? false;
        $startupScript = $this->data['startup_script'] ?? '';


        $dockerfile = new Dockerfile($this->image);

        $needsRunSh = ($this->quirks && ($this->hasEditableEnv || !empty($startupScript) || $allowUserEnv)) || $allowUserEnv;

        if ($needsRunSh) {
            if ($allowUserEnv) {
                $dockerfile->addCommand('COPY --from=hairyhenderson/gomplate:stable /gomplate /bin/gomplate');
            }

            $dockerfile->addCommand("\n# Add wrapper script");
            $dockerfile->addCommand("COPY run.sh /run.sh");
            $dockerfile->addCommand("RUN chmod +x /run.sh");

            $dockerfile->addCommand("\n# Add stored original entrypoint/cmd");
            $dockerfile->addCommand("COPY original_entrypoint /run/original_entrypoint");
            $dockerfile->addCommand("COPY original_cmd /run/original_cmd");

            if (!empty($startupScript)) {
                $dockerfile->addCommand("\n# Add startup script");
                $dockerfile->addCommand("COPY start.sh /start.sh");
                $dockerfile->addCommand("RUN chmod +x /start.sh");
            }

            $dockerfile->addCommand("\nENTRYPOINT [\"/run.sh\"]");
            $dockerfile->addCommand("CMD []");
        }

        file_put_contents($this->addonPath . '/' . Dockerfile::FILENAME, (string)$dockerfile);
    }

    private function ensureRepositoryYaml(): void
    {
        $repoFile = $this->dataDir . '/' . HArepository::FILENAME;
        if (!file_exists($repoFile)) {
            $haRepository = new HARepository('My HAOS Add-on Repository');
            $haRepository->setMaintainer('HAOS Add-on Converter');
            file_put_contents($repoFile, $haRepository);
        }
    }
}