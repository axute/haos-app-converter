<?php

namespace App\File\App;

use App\File\FileAbstract;
use App\Tools\Webform;

class StartSh extends FileAbstract
{
    public ?string $startup_script = null;

    public function loadFileContent(): static
    {
        $this->startup_script = $this->isFile() ? $this->loadFile() : '';
        return $this;
    }

    public function getFilename(): string
    {
        return 'start.sh';
    }

    public function __toString(): string
    {
        return $this->startup_script;
    }

    public function saveFileContent(): static
    {
        if (!empty($this->startup_script)) {
            $this->saveFile();
            return $this;
        }
        return $this->clearFile();
    }

    public function jsonSerialize(): array
    {
        return [
            'startup_script' => $this->startup_script,
        ];
    }

    public function updateFromWebui(Webform $webform): static
    {
        $webform->setIfNotEmpty($this, 'startup_script');
        return $this->saveFileContent();
    }
}