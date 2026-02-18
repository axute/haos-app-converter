<?php

namespace App\File\App;

use App\File\App\Defaults\ConfigYaml as Defaults;
use App\File\FileAbstract;
use App\File\Traits\DataTrait;
use App\Tools\Crane;
use App\Tools\Version;
use App\Tools\Webform;
use InvalidArgumentException;

/**
 * @property array $arch
 * @property ?string $backup
 * @property string $boot
 * @property ?array $environment
 * @property string $description
 * @property ?bool $ingress
 * @property ?string $ingress_entry
 * @property ?int $ingress_port
 * @property ?bool $ingress_stream
 * @property ?array $map
 * @property string $name
 * @property ?array $options
 * @property ?bool $panel_admin
 * @property ?string $panel_title
 * @property ?string $panel_icon
 * @property ?array $ports
 * @property ?array $ports_description
 * @property ?array $privileged
 * @property ?array $schema
 * @property string $slug
 * @property string $startup
 * @property ?int $timeout
 * @property ?bool $tmpfs
 * @property ?string $url
 * @property string $version
 * @property ?string $watchdog
 * @property ?string $webui
 * FeatureFlags
 * @property ?bool $host_network
 * @property ?bool $host_ipc
 * @property ?bool $host_dbus
 * @property ?bool $host_pid
 * @property ?bool $host_uts
 * @property ?bool $hassio_api
 * @property ?bool $homeassistant_api
 * @property ?bool $docker_api
 * @property ?bool $full_access
 * @property ?bool $audio
 * @property ?bool $video
 * @property ?bool $gpio
 * @property ?bool $usb
 * @property ?bool $uart
 * @property ?bool $udev
 * @property ?bool $devicetree
 * @property ?bool $kernel_modules
 * @property ?bool $stdin
 * @property ?bool $legacy
 * @property ?bool $auth_api
 * @property ?bool $advanced
 * @property ?bool $realtime
 * @property ?bool $journald
 */
abstract class Config extends FileAbstract
{
    use DataTrait;

    public function removeIngress(): static
    {
        unset($this->ingress, $this->ingress_entry, $this->ingress_port, $this->ingress_stream);
        unset($this->panel_admin, $this->panel_icon, $this->panel_title);
        return $this;
    }

    public function setVersionFromTag(string $image_tag): static
    {
        $semver = Version::fromSemverTag($image_tag);
        if ($semver !== null) {
            $this->version = (string)$semver;
            return $this->saveFileContent();
        }
        return $this;
    }

    public function addOption(string $key, mixed $value, mixed $schema): static
    {
        $options = $this->options ?? [];
        $schemas = $this->schema ?? [];
        $options[$key] = $value;
        $schemas[$key] = $schema;
        $this->options = $options;
        $this->schema = $schemas;
        return $this;
    }

    public function setupIngress(int $port = Defaults::INGRESS_PORT, bool $stream = Defaults::INGRESS_STREAM, ?string $title = null, ?string $icon = null, string $ingressEntry = Defaults::INGRESS_ENTRY, bool $panelAdmin = Defaults::PANEL_ADMIN): static
    {
        $this->ingress = true;
        $this->ingress_port = $port;
        $this->ingress_stream = $stream;
        $this->ingress_entry = $ingressEntry;
        $this->panel_title = $title ?? $this->name;
        $this->panel_icon = $icon ?? Defaults::PANEL_ICON;
        $this->panel_admin = $panelAdmin;
        return $this;
    }

    public function addPort(int $portContainer, int|null $portHost, string $protocol = Defaults::PORT_PROTOCOL, ?string $description = null): static
    {
        $ports = $this->ports ?? [];
        $ports[$portContainer . '/' . $protocol] = $portHost;
        $this->ports = $ports;
        if (!empty($description)) {
            $ports_description = $this->ports_description ?? [];
            $ports_description[$portContainer . '/' . $protocol] = $description;
            $this->ports_description = $ports_description;
        }

        return $this;
    }

    public function addEnvironment(string $key, mixed $value): static
    {
        $this->environment = $this->environment ?? [];
        $environment = $this->environment;
        $environment[$key] = $value;
        $this->environment = $environment;
        return $this;
    }

    public function setBoot(string $boot): static
    {
        if (!in_array($boot, Defaults::_BOOT_OPTIONS)) {
            throw new InvalidArgumentException('Invalid boot mode: ' . var_export($boot, true));
        }
        $this->boot = $boot;
        return $this;
    }

    public function updateFromWebui(Webform $webform): static
    {
        $webform->setIfNotEmpty($this, 'arch', fn() => Crane::i($webform->extractFullDockerImageName())->getArchitectures());
        $webform->setIfDefined($this, 'name', '');
        $webform->setIfDefined($this, 'description', '');
        $webform->setIfDefined($this, 'version', Defaults::VERSION);
        if ($webform->isNotEmpty('version_fixation') && $webform->version_fixation === true) {
            $semverTag = Version::fromSemverTag($webform->extractDockerImageTag());
            if ($semverTag !== null) {
                $this->version = (string)$semverTag;
            }
        }
        $webform->setIfDefined($this, 'url');
        $webform->setIfDefined($this, 'privileged');
        $webform->setIfDefined($this, 'tmpfs', Defaults::TMPFS);
        $webform->setIfDefined($this, 'timeout');
        $webform->setIfDefined($this, 'watchdog');
        $webform->setIfNotEmpty($this, 'boot', Defaults::BOOT, null, $this->setBoot(...));
        $this->startup = Defaults::STARTUP;
        $this->environment = [];
        $this->options = [];
        $this->addEnvironment('HAOS_CONVERTER_BASHIO_VERSION', $webform->isNotEmpty('bashio_version') ? $webform->bashio_version : '0.17.5');
        if ($webform->isNotEmpty('detected_pm')) {
            $this->addEnvironment('HAOS_CONVERTER_PM', $webform->detected_pm);
        }
        $webform->setIfNotEmpty($this, 'backup', null, null, $this->setBackup(...));
        $webform->setIfNotEmpty($this, 'map', [], null, $this->setMapEntries(...));
        $webform->setIfNotEmpty($this, 'ports_data', [], null, function ($data) {
            $this->setPortEntries($data['ports'] ?? []);
            if (!empty($data['descriptions'])) {
                $this->ports_description = $data['descriptions'];
            }
        });

        if ($webform->ingress === true) {
            $this->setupIngress(
                port: $webform->ingress_port ?? 80,
                stream: $webform->ingress_stream ?? false,
                title: $webform->panel_title ?? $this->name,
                icon: $webform->panel_icon ?? Defaults::PANEL_ICON,
                ingressEntry: $webform->ingress_entry ?? Defaults::INGRESS_ENTRY,
                panelAdmin: $webform->panel_admin ?? true,
            );
            unset($this->webui);
        } else if ($webform->webui_port) {
            $this->generateWebui(
                port: $webform->webui_port,
                path: $webform->webui_path ?? Defaults::WEBUI_PATH,
                protocol: $webform->webui_scheme ?? Defaults::WEBUI_PROTOCOL,
            );
            $this->removeIngress();
        } else if (is_string($webform->webui) && $webform->webui !== '') {
            $this->webui = $webform->webui;
            $this->removeIngress();
        }
        $allow_user_env = $webform->allow_user_env ?? false;
        if ($webform->env_vars || $allow_user_env) {
            foreach ($webform->env_vars ?? [] as $envVar) {
                if (!empty($envVar['key'])) {
                    $editable = $envVar['editable'] ?? false;
                    if ($allow_user_env && $editable) {
                        $this->addOption(
                            key: $envVar['key'],
                            value: $envVar['value'] ?? null,
                            schema: 'str?'
                        );
                    } else {
                        $this->addEnvironment($envVar['key'], $envVar['value'] ?? '');
                    }
                }
            }
            if ($allow_user_env) {
                $this->addOption('env_vars', [], ['str']);
            }
        }
        // AppArmor: Custom profile name vs. boolean flag
        if ($webform->isNotEmpty('apparmor_custom') && $webform->apparmor_custom === true) {
            $profileName = ($webform->apparmor_name ?? null);
            if (is_string($profileName) && $profileName !== '') {
                $this->apparmor = $profileName;
            } else {
                // Fallback auf Slug, wenn kein Name angegeben wurde
                $this->apparmor = $this->getArchive()->slug;
            }
        } else {
            // Standard-Checkbox steuert booleschen Wert
            $webform->setIfDefined($this, 'apparmor', false);
        }

        foreach (Defaults::_POSSIBLE_FEATURE_FLAGS as $featureFlag) {
            if ($featureFlag === 'apparmor') continue; // bereits oben behandelt
            $webform->setIfDefined($this, $featureFlag);
        }
        return $this->saveFileContent();
    }

    public function addMap(string $type, bool $readOnly, ?string $path = null): static
    {
        $this->map = $this->map ?? [];
        $map = $this->map;
        $entry = [
            Defaults::MAP_TYPE      => $type,
            Defaults::MAP_READ_ONLY => $readOnly,
        ];
        if ($path) $entry[Defaults::MAP_PATH] = $path;
        $map[] = $entry;
        $this->map = $map;
        return $this;
    }

    public function setBackup(?string $backup): static
    {
        if (!in_array($backup, Defaults::_BACKUP_OPTIONS, true)) {
            throw new InvalidArgumentException('Invalid backup mode: ' . var_export($backup, true));
        }
        if ($backup === null || $backup === 'disabled') {
            $this->backup = null;
            return $this;
        }
        $this->backup = $backup;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $envVars = [];
        foreach ($this->environment ?? [] as $key => $value) {
            $envVars[] = [
                'key'      => $key,
                'value'    => $value,
                'editable' => false
            ];
        }
        foreach ($this->options ?? [] as $key => $value) {
            if ($key === 'env_vars') continue;
            $envVars[] = [
                'key'      => $key,
                'value'    => $value,
                'editable' => true
            ];
        }

        if (empty($this->url)) {
            unset($this->url);
        }
        if (empty($this->backup)) {
            unset($this->backup);
        }
        if ((int)$this->timeout <= 0) {
            unset($this->timeout);
        }
        if (empty($this->watchdog)) {
            unset($this->watchdog);
        }
        if (empty($this->environment) || $this->environment === []) {
            unset($this->environment);
        }
        if (empty($this->webui)) {
            unset($this->webui);
        }
        if (empty($this->ingress) || $this->ingress === false) {
            unset(
                $this->ingress,
                $this->ingress_port,
                $this->ingress_stream,
                $this->ingress_entry,
                $this->panel_icon,
                $this->panel_admin,
                $this->panel_title
            );
        }
        if (empty($this->ports) || $this->ports === []) {
            unset($this->ports, $this->ports_description);
        }
        if (empty($this->map) || $this->map === []) {
            unset($this->map);
        }
        if (empty($this->options) || $this->options === []) {
            unset($this->options, $this->schema);
        }
        if (empty($this->privileged) || $this->privileged === []) {
            unset($this->privileged);
        }
        if ($this->tmpfs !== true) {
            unset($this->tmpfs);
        }
        foreach (Defaults::_POSSIBLE_FEATURE_FLAGS as $featureFlag) {
            if (empty($this->{$featureFlag})) {
                unset($this->{$featureFlag});
            }
        }
        $data = $this->getData();
        $data['env_vars'] = $envVars;
        ksort($data);
        return $data;
    }

    public function generateWebui(int|null $port, string $path = Defaults::WEBUI_PATH, string $protocol = Defaults::WEBUI_PROTOCOL): static
    {
        if ($port === null) {
            $this->webui = null;
            return $this;
        }
        if (in_array($protocol, [
                'http',
                'https'
            ]) === false) {
            throw new InvalidArgumentException('Invalid protocol: ' . var_export($protocol, true));
        }
        $this->webui = $protocol . '://[HOST]:[PORT:' . $port . ']' . $path;
        return $this;
    }

    protected function setMapEntries(array $mapEntries): static
    {
        $this->map = null;
        foreach ($mapEntries as $map) {
            $type = $map['folder'] ?? $map['type'];
            if (in_array($type, Defaults::MAPPINGS) === false) {
                continue;
            }
            $readOnly = array_key_exists('mode', $map) ? $map['mode'] === 'ro' : ($map['read_only'] ?? true);
            $this->addMap(
                type: $type,
                readOnly: $readOnly,
                path: $map['path'] ?? null
            );
        }
        return $this;
    }

    protected function setPortEntries(array $portEntries): static
    {
        $this->ports = null;
        $this->ports_description = null;
        foreach ($portEntries as $key => $host) {
            $parts = explode('/', $key);
            $portContainer = (int)$parts[0];
            $protocol = $parts[1] ?? Defaults::PORT_PROTOCOL;

            $this->addPort(
                portContainer: $portContainer,
                portHost: $host !== null ? (int)$host : null,
                protocol: $protocol
            );
        }
        return $this;
    }
}