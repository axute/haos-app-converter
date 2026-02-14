<?php

namespace App\File\App;

use App\File\App\Defaults\MetadataJson as Defaults;
use App\File\FileAbstract;
use App\File\Traits\DataTrait;
use App\File\Traits\TypeJsonTrait;
use App\Tools\Crane;
use App\Tools\Scripts;
use App\Tools\Webform;

/**
 * @property ?bool $auto_update
 * @property ?string $bashio_version
 * @property ?string $detected_pm
 * @property ?bool $has_startup_script
 * @property ?string $original_cmd
 * @property ?string $original_entrypoint
 * @property ?bool $quirks
 * @property ?bool $version_fixation
 * @property mixed $allow_user_env
 */
class MetadataJson extends FileAbstract
{
    use DataTrait;
    use TypeJsonTrait;


    public function __set(string $key, mixed $value): void
    {
        if (in_array($key, Defaults::ALLOWED_KEYS)) {
            $this->data[$key] = $value;
        }
    }

    public function getFilename(): string
    {
        return 'metadata.json';
    }

    public function jsonSerialize(): array
    {
        $data = $this->getData();
        ksort($data);
        return $data;
    }
    public function updateFromWebUi(Webform $webform): static
    {
        $this->quirks = $webform->isQuirks();
        $extractImage = $webform->extractFullDockerImageName();
        if ($extractImage !== null) {
            $this->detected_pm = Scripts::detectPm($extractImage);
        }
        $webform->setIfDefined($this, 'allow_user_env', false);
        $webform->setIfDefined($this, 'bashio_version');
        $webform->setIfDefined($this, 'version_fixation', false);
        $webform->setIfNotEmpty($this, 'auto_update', false);
        if ((string)$webform->startup_script !== '') {
            $this->has_startup_script = true;
        } else {
            $this->has_startup_script = $this->has_startup_script ?? false;
        }

        $extractImage = $webform->extractFullDockerImageName();
        if ($extractImage !== null) {
            $crane = new Crane($extractImage);
            // Metadaten initial speichern/laden
            $this->original_cmd = $crane->getCmd('');
            $this->original_entrypoint = $crane->getEntrypoint('');
        }
        return $this->saveFileContent();
    }
}