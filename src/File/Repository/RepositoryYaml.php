<?php

namespace App\File\Repository;

use App\File\Traits\DataTrait;
use App\File\Traits\TypeYamlTrait;
use App\Tools\App;
use App\Tools\Converter;
use App\Tools\Webform;
use JsonSerializable;
use Stringable;

/**
 * @property string $name
 * @property string|null $maintainer
 * @property string|null $url
 */
class RepositoryYaml implements JsonSerializable, Stringable
{
    use DataTrait;
    use TypeYamlTrait;

    private function __construct(string $name = Converter::DEFAULT_REPOSITORY_NAME)
    {
        $this->name = $name;
        $this->maintainer = '';
        $this->loadFileContent();
    }

    public static function instance(): static
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }


    public function jsonSerialize(): array
    {
        $data = $this->getData();
        ksort($data);
        return $data;
    }

    public function __destruct()
    {
        $this->saveFileContent();
    }

    protected function getFilePath(): string
    {
        return App::getDataDir() . '/' . $this->getFilename();
    }

    public function getFilename(): string
    {
        return 'repository.yaml';
    }

    public function updateFromWebui(Webform $webform): static
    {
        if ($webform->isNotEmpty('name')) {
            $this->name = $webform->name;
        } else {
            $this->name = $this->name ?? '';
        }
        if ($webform->isNotEmpty('maintainer')) {
            $this->maintainer = $webform->maintainer;
        } else {
            $this->maintainer = $this->maintainer ?? '';
        }
        if ($webform->isNotEmpty('url')) {
            $this->url = $webform->url;
        } else {
            $this->url = $this->url ?? null;
        }
        $this->saveFileContent();
        return $this;
    }

    public function isFile(): bool
    {
        return is_file($this->getFilePath());
    }
}