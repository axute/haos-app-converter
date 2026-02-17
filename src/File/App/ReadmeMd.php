<?php

namespace App\File\App;

use App\File\FileAbstract;
use App\Tools\Webform;

class ReadmeMd extends FileAbstract
{
    public ?string $long_description = null;

    public function loadFileContent(): static
    {
        if($this->isFile()) {
            $this->long_description = $this->loadFile();
        }
        return $this;
    }

    public function getFilename(): string
    {
        return 'README.md';
    }

    public function saveFileContent(): static
    {
        if(!empty(trim($this->long_description ?? ''))) {
            $this->saveFile();
            return $this;
        }
        return $this->clearFile();
    }

    public function updateFromWebui(Webform $webform): static
    {
        $webform->setIfDefined($this,'long_description');
        return $this->saveFileContent();
    }

    public function jsonSerialize(): array
    {
        return [
            'long_description' => $this->long_description,
        ];
    }

    public function __toString()
    {
        if(!empty(trim($this->long_description))) {
            return $this->long_description;
        }
        return '';
    }
}