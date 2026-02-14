<?php

namespace App\Tools;

use App\File\Traits\DataTrait;

/**
 * @property string $image
 * @property string $image_tag
 * @property ?string $startup_script
 * @property ?bool $allow_user_env
 * @property string $name
 * @property string $description
 * @property ?string $url
 * @property string $version
 * @property ?bool $tmpfs
 * @property ?array $privileged
 * @property ?int|string $timeout
 * @property ?string $watchdog
 * @property ?string $bashio_version
 * @property ?string $backup
 * @property ?array $map
 * @property ?array $ports
 * @property ?bool $ingress
 * @property ?int $ingress_port
 * @property ?bool $ingress_stream
 * @property ?string $panel_title
 * @property ?string $ingress_entry
 * @property ?bool $panel_admin
 * @property ?int $webui_port
 * @property ?string $webui_path
 * @property ?string $webui_scheme
 * @property ?string $webui
 * @property ?array $env_vars
 * @property ?string $icon_file
 * @property ?string $detected_pm
 * @property ?bool $version_fixation
 * @property ?bool $auto_update
 * @property ?array $ports_data
 */
class Webform
{
    use DataTrait;

    public function __construct(array $data)
    {
        $this->setData($data);
    }

    public function setIfDefined(object $object, string $keyForm, $default = null, null|string $keyObject = null, null|callable $callable = null): static
    {
        $keyObject = $keyObject !== null ? $keyObject : $keyForm;
        $callable = $callable !== null ? $callable : fn($value) => $object->{$keyObject} = $value;
        if (isset($this->{$keyForm})) {
            $callable($this->{$keyForm});
        } else {
            $callable($object->{$keyObject} ?? (is_callable($default) ? $default() : $default));
        }
        return $this;
    }

    public function setIfNotEmpty(object $object, string $keyForm, $default = null, null|string $keyObject = null, null|callable $callable = null): static
    {
        $keyObject = $keyObject !== null ? $keyObject : $keyForm;
        $callable = $callable !== null ? $callable : fn($value) => $object->{$keyObject} = $value;
        if ($this->isNotEmpty($keyForm)) {
            $callable($this->{$keyForm});
        } else if (isset($this->{$keyForm})) {
            $callable((is_callable($default) ? $default() : $default));
        } else {
            $callable($object->{$keyObject} ?? (is_callable($default) ? $default() : $default));
        }
        return $this;
    }

    public function isNotEmpty(string $key): bool
    {
        if (array_key_exists($key, $this->data) === false) {
            return false;
        }
        if (is_string($this->data[$key]) && $this->data[$key] !== '') {
            return true;
        }
        if (is_array($this->data[$key]) && $this->data[$key] !== []) {
            return true;
        }
        return !($this->data[$key] === null);
    }

    public function extractDockerImageTag(): ?string
    {
        $image = $this->extractFullDockerImageName();
        if ($image === null) {
            return null;
        }
        return explode(':', $image, 2)[1];
    }

    public function extractFullDockerImageName(): ?string
    {
        if (!isset($this->image)) {
            return null;
        }
        $image = $this->image;
        if (empty($image)) {
            return null;
        }
        $imageParts = explode(':', $image, 2);
        if (count($imageParts) === 1) {
            if (isset($this->image_tag)) {
                $imageParts[1] = $this->image_tag;
            } else {
                $imageParts[1] = 'latest';
            }
        }
        return implode(':', $imageParts);
    }

    public function isQuirks(): bool
    {
        if (is_string($this->startup_script) && $this->startup_script !== '') {
            return true;
        }
        if ($this->allow_user_env === true) {
            return true;
        }
        foreach ($this->env_vars ?? [] as $env_var) {
            if (!empty($env_var['key']) && ($env_var['editable'] ?? false) === true) {
                return true;
            }
        }
        return false;
    }
}