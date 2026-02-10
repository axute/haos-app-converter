<?php

namespace App\App;

use App\Generator\HaConfig;
use App\Generator\Metadata;
use JsonSerializable;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class FilesReader extends FilesAbstract implements JsonSerializable
{
    protected array $config = [];
    protected array $envVars = [];
    protected array $ports = [];
    protected string $image = '';
    protected string $image_tag = '';
    protected ?string $iconFileContent = null;
    protected string $longDescription = '';
    protected bool $quirks = false;
    protected bool $allowUserEnv = false;
    protected string $bashioVersion = '';
    protected string $detectedPm = '';
    protected bool $tmpfs = false;
    protected string $startupScript = '';
    protected array $architectures = [];
    protected array $featureFlags = [];
    protected array $privileged = [];

    public function __construct(protected string $slug)
    {
        $dataDir = $this->getDataDir();
        $this->readConfig();
        $this->readIcon();
        $this->readImage();
        $this->readReadme();
        $this->readMetadata();
        if (!$this->quirks) {
            $this->quirks = file_exists($dataDir . '/' . $slug . '/run.sh');
        }

        if (file_exists($dataDir . '/' . $slug . '/start.sh')) {
            $this->startupScript = file_get_contents($dataDir . '/' . $slug . '/start.sh');
        }

    }

    protected function readConfig(): static
    {
        $configFile = self::getAppDir($this->slug) . '/' . HaConfig::FILENAME;

        if (!file_exists($configFile)) {
            throw new RuntimeException('App not found: ' . $configFile);
        }

        $this->config = Yaml::parseFile($configFile);
        // Fixierte Variablen aus 'environment'
        if (isset($this->config['environment']) && is_array($this->config['environment'])) {
            foreach ($this->config['environment'] as $key => $value) {
                // HAOS_CONVERTER_* sind systemgenerierte Variablen und sollen nicht gelistet werden.
                if (str_starts_with($key, 'HAOS_CONVERTER_')) {
                    continue;
                }
                $this->envVars[] = [
                    'key'      => $key,
                    'value'    => $value,
                    'editable' => false
                ];
            }
        }
        // Editierbare Variablen aus 'options'/'schema'
        if (isset($this->config['options']) && is_array($this->config['options'])) {
            foreach ($this->config['options'] as $key => $value) {
                // 'env_vars' ist ein spezielles Feld für die Checkbox "Allow user to create environment variables"
                // HAOS_CONVERTER_* sind systemgenerierte Variablen.
                // Beides soll nicht als normale Umgebungsvariable gelistet werden.
                if ($key === 'env_vars' || str_starts_with($key, 'HAOS_CONVERTER_')) {
                    continue;
                }
                // Nur hinzufügen, wenn noch nicht als fixierte Variable vorhanden (sollte eigentlich nicht passieren)
                $exists = false;
                foreach ($this->envVars as $ev) {
                    if ($ev['key'] === $key) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $this->envVars[] = [
                        'key'      => $key,
                        'value'    => $value,
                        'editable' => true
                    ];
                }
            }
        }
        if (isset($this->config['ports']) && is_array($this->config['ports'])) {
            $portsDescriptions = $this->config['ports_description'] ?? [];


            foreach ($this->config['ports'] as $portProtocol => $hostPort) {
                if (is_array($hostPort)) {
                    $portProtocol = array_key_first($hostPort);
                    $hostPort = $hostPort[$portProtocol];
                }
                $parts = explode('/', $portProtocol);
                if (count($parts) === 2) {
                    $containerPort = $parts[0];
                    $protocol = $parts[1];
                } else {
                    $containerPort = $parts[0];
                    $protocol = 'tcp';
                }
                $description = null;
                foreach ($portsDescriptions as $pd => $portsDescription) {
                    if ($pd === $portProtocol) {
                        $description = $portsDescription;
                        break;
                    }
                    if (is_array($portsDescription)) {
                        if (array_key_first($portsDescription) === $portProtocol) {
                            $description = $portsDescription[array_key_first($portsDescription)];
                            break;
                        }
                    }
                }
                // Suche nach der Beschreibung in ports_description
                // Das Format in config.yaml ist ports_description: [{ "80/tcp": "Description" }]

                $this->ports[] = [
                    'container'   => $containerPort,
                    'host'        => $hostPort,
                    'protocol'    => $protocol,
                    'description' => $description
                ];
            }
        }

        // Feature Flags einlesen
        $possibleFlags = [
            'host_network', 'host_ipc', 'host_dbus', 'host_pid', 'host_uts',
            'hassio_api', 'homeassistant_api', 'docker_api', 'full_access',
            'audio', 'video', 'gpio', 'usb', 'uart', 'udev',
            'devicetree', 'kernel_modules', 'stdin', 'legacy', 'auth_api',
            'advanced', 'realtime', 'journald'
        ];
        foreach ($possibleFlags as $flag) {
            if (isset($this->config[$flag])) {
                $uiKey = ($flag === 'advanced') ? 'advanced_feature' : $flag;
                $this->featureFlags[$uiKey] = (bool)$this->config[$flag];
            }
        }

        if (isset($this->config['privileged']) && is_array($this->config['privileged'])) {
            $this->privileged = $this->config['privileged'];
        }

        return $this;
    }

    protected function readIcon(): static
    {
        $appDir = self::getAppDir($this->slug);
        $hasLocalIcon = file_exists($appDir . '/icon.png');
        if ($hasLocalIcon) {
            $type = pathinfo($appDir . '/icon.png', PATHINFO_EXTENSION);
            $data = file_get_contents($appDir . '/icon.png');
            $this->iconFileContent = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        return $this;
    }

    protected function readImage(): static
    {
        $dockerfile = self::getAppDir($this->slug) . '/Dockerfile';
        if (file_exists($dockerfile)) {
            $content = file_get_contents($dockerfile);
            if (preg_match('/^FROM\s+(.+)$/m', $content, $matches)) {
                $fullImage = trim($matches[1]);
                if (str_contains($fullImage, ':')) {
                    [
                        $this->image,
                        $this->image_tag
                    ] = explode(':', $fullImage, 2);
                } else {
                    $this->image = $fullImage;
                    $this->image_tag = 'latest';
                }
            }
        }
        return $this;
    }

    protected function readReadme(): static
    {
        $readmeFile = self::getAppDir($this->slug) . '/README.md';
        if (is_file($readmeFile)) {
            $this->longDescription = file_get_contents($readmeFile);
        }
        return $this;
    }

    protected function readMetadata()
    {
        // Versuchen wir auch den Ingress Port zu finden, falls er nicht in der config steht (obwohl er dort stehen sollte)
        $metadataFile = self::getAppDir($this->slug) . '/' . Metadata::FILENAME;
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true);
            if (isset($metadata['detected_pm'])) {
                $this->detectedPm = $metadata['detected_pm'];
            }

            // Quirks erkennen (aus metadata.json oder anhand run.sh)
            if (isset($metadata['quirks'])) {
                $this->quirks = (bool)$metadata['quirks'];
            }
            if (isset($metadata['allow_user_env'])) {
                $this->allowUserEnv = (bool)$metadata['allow_user_env'];
            }
            if (isset($metadata['tmpfs'])) {
                $this->tmpfs = (bool)$metadata['tmpfs'];
            }
            if (isset($metadata['bashio_version'])) {
                $this->bashioVersion = $metadata['bashio_version'];
            }
            if (isset($metadata['architectures'])) {
                $this->architectures = $metadata['architectures'];
            }
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'name'             => $this->config['name'] ?? '',
            'slug'             => $this->slug,
            'description'      => $this->config['description'] ?? '',
            'url'              => $this->config['url'] ?? null,
            'long_description' => $this->longDescription,
            'image'            => $this->image ?: ($this->config['image'] ?? ''),
            'image_tag'        => $this->image_tag,
            'version'          => $this->config['version'] ?? '',
            'ingress'          => $this->config['ingress'] ?? false,
            'ingress_port'     => $this->config['ingress_port'] ?? 80,
            'ingress_entry'    => $this->config['ingress_entry'] ?? '/',
            'ingress_stream'   => $this->config['ingress_stream'] ?? false,
            'panel_icon'       => $this->config['panel_icon'] ?? 'mdi:link-variant',
            'panel_title'      => $this->config['panel_title'] ?? null,
            'panel_admin'      => $this->config['panel_admin'] ?? true,
            'webui'            => $this->config['webui'] ?? '',
            'backup'           => $this->config['backup'] ?? 'disabled',
            'timeout'          => $this->config['timeout'] ?? null,
            'watchdog'         => $this->config['watchdog'] ?? '',
            'tmpfs'            => $tmpfs ?? (bool)($this->config['tmpfs'] ?? false),
            'detected_pm'      => $this->detectedPm,
            'has_local_icon'   => $this->iconFileContent !== null,
            'icon_file'        => $this->iconFileContent,
            'ports'            => $this->ports,
            'map'              => $this->config['map'] ?? [],
            'env_vars'         => $this->envVars,
            'feature_flags'    => $this->featureFlags,
            'privileged'       => $this->privileged,
            'quirks'           => $this->quirks,
            'allow_user_env'   => $this->allowUserEnv,
            'bashio_version'   => $this->bashioVersion,
            'architectures'    => $this->architectures,
            'startup_script'   => $this->startupScript
        ];

    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getImageTag(): string
    {
        return $this->image_tag;
    }

    public function setImageTag(string $tag): static
    {
        $this->image_tag = $tag;
        return $this;
    }
}