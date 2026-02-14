<?php

namespace App\File\App;

use App\File\FileAbstract;
use App\Tools\Converter;
use App\Tools\Crane;
use App\Tools\Webform;

class OriginalEntrypoint extends FileAbstract
{
    public ?string $original_entrypoint = null;

    public function loadFileContent(): static
    {
        $this->original_entrypoint = $this->isFile() ? file_get_contents($this->getFilePath()) : '';
        return $this;
    }

    public function getFilename(): string
    {
        return 'original_entrypoint';
    }

    public function __toString(): string
    {
        if(!empty($this->original_entrypoint)) {
            return $this->original_entrypoint;
        }
        return '';
    }

    public function saveFileContent(): static
    {
        if($this->original_entrypoint !== null) {
            Converter::writeFileContent($this->getFilePath(), $this->original_entrypoint);
            return $this;
        }
        return $this->clearFile();
    }


    public function jsonSerialize(): array
    {
        return [
            'original_entrypoint' => $this->original_entrypoint,
        ];
    }

    public function updateFromWebui(Webform $webform): static
    {
        $this->original_entrypoint = $webform->isQuirks() ? Crane::getOriginalEntrypoint($webform->extractFullDockerImageName(), '') : null;
        return $this->saveFileContent();
    }
}