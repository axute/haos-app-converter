<?php

namespace App\File\Repository;

use App\File\FileAbstract;
use App\File\Traits\DataTrait;
use App\File\Traits\TypeYamlTrait;
use App\Repository;
use App\Tools\Webform;

/**
 * @property string $name
 * @property string|null $maintainer
 * @property string|null $url
 */
class RepositoryYaml extends FileAbstract
{
    use DataTrait;
    use TypeYamlTrait;

    public static function instance(): static
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self(new Repository());
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
}